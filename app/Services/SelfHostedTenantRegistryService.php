<?php

namespace App\Services;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SelfHostedTenantRegistryService
{
    public function upsertInstallRegistration(array $payload): User
    {
        $fingerprint = trim((string) ($payload['fingerprint'] ?? ''));
        $tenant = $this->findByFingerprint($fingerprint);
        $defaultName = $this->defaultInstallName($payload);
        $adminEmail = $this->normalizedString($payload['admin_email'] ?? null);

        if (! $tenant) {
            $tenant = User::create([
                'name' => $defaultName,
                'email' => $this->resolvedInstallEmail($fingerprint, $adminEmail),
                'password' => Hash::make(Str::random(40)),
                'company_name' => (string) ($payload['app_name'] ?? 'Rafen Self-Hosted'),
                'role' => 'administrator',
                'is_super_admin' => false,
                'is_self_hosted_instance' => true,
                'subscription_status' => 'suspended',
                'subscription_method' => User::SUBSCRIPTION_METHOD_LICENSE,
                'self_hosted_license_id' => $this->normalizedString($payload['current_license_id'] ?? null),
                'self_hosted_instance_name' => $this->normalizedString($payload['server_name'] ?? null),
                'self_hosted_fingerprint' => $fingerprint,
                'self_hosted_app_url' => $this->normalizedString($payload['app_url'] ?? null),
                'trial_days_remaining' => 0,
                'registered_at' => now(),
            ]);
        } else {
            $tenant->update([
                'name' => $tenant->name ?: $defaultName,
                'email' => $this->resolvedInstallEmail($fingerprint, $adminEmail, $tenant),
                'company_name' => $tenant->company_name ?: (string) ($payload['app_name'] ?? 'Rafen Self-Hosted'),
                'is_self_hosted_instance' => true,
                'subscription_method' => User::SUBSCRIPTION_METHOD_LICENSE,
                'self_hosted_license_id' => $this->normalizedString($payload['current_license_id'] ?? null) ?: $tenant->self_hosted_license_id,
                'self_hosted_instance_name' => $this->normalizedString($payload['server_name'] ?? null) ?: $tenant->self_hosted_instance_name,
                'self_hosted_fingerprint' => $fingerprint,
                'self_hosted_app_url' => $this->normalizedString($payload['app_url'] ?? null) ?: $tenant->self_hosted_app_url,
                'trial_days_remaining' => 0,
            ]);
        }

        $settings = $tenant->getSettings();
        $subdomain = $this->generatedSubdomain($fingerprint);

        $settings->update([
            'business_name' => $settings->business_name ?: $tenant->company_name ?: $tenant->name,
            'business_email' => $settings->business_email ?: $adminEmail ?: $tenant->email,
            'admin_subdomain' => $settings->admin_subdomain ?: $subdomain,
            'portal_slug' => $settings->portal_slug ?: $subdomain,
        ]);

        return $tenant->refresh();
    }

    public function upsertFromIssuedLicense(array $payload, ?string $presetKey = null): User
    {
        $fingerprint = trim((string) ($payload['fingerprint'] ?? ''));
        $expiresAt = Carbon::parse((string) ($payload['expires_at'] ?? now()->toDateString()));
        $limits = is_array($payload['limits'] ?? null) ? $payload['limits'] : [];
        $status = $expiresAt->isPast() ? 'expired' : 'active';

        $tenant = $this->findByFingerprint($fingerprint);

        if (! $tenant) {
            $tenant = User::create([
                'name' => (string) ($payload['customer_name'] ?? 'Self-Hosted Tenant'),
                'email' => $this->generatedEmail($fingerprint),
                'password' => Hash::make(Str::random(40)),
                'company_name' => (string) ($payload['customer_name'] ?? 'Self-Hosted Tenant'),
                'role' => 'administrator',
                'is_super_admin' => false,
                'is_self_hosted_instance' => true,
                'subscription_status' => $status,
                'subscription_method' => User::SUBSCRIPTION_METHOD_LICENSE,
                'subscription_expires_at' => $expiresAt->toDateString(),
                'subscription_plan_id' => $this->resolvePlanId($presetKey),
                'self_hosted_license_id' => (string) ($payload['license_id'] ?? ''),
                'self_hosted_instance_name' => (string) ($payload['instance_name'] ?? ''),
                'self_hosted_fingerprint' => $fingerprint,
                'self_hosted_app_url' => null,
                'license_max_mikrotik' => $this->nullableInteger($limits['max_mikrotik'] ?? null),
                'license_max_ppp_users' => $this->nullableInteger($limits['max_ppp_users'] ?? null),
                'trial_days_remaining' => 0,
                'registered_at' => now(),
            ]);
        } else {
            $tenant->update([
                'name' => (string) ($payload['customer_name'] ?? $tenant->name),
                'company_name' => (string) ($payload['customer_name'] ?? $tenant->company_name),
                'is_self_hosted_instance' => true,
                'subscription_status' => $status,
                'subscription_method' => User::SUBSCRIPTION_METHOD_LICENSE,
                'subscription_expires_at' => $expiresAt->toDateString(),
                'subscription_plan_id' => $this->resolvePlanId($presetKey) ?? $tenant->subscription_plan_id,
                'self_hosted_license_id' => (string) ($payload['license_id'] ?? $tenant->self_hosted_license_id),
                'self_hosted_instance_name' => (string) ($payload['instance_name'] ?? $tenant->self_hosted_instance_name),
                'self_hosted_fingerprint' => $fingerprint,
                'self_hosted_app_url' => $tenant->self_hosted_app_url,
                'license_max_mikrotik' => $this->nullableInteger($limits['max_mikrotik'] ?? null),
                'license_max_ppp_users' => $this->nullableInteger($limits['max_ppp_users'] ?? null),
                'trial_days_remaining' => 0,
            ]);
        }

        $settings = $tenant->getSettings();
        $subdomain = $this->generatedSubdomain($fingerprint);

        $settings->update([
            'business_name' => (string) ($payload['customer_name'] ?? $settings->business_name ?? $tenant->name),
            'business_email' => $settings->business_email ?: $tenant->email,
            'admin_subdomain' => $settings->admin_subdomain ?: $subdomain,
            'portal_slug' => $settings->portal_slug ?: $subdomain,
        ]);

        return $tenant->refresh();
    }

    private function findByFingerprint(string $fingerprint): ?User
    {
        return User::query()
            ->where('self_hosted_fingerprint', $fingerprint)
            ->first();
    }

    private function defaultInstallName(array $payload): string
    {
        $adminName = $this->normalizedString($payload['admin_name'] ?? null);
        $serverName = $this->normalizedString($payload['server_name'] ?? null);

        return $adminName
            ?: ($serverName ? 'Self-Hosted '.$serverName : 'Self-Hosted Tenant');
    }

    private function generatedEmail(string $fingerprint): string
    {
        $suffix = substr(sha1($fingerprint), 0, 12);

        return 'selfhosted+'.$suffix.'@tenant.rafen.local';
    }

    private function generatedSubdomain(string $fingerprint): string
    {
        return 'sh-'.substr(sha1($fingerprint), 0, 12);
    }

    private function resolvedInstallEmail(string $fingerprint, ?string $adminEmail, ?User $tenant = null): string
    {
        if ($adminEmail !== null && $this->emailAvailableForTenant($adminEmail, $tenant)) {
            return $adminEmail;
        }

        return $tenant?->email ?: $this->generatedEmail($fingerprint);
    }

    private function emailAvailableForTenant(string $email, ?User $tenant = null): bool
    {
        return ! User::query()
            ->where('email', $email)
            ->when($tenant !== null, fn ($query) => $query->whereKeyNot($tenant->getKey()))
            ->exists();
    }

    private function resolvePlanId(?string $presetKey): ?int
    {
        if (! is_string($presetKey) || $presetKey === '') {
            return null;
        }

        return SubscriptionPlan::query()
            ->where('slug', $presetKey)
            ->value('id');
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizedString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
