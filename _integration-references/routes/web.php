<?php

use App\Http\Controllers\ActiveSessionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BandwidthProfileController;
use App\Http\Controllers\CpeController;
use App\Http\Controllers\CustomerMapController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FreeRadiusSettingsController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\HotspotProfileController;
use App\Http\Controllers\HotspotUserController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\IncomeReportController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\IsolirPageController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\MetaWhatsAppWebhookController;
use App\Http\Controllers\MikrotikConnectionController;
use App\Http\Controllers\OdpController;
use App\Http\Controllers\OltConnectionController;
use App\Http\Controllers\OutageController;
use App\Http\Controllers\OutageStatusController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalDashboardController;
use App\Http\Controllers\PppProfileController;
use App\Http\Controllers\PppUserController;
use App\Http\Controllers\ProfileGroupController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RadiusAccountController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SuperAdminLicensePublicKeyController;
use App\Http\Controllers\SuperAdminSelfHostedToolkitController;
use App\Http\Controllers\SuperAdminTerminalController;
use App\Http\Controllers\SystemToolController;
use App\Http\Controllers\TeknisiSetoranController;
use App\Http\Controllers\TenantSettingsController;
use App\Http\Controllers\TenantWalletController;
use App\Http\Controllers\TicketPublicController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\WaBlastController;
use App\Http\Controllers\WaChatController;
use App\Http\Controllers\WaKeywordRuleController;
use App\Http\Controllers\WaMultiSessionProxyController;
use App\Http\Controllers\WaTicketController;
use App\Http\Controllers\WaWebhookController;
use App\Http\Controllers\WgSettingsController;
use App\Http\Controllers\WithdrawalController;
use App\Http\Middleware\SuperAdminMiddleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

$selfHostedLicenseEnabled = (bool) config('license.self_hosted_enabled', false);
$tenantAppMiddleware = array_values(array_filter([
    'auth',
    'tenant',
    $selfHostedLicenseEnabled ? 'system.license' : null,
]));
$toolsMiddleware = array_values(array_filter([
    'auth',
    $selfHostedLicenseEnabled ? 'system.license' : null,
]));
$superAdminAppMiddleware = array_values(array_filter([
    'auth',
    SuperAdminMiddleware::class,
    $selfHostedLicenseEnabled ? 'system.license' : null,
]));
$oltFeatureMiddleware = $selfHostedLicenseEnabled ? ['system.feature:olt'] : [];
$genieacsFeatureMiddleware = $selfHostedLicenseEnabled ? ['system.feature:genieacs'] : [];
$radiusFeatureMiddleware = $selfHostedLicenseEnabled ? ['super.admin', 'system.feature:radius'] : ['super.admin'];
$vpnFeatureMiddleware = $selfHostedLicenseEnabled ? ['system.feature:vpn'] : [];
$waFeatureMiddleware = $selfHostedLicenseEnabled ? ['system.feature:wa'] : [];

// PWA manifest dinamis — nama sesuai tenant yang login
Route::get('/manifest.json', [ManifestController::class, 'admin'])->name('manifest.admin');
Route::get('/pwa-icon/{size}', [ManifestController::class, 'icon'])->whereIn('size', ['32', '180', '192', '512'])->name('manifest.admin.icon');

Route::any('/wa-multi-session/{path?}', WaMultiSessionProxyController::class)->where('path', '.*');

// VAPID public key — no auth required (public key is safe to expose)
Route::get('push/vapid-public-key', [PushSubscriptionController::class, 'vapidKey'])->name('push.vapid-key');

Route::get('login', [LoginController::class, 'show'])->name('login');
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');
Route::get('logout', function () {
    return redirect()->route('login')->with('status', 'Anda sudah logout. Gunakan tombol logout (POST) untuk keluar.');
});
Route::get('register', [RegisterController::class, 'show'])->name('register');
Route::post('register', [RegisterController::class, 'register'])->name('register.submit');

// Public API — no auth required
Route::get('api/public/plans', [SubscriptionController::class, 'publicPlans'])->name('api.public.plans');

// Public contact/support page
Route::get('contact', function () {
    return view('contact');
})->name('contact');

// Public privacy policy page
Route::get('kebijakan-privasi', function () {
    return view('privacy-policy');
})->name('privacy-policy');

// Public terms of service page
Route::get('ketentuan-layanan', function () {
    return view('terms-of-service');
})->name('terms-of-service');

Route::middleware($tenantAppMiddleware)->group(function () use (
    $genieacsFeatureMiddleware,
    $oltFeatureMiddleware,
    $radiusFeatureMiddleware,
    $vpnFeatureMiddleware,
    $waFeatureMiddleware,
): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Web Push Subscriptions — staff
    Route::post('push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::delete('push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
    Route::get('api-dashboard', [DashboardController::class, 'apiDashboard'])->name('dashboard.api');
    Route::get('api-dashboard/data', [DashboardController::class, 'apiDashboardData'])->name('dashboard.api.data');
    Route::get('api-dashboard/menu-data', [DashboardController::class, 'apiDashboardMenu'])->name('dashboard.api.menu');
    Route::get('api-dashboard/traffic', [DashboardController::class, 'apiDashboardTraffic'])->name('dashboard.api.traffic');
    // PPP Secret CRUD via MikroTik API
    Route::post('api-dashboard/ppp-secret', [DashboardController::class, 'pppSecretStore'])->name('dashboard.api.ppp-secret.store');
    Route::put('api-dashboard/ppp-secret/{id}', [DashboardController::class, 'pppSecretUpdate'])->name('dashboard.api.ppp-secret.update');
    Route::delete('api-dashboard/ppp-secret/{id}', [DashboardController::class, 'pppSecretDestroy'])->name('dashboard.api.ppp-secret.destroy');
    Route::post('api-dashboard/ppp-active/{id}/disconnect', [DashboardController::class, 'pppActiveDisconnect'])->name('dashboard.api.ppp-active.disconnect');
    Route::middleware('tenant.module:hotspot')->group(function () {
        // Hotspot User CRUD via MikroTik API
        Route::post('api-dashboard/hotspot-user', [DashboardController::class, 'hotspotUserStore'])->name('dashboard.api.hotspot-user.store');
        Route::put('api-dashboard/hotspot-user/{id}', [DashboardController::class, 'hotspotUserUpdate'])->name('dashboard.api.hotspot-user.update');
        Route::delete('api-dashboard/hotspot-user/{id}', [DashboardController::class, 'hotspotUserDestroy'])->name('dashboard.api.hotspot-user.destroy');
        Route::post('api-dashboard/hotspot-active/{id}/disconnect', [DashboardController::class, 'hotspotActiveDisconnect'])->name('dashboard.api.hotspot-active.disconnect');
        // Hotspot IP Binding CRUD via MikroTik API
        Route::post('api-dashboard/hotspot-ip-binding', [DashboardController::class, 'hotspotIpBindingStore'])->name('dashboard.api.hotspot-ip-binding.store');
        Route::put('api-dashboard/hotspot-ip-binding/{id}', [DashboardController::class, 'hotspotIpBindingUpdate'])->name('dashboard.api.hotspot-ip-binding.update');
        Route::delete('api-dashboard/hotspot-ip-binding/{id}', [DashboardController::class, 'hotspotIpBindingDestroy'])->name('dashboard.api.hotspot-ip-binding.destroy');
    });
    // PPPoE Server CRUD via MikroTik API
    Route::post('api-dashboard/pppoe-server', [DashboardController::class, 'pppoeServerStore'])->name('dashboard.api.pppoe-server.store');
    Route::put('api-dashboard/pppoe-server/{id}', [DashboardController::class, 'pppoeServerUpdate'])->name('dashboard.api.pppoe-server.update');
    Route::delete('api-dashboard/pppoe-server/{id}', [DashboardController::class, 'pppoeServerDestroy'])->name('dashboard.api.pppoe-server.destroy');
    Route::get('reports/income', IncomeReportController::class)->name('reports.income');
    Route::post('reports/income/expenses', [IncomeReportController::class, 'storeExpense'])->name('reports.expenses.store');

    // Log Aplikasi
    Route::prefix('logs')->name('logs.')->group(function () {
        Route::get('login', [LogController::class, 'loginIndex'])->name('login');
        Route::get('login/datatable', [LogController::class, 'loginDatatable'])->name('login.datatable');
        Route::get('activity', [LogController::class, 'activityIndex'])->name('activity');
        Route::get('activity/data', [LogController::class, 'activityData'])->name('activity.data');
        Route::get('bg-process', [LogController::class, 'bgProcessIndex'])->name('bg-process');
        Route::get('bg-process/datatable', [LogController::class, 'bgProcessDatatable'])->name('bg-process.datatable');
        Route::get('genieacs', [LogController::class, 'genieacsIndex'])->name('genieacs');
        Route::get('genieacs/data', [LogController::class, 'genieacsData'])->name('genieacs.data');
        Route::get('genieacs/status', [LogController::class, 'genieacsStatus'])->name('genieacs.status');
        Route::delete('genieacs/task', [LogController::class, 'genieacsDeleteTask'])->name('genieacs.delete-task');
        Route::post('genieacs/connection-request', [LogController::class, 'genieacsConnectionRequest'])->name('genieacs.connection-request');
        Route::delete('genieacs/device', [LogController::class, 'genieacsDeleteDevice'])->name('genieacs.delete-device');
        Route::get('radius-auth', [LogController::class, 'radiusAuthIndex'])->name('radius-auth');
        Route::get('radius-auth/datatable', [LogController::class, 'radiusAuthDatatable'])->name('radius-auth.datatable');
        Route::get('wa-pengiriman', [LogController::class, 'waPengirimanIndex'])->name('wa-pengiriman');
        Route::get('wa-pengiriman/keluar/datatable', [LogController::class, 'waBlastDatatable'])->name('wa-pengiriman.keluar.datatable');
        Route::get('wa-pengiriman/masuk/datatable', [LogController::class, 'waWebhookDatatable'])->name('wa-pengiriman.masuk.datatable');
        // backward-compat redirect
        Route::get('wa-blast', fn () => redirect()->route('logs.wa-pengiriman'))->name('wa-blast');
        Route::get('wa-blast/datatable', [LogController::class, 'waBlastDatatable'])->name('wa-blast.datatable');
    });
    Route::post('mikrotik-connections/test', [MikrotikConnectionController::class, 'test'])->name('mikrotik-connections.test');
    Route::post('mikrotik-connections/{mikrotikConnection}/ping', [MikrotikConnectionController::class, 'pingNow'])->name('mikrotik-connections.ping-now');
    Route::post('mikrotik-connections/{mikrotikConnection}/isolir-reset', [MikrotikConnectionController::class, 'isolirReset'])->name('mikrotik-connections.isolir-reset');
    Route::post('mikrotik-connections/radius-sync-clients', [MikrotikConnectionController::class, 'syncRadiusClients'])->name('mikrotik-connections.radius-sync-clients');
    Route::get('mikrotik-connections/datatable', [MikrotikConnectionController::class, 'datatable'])->name('mikrotik-connections.datatable');
    Route::middleware($oltFeatureMiddleware)->group(function () {
        Route::post('olt-connections/auto-detect-model', [OltConnectionController::class, 'autoDetectModel'])->name('olt-connections.auto-detect-model');
        Route::post('olt-connections/auto-detect-oid', [OltConnectionController::class, 'autoDetectOid'])->name('olt-connections.auto-detect-oid');
        Route::post('olt-connections/{oltConnection}/poll', [OltConnectionController::class, 'poll'])->name('olt-connections.poll');
        Route::post('olt-connections/{oltConnection}/onu/reboot', [OltConnectionController::class, 'rebootOnu'])->name('olt-connections.onu-reboot');
        Route::get('olt-connections/{oltConnection}/onu/status', [OltConnectionController::class, 'onuStatus'])->name('olt-connections.onu-status');
        Route::get('olt-connections/{oltConnection}/onu/alarms', [OltConnectionController::class, 'onuAlarms'])->name('olt-connections.onu-alarms');
        Route::get('olt-connections/{oltConnection}/onu/history', [OltConnectionController::class, 'onuHistory'])->name('olt-connections.onu-history');
        Route::get('olt-connections/{oltConnection}/onu/wifi-config', [OltConnectionController::class, 'onuWifiConfig'])->name('olt-connections.onu-wifi-config');
        Route::post('olt-connections/{oltConnection}/onu/wifi-update', [OltConnectionController::class, 'onuWifiUpdate'])->name('olt-connections.onu-wifi-update');
        Route::get('olt-connections/{oltConnection}/polling-status', [OltConnectionController::class, 'pollingStatus'])->name('olt-connections.polling-status');
        Route::get('olt-connections/{oltConnection}/datatable', [OltConnectionController::class, 'datatable'])->name('olt-connections.datatable');
        Route::resource('olt-connections', OltConnectionController::class);
    });
    Route::post('radius/restart', [DashboardController::class, 'restartRadius'])->name('radius.restart');
    Route::post('genieacs/restart', [DashboardController::class, 'restartGenieacs'])->name('genieacs.restart');
    Route::resource('mikrotik-connections', MikrotikConnectionController::class);
    Route::middleware($genieacsFeatureMiddleware)->group(function () {
        Route::get('cpe', [CpeController::class, 'index'])->name('cpe.index');
        Route::get('cpe/datatable', [CpeController::class, 'datatable'])->name('cpe.datatable');
        Route::get('cpe/unlinked', [CpeController::class, 'unlinkedDevices'])->name('cpe.unlinked');
        Route::post('cpe/link', [CpeController::class, 'linkDevice'])->name('cpe.link');
        Route::post('cpe/unlinked/bulk-auto-link', [CpeController::class, 'bulkAutoLink'])->name('cpe.bulk-auto-link');
        Route::post('cpe/unlinked/{genieacsId}/refresh-param', [CpeController::class, 'refreshUnlinkedParam'])
            ->name('cpe.unlinked.refresh-param')
            ->where('genieacsId', '.+');
        Route::delete('cpe/unlinked/{genieacsId}', [CpeController::class, 'deleteUnlinkedDevice'])
            ->name('cpe.unlinked.delete')
            ->where('genieacsId', '.+');
        Route::get('cpe/unlinked/{genieacsId}/info', [CpeController::class, 'showUnlinkedDeviceInfo'])
            ->name('cpe.unlinked.info')
            ->where('genieacsId', '.+');
        Route::post('cpe/unlinked/{genieacsId}/set-pppoe', [CpeController::class, 'setPppoeUnlinked'])
            ->name('cpe.unlinked.set-pppoe')
            ->where('genieacsId', '.+');
        Route::get('cpe/search-ppp-users', [CpeController::class, 'searchPppUsers'])->name('cpe.search-ppp-users');
        Route::prefix('ppp-users/{pppUserId}/cpe')->group(function () {
            Route::get('', [CpeController::class, 'show'])->name('cpe.show');
            Route::post('sync', [CpeController::class, 'sync'])->name('cpe.sync');
            Route::post('reboot', [CpeController::class, 'reboot'])->name('cpe.reboot');
            Route::post('wifi', [CpeController::class, 'updateWifi'])->name('cpe.update-wifi');
            Route::post('pppoe', [CpeController::class, 'updatePppoe'])->name('cpe.update-pppoe');
            Route::get('refresh-cache', [CpeController::class, 'refreshFromCache'])->name('cpe.refresh-cache');
            Route::post('refresh', [CpeController::class, 'refreshParams'])->name('cpe.refresh');
            Route::get('info', [CpeController::class, 'getInfo'])->name('cpe.info');
            Route::get('traffic', [CpeController::class, 'getTraffic'])->name('cpe.traffic');
            Route::get('olt-history', [CpeController::class, 'getOltHistory'])->name('cpe.olt-history');
            Route::get('olt-onus', [CpeController::class, 'searchOltOnus'])->name('cpe.olt-onus');
            Route::post('olt-link', [CpeController::class, 'linkOltOnu'])->name('cpe.olt-link');
            Route::post('mac', [CpeController::class, 'updateMac'])->name('cpe.update-mac');
            Route::delete('', [CpeController::class, 'destroy'])->name('cpe.destroy');
            Route::post('wifi/{wlanIdx}', [CpeController::class, 'updateWifiByIndex'])->name('cpe.wifi-by-index');
            Route::get('wan', [CpeController::class, 'getWanConnections'])->name('cpe.wan-list');
            Route::put('wan/{wanIdx}/{cdIdx}/{connIdx}', [CpeController::class, 'updateWanConnection'])->name('cpe.wan-update');
        });
    });

    Route::get('radius-accounts/datatable', [RadiusAccountController::class, 'datatable'])->name('radius-accounts.datatable');
    Route::resource('radius-accounts', RadiusAccountController::class);
    Route::get('bandwidth-profiles/datatable', [BandwidthProfileController::class, 'datatable'])->name('bandwidth-profiles.datatable');
    Route::delete('bandwidth-profiles/bulk-destroy', [BandwidthProfileController::class, 'bulkDestroy'])->name('bandwidth-profiles.bulk-destroy');
    Route::resource('bandwidth-profiles', BandwidthProfileController::class);
    Route::post('profile-groups/{profileGroup}/export', [ProfileGroupController::class, 'export'])->name('profile-groups.export');
    Route::post('profile-groups/export-bulk', [ProfileGroupController::class, 'bulkExport'])->name('profile-groups.export-bulk');
    Route::delete('profile-groups/bulk-destroy', [ProfileGroupController::class, 'bulkDestroy'])->name('profile-groups.bulk-destroy');
    Route::get('profile-groups/datatable', [ProfileGroupController::class, 'datatable'])->name('profile-groups.datatable');
    Route::get('profile-groups/mikrotik-queues', [ProfileGroupController::class, 'mikrotikQueues'])->name('profile-groups.mikrotik-queues');
    Route::resource('profile-groups', ProfileGroupController::class);
    Route::middleware('tenant.module:hotspot')->group(function () {
        Route::get('hotspot-profiles/datatable', [HotspotProfileController::class, 'datatable'])->name('hotspot-profiles.datatable');
        Route::delete('hotspot-profiles/bulk-destroy', [HotspotProfileController::class, 'bulkDestroy'])->name('hotspot-profiles.bulk-destroy');
        Route::resource('hotspot-profiles', HotspotProfileController::class);
    });
    Route::get('invoices/datatable', [InvoiceController::class, 'datatable'])->name('invoices.datatable');
    Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
    Route::get('invoices/{invoice}/nota', [InvoiceController::class, 'nota'])->name('invoices.nota');
    Route::get('invoices/nota-bulk', [InvoiceController::class, 'notaBulk'])->name('invoices.nota-bulk');
    Route::get('invoices/belum-lunas', [InvoiceController::class, 'unpaidIndex'])->name('invoices.unpaid');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::post('invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
    Route::get('invoices/{invoice}/pay', fn ($invoice) => redirect()->route('invoices.show', $invoice));
    Route::post('invoices/{invoice}/renew', [InvoiceController::class, 'renew'])->name('invoices.renew');
    Route::post('invoices/{invoice}/send-wa', [InvoiceController::class, 'sendWa'])->name('invoices.send-wa');
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::get('teknisi-setoran/datatable', [TeknisiSetoranController::class, 'datatable'])->name('teknisi-setoran.datatable');
    Route::get('teknisi-setoran/teknisi-list', [TeknisiSetoranController::class, 'teknisiList'])->name('teknisi-setoran.teknisi-list');
    Route::get('teknisi-setoran/{teknisiSetoran}', [TeknisiSetoranController::class, 'show'])->name('teknisi-setoran.show');
    Route::get('teknisi-setoran', [TeknisiSetoranController::class, 'index'])->name('teknisi-setoran.index');
    Route::post('teknisi-setoran', [TeknisiSetoranController::class, 'store'])->name('teknisi-setoran.store');
    Route::post('teknisi-setoran/{teknisiSetoran}/submit', [TeknisiSetoranController::class, 'submit'])->name('teknisi-setoran.submit');
    Route::post('teknisi-setoran/{teknisiSetoran}/verify', [TeknisiSetoranController::class, 'verify'])->name('teknisi-setoran.verify');
    Route::get('users/datatable', [UserManagementController::class, 'datatable'])->name('users.datatable');
    Route::resource('users', UserManagementController::class);
    Route::get('ppp-profiles/datatable', [PppProfileController::class, 'datatable'])->name('ppp-profiles.datatable');
    Route::middleware($radiusFeatureMiddleware)->group(function () {
        Route::get('settings/freeradius', [FreeRadiusSettingsController::class, 'index'])->name('settings.freeradius');
        Route::post('settings/freeradius/sync', [FreeRadiusSettingsController::class, 'sync'])->name('settings.freeradius.sync');
        Route::post('settings/freeradius/sync-replies', [FreeRadiusSettingsController::class, 'syncReplies'])->name('settings.freeradius.sync-replies');
    });
    Route::middleware($vpnFeatureMiddleware)->group(function () {
        Route::get('settings/wg', [WgSettingsController::class, 'index'])->name('settings.wg');
        Route::post('settings/wg/peers', [WgSettingsController::class, 'store'])->name('settings.wg.peers.store');
        Route::patch('settings/wg/peers/{wgPeer}', [WgSettingsController::class, 'update'])->name('settings.wg.peers.update');
        Route::delete('settings/wg/peers/{wgPeer}', [WgSettingsController::class, 'destroy'])->name('settings.wg.peers.destroy');
        Route::post('settings/wg/peers/{wgPeer}/sync', [WgSettingsController::class, 'sync'])->name('settings.wg.peers.sync');
        Route::post('settings/wg/peers/{wgPeer}/create-nas', [WgSettingsController::class, 'createNas'])->name('settings.wg.peers.create-nas');
        Route::post('settings/wg/peers/{wgPeer}/keygen', [WgSettingsController::class, 'keygen'])->name('settings.wg.peers.keygen');
        Route::post('settings/wg/save-server-keys', [WgSettingsController::class, 'saveServerKeys'])->name('settings.wg.save-server-keys');
        Route::post('settings/wg/save-host', [WgSettingsController::class, 'saveHost'])->name('settings.wg.save-host');
        Route::get('settings/wg/ping', [WgSettingsController::class, 'ping'])->name('settings.wg.ping');
    });
    Route::delete('ppp-profiles/bulk-destroy', [PppProfileController::class, 'bulkDestroy'])->name('ppp-profiles.bulk-destroy');
    Route::resource('ppp-profiles', PppProfileController::class);
    Route::get('ppp-users/datatable', [PppUserController::class, 'datatable'])->name('ppp-users.datatable');
    Route::get('ppp-users/autocomplete', [PppUserController::class, 'autocomplete'])->name('ppp-users.autocomplete');
    Route::get('ppp-users/generate-customer-id', [PppUserController::class, 'generateCustomerId'])->name('ppp-users.generate-customer-id');
    Route::delete('ppp-users/bulk-destroy', [PppUserController::class, 'bulkDestroy'])->name('ppp-users.bulk-destroy');
    Route::post('ppp-users/{pppUser}/toggle-status', [PppUserController::class, 'toggleStatus'])->name('ppp-users.toggle-status');
    Route::get('ppp-users/{pppUser}/invoice-datatable', [PppUserController::class, 'invoiceDatatable'])->name('ppp-users.invoice-datatable');
    Route::get('ppp-users/{pppUser}/dialup-datatable', [PppUserController::class, 'dialupDatatable'])->name('ppp-users.dialup-datatable');
    Route::post('ppp-users/{pppUser}/add-invoice', [PppUserController::class, 'addInvoice'])->name('ppp-users.add-invoice');
    Route::post('ppp-users/{pppUser}/disconnect', [PppUserController::class, 'disconnect'])->name('ppp-users.disconnect');
    Route::get('ppp-users/{pppUser}/nota-aktivasi', [PppUserController::class, 'notaAktivasi'])->name('ppp-users.nota-aktivasi');
    Route::resource('ppp-users', PppUserController::class);
    Route::get('odps/datatable', [OdpController::class, 'datatable'])->name('odps.datatable');
    Route::get('odps/generate-code', [OdpController::class, 'generateCode'])->name('odps.generate-code');
    Route::get('odps/autocomplete', [OdpController::class, 'autocomplete'])->name('odps.autocomplete');
    Route::resource('odps', OdpController::class);
    Route::get('customer-map', [CustomerMapController::class, 'index'])->name('customer-map.index');
    Route::get('customer-map/cache-config', [CustomerMapController::class, 'cacheConfig'])->name('customer-map.cache-config');
    Route::get('customer-map/cache-tiles', [CustomerMapController::class, 'cacheTiles'])->name('customer-map.cache-tiles');
    Route::middleware('tenant.module:hotspot')->group(function () {
        Route::get('hotspot-users/datatable', [HotspotUserController::class, 'datatable'])->name('hotspot-users.datatable');
        Route::get('hotspot-users/autocomplete', [HotspotUserController::class, 'autocomplete'])->name('hotspot-users.autocomplete');
        Route::get('hotspot-users/generate-customer-id', [HotspotUserController::class, 'generateCustomerId'])->name('hotspot-users.generate-customer-id');
        Route::delete('hotspot-users/bulk-destroy', [HotspotUserController::class, 'bulkDestroy'])->name('hotspot-users.bulk-destroy');
        Route::post('hotspot-users/{hotspotUser}/renew', [HotspotUserController::class, 'renew'])->name('hotspot-users.renew');
        Route::post('hotspot-users/{hotspotUser}/toggle-status', [HotspotUserController::class, 'toggleStatus'])->name('hotspot-users.toggle-status');
        Route::resource('hotspot-users', HotspotUserController::class);
    });
    Route::get('vouchers/datatable', [VoucherController::class, 'datatable'])->name('vouchers.datatable');
    Route::delete('vouchers/bulk-destroy', [VoucherController::class, 'bulkDestroy'])->name('vouchers.bulk-destroy');
    Route::get('vouchers/{batch}/print', [VoucherController::class, 'printBatch'])->name('vouchers.print');
    Route::resource('vouchers', VoucherController::class);
    Route::get('help', [HelpController::class, 'index'])->name('help.index');
    Route::get('help/{slug}', [HelpController::class, 'topic'])->name('help.topic');
    Route::view('branding-preview', 'branding.preview')->name('branding.preview');

    Route::get('sessions/pppoe', [ActiveSessionController::class, 'pppoe'])->name('sessions.pppoe');
    Route::get('sessions/pppoe/datatable', [ActiveSessionController::class, 'pppoeDatatable'])->name('sessions.pppoe.datatable');
    Route::get('sessions/pppoe-inactive', [ActiveSessionController::class, 'pppoeInactive'])->name('sessions.pppoe-inactive');
    Route::get('sessions/pppoe-inactive/datatable', [ActiveSessionController::class, 'pppoeInactiveDatatable'])->name('sessions.pppoe-inactive.datatable');
    Route::middleware('tenant.module:hotspot')->group(function () {
        Route::get('sessions/hotspot', [ActiveSessionController::class, 'hotspot'])->name('sessions.hotspot');
        Route::get('sessions/hotspot/datatable', [ActiveSessionController::class, 'hotspotDatatable'])->name('sessions.hotspot.datatable');
        Route::get('sessions/hotspot-inactive', [ActiveSessionController::class, 'hotspotInactive'])->name('sessions.hotspot-inactive');
        Route::get('sessions/hotspot-inactive/datatable', [ActiveSessionController::class, 'hotspotInactiveDatatable'])->name('sessions.hotspot-inactive.datatable');
    });
    Route::post('sessions/refresh-router/{connection}', [ActiveSessionController::class, 'refreshRouter'])->name('sessions.refresh-router');
    Route::post('sessions/refresh-all', [ActiveSessionController::class, 'refreshAll'])->name('sessions.refresh-all');

    // Subscription routes for tenants
    Route::prefix('subscription')->name('subscription.')->group(function () {
        Route::get('/', [SubscriptionController::class, 'index'])->name('index');
        Route::get('/subscriptions/datatable', [SubscriptionController::class, 'subscriptionsDatatable'])->name('subscriptions-datatable');
        Route::get('/plans', [SubscriptionController::class, 'plans'])->name('plans');
        Route::post('/subscribe/{plan}', [SubscriptionController::class, 'subscribe'])->name('subscribe');
        Route::get('/payment/{subscription}', [SubscriptionController::class, 'payment'])->name('payment');
        Route::post('/payment/{subscription}', [SubscriptionController::class, 'processPayment'])->name('process-payment');
        Route::get('/expired', [SubscriptionController::class, 'expired'])->name('expired');
        Route::post('/renew', [SubscriptionController::class, 'renew'])->name('renew');
        Route::get('/history', [SubscriptionController::class, 'history'])->name('history');
        Route::get('/history/datatable', [SubscriptionController::class, 'historyDatatable'])->name('history-datatable');
    });

    // Payment routes
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])->name('index');
        Route::get('/pending', [PaymentController::class, 'pendingIndex'])->name('pending');
        Route::get('/pending/datatable', [PaymentController::class, 'pendingDatatable'])->name('pending.datatable');
        Route::get('/{payment}', [PaymentController::class, 'show'])->name('show');
        Route::get('/invoice/{invoice}/create', [PaymentController::class, 'createForInvoice'])->name('create-for-invoice');
        Route::post('/invoice/{invoice}', [PaymentController::class, 'storeForInvoice'])->name('store-for-invoice');
        Route::get('/{payment}/check-status', [PaymentController::class, 'checkStatus'])->name('check-status');
        Route::get('/invoice/{invoice}/manual', [PaymentController::class, 'manualForm'])->name('manual-form');
        Route::post('/invoice/{invoice}/manual', [PaymentController::class, 'manualConfirmation'])->name('manual-confirmation');
        Route::post('/{payment}/confirm', [PaymentController::class, 'confirmManual'])->name('confirm-manual');
        Route::post('/{payment}/reject', [PaymentController::class, 'rejectManual'])->name('reject-manual');
    });
    Route::get('/payment/success', [PaymentController::class, 'success'])->name('payment.success');

    // Tenant Wallet
    Route::prefix('wallet')->name('wallet.')->group(function () {
        Route::get('/', [TenantWalletController::class, 'index'])->name('index');
        Route::get('/transactions/datatable', [TenantWalletController::class, 'transactionsDatatable'])->name('transactions.datatable');
        Route::post('/withdrawal', [TenantWalletController::class, 'requestWithdrawal'])->name('withdrawal.request');
        Route::get('/withdrawals', [TenantWalletController::class, 'withdrawalIndex'])->name('withdrawals.index');
        Route::get('/withdrawals/datatable', [TenantWalletController::class, 'withdrawalDatatable'])->name('withdrawals.datatable');
    });

    // Tenant Settings
    Route::prefix('settings/tenant')->name('tenant-settings.')->group(function () {
        Route::get('/', [TenantSettingsController::class, 'index'])->name('index');
        Route::put('/business', [TenantSettingsController::class, 'updateBusiness'])->name('update-business');
        Route::put('/payment', [TenantSettingsController::class, 'updatePayment'])->name('update-payment');
        Route::put('/modules', [TenantSettingsController::class, 'updateModules'])->name('update-modules');
        Route::put('/map-cache', [TenantSettingsController::class, 'updateMapCache'])->name('update-map-cache');
        Route::post('/test-tripay', [TenantSettingsController::class, 'testTripay'])->name('test-tripay');
        Route::post('/test-midtrans', [TenantSettingsController::class, 'testMidtrans'])->name('test-midtrans');
        Route::post('/test-duitku', [TenantSettingsController::class, 'testDuitku'])->name('test-duitku');
        Route::get('/payment-channels', [TenantSettingsController::class, 'getPaymentChannels'])->name('payment-channels');
        Route::post('/logo', [TenantSettingsController::class, 'uploadLogo'])->name('upload-logo');
        Route::post('/logo-nota', [TenantSettingsController::class, 'uploadInvoiceLogo'])->name('upload-invoice-logo');

        // Bank accounts
        Route::post('/bank-accounts', [TenantSettingsController::class, 'storeBankAccount'])->name('bank-accounts.store');
        Route::put('/bank-accounts/{bankAccount}', [TenantSettingsController::class, 'updateBankAccount'])->name('bank-accounts.update');
        Route::delete('/bank-accounts/{bankAccount}', [TenantSettingsController::class, 'destroyBankAccount'])->name('bank-accounts.destroy');
        Route::post('/bank-accounts/{bankAccount}/primary', [TenantSettingsController::class, 'setPrimaryBankAccount'])->name('bank-accounts.set-primary');

        // WhatsApp settings
        Route::get('/wa', function (Request $request) {
            $params = [];
            if ($request->user()?->isSuperAdmin()) {
                $tenantId = $request->integer('tenant_id');
                if ($tenantId > 0) {
                    $params['tenant_id'] = $tenantId;
                }
            }

            return redirect()->route('wa-gateway.index', $params);
        })->name('wa-redirect');
        Route::put('/wa', [TenantSettingsController::class, 'updateWa'])->name('update-wa');
        Route::post('/test-wa', [TenantSettingsController::class, 'testWa'])->name('test-wa');
        Route::post('/test-wa-meta', [TenantSettingsController::class, 'testWaMeta'])->name('test-wa-meta');
        Route::post('/test-template', [TenantSettingsController::class, 'testTemplate'])->name('test-template');
        Route::match(['GET', 'POST'], '/wa/session/{action}', [TenantSettingsController::class, 'sessionControl'])->name('wa-session-control');
        Route::post('/wa/service/{action}', [TenantSettingsController::class, 'serviceControl'])->name('wa-service-control');
        Route::get('/wa/devices', [TenantSettingsController::class, 'waDevices'])->name('wa-devices.index');
        Route::post('/wa/devices', [TenantSettingsController::class, 'storeWaDevice'])->name('wa-devices.store');
        Route::post('/wa/devices/{device}/default', [TenantSettingsController::class, 'setDefaultWaDevice'])->name('wa-devices.default');
        Route::post('/wa/devices/{device}/warmup', [TenantSettingsController::class, 'updateWaDeviceWarmup'])->name('wa-devices.warmup');
        Route::post('/wa/devices/{device}/test', [TenantSettingsController::class, 'testWaDevice'])->name('wa-devices.test');
        Route::delete('/wa/devices/{device}', [TenantSettingsController::class, 'destroyWaDevice'])->name('wa-devices.destroy');
        Route::post('/wa/sticky-sender/reset', [TenantSettingsController::class, 'resetWaStickySender'])->name('wa-sticky-sender.reset');
        Route::get('/wa/groups', [TenantSettingsController::class, 'getWaGroups'])->name('wa-groups.index');
        Route::post('/wa/ticket-group', [TenantSettingsController::class, 'updateTicketGroup'])->name('wa-ticket-group.update');

        // Isolir page settings
        Route::put('/isolir', [TenantSettingsController::class, 'updateIsolir'])->name('update-isolir');
        Route::get('/isolir-preview', [TenantSettingsController::class, 'isolirPreview'])->name('isolir-preview');
        // GenieACS settings
        Route::put('/genieacs', [TenantSettingsController::class, 'updateGenieacs'])->name('update-genieacs');
    });

    // WA Gateway (halaman tersendiri)
    Route::middleware($waFeatureMiddleware)->prefix('settings/wa-gateway')->name('wa-gateway.')->group(function () {
        Route::get('/', [TenantSettingsController::class, 'waGateway'])->name('index');
    });

    // WA Platform Device Request (tenant → super admin)
    Route::post('wa/platform-device-request', [TenantSettingsController::class, 'storePlatformDeviceRequest'])->name('wa-platform-device.request');
    Route::delete('wa/platform-device-request', [TenantSettingsController::class, 'cancelPlatformDeviceRequest'])->name('wa-platform-device.cancel');

    // WA Blast
    Route::prefix('wa-blast')->name('wa-blast.')->group(function () {
        Route::get('/', [WaBlastController::class, 'index'])->name('index');
        Route::get('/customer-options', [WaBlastController::class, 'customerOptions'])->name('customer-options');
        Route::get('/preview', [WaBlastController::class, 'preview'])->name('preview');
        Route::post('/send', [WaBlastController::class, 'send'])->name('send');
    });

    // Chat WA Inbox
    Route::prefix('wa-chat')->name('wa-chat.')->group(function () {
        Route::get('/', [WaChatController::class, 'index'])->name('index');
        Route::get('/conversations', [WaChatController::class, 'conversations'])->name('conversations');
        Route::get('/conversations/{waConversation}/messages', [WaChatController::class, 'show'])->name('show');
        Route::post('/conversations/{waConversation}/reply', [WaChatController::class, 'reply'])->name('reply');
        Route::post('/conversations/{waConversation}/reply-image', [WaChatController::class, 'replyImage'])->name('reply-image');
        Route::post('/conversations/{waConversation}/resolve', [WaChatController::class, 'markResolved'])->name('resolve');
        Route::post('/conversations/{waConversation}/open', [WaChatController::class, 'markOpen'])->name('open');
        Route::post('/conversations/{waConversation}/assign', [WaChatController::class, 'assign'])->name('assign');
        Route::post('/conversations/{waConversation}/resume-bot', [WaChatController::class, 'resumeBot'])->name('resume-bot');
        Route::delete('/conversations/{waConversation}', [WaChatController::class, 'destroy'])->name('destroy');
        Route::get('/search-customers', [WaChatController::class, 'searchCustomers'])->name('search-customers');
        Route::get('/assignable-users', [WaChatController::class, 'assignableUsers'])->name('assignable-users');
    });

    // Keyword Rules Bot WA
    Route::prefix('wa-keyword-rules')->name('wa-keyword-rules.')->group(function () {
        Route::get('/', [WaKeywordRuleController::class, 'index'])->name('index');
        Route::post('/', [WaKeywordRuleController::class, 'store'])->name('store');
        Route::put('/{waKeywordRule}', [WaKeywordRuleController::class, 'update'])->name('update');
        Route::delete('/{waKeywordRule}', [WaKeywordRuleController::class, 'destroy'])->name('destroy');
    });

    // Tiket WA
    // Outage Tracking — Pelacakan Gangguan Jaringan
    Route::prefix('outages')->name('outages.')->group(function () {
        Route::get('/', [OutageController::class, 'index'])->name('index');
        Route::get('/datatable', [OutageController::class, 'datatable'])->name('datatable');
        Route::get('/create', [OutageController::class, 'create'])->name('create');
        Route::post('/', [OutageController::class, 'store'])->name('store');
        Route::post('/affected-users-preview', [OutageController::class, 'affectedUsersPreview'])->name('affected-users-preview');
        Route::post('/test-blast', [OutageController::class, 'testBlast'])->name('test-blast');
        Route::get('/{outage}', [OutageController::class, 'show'])->name('show');
        Route::get('/{outage}/edit', [OutageController::class, 'edit'])->name('edit');
        Route::put('/{outage}', [OutageController::class, 'update'])->name('update');
        Route::delete('/{outage}', [OutageController::class, 'destroy'])->name('destroy');
        Route::post('/{outage}/updates', [OutageController::class, 'addUpdate'])->name('updates.store');
        Route::post('/{outage}/resolve', [OutageController::class, 'resolve'])->name('resolve');
        Route::post('/{outage}/blast', [OutageController::class, 'blast'])->name('blast');
        Route::get('/{outage}/affected-users', [OutageController::class, 'affectedUsers'])->name('affected-users');
        Route::post('/{outage}/assign', [OutageController::class, 'assign'])->name('assign');
    });

    Route::prefix('wa-tickets')->name('wa-tickets.')->group(function () {
        Route::get('/', [WaTicketController::class, 'index'])->name('index');
        Route::get('/datatable', [WaTicketController::class, 'datatable'])->name('datatable');
        Route::get('/customer-autocomplete', [WaTicketController::class, 'customerAutocomplete'])->name('customer-autocomplete');
        Route::post('/', [WaTicketController::class, 'store'])->name('store');
        Route::get('/{waTicket}', [WaTicketController::class, 'show'])->name('show');
        Route::put('/{waTicket}', [WaTicketController::class, 'update'])->name('update');
        Route::post('/{waTicket}/assign', [WaTicketController::class, 'assign'])->name('assign');
        Route::post('/{waTicket}/notes', [WaTicketController::class, 'addNote'])->name('notes.store');
        Route::get('/{waTicket}/chat', [WaTicketController::class, 'ticketChatHistory'])->name('chat.history');
        Route::post('/{waTicket}/chat', [WaTicketController::class, 'ticketChatReply'])->name('chat.reply');
        Route::delete('/{waTicket}', [WaTicketController::class, 'destroy'])->name('destroy');
    });

    // Jadwal Shift
    Route::prefix('shifts')->name('shifts.')->group(function () {
        Route::get('/', [ShiftController::class, 'index'])->name('index');
        Route::get('/my', [ShiftController::class, 'mySchedule'])->name('my');
        Route::get('/schedule', [ShiftController::class, 'schedule'])->name('schedule');
        Route::post('/schedule', [ShiftController::class, 'storeSchedule'])->name('schedule.store');
        Route::post('/schedule/bulk', [ShiftController::class, 'bulkSchedule'])->name('schedule.bulk');
        Route::delete('/schedule/{shiftSchedule}', [ShiftController::class, 'destroySchedule'])->name('schedule.destroy');
        Route::get('/definitions', [ShiftController::class, 'definitions'])->name('definitions');
        Route::post('/definitions', [ShiftController::class, 'storeDefinition'])->name('definitions.store');
        Route::put('/definitions/{shiftDefinition}', [ShiftController::class, 'updateDefinition'])->name('definitions.update');
        Route::delete('/definitions/{shiftDefinition}', [ShiftController::class, 'destroyDefinition'])->name('definitions.destroy');
        Route::get('/swap-requests', [ShiftController::class, 'swapRequests'])->name('swap-requests');
        Route::post('/swap-requests', [ShiftController::class, 'requestSwap'])->name('swap-requests.store');
        Route::post('/swap-requests/{shiftSwapRequest}/review', [ShiftController::class, 'reviewSwap'])->name('swap-requests.review');
        Route::post('/send-reminders', [ShiftController::class, 'sendReminders'])->name('send-reminders');
    });
});

// Tool Sistem (auth required, fitur sensitif dibatasi di controller)
Route::middleware($toolsMiddleware)->prefix('tools')->name('tools.')->group(function () {
    // Cek Pemakaian — semua user terotentikasi
    Route::get('usage', [SystemToolController::class, 'usageIndex'])->name('usage');
    Route::get('usage/data', [SystemToolController::class, 'usageData'])->name('usage.data');

    // Impor User — tenant admin & super admin
    Route::get('import', [SystemToolController::class, 'importIndex'])->name('import');
    Route::post('import/preview', [SystemToolController::class, 'importPreview'])->name('import.preview');
    Route::post('import/confirm', [SystemToolController::class, 'importConfirm'])->name('import.confirm');
    Route::get('import/template/{type}', [SystemToolController::class, 'importTemplate'])->name('import.template');

    // Ekspor User — tenant admin & super admin
    Route::get('export-users', [SystemToolController::class, 'exportUsersIndex'])->name('export-users');
    Route::get('export-users/download', [SystemToolController::class, 'exportUsersDownload'])->name('export-users.download');

    // Ekspor Transaksi — tenant admin & super admin
    Route::get('export-transactions', [SystemToolController::class, 'exportTransactionsIndex'])->name('export-transactions');
    Route::get('export-transactions/download', [SystemToolController::class, 'exportTransactionsDownload'])->name('export-transactions.download');

    // Backup & Restore — super admin only (dibatasi di controller)
    Route::get('backup', [SystemToolController::class, 'backupIndex'])->name('backup');
    Route::post('backup/create', [SystemToolController::class, 'backupCreate'])->name('backup.create');
    Route::get('backup/download', [SystemToolController::class, 'backupDownload'])->name('backup.download');
    Route::post('backup/restore', [SystemToolController::class, 'backupRestore'])->name('backup.restore');
    Route::delete('backup/delete', [SystemToolController::class, 'backupDelete'])->name('backup.delete');

    // Reset Laporan — super admin only
    Route::get('reset-report', [SystemToolController::class, 'resetReportIndex'])->name('reset-report');
    Route::post('reset-report', [SystemToolController::class, 'resetReportExecute'])->name('reset-report.execute');

    // Reset Database — super admin only
    Route::get('reset-database', [SystemToolController::class, 'resetDatabaseIndex'])->name('reset-database');
    Route::post('reset-database', [SystemToolController::class, 'resetDatabaseExecute'])->name('reset-database.execute');
});

// Halaman isolir publik (no auth required) — diakses via DNAT Mikrotik
Route::get('/isolir/{userId}', [IsolirPageController::class, 'show'])->name('isolir.show')->where('userId', '[0-9]+');

// Halaman status gangguan publik (no auth required) — link dibagikan via WA
Route::get('/status/{token}', [OutageStatusController::class, 'show'])->name('outage.public-status');

// Halaman progres tiket publik (no auth required) — link dibagikan via WA
Route::get('/tiket/{token}', [TicketPublicController::class, 'show'])->name('ticket.public-progress');

// Portal pembayaran pelanggan (no auth required) — diakses via link WA
Route::get('/bayar/{token}', [PaymentController::class, 'customerPortal'])->name('customer.invoice');
Route::post('/bayar/{token}/manual', [PaymentController::class, 'customerManualConfirmation'])->name('customer.invoice.manual');
Route::post('/bayar/{token}/gateway', [PaymentController::class, 'customerStorePayment'])->name('customer.invoice.gateway');

// Payment Callbacks (no auth required)
Route::post('/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');
Route::post('/payment/callback/midtrans', [PaymentController::class, 'callbackMidtrans'])->name('payment.callback.midtrans');
Route::match(['GET', 'POST'], '/payment/callback/duitku', [PaymentController::class, 'callbackDuitku'])->name('payment.callback.duitku');
Route::post('/subscription/payment/callback', [SubscriptionController::class, 'paymentCallback'])->name('subscription.payment.callback');
// Public subscription payment page (no auth required) — diakses via link email/WA
Route::get('/subscription/bayar/{token}', [SubscriptionController::class, 'publicPayment'])->name('subscription.payment.public');
Route::post('/subscription/bayar/{token}', [SubscriptionController::class, 'publicProcessPayment'])->name('subscription.payment.public.process');
Route::get('/subscription/bayar/{token}/status/{payment}', [SubscriptionController::class, 'publicCheckStatus'])->name('subscription.payment.public.check-status');

// WA Gateway Webhooks (no auth required)
// GET = verification ping from gateway, POST = actual webhook payload
Route::match(['GET', 'POST'], '/webhook/wa', [WaWebhookController::class, 'ingest'])->name('wa.webhook.ingest');
Route::match(['GET', 'POST'], '/webhook/wa/session', [WaWebhookController::class, 'session'])->name('wa.webhook.session');
Route::match(['GET', 'POST'], '/webhook/wa/message', [WaWebhookController::class, 'message'])->name('wa.webhook.message');
Route::match(['GET', 'POST'], '/webhook/wa/auto-reply', [WaWebhookController::class, 'autoReply'])->name('wa.webhook.auto-reply');
Route::match(['GET', 'POST'], '/webhook/wa/status', [WaWebhookController::class, 'status'])->name('wa.webhook.status');
Route::match(['GET', 'POST'], '/webhook/wa/{tenant}/{secret}', [WaWebhookController::class, 'ingest'])->whereNumber('tenant')->name('wa.webhook.ingest.tenant');
Route::match(['GET', 'POST'], '/webhook/wa/{tenant}/{secret}/session', [WaWebhookController::class, 'session'])->whereNumber('tenant')->name('wa.webhook.session.tenant');
Route::match(['GET', 'POST'], '/webhook/wa/{tenant}/{secret}/message', [WaWebhookController::class, 'message'])->whereNumber('tenant')->name('wa.webhook.message.tenant');
Route::match(['GET', 'POST'], '/webhook/wa/{tenant}/{secret}/auto-reply', [WaWebhookController::class, 'autoReply'])->whereNumber('tenant')->name('wa.webhook.auto-reply.tenant');
Route::match(['GET', 'POST'], '/webhook/wa/{tenant}/{secret}/status', [WaWebhookController::class, 'status'])->whereNumber('tenant')->name('wa.webhook.status.tenant');
Route::match(['GET', 'POST'], '/webhook', [WaWebhookController::class, 'ingest'])->name('wa.webhook.ingest.compat');
Route::match(['GET', 'POST'], '/webhook/session', [WaWebhookController::class, 'session'])->name('wa.webhook.session.compat');
Route::match(['GET', 'POST'], '/webhook/message', [WaWebhookController::class, 'message'])->name('wa.webhook.message.compat');
Route::match(['GET', 'POST'], '/webhook/auto-reply', [WaWebhookController::class, 'autoReply'])->name('wa.webhook.auto-reply.compat');
Route::match(['GET', 'POST'], '/webhook/status', [WaWebhookController::class, 'status'])->name('wa.webhook.status.compat');
Route::match(['GET', 'POST'], '/webhook/{tenant}/{secret}', [WaWebhookController::class, 'ingest'])->whereNumber('tenant')->name('wa.webhook.ingest.tenant.compat');
Route::match(['GET', 'POST'], '/webhook/{tenant}/{secret}/session', [WaWebhookController::class, 'session'])->whereNumber('tenant')->name('wa.webhook.session.tenant.compat');
Route::match(['GET', 'POST'], '/webhook/{tenant}/{secret}/message', [WaWebhookController::class, 'message'])->whereNumber('tenant')->name('wa.webhook.message.tenant.compat');
Route::match(['GET', 'POST'], '/webhook/{tenant}/{secret}/auto-reply', [WaWebhookController::class, 'autoReply'])->whereNumber('tenant')->name('wa.webhook.auto-reply.tenant.compat');
Route::match(['GET', 'POST'], '/webhook/{tenant}/{secret}/status', [WaWebhookController::class, 'status'])->whereNumber('tenant')->name('wa.webhook.status.tenant.compat');

// Meta WhatsApp Cloud API Webhook (no auth required)
Route::get('/webhook/meta/whatsapp', [MetaWhatsAppWebhookController::class, 'verify'])->name('meta.whatsapp.webhook.verify');
Route::post('/webhook/meta/whatsapp', [MetaWhatsAppWebhookController::class, 'receive'])->name('meta.whatsapp.webhook.receive');

// Portal Pelanggan PPPoE — tenant diidentifikasi dari subdomain (tmd.watumalang.online/portal/)
Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('/manifest.json', [PortalAuthController::class, 'manifest'])->name('manifest')
        ->withoutMiddleware(['web', StartSession::class]);
    Route::get('/icon/{size}', [PortalAuthController::class, 'icon'])->whereIn('size', ['32', '180', '192', '512'])->name('icon')
        ->withoutMiddleware(['web', StartSession::class]);
    Route::get('/login', [PortalAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [PortalAuthController::class, 'login'])->name('login.post');
    Route::post('/logout', [PortalAuthController::class, 'logout'])->name('logout');

    Route::middleware('portal.auth')->group(function () {
        Route::get('/', [PortalDashboardController::class, 'index'])->name('dashboard');
        Route::get('/invoices', [PortalDashboardController::class, 'invoices'])->name('invoices');
        Route::get('/account', [PortalDashboardController::class, 'account'])->name('account');
        Route::post('/change-password', [PortalDashboardController::class, 'changePassword'])->name('change-password');
        Route::post('/tickets', [PortalDashboardController::class, 'storeTicket'])->name('tickets.store');
        Route::post('/wifi', [PortalDashboardController::class, 'updateWifi'])->name('wifi.update');
        Route::get('/traffic', [PortalDashboardController::class, 'getTraffic'])->name('traffic');
        // Web Push Subscriptions — customer portal
        Route::post('/push/subscribe', [PushSubscriptionController::class, 'portalStore'])->name('push.subscribe');
        Route::delete('/push/unsubscribe', [PushSubscriptionController::class, 'portalDestroy'])->name('push.unsubscribe');
    });
});

// Self-hosted license routes are isolated to make extraction into a dedicated repo straightforward.
if ($selfHostedLicenseEnabled) {
    require __DIR__.'/self_hosted_license.php';
}

Route::middleware($superAdminAppMiddleware)->prefix('super-admin')->name('super-admin.')->group(function () use ($waFeatureMiddleware): void {
    Route::get('/', [SuperAdminController::class, 'dashboard'])->name('dashboard');

    // Impersonation
    Route::post('/impersonate/{tenant}', [ImpersonationController::class, 'start'])->name('impersonate');
    Route::post('/stop-impersonating', [ImpersonationController::class, 'stop'])->name('stop-impersonating');

    // Tenant Management
    Route::get('/tenants', [SuperAdminController::class, 'tenants'])->name('tenants');
    Route::get('/tenants/create', [SuperAdminController::class, 'createTenant'])->name('tenants.create');
    Route::post('/tenants', [SuperAdminController::class, 'storeTenant'])->name('tenants.store');
    Route::get('/tenants/{tenant}', [SuperAdminController::class, 'showTenant'])->name('tenants.show');
    Route::get('/tenants/{tenant}/edit', [SuperAdminController::class, 'editTenant'])->name('tenants.edit');
    Route::put('/tenants/{tenant}', [SuperAdminController::class, 'updateTenant'])->name('tenants.update');
    Route::delete('/tenants/{tenant}', [SuperAdminController::class, 'deleteTenant'])->name('tenants.delete');
    Route::post('/tenants/{tenant}/activate', [SuperAdminController::class, 'activateTenant'])->name('tenants.activate');
    Route::post('/tenants/{tenant}/suspend', [SuperAdminController::class, 'suspendTenant'])->name('tenants.suspend');
    Route::post('/tenants/{tenant}/extend', [SuperAdminController::class, 'extendTenant'])->name('tenants.extend');
    Route::post('/tenants/{tenant}/subscriptions/{subscription}/confirm-payment', [SuperAdminController::class, 'confirmSubscriptionPayment'])->name('tenants.subscriptions.confirm-payment');
    Route::delete('/tenants/{tenant}/subscriptions/{subscription}', [SuperAdminController::class, 'deleteSubscription'])->name('tenants.subscriptions.delete');
    Route::get('/tenants/{tenant}/change-plan/preview', [SuperAdminController::class, 'changePlanPreview'])->name('tenants.change-plan.preview');
    Route::post('/tenants/{tenant}/change-plan', [SuperAdminController::class, 'changePlan'])->name('tenants.change-plan');

    // Tenant VPN Management
    Route::get('/tenants/{tenant}/vpn', [SuperAdminController::class, 'vpnSettings'])->name('tenants.vpn');
    Route::put('/tenants/{tenant}/vpn', [SuperAdminController::class, 'updateVpnSettings'])->name('tenants.vpn.update');
    Route::post('/tenants/{tenant}/vpn/generate', [SuperAdminController::class, 'generateVpnCredentials'])->name('tenants.vpn.generate');

    // Subscription Plans
    Route::resource('subscription-plans', SubscriptionPlanController::class)->except(['show']);
    Route::post('/subscription-plans/{subscriptionPlan}/toggle-active', [SubscriptionPlanController::class, 'toggleActive'])->name('subscription-plans.toggle-active');

    // Payment Gateways
    Route::get('/payment-gateways', [SuperAdminController::class, 'paymentGateways'])->name('payment-gateways');
    Route::post('/payment-gateways', [SuperAdminController::class, 'storePaymentGateway'])->name('payment-gateways.store');
    Route::put('/payment-gateways/{gateway}', [SuperAdminController::class, 'updatePaymentGateway'])->name('payment-gateways.update');

    // Wallet Balances & Withdrawals (super admin)
    Route::get('/wallets', [WithdrawalController::class, 'walletBalances'])->name('wallets.index');
    Route::prefix('withdrawals')->name('withdrawals.')->group(function () {
        Route::get('/', [WithdrawalController::class, 'index'])->name('index');
        Route::get('/datatable', [WithdrawalController::class, 'datatable'])->name('datatable');
        Route::post('/{withdrawal}/approve', [WithdrawalController::class, 'approve'])->name('approve');
        Route::post('/{withdrawal}/reject', [WithdrawalController::class, 'reject'])->name('reject');
        Route::post('/{withdrawal}/settle', [WithdrawalController::class, 'settle'])->name('settle');
    });

    // Reports
    Route::get('/reports/revenue', [SuperAdminController::class, 'revenueReport'])->name('reports.revenue');
    Route::get('/reports/tenants', [SuperAdminController::class, 'tenantsReport'])->name('reports.tenants');

    // Email Settings
    Route::get('/settings/email', [SuperAdminController::class, 'emailSettings'])->name('settings.email');
    Route::put('/settings/email', [SuperAdminController::class, 'updateEmailSettings'])->name('settings.email.update');
    Route::post('/settings/email/test', [SuperAdminController::class, 'testEmailSettings'])->name('settings.email.test');
    Route::get('/settings/license-public-key', [SuperAdminLicensePublicKeyController::class, 'index'])->name('settings.license-public-key');
    Route::put('/settings/license-public-key', [SuperAdminLicensePublicKeyController::class, 'update'])->name('settings.license-public-key.update');
    Route::post('/settings/license-public-key/issue', [SuperAdminLicensePublicKeyController::class, 'issue'])->name('settings.license-public-key.issue');

    // Server Health
    Route::get('/server-health', [SuperAdminController::class, 'serverHealth'])->name('server-health');
    Route::post('/server-health/restart/{service}', [SuperAdminController::class, 'restartService'])->name('server-health.restart');
    Route::post('/server-health/clear-ram', [SuperAdminController::class, 'clearRam'])->name('server-health.clear-ram');

    // Super Admin Terminal (safe command runner)
    Route::get('/terminal', [SuperAdminTerminalController::class, 'index'])->name('terminal.index');
    Route::post('/terminal/run', [SuperAdminTerminalController::class, 'run'])->name('terminal.run');
    Route::get('/self-hosted-toolkit', [SuperAdminSelfHostedToolkitController::class, 'index'])->name('self-hosted-toolkit.index');
    Route::post('/self-hosted-toolkit/run', [SuperAdminSelfHostedToolkitController::class, 'run'])->name('self-hosted-toolkit.run');
    Route::get('/self-hosted-toolkit/download/{action}', [SuperAdminSelfHostedToolkitController::class, 'download'])->name('self-hosted-toolkit.download');

    // WA Gateway Management
    Route::middleware($waFeatureMiddleware)->group(function () {
        Route::get('/wa-gateway', [SuperAdminController::class, 'waGateway'])->name('wa-gateway');
        Route::post('/wa-gateway/upgrade', [SuperAdminController::class, 'waGatewayUpgrade'])->name('wa-gateway.upgrade');
        Route::post('/wa-gateway/restart', [SuperAdminController::class, 'waGatewayRestart'])->name('wa-gateway.restart');
        Route::post('/wa-gateway/check-update', [SuperAdminController::class, 'waGatewayCheckUpdate'])->name('wa-gateway.check-update');
        Route::post('/wa-gateway/devices/{device}/toggle-platform', [SuperAdminController::class, 'togglePlatformDevice'])->name('wa-gateway.devices.toggle-platform');
    });

    // WA Platform Device Requests
    Route::get('/wa-platform-device-requests', [SuperAdminController::class, 'platformDeviceRequests'])->name('wa-platform-device-requests.index');
    Route::post('/wa-platform-device-requests/{platformDeviceRequest}/approve', [SuperAdminController::class, 'approvePlatformDeviceRequest'])->name('wa-platform-device-requests.approve');
    Route::post('/wa-platform-device-requests/{platformDeviceRequest}/reject', [SuperAdminController::class, 'rejectPlatformDeviceRequest'])->name('wa-platform-device-requests.reject');
    Route::delete('/wa-platform-device-requests/{platformDeviceRequest}/revoke', [SuperAdminController::class, 'revokePlatformDeviceAccess'])->name('wa-platform-device-requests.revoke');

});
