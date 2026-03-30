<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class HelpController extends Controller
{
    public function index(): View
    {
        return view('help.index');
    }

    public function topic(string $slug): View
    {
        $topics = [
            'freeradius' => 'help.topics.freeradius',
            'hotspot' => 'help.topics.hotspot',
            'pppoe' => 'help.topics.pppoe',
            'wireguard' => 'help.topics.wireguard',
            'voucher' => 'help.topics.voucher',
            'profil-paket' => 'help.topics.profil-paket',
            'session' => 'help.topics.session',
            'invoice' => 'help.topics.invoice',
            'troubleshoot' => 'help.topics.troubleshoot',
            'panduan-role' => 'help.topics.panduan-role',
            'fitur-operasional' => 'help.topics.fitur-operasional',
            'faq' => 'help.topics.faq',
            'whatsapp-gateway' => 'help.topics.whatsapp-gateway',
            'pelanggan-infrastruktur' => 'help.topics.pelanggan-infrastruktur',
            'cpe-genieacs' => 'help.topics.cpe-genieacs',
            'chat-wa-ticketing' => 'help.topics.chat-wa-ticketing',
            'gangguan-jaringan' => 'help.topics.gangguan-jaringan',
            'jadwal-shift' => 'help.topics.jadwal-shift',
            'wallet-withdrawal' => 'help.topics.wallet-withdrawal',
            'tool-sistem-audit' => 'help.topics.tool-sistem-audit',
            'super-admin-platform' => 'help.topics.super-admin-platform',
        ];

        abort_unless(isset($topics[$slug]), 404);

        return view($topics[$slug]);
    }
}
