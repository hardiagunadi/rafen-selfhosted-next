<?php

namespace App\Http\Controllers;

use App\Models\TenantSettings;
use App\Models\WaTicket;

class TicketPublicController extends Controller
{
    public function show(string $token)
    {
        $ticket = WaTicket::where('public_token', $token)->firstOrFail();

        $ticket->load([
            'assignedTo:id,name,nickname',
            'notes' => fn ($q) => $q
                ->whereIn('type', ['created', 'status_change', 'assigned', 'note'])
                ->orderBy('created_at'),
            'notes.user:id,name,nickname',
        ]);

        $settings = TenantSettings::where('user_id', $ticket->owner_id)->first();

        return view('tickets.public-progress', compact('ticket', 'settings'));
    }
}
