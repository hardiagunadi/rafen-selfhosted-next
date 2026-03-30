<?php

namespace Database\Factories;

use App\Models\PppUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PppUserFactory extends Factory
{
    protected $model = PppUser::class;

    public function definition(): array
    {
        return [
            'owner_id'           => null,
            'customer_id'        => $this->faker->numerify('############'),
            'customer_name'      => $this->faker->name(),
            'nomor_hp'           => $this->faker->phoneNumber(),
            'email'              => $this->faker->safeEmail(),
            'alamat'             => $this->faker->address(),
            'username'           => $this->faker->userName().'@isp.id',
            'ppp_password'       => $this->faker->password(8, 16),
            'password_clientarea'=> $this->faker->password(8, 16),
            'status_akun'        => 'enable',
            'status_registrasi'  => 'aktif',
            'status_bayar'       => 'belum_bayar',
            'tipe_pembayaran'    => 'bulanan',
            'jatuh_tempo'        => now()->addDays(30)->toDateString(),
            'aksi_jatuh_tempo'   => 'isolir',
            'tipe_service'       => 'pppoe',
            'tipe_ip'            => 'dynamic',
        ];
    }

    public function forOwner(User $owner): static
    {
        return $this->state(['owner_id' => $owner->id]);
    }
}
