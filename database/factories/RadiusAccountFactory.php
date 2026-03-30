<?php

namespace Database\Factories;

use App\Models\MikrotikConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RadiusAccount>
 */
class RadiusAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $service = $this->faker->randomElement(['pppoe', 'hotspot']);

        return [
            'mikrotik_connection_id' => MikrotikConnection::factory(),
            'username' => $this->faker->userName(),
            'password' => 'password',
            'service' => $service,
            'ipv4_address' => $service === 'pppoe' ? $this->faker->ipv4() : null,
            'rate_limit' => '10M/10M',
            'profile' => $this->faker->randomElement(['silver', 'gold', 'platinum']),
            'is_active' => true,
            'notes' => $this->faker->sentence(),
        ];
    }
}
