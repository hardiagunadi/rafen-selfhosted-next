<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\MikrotikConnection;
use App\Models\Outage;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Models\WaConversation;
use App\Models\WaTicket;
use App\Services\GenieAcsClient;
use App\Services\MikrotikApiClient;
use App\Services\WaGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Throwable;

class PortalDashboardController extends Controller
{
    private function getPppUser(Request $request): PppUser
    {
        return $request->attributes->get('portal_ppp_user');
    }

    private function getPortalSlug(Request $request): string
    {
        return (string) $request->query('slug', '');
    }

    public function index(Request $request)
    {
        $pppUser = $this->getPppUser($request);
        $portalSlug = $this->getPortalSlug($request);
        $pppUser->load(['profile', 'owner.tenantSettings', 'cpeDevice']);

        $latestInvoice = Invoice::where('ppp_user_id', $pppUser->id)
            ->orderByRaw("CASE WHEN status = 'unpaid' THEN 0 ELSE 1 END")
            ->orderByDesc('due_date')
            ->first();

        if ($latestInvoice && empty($latestInvoice->payment_token)) {
            $latestInvoice->update(['payment_token' => Invoice::generatePaymentToken()]);
            $latestInvoice->refresh();
        }

        // Cek gangguan jaringan aktif yang terdampak ke pelanggan ini
        $activeOutages = collect();
        if ($pppUser->odp_id || $pppUser->alamat) {
            $outageQuery = Outage::query()
                ->where('owner_id', $pppUser->owner_id)
                ->whereIn('status', [Outage::STATUS_OPEN, Outage::STATUS_IN_PROGRESS])
                ->where(function ($q) use ($pppUser) {
                    if ($pppUser->odp_id) {
                        $q->orWhereHas('affectedAreas', fn ($aq) => $aq->where('area_type', 'odp')->where('odp_id', $pppUser->odp_id)
                        );
                    }
                    if ($pppUser->alamat) {
                        $q->orWhereHas('affectedAreas', fn ($aq) => $aq->where('area_type', 'keyword')
                            ->whereRaw('? LIKE CONCAT("%", label, "%")', [$pppUser->alamat])
                        );
                    }
                })
                ->with(['updates' => fn ($q) => $q->where('is_public', true)->latest()->limit(1)])
                ->orderByDesc('started_at');

            $activeOutages = $outageQuery->get();
        }

        return view('portal.dashboard', compact('pppUser', 'latestInvoice', 'portalSlug', 'activeOutages'));
    }

    public function invoices(Request $request)
    {
        $pppUser = $this->getPppUser($request);
        $portalSlug = $this->getPortalSlug($request);
        $pppUser->load(['owner.tenantSettings']);

        $invoices = Invoice::where('ppp_user_id', $pppUser->id)
            ->orderByDesc('due_date')
            ->paginate(15);

        foreach ($invoices as $invoice) {
            if (empty($invoice->payment_token)) {
                $invoice->update(['payment_token' => Invoice::generatePaymentToken()]);
            }
        }

        return view('portal.invoices', compact('pppUser', 'invoices', 'portalSlug'));
    }

    public function account(Request $request)
    {
        $pppUser = $this->getPppUser($request);
        $portalSlug = $this->getPortalSlug($request);
        $pppUser->load(['profile', 'owner.tenantSettings']);

        return view('portal.account', compact('pppUser', 'portalSlug'));
    }

    public function changePassword(Request $request)
    {
        $pppUser = $this->getPppUser($request);

        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $storedPassword = $pppUser->password_clientarea;

        $valid = false;
        try {
            $valid = Hash::check($request->current_password, $storedPassword);
        } catch (Throwable) {
        }
        if (! $valid) {
            $valid = $storedPassword === $request->current_password;
        }

        if (! $valid) {
            return response()->json(['success' => false, 'message' => 'Password lama tidak sesuai.'], 422);
        }

        $pppUser->update(['password_clientarea' => Hash::make($request->new_password)]);

        return response()->json(['success' => true, 'message' => 'Password berhasil diubah.']);
    }

    public function updateWifi(Request $request)
    {
        $pppUser = $this->getPppUser($request);

        $validated = $request->validate([
            'ssid' => ['required', 'string', 'max:32'],
            'password' => ['nullable', 'string', 'min:8', 'max:63'],
        ]);

        $ownerId = $pppUser->owner_id;
        $settings = TenantSettings::where('user_id', $ownerId)->first();

        // Check if tenant has GenieACS configured
        if (! $settings || ! $settings->hasGenieacsConfigured()) {
            return response()->json([
                'success' => false,
                'no_genieacs' => true,
                'message' => 'Fitur ganti WiFi tidak tersedia saat ini. Silakan buat tiket bantuan.',
            ], 422);
        }

        $device = $pppUser->cpeDevice;

        if (! $device || ! $device->genieacs_device_id) {
            return response()->json([
                'success' => false,
                'no_device' => true,
                'message' => 'Perangkat Anda belum terdaftar di sistem. Silakan buat tiket bantuan.',
            ], 422);
        }

        $client = GenieAcsClient::fromTenantSettings($settings);

        try {
            $result = $client->setWifi(
                $device->genieacs_device_id,
                $validated['ssid'],
                $validated['password'] ?? null,
                $device->param_profile ?? 'igd'
            );
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'no_genieacs' => true,
                'message' => 'Gagal terhubung ke perangkat. Silakan buat tiket bantuan untuk meminta teknisi mengubah WiFi Anda.',
            ], 503);
        }

        // Update cached SSID
        $cached = $device->cached_params ?? [];
        $cached['wifi_ssid'] = $validated['ssid'];
        $device->cached_params = $cached;
        $device->save();

        $passwordWasChanged = array_key_exists('password', $validated) && is_string($validated['password']) && $validated['password'] !== '';

        $msg = $result['queued']
            ? ($passwordWasChanged
                ? 'Pengaturan WiFi dikirim. Nama dan password WiFi akan berubah saat modem online.'
                : 'Pengaturan WiFi dikirim. Nama WiFi akan berubah saat modem online.')
            : ($passwordWasChanged
                ? 'Nama dan password WiFi berhasil diubah.'
                : 'Nama WiFi berhasil diubah.');

        return response()->json(['success' => true, 'message' => $msg]);
    }

    public function getTraffic(Request $request): JsonResponse
    {
        $pppUser = $this->getPppUser($request);
        $queueName = '<pppoe-'.$pppUser->username.'>';
        $connections = MikrotikConnection::where('owner_id', $pppUser->owner_id)->get();

        foreach ($connections as $connection) {
            try {
                $client = new MikrotikApiClient($connection);
                $result = $client->command('/queue/simple/print', [], [
                    'name' => $queueName,
                ]);
                $client->disconnect();

                $queue = $result['data'][0] ?? null;
                if (! $queue) {
                    continue;
                }

                [$bytesIn, $bytesOut] = array_pad(explode('/', $queue['bytes'] ?? '0/0'), 2, '0');
                [$rxRate, $txRate] = array_pad(explode('/', $queue['rate'] ?? '0/0'), 2, '0');

                return response()->json([
                    'is_active' => true,
                    'tx' => (int) $txRate,
                    'rx' => (int) $rxRate,
                    'bytes_in' => (int) $bytesIn,
                    'bytes_out' => (int) $bytesOut,
                ]);
            } catch (\RuntimeException) {
                continue;
            }
        }

        return response()->json(['is_active' => false]);
    }

    public function storeTicket(Request $request)
    {
        $pppUser = $this->getPppUser($request);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'type' => ['required', 'string', 'in:complaint,installation,troubleshoot,other'],
        ]);

        $ownerId = $pppUser->owner_id;

        // Get or create conversation
        $conversation = WaConversation::firstOrCreate(
            ['owner_id' => $ownerId, 'contact_phone' => $pppUser->nomor_hp ?? ''],
            ['contact_name' => $pppUser->customer_name, 'status' => 'open']
        );

        $ticket = WaTicket::create([
            'owner_id' => $ownerId,
            'conversation_id' => $conversation->id,
            'title' => $data['subject'],
            'description' => $data['message'],
            'type' => $data['type'],
            'priority' => 'normal',
            'status' => 'open',
        ]);

        // Notify CS via WA
        try {
            $settings = TenantSettings::where('user_id', $ownerId)->first();
            if ($settings && $settings->hasWaConfigured() && $settings->business_phone) {
                $service = WaGatewayService::forTenant($settings);
                if ($service) {
                    $name = $pppUser->customer_name ?? $pppUser->nomor_hp;
                    $msg = "Tiket pengaduan baru dari portal pelanggan.\n\nPelanggan: {$name}\nJudul: {$ticket->title}\nTipe: {$ticket->type}\n\nCek dashboard untuk detail.";
                    $service->sendMessage($settings->business_phone, $msg, ['event' => 'ticket_from_portal']);
                }
            }
        } catch (Throwable) {
            // Non-blocking
        }

        // Notify customer (confirmation)
        try {
            $settings = $settings ?? TenantSettings::where('user_id', $ownerId)->first();
            if ($settings && $settings->hasWaConfigured() && $pppUser->nomor_hp) {
                $service = WaGatewayService::forTenant($settings);
                if ($service) {
                    $publicUrl = $ticket->publicUrl();
                    $msg = "Tiket pengaduan Anda #{$ticket->id} berhasil dibuat.\nJudul: {$ticket->title}\n\nPantau progres tiket Anda:\n{$publicUrl}\n\nTim kami akan segera menanganinya. Terima kasih.";
                    $service->sendMessage($pppUser->nomor_hp, $msg, ['event' => 'ticket_created_portal']);
                }
            }
        } catch (Throwable) {
            // Non-blocking
        }

        return response()->json(['success' => true, 'ticket_id' => $ticket->id, 'message' => 'Tiket berhasil dibuat.']);
    }
}
