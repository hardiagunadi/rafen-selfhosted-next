<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        if (! $this->isModuleEnabled($user, $module)) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'message' => 'Modul tidak aktif untuk tenant ini.',
                ], 404);
            }

            abort(404);
        }

        return $next($request);
    }

    private function isModuleEnabled(User $user, string $module): bool
    {
        return match ($module) {
            'hotspot' => $user->isHotspotModuleEnabled(),
            default => $this->unknownModule($module),
        };
    }

    private function unknownModule(string $module): false
    {
        Log::warning("EnsureTenantModuleEnabled: modul '{$module}' tidak dikenal dan selalu ditolak. Tambahkan handler di isModuleEnabled().");

        return false;
    }
}
