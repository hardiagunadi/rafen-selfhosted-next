<?php

namespace App\Models;

use Database\Factories\ServiceNoteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ServiceNote extends Model
{
    /** @use HasFactory<ServiceNoteFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'ppp_user_id',
        'created_by',
        'paid_by',
        'note_type',
        'document_number',
        'document_title',
        'summary_title',
        'service_type',
        'status',
        'note_date',
        'customer_id',
        'customer_name',
        'customer_phone',
        'customer_address',
        'package_name',
        'item_lines',
        'subtotal',
        'total',
        'payment_method',
        'transfer_accounts',
        'show_service_section',
        'cash_received',
        'notes',
        'footer',
        'paid_at',
        'printed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'note_date' => 'date',
            'item_lines' => 'array',
            'transfer_accounts' => 'array',
            'show_service_section' => 'boolean',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'cash_received' => 'decimal:2',
            'paid_at' => 'datetime',
            'printed_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function pppUser(): BelongsTo
    {
        return $this->belongsTo(PppUser::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            $impersonatingId = session('impersonating_tenant_id');

            if ($impersonatingId) {
                return $query->where('owner_id', $impersonatingId);
            }

            return $query;
        }

        return $query->where('owner_id', $user->effectiveOwnerId());
    }

    public static function generateNumber(int $ownerId): string
    {
        return DB::transaction(function () use ($ownerId): string {
            $yearMonth = now()->format('Ym');
            $prefix = 'NTA-'.$yearMonth;
            $last = static::query()
                ->where('owner_id', $ownerId)
                ->where('document_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->max('document_number');

            $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
    }
}
