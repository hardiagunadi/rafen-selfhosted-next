<?php

namespace Database\Seeders\MixRadius;

use App\Models\PppProfile;
use App\Models\PppUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PppCustomersSeeder extends Seeder
{
    public function run(MixRadiusSqlParser $parser): void
    {
        $rows = $parser->getTableData('tbl_customers');

        $defaultOwner = User::where('is_super_admin', true)->first()
            ?? User::first();

        $count = 0;

        foreach ($rows as $row) {
            if (strtoupper($row['type'] ?? '') !== 'PPP') {
                continue;
            }

            $profile = PppProfile::where('name', $row['plan_name'] ?? '')->first();
            $jatuhTempo = $this->parseValidDate($row['expired_on'] ?? null);
            $isExpired = $jatuhTempo && $jatuhTempo->isPast();
            $isStatic = ! empty($row['local_address']);

            PppUser::updateOrCreate(
                ['mixradius_id' => (string) $row['id']],
                [
                    'status_registrasi'   => 'aktif',
                    'tipe_pembayaran'     => strtolower($row['payment_type'] ?? 'prepaid'),
                    'status_bayar'        => $isExpired ? 'belum_bayar' : 'sudah_bayar',
                    'status_akun'         => $this->mapAuthStatus($row['auth_status'] ?? null),
                    'owner_id'            => $defaultOwner->id,
                    'ppp_profile_id'      => $profile?->id,
                    'tipe_service'        => 'pppoe',
                    'tagihkan_ppn'        => false,
                    'prorata_otomatis'    => false,
                    'promo_aktif'         => false,
                    'durasi_promo_bulan'  => 0,
                    'biaya_instalasi'     => 0,
                    'jatuh_tempo'         => $jatuhTempo?->toDateString(),
                    'aksi_jatuh_tempo'    => 'isolir',
                    'tipe_ip'             => $isStatic ? 'static' : 'dhcp',
                    'profile_group_id'    => null,
                    'ip_static'           => $isStatic ? $row['local_address'] : null,
                    'odp_pop'             => null,
                    'customer_id'         => 'MX-'.str_pad((string) $row['id'], 6, '0', STR_PAD_LEFT),
                    'customer_name'       => ! empty($row['fullname']) ? $row['fullname'] : $row['username'],
                    'nik'                 => $row['identity_number'] ?? null,
                    'nomor_hp'            => $this->normalizePhone($row['phonenumber'] ?? null),
                    'email'               => $row['email'] ?? null,
                    'alamat'              => $row['address'] ?? null,
                    'latitude'            => null,
                    'longitude'           => null,
                    'metode_login'        => 'username_password',
                    'username'            => $row['username'],
                    'ppp_password'        => $row['password'] ?? '',
                    'password_clientarea' => $row['password'] ?? '',
                    'catatan'             => $row['note'] ?? null,
                ]
            );

            $count++;
        }

        $this->command->info("PPP customers imported: {$count}");
    }

    private function parseValidDate(?string $dateStr): ?Carbon
    {
        if (empty($dateStr) || $dateStr === '0000-00-00' || $dateStr === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            $dt = Carbon::parse($dateStr);
            // Reject dates before year 2000 as invalid
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
