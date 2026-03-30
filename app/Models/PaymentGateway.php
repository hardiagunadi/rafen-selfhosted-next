<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'provider',
        'api_key',
        'private_key',
        'merchant_code',
        'callback_url',
        'is_sandbox',
        'is_active',
        'platform_fee_percent',
        'fee_description',
        'supported_channels',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_sandbox' => 'boolean',
            'is_active' => 'boolean',
            'platform_fee_percent' => 'decimal:2',
            'supported_channels' => 'array',
            'settings' => 'array',
        ];
    }

    protected $hidden = [
        'api_key',
        'private_key',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function calculateFeeAmount(float $grossAmount): float
    {
        return round($grossAmount * ((float) $this->platform_fee_percent / 100), 2);
    }

    public function calculateTenantNetAmount(float $grossAmount): float
    {
        return round($grossAmount - $this->calculateFeeAmount($grossAmount), 2);
    }

    public function getApiUrl(): string
    {
        if ($this->provider === 'tripay') {
            return $this->is_sandbox
                ? 'https://tripay.co.id/api-sandbox'
                : 'https://tripay.co.id/api';
        }

        return '';
    }
}
