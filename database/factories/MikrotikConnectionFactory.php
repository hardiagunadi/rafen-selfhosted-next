<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MikrotikConnection>
 */
class MikrotikConnectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->city(),
            'host' => $this->faker->ipv4(),
            'api_port' => 8728,
            'api_ssl_port' => 8729,
            'use_ssl' => false,
            'username' => 'admin',
            'password' => 'secret',
            'radius_secret' => $this->faker->password(),
            'ros_version' => $this->faker->randomElement(['6', '7']),
            'api_timeout' => 10,
            'notes' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }
}
