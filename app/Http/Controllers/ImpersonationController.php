<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ImpersonationController extends Controller
{
    public function start(User $tenant): RedirectResponse
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
        abort_unless($tenant->isAdmin() && ! $tenant->isSuperAdmin() && ! $tenant->isSubUser(), 403);

        session(['impersonating_tenant_id' => $tenant->id]);

        return redirect()->route('dashboard');
    }

    public function stop(): RedirectResponse
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        session()->forget('impersonating_tenant_id');

        return redirect()->route('super-admin.dashboard');
    }
}
