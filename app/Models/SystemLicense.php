<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class SystemLicense extends Model
{
    protected $fillable = [
        'status',
        'license_id',
        'customer_name',
        'instance_name',
        'fingerprint',
        'issued_at',
        'expires_at',
        'support_until',
        'grace_days',
        'domains',
        'modules',
        'limits',
        'payload',
        'validation_error',
        'uploaded_at',
        'last_verified_at',
        'restricted_mode_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
            'support_until' => 'date',
            'domains' => 'array',
            'modules' => 'array',
            'limits' => 'array',
            'payload' => 'array',
            'uploaded_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'restricted_mode_at' => 'datetime',
        ];
    }

    protected function isValid(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => in_array($this->status, ['active', 'grace'], true),
        );
    }
}
