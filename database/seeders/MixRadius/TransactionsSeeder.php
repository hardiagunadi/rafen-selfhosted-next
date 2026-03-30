<?php

namespace Database\Seeders\MixRadius;

use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TransactionsSeeder extends Seeder
{
    public function run(MixRadiusSqlParser $parser): void
    {
        $rows = $parser->getTableData('tbl_transactions');

        $defaultOwner = User::where('is_super_admin', true)->first()
            ?? User::first();

        $count = 0;
        $chunk = [];

        foreach ($rows as $row) {
            $type = match (strtoupper($row['type'] ?? '')) {
                'HOTSPOT' => 'hotspot',
                'VOUCHER' => 'voucher',
                default   => 'ppp',
            };

            $tax = (float) str_replace('%', '', $row['tax'] ?? '0');
            $price = (float) ($row['price'] ?? 0);
            $taxAmount = round($price * $tax / 100, 2);

            $chunk[] = [
                'owner_id'       => $defaultOwner->id,
                'type'           => $type,
                'username'       => $row['username'] ?? null,
                'plan_name'      => $row['plan_name'] ?? null,
                'amount'         => $price,
                'tax_amount'     => $taxAmount,
                'total'          => (float) ($row['total'] ?? $price),
                'status'         => $this->mapStatus($row['trx_status'] ?? null),
                'payment_method' => $row['payment_method'] ?? null,
                'paid_at'        => ! empty($row['invoice_date']) ? Carbon::parse($row['invoice_date'])->toDateTimeString() : null,
                'period_start'   => ! empty($row['renewed_on']) ? Carbon::parse($row['renewed_on'])->toDateString() : null,
                'period_end'     => ! empty($row['expired_on']) ? Carbon::parse($row['expired_on'])->toDateString() : null,
                'mixradius_id'   => (string) $row['id'],
                'notes'          => null,
                'created_at'     => ! empty($row['invoice_date']) ? Carbon::parse($row['invoice_date'])->toDateTimeString() : now(),
                'updated_at'     => now(),
            ];

            if (count($chunk) >= 500) {
                $this->upsertChunk($chunk);
                $count += count($chunk);
                $chunk = [];
            }
        }

        if (! empty($chunk)) {
            $this->upsertChunk($chunk);
            $count += count($chunk);
        }

        $this->command->info("Transactions imported: {$count}");
    }

    private function mapStatus(?string $status): string
    {
        return match (strtoupper((string) $status)) {
            'PAID'    => 'paid',
            'PENDING' => 'unpaid',
            default   => 'unpaid',
        };
    }

    /**
     * @param array<int, array<string, mixed>> $chunk
     */
    private function upsertChunk(array $chunk): void
    {
        Transaction::upsert(
            $chunk,
            ['mixradius_id'],
            ['owner_id', 'type', 'username', 'plan_name', 'amount', 'tax_amount', 'total', 'status', 'payment_method', 'paid_at', 'period_start', 'period_end', 'updated_at']
        );
    }
}
