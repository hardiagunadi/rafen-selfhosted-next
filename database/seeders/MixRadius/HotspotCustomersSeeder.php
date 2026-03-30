<?php

namespace Database\Seeders\MixRadius;

use App\Models\HotspotProfile;
use App\Models\HotspotUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class HotspotCustomersSeeder extends Seeder
{
    public function run(MixRadiusSqlParser $parser): void
    {
        $rows = $parser->getTableData('tbl_customers');

        $defaultOwner = User::where('is_super_admin', true)->first()
            ?? User::first();

        $count = 0;

        foreach ($rows as $row) {
            if (strtoupper($row['type'] ?? '') !== 'HOTSPOT') {
                continue;
            }

            $profile = HotspotProfile::where('name', $row['plan_name'] ?? '')->first();
            $jatuhTempo = $this->parseValidDate($row['expired_on'] ?? null);
            $isExpired = $jatuhTempo && $jatuhTempo->isPast();

            HotspotUser::updateOrCreate(
                ['mixradius_id' => (string) $row['id']],
                [
                    'status_registrasi'  => 'aktif',
                    'tipe_pembayaran'    => strtolower($row['payment_type'] ?? 'prepaid'),
                    'status_bayar'       => $isExpired ? 'belum_bayar' : 'sudah_bayar',
                    'status_akun'        => $this->mapAuthStatus($row['auth_status'] ?? null),
                    'owner_id'           => $defaultOwner->id,
                    'hotspot_profile_id' => $profile?->id,
                    'profile_group_id'   => null,
                    'tagihkan_ppn'       => false,
                    'biaya_instalasi'    => 0,
                    'jatuh_tempo'        => $jatuhTempo?->toDateString(),
                    'aksi_jatuh_tempo'   => 'isolir',
                    'customer_id'        => 'MX-'.str_pad((string) $row['id'], 6, '0', STR_PAD_LEFT),
                    'customer_name'      => ! empty($row['fullname']) ? $row['fullname'] : $row['username'],
                    'nik'                => $row['identity_number'] ?? null,
                    'nomor_hp'           => $this->normalizePhone($row['phonenumber'] ?? null),
                    'email'              => $row['email'] ?? null,
                    'alamat'             => $row['address'] ?? null,
                    'username'           => $row['username'],
                    'hotspot_password'   => $row['password'] ?? '',
                    'catatan'            => $row['note'] ?? null,
                ]
            );

            $count++;
        }

        $this->command->info("Hotspot customers imported: {$count}");
    }

    private function parseValidDate(?string $dateStr): ?Carbon
    {
        if (empty($dateStr) || $dateStr === '0000-00-00' || $dateStr === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            $dt = Carbon::parse($dateStr);
            if ($dt->year < 2000) {
                return null;
            }

            return $dt;
        } catch (\Exception) {
            return null;
        }
    }

    private function mapAuthStatus(?string $status): string
    {
        return match ($status) {
            'Disabled-Users' => 'disable',
            default          => 'enable',
        };
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        } elseif (! str_starts_with($phone, '62')) {
            $phone = '62'.$phone;
        }

        return $phone;
    }
}
