<?php

namespace Database\Factories;

use App\Models\ProfileGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HotspotProfile>
 */
class HotspotProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Hotspot '.$this->faker->unique()->word(),
            'owner_id' => User::factory(),
            'harga_jual' => $this->faker->randomFloat(2, 10000, 150000),
            'harga_promo' => $this->faker->randomFloat(2, 0, 50000),
            'ppn' => $this->faker->randomFloat(2, 0, 10),
            'bandwidth_profile_id' => null,
            'profile_type' => 'unlimited',
            'limit_type' => null,
            'time_limit_value' => null,
            'time_limit_unit' => null,
            'quota_limit_value' => null,
            'quota_limit_unit' => null,
            'masa_aktif_value' => 30,
            'masa_aktif_unit' => 'hari',
            'profile_group_id' => ProfileGroup::factory(),
            'shared_users' => 1,
            'prioritas' => 'default',
        ];
    }

    public function limitedTime(): static
    {
        return $this->state(function () {
            return [
                'profile_type' => 'limited',
                'limit_type' => 'time',
                'time_limit_value' => 60,
                'time_limit_unit' => 'menit',
                'quota_limit_value' => null,
                'quota_limit_unit' => null,
                'masa_aktif_value' => null,
                'masa_aktif_unit' => null,
            ];
        });
    }

    public function limitedQuota(): static
    {
        return $this->state(function () {
            return [
                'profile_type' => 'limited',
                'limit_type' => 'quota',
                'quota_limit_value' => 5000,
                'quota_limit_unit' => 'mb',
                'time_limit_value' => null,
                'time_limit_unit' => null,
                'masa_aktif_value' => null,
                'masa_aktif_unit' => null,
            ];
        });
    }
}
