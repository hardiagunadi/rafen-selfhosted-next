<?php

namespace App\Http\Controllers;

use App\Models\Odp;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Services\MapCoverageTileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerMapController extends Controller
{
    public function index(Request $request): View
    {
        $currentUser = $request->user();

        $odpsQuery = Odp::query()
            ->withCount('pppUsers')
            ->accessibleBy($currentUser)
            ->orderBy('code');

        $selectedOdpId = $request->integer('odp_id');
        $selectedStatusAkun = $request->input('status_akun');

        $customerQuery = PppUser::query()
            ->with(['profile:id,name', 'odp:id,code,name'])
            ->accessibleBy($currentUser)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($selectedOdpId > 0) {
            $customerQuery->where('odp_id', $selectedOdpId);
        }

        if (in_array($selectedStatusAkun, ['enable', 'disable', 'isolir'], true)) {
            $customerQuery->where('status_akun', $selectedStatusAkun);
        }

        $customers = $customerQuery->get();

        $customerMarkers = $customers
            ->filter(fn (PppUser $customer) => is_numeric($customer->latitude) && is_numeric($customer->longitude))
            ->map(function (PppUser $customer): array {
                return [
                    'id' => $customer->id,
                    'name' => $customer->customer_name,
                    'customer_id' => $customer->customer_id,
                    'username' => $customer->username,
                    'odp_code' => $customer->odp?->code ?? $customer->odp_pop,
                    'profile' => $customer->profile?->name,
                    'status_akun' => $customer->status_akun,
                    'status_registrasi' => $customer->status_registrasi,
                    'latitude' => (float) $customer->latitude,
                    'longitude' => (float) $customer->longitude,
                    'accuracy' => $customer->location_accuracy_m !== null ? (float) $customer->location_accuracy_m : null,
                    'captured_method' => $customer->location_capture_method,
                ];
            })
            ->values();

        $odpMarkers = $odpsQuery->get()
            ->filter(fn (Odp $odp) => $odp->latitude !== null && $odp->longitude !== null)
            ->map(function (Odp $odp): array {
                return [
                    'id' => $odp->id,
                    'code' => $odp->code,
                    'name' => $odp->name,
                    'area' => $odp->area,
                    'status' => $odp->status,
                    'capacity_ports' => (int) $odp->capacity_ports,
                    'used_ports' => (int) $odp->ppp_users_count,
                    'latitude' => (float) $odp->latitude,
                    'longitude' => (float) $odp->longitude,
                ];
            })
            ->values();

        return view('customers.map', [
            'odps' => $odpsQuery->get(),
            'customerMarkers' => $customerMarkers,
            'odpMarkers' => $odpMarkers,
            'selectedOdpId' => $selectedOdpId > 0 ? $selectedOdpId : null,
            'selectedStatusAkun' => $selectedStatusAkun,
            'mapCacheConfigEndpoint' => route('customer-map.cache-config'),
            'mapCacheTilesEndpoint' => route('customer-map.cache-tiles'),
            'summary' => [
                'odps_total' => Odp::query()->accessibleBy($currentUser)->count(),
                'odps_with_coordinate' => $odpMarkers->count(),
                'customers_total' => PppUser::query()->accessibleBy($currentUser)->count(),
                'customers_with_coordinate' => $customerMarkers->count(),
            ],
        ]);
    }

    public function cacheConfig(Request $request): JsonResponse
    {
        $cacheContext = $this->resolveMapCacheContext($request);

        return response()->json([
            'enabled' => $cacheContext['enabled'],
            'cache_name' => $cacheContext['cache_name'],
            'tenant_cache_prefix' => $cacheContext['tenant_cache_prefix'],
            'auto_disabled' => $cacheContext['auto_disabled'],
            'all_odps_geocoded' => $cacheContext['all_odps_geocoded'],
            'coverage' => $cacheContext['coverage'],
            'max_tiles' => $cacheContext['max_tiles'],
            'odps_total' => $cacheContext['odps_total'],
            'odps_with_coordinate' => $cacheContext['odps_with_coordinate'],
        ]);
    }

    public function cacheTiles(Request $request, MapCoverageTileService $tileService): JsonResponse
    {
        $cacheContext = $this->resolveMapCacheContext($request);

        if (! $cacheContext['enabled']) {
            return response()->json([
                'enabled' => false,
                'cache_name' => $cacheContext['cache_name'],
                'urls' => [],
            ]);
        }

        $coverage = $cacheContext['coverage'];
        $urls = $tileService->buildCoverageTileUrls(
            (float) $coverage['center_lat'],
            (float) $coverage['center_lng'],
            (float) $coverage['radius_km'],
            (int) $coverage['min_zoom'],
            (int) $coverage['max_zoom'],
            (int) $cacheContext['max_tiles']
        );

        return response()->json([
            'enabled' => true,
            'cache_name' => $cacheContext['cache_name'],
            'tile_count' => count($urls),
            'urls' => $urls,
        ]);
    }

    /**
     * @return array{
     *     enabled: bool,
     *     cache_name: string,
     *     tenant_cache_prefix: string,
     *     auto_disabled: bool,
     *     all_odps_geocoded: bool,
     *     odps_total: int,
     *     odps_with_coordinate: int,
     *     max_tiles: int,
     *     coverage: array{
     *         center_lat: float|null,
     *         center_lng: float|null,
     *         radius_km: float,
     *         min_zoom: int,
     *         max_zoom: int
     *     }
     * }
     */
    protected function resolveMapCacheContext(Request $request): array
    {
        $user = $request->user();
        $ownerId = $user->effectiveOwnerId();
        $settings = TenantSettings::getOrCreate($ownerId);

        $odpsTotal = Odp::query()->where('owner_id', $ownerId)->count();
        $odpsWithCoordinate = Odp::query()
            ->where('owner_id', $ownerId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->count();

        $allOdpsGeocoded = $odpsTotal > 0 && $odpsWithCoordinate >= $odpsTotal;
        $autoDisabled = false;

        if ($settings->map_cache_enabled && $allOdpsGeocoded) {
            $settings->update([
                'map_cache_enabled' => false,
                'map_cache_version' => max(1, (int) ($settings->map_cache_version ?? 1)) + 1,
            ]);
            $settings->refresh();
            $autoDisabled = true;
        }

        $version = max(1, (int) ($settings->map_cache_version ?? 1));
        $tenantCachePrefix = 'tenant-map-'.$ownerId;
        $cacheName = $tenantCachePrefix.'-v'.$version;

        $centerLatitude = $settings->map_cache_center_lat;
        $centerLongitude = $settings->map_cache_center_lng;
        $enabled = (bool) $settings->map_cache_enabled && $centerLatitude !== null && $centerLongitude !== null;

        return [
            'enabled' => $enabled,
            'cache_name' => $cacheName,
            'tenant_cache_prefix' => $tenantCachePrefix,
            'auto_disabled' => $autoDisabled,
            'all_odps_geocoded' => $allOdpsGeocoded,
            'odps_total' => $odpsTotal,
            'odps_with_coordinate' => $odpsWithCoordinate,
            'max_tiles' => 1400,
            'coverage' => [
                'center_lat' => $centerLatitude !== null ? (float) $centerLatitude : null,
                'center_lng' => $centerLongitude !== null ? (float) $centerLongitude : null,
                'radius_km' => (float) ($settings->map_cache_radius_km ?? 3),
                'min_zoom' => (int) ($settings->map_cache_min_zoom ?? 14),
                'max_zoom' => (int) ($settings->map_cache_max_zoom ?? 17),
            ],
        ];
    }
}
