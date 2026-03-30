<?php

namespace App\Http\Controllers;

use App\Models\HotspotProfile;
use App\Models\HotspotUser;
use App\Models\MikrotikConnection;
use App\Models\Odp;
use App\Models\PppProfile;
use App\Models\PppUser;
use App\Models\ProfileGroup;
use App\Models\TenantSettings;
use App\Services\WaGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WaBlastController extends Controller
{
    private function authorizeAccess(): void
    {
        $user = auth()->user();
        if (
            ! $user->isSuperAdmin()
            && ! in_array($user->role, ['administrator', 'noc', 'it_support', 'cs'])
        ) {
            abort(403);
        }
    }

    public function index(Request $request): View|RedirectResponse
    {
        $this->authorizeAccess();

        $currentUser = $request->user();
        $settings = TenantSettings::getOrCreate($currentUser->effectiveOwnerId());

        if (! $settings->wa_broadcast_enabled && ! $currentUser->isSuperAdmin()) {
            return redirect()->route('wa-gateway.index')
                ->with('error', 'Fitur WA Blast belum diaktifkan. Aktifkan toggle "Aktifkan Fitur WA Blast" di halaman ini lalu simpan.');
        }

        $pppProfiles = PppProfile::query()->accessibleBy($currentUser)->orderBy('name')->get();
        $hotspotProfiles = HotspotProfile::query()->accessibleBy($currentUser)->orderBy('name')->get();
        $odps = Odp::query()->accessibleBy($currentUser)->orderBy('name')->get();
        $nasConnections = MikrotikConnection::query()->accessibleBy($currentUser)->orderBy('name')->get();

        return view('wa-blast.index', compact('settings', 'pppProfiles', 'hotspotProfiles', 'odps', 'nasConnections'));
    }

    /**
     * Preview: return count + phone numbers based on filter.
     */
    public function preview(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $currentUser = $request->user();
        $tipe = $request->input('tipe', 'ppp');
        $statusAkun = $request->input('status_akun', '');
        $statusBayar = $request->input('status_bayar', '');
        $profileId = $request->input('profile_id', '');
        $odpIds = $request->input('odp_ids', []);
        $nasIds = $request->input('nas_ids', []);
        $recipientKeys = $request->input('recipient_keys', []);

        $recipients = $this->buildRecipients($currentUser, $tipe, $statusAkun, $statusBayar, $profileId, '', $odpIds, $nasIds, $recipientKeys);

        return response()->json([
            'count' => count($recipients),
            'phones' => array_column($recipients, 'phone'),
        ]);
    }

    /**
     * Send broadcast messages.
     */
    public function send(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'tipe' => 'required|in:ppp,hotspot,all',
            'status_akun' => 'nullable|string',
            'status_bayar' => 'nullable|string',
            'profile_id' => 'nullable|integer',
            'odp_ids' => 'nullable|array',
            'odp_ids.*' => 'integer',
            'nas_ids' => 'nullable|array',
            'nas_ids.*' => 'integer',
            'recipient_keys' => 'nullable|array',
            'recipient_keys.*' => 'string',
            'message' => 'required|string|min:5|max:4096',
        ]);

        $currentUser = $request->user();
        $settings = TenantSettings::getOrCreate($currentUser->effectiveOwnerId());

        if (! $settings->wa_broadcast_enabled && ! $currentUser->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Fitur WA Blast tidak aktif.'], 403);
        }

        $waService = WaGatewayService::forTenant($settings);
        if (! $waService) {
            return response()->json(['success' => false, 'message' => 'WA Gateway belum dikonfigurasi di Pengaturan.'], 422);
        }

        $recipients = $this->buildRecipients(
            $currentUser,
            $validated['tipe'],
            $validated['status_akun'] ?? '',
            $validated['status_bayar'] ?? '',
            $validated['profile_id'] ?? '',
            $validated['message'],
            $validated['odp_ids'] ?? [],
            $validated['nas_ids'] ?? [],
            $validated['recipient_keys'] ?? []
        );

        if (empty($recipients)) {
            return response()->json(['success' => false, 'message' => 'Tidak ada penerima yang cocok dengan filter.'], 422);
        }

        $result = $waService->sendBulk($recipients);

        return response()->json([
            'success' => true,
            'message' => "Pesan terkirim ke {$result['success']} penerima. Gagal/Skip: {$result['failed']}.",
            'success_count' => $result['success'],
            'failed_count' => $result['failed'],
            'results' => $result['results'],
        ]);
    }

    /**
     * Build recipient list from filters.
     *
     * @return array<array{phone: string, message: string, name: string}>
     */
    private function buildRecipients($currentUser, string $tipe, string $statusAkun, string $statusBayar, $profileId, string $message, array $odpIds = [], array $nasIds = [], array $recipientKeys = []): array
    {
        $recipients = [];
        [$selectedPppIds, $selectedHotspotIds] = $this->resolveRecipientKeys($recipientKeys);
        $hasSpecificRecipients = $selectedPppIds !== [] || $selectedHotspotIds !== [];

        // Resolve profile_group_ids dari NAS yang dipilih (berlaku untuk PPP maupun Hotspot)
        $nasProfileGroupIds = [];
        if (! empty($nasIds)) {
            $nasProfileGroupIds = ProfileGroup::whereIn('mikrotik_connection_id', $nasIds)
                ->pluck('id')
                ->all();
        }

        if (($tipe === 'ppp' || $tipe === 'all') && (! $hasSpecificRecipients || $selectedPppIds !== [])) {
            $query = PppUser::query()->accessibleBy($currentUser)->whereNotNull('nomor_hp')->where('nomor_hp', '!=', '');

            if ($statusAkun !== '') {
                $query->where('status_akun', $statusAkun);
            }
            if ($statusBayar !== '') {
                $query->where('status_bayar', $statusBayar);
            }
            if ($profileId !== '' && $profileId !== null) {
                $query->where('ppp_profile_id', (int) $profileId);
            }
            if (! empty($odpIds)) {
                $query->whereIn('odp_id', $odpIds);
            }
            if (! empty($nasProfileGroupIds)) {
                $query->whereIn('profile_group_id', $nasProfileGroupIds);
            }
            if ($selectedPppIds !== []) {
                $query->whereIn('id', $selectedPppIds);
            }

            foreach ($query->get() as $user) {
                $recipients[] = [
                    'phone' => $user->nomor_hp,
                    'message' => $message,
                    'name' => $user->customer_name,
                ];
            }
        }

        if (($tipe === 'hotspot' || $tipe === 'all') && (! $hasSpecificRecipients || $selectedHotspotIds !== [])) {
            $query = HotspotUser::query()->accessibleBy($currentUser)->whereNotNull('nomor_hp')->where('nomor_hp', '!=', '');

            if ($statusAkun !== '') {
                $query->where('status_akun', $statusAkun);
            }
            if ($statusBayar !== '') {
                $query->where('status_bayar', $statusBayar);
            }
            if ($profileId !== '' && $profileId !== null && $tipe === 'hotspot') {
                $query->where('hotspot_profile_id', (int) $profileId);
            }
            if (! empty($nasProfileGroupIds)) {
                $query->whereIn('profile_group_id', $nasProfileGroupIds);
            }
            if ($selectedHotspotIds !== []) {
                $query->whereIn('id', $selectedHotspotIds);
            }

            foreach ($query->get() as $user) {
                $recipients[] = [
                    'phone' => $user->nomor_hp,
                    'message' => $message,
                    'name' => $user->customer_name,
                ];
            }
        }

        return $recipients;
    }

    public function customerOptions(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $currentUser = $request->user();
        $tipe = (string) $request->input('tipe', 'all');
        $keyword = trim((string) $request->input('q', ''));

        $results = [];

        if (in_array($tipe, ['ppp', 'all'], true)) {
            $query = PppUser::query()
                ->accessibleBy($currentUser)
                ->whereNotNull('nomor_hp')
                ->where('nomor_hp', '!=', '');

            if ($keyword !== '') {
                $query->where(function ($inner) use ($keyword): void {
                    $inner->where('customer_name', 'like', "%{$keyword}%")
                        ->orWhere('customer_id', 'like', "%{$keyword}%")
                        ->orWhere('username', 'like', "%{$keyword}%")
                        ->orWhere('nomor_hp', 'like', "%{$keyword}%");
                });
            }

            $query->orderBy('customer_name')->limit(20)->get(['id', 'customer_name', 'customer_id', 'username', 'nomor_hp'])
                ->each(function (PppUser $user) use (&$results): void {
                    $name = trim((string) ($user->customer_name ?: $user->username ?: $user->customer_id ?: 'Pelanggan PPP'));
                    $results[] = [
                        'id' => 'ppp:'.$user->id,
                        'text' => sprintf('PPPoE · %s (%s)', $name, $user->nomor_hp),
                    ];
                });
        }

        if (in_array($tipe, ['hotspot', 'all'], true)) {
            $query = HotspotUser::query()
                ->accessibleBy($currentUser)
                ->whereNotNull('nomor_hp')
                ->where('nomor_hp', '!=', '');

            if ($keyword !== '') {
                $query->where(function ($inner) use ($keyword): void {
                    $inner->where('customer_name', 'like', "%{$keyword}%")
                        ->orWhere('customer_id', 'like', "%{$keyword}%")
                        ->orWhere('username', 'like', "%{$keyword}%")
                        ->orWhere('nomor_hp', 'like', "%{$keyword}%");
                });
            }

            $query->orderBy('customer_name')->limit(20)->get(['id', 'customer_name', 'customer_id', 'username', 'nomor_hp'])
                ->each(function (HotspotUser $user) use (&$results): void {
                    $name = trim((string) ($user->customer_name ?: $user->username ?: $user->customer_id ?: 'Pelanggan Hotspot'));
                    $results[] = [
                        'id' => 'hotspot:'.$user->id,
                        'text' => sprintf('Hotspot · %s (%s)', $name, $user->nomor_hp),
                    ];
                });
        }

        return response()->json(['results' => $results]);
    }

    /**
     * @param  array<int, mixed>  $recipientKeys
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    private function resolveRecipientKeys(array $recipientKeys): array
    {
        $selectedPppIds = [];
        $selectedHotspotIds = [];

        foreach ($recipientKeys as $recipientKey) {
            $raw = trim((string) $recipientKey);
            if ($raw === '' || ! str_contains($raw, ':')) {
                continue;
            }

            [$type, $id] = explode(':', $raw, 2);
            $numericId = (int) $id;
            if ($numericId < 1) {
                continue;
            }

            if ($type === 'ppp') {
                $selectedPppIds[] = $numericId;
            } elseif ($type === 'hotspot') {
                $selectedHotspotIds[] = $numericId;
            }
        }

        return [array_values(array_unique($selectedPppIds)), array_values(array_unique($selectedHotspotIds))];
    }
}
