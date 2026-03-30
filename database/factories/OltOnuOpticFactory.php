<?php

namespace Database\Factories;

use App\Models\OltConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OltOnuOptic>
 */
class OltOnuOpticFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'olt_connection_id' => OltConnection::factory(),
            'owner_id' => User::factory(),
            'onu_index' => (string) $this->faker->unique()->numberBetween(10001, 99999),
            'pon_interface' => (string) $this->faker->numberBetween(1, 16),
            'onu_number' => (string) $this->faker->numberBetween(1, 128),
            'serial_number' => strtoupper($this->faker->bothify('HSGQ########')),
            'onu_name' => 'ONU '.$this->faker->word(),
            'distance_m' => $this->faker->numberBetween(50, 8000),
            'rx_onu_dbm' => $this->faker->randomFloat(2, -35, -12),
            'tx_onu_dbm' => $this->faker->randomFloat(2, 1, 6),
            'rx_olt_dbm' => $this->faker->randomFloat(2, -35, -12),
            'tx_olt_dbm' => $this->faker->randomFloat(2, 1, 6),
            'status' => $this->faker->randomElement(['online', 'offline']),
            'raw_payload' => [
                'rx_onu' => '-24.6',
                'tx_onu' => '2.1',
                'rx_olt' => '-22.4',
                'tx_olt' => '3.0',
                'distance' => '3850',
                'status' => 'online',
            ],
            'last_seen_at' => now(),
        ];
    }
}
