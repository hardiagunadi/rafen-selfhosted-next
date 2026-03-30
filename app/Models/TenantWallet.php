<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class TenantWallet extends Model
{
    protected $fillable = [
        'owner_id',
        'balance',
        'total_credited',
        'total_withdrawn',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'total_credited' => 'decimal:2',
            'total_withdrawn' => 'decimal:2',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(TenantWalletTransaction::class, 'owner_id', 'owner_id');
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class, 'owner_id', 'owner_id');
    }

    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where('owner_id', $user->effectiveOwnerId());
    }

    public static function getOrCreate(int $ownerId): self
    {
        return static::firstOrCreate(
            ['owner_id' => $ownerId],
            ['balance' => 0, 'total_credited' => 0, 'total_withdrawn' => 0]
        );
    }

    /**
     * Credit wallet. Harus dipanggil di dalam DB::transaction.
     */
    public function credit(float $netAmount, float $feeDeducted, string $description, string $referenceType, int $referenceId): TenantWalletTransaction
    {
        $this->increment('balance', $netAmount);
        $this->increment('total_credited', $netAmount + $feeDeducted);
        $this->refresh();

        return TenantWalletTransaction::create([
            'owner_id' => $this->owner_id,
            'type' => 'credit',
            'amount' => $netAmount,
            'fee_deducted' => $feeDeducted,
            'balance_after' => $this->balance,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    /**
     * Debit wallet. Harus dipanggil di dalam DB::transaction.
     *
     * @throws \RuntimeException jika saldo tidak mencukupi
     */
    public function debit(float $amount, string $description, string $referenceType, int $referenceId): TenantWalletTransaction
    {
        if ((float) $this->balance < $amount) {
            throw new \RuntimeException('Saldo wallet tidak mencukupi untuk penarikan ini.');
        }

        $this->decrement('balance', $amount);
        $this->increment('total_withdrawn', $amount);
        $this->refresh();

        return TenantWalletTransaction::create([
            'owner_id' => $this->owner_id,
            'type' => 'debit',
            'amount' => $amount,
            'fee_deducted' => 0,
            'balance_after' => $this->balance,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }
}
