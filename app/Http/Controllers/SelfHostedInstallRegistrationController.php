<?php

namespace App\Http\Controllers;

use App\Services\SelfHostedTenantRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelfHostedInstallRegistrationController extends Controller
{
    public function __invoke(
        Request $request,
        SelfHostedTenantRegistryService $selfHostedTenantRegistryService,
    ): JsonResponse {
        abort_if((bool) config('license.self_hosted_enabled', false), 404);

        $expectedToken = trim((string) config('services.self_hosted_registry.token', ''));
        abort_if($expectedToken === '', 404);

        $providedToken = trim((string) ($request->bearerToken() ?? ''));
        abort_unless(hash_equals($expectedToken, $providedToken), 403, 'Token registrasi install-time tidak valid.');

        $payload = $request->validate([
            'app_name' => ['nullable', 'string', 'max:255'],
            'app_url' => ['nullable', 'url', 'max:255'],
            'app_env' => ['nullable', 'string', 'max:50'],
            'generated_at' => ['nullable', 'date'],
            'server_name' => ['nullable', 'string', 'max:255'],
            'fingerprint' => ['required', 'string', 'regex:/^sha256:[a-f0-9]{64}$/'],
            'current_license_status' => ['nullable', 'string', 'max:50'],
            'current_license_id' => ['nullable', 'string', 'max:255'],
            'admin_name' => ['nullable', 'string', 'max:255'],
            'admin_email' => ['nullable', 'email', 'max:255'],
            'access_mode' => ['nullable', 'string', 'max:50'],
        ]);

        $tenant = $selfHostedTenantRegistryService->upsertInstallRegistration($payload);

        return response()->json([
            'message' => 'Registrasi install-time self-hosted berhasil disimpan.',
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
        ]);
    }
}
