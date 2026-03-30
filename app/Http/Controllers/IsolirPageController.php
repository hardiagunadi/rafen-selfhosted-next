<?php

namespace App\Http\Controllers;

use App\Models\TenantSettings;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman info isolir publik — dapat diakses tanpa login.
 *
 * URL: /isolir/{userId}
 *
 * Saat firewall Mikrotik men-DNAT port 80/443 dari subnet isolir ke server Rafen,
 * semua request HTTP masuk ke sini. Controller ini menampilkan halaman editable
 * milik tenant yang bersangkutan.
 *
 * Keamanan:
 * - Tidak ada link keluar ke internet di halaman ini
 * - Tidak ada auth form yang bisa dieksploitasi
 * - Hanya menampilkan informasi statis + kontak ISP
 */
class IsolirPageController extends Controller
{
    /**
     * Tampilkan halaman info isolir untuk tenant tertentu.
     * URL: GET /isolir/{userId}
     */
    public function show(Request $request, int $userId): View
    {
        $settings = TenantSettings::where('user_id', $userId)->first();

        if (! $settings) {
            // Fallback: tampilkan halaman generik jika tenant tidak ditemukan
            $settings = new TenantSettings([
                'business_name' => 'ISP',
            ]);
        }

        return view('isolir.show', [
            'settings'  => $settings,
            'title'     => $settings->getIsolirPageTitle(),
            'body'      => $settings->getIsolirPageBody(),
            'contact'   => $settings->getIsolirPageContact(),
            'bgColor'   => $settings->isolir_page_bg_color   ?: '#1a1a2e',
            'accentColor' => $settings->isolir_page_accent_color ?: '#e94560',
        ]);
    }

    /**
     * Preview halaman isolir untuk tenant yang login (digunakan di TenantSettings).
     * URL: GET /settings/tenant/isolir-preview
     */
    public function preview(Request $request): View
    {
        $user     = $request->user();
        $settings = TenantSettings::getOrCreate((int) $user->effectiveOwnerId());

        return view('isolir.show', [
            'settings'    => $settings,
            'title'       => $settings->getIsolirPageTitle(),
            'body'        => $settings->getIsolirPageBody(),
            'contact'     => $settings->getIsolirPageContact(),
            'bgColor'     => $settings->isolir_page_bg_color    ?: '#1a1a2e',
            'accentColor' => $settings->isolir_page_accent_color ?: '#e94560',
            'isPreview'   => true,
        ]);
    }
}
