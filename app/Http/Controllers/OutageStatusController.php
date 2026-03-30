<?php

namespace App\Http\Controllers;

use App\Models\Outage;
use App\Models\TenantSettings;

class OutageStatusController extends Controller
{
    public function show(string $token)
    {
        $outage = Outage::where('public_token', $token)->firstOrFail();

        $outage->load([
            'affectedAreas.odp:id,name,area',
            'updates' => fn ($q) => $q->where('is_public', true)->orderBy('created_at'),
            'updates.user:id,name,nickname',
            'assignedTeknisi:id,name,nickname',
        ]);

        $settings = TenantSettings::where('user_id', $outage->owner_id)->first();

        return view('outages.public-status', compact('outage', 'settings'));
    }
}
