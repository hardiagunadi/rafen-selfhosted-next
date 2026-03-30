<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeknisiSetoran extends Model
{
    protected $fillable = [
        'owner_id',
        'teknisi_id',
        'verified_by',
        'period_date',
        'total_invoices',
        'total_tagihan',
        'total_cash',
        'status',
        'submitted_at',
        'verified_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_date'  => 'date',
            'submitted_at' => 'datetime',
            'verified_at'  => 'datetime',
            'total_tagihan' => 'decimal:2',
            'total_cash'    => 'decimal:2',
        ];
    }

    public function teknisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teknisi_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function getInvoices()
    {
        return Invoice::where('paid_by', $this->teknisi_id)
            ->whereDate('paid_at', $this->period_date)
            ->where('owner_id', $this->owner_id)
            ->with('pppUser')
            ->get();
    }

    public function recalculate(): void
    {
        $invoices = $this->getInvoices();
        $this->update([
            'total_invoices' => $invoices->count(),
            'total_tagihan'  => $invoices->sum('total'),
            'total_cash'     => $invoices->sum('cash_received'),
        ]);
    }

    public static function createOrRecalculateForUser(int $userId, int $ownerId, string $date): void
    {
        $setoran = static::firstOrCreate(
            ['owner_id' => $ownerId, 'teknisi_id' => $userId, 'period_date' => $date],
            ['total_invoices' => 0, 'total_tagihan' => 0, 'total_cash' => 0, 'status' => 'draft']
        );

        if ($setoran->status !== 'draft') {
            return;
        }

        $setoran->recalculate();
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
        $query->where('owner_id', $user->effectiveOwnerId());
        if ($user->role === 'teknisi') {
            $query->where('teknisi_id', $user->id);
        }
        return $query;
    }
}
