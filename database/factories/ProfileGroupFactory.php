<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProfileGroup>
 */
class ProfileGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ip = $this->faker->ipv4();

        return [
            'name' => $this->faker->unique()->word().' group',
            'owner' => $this->faker->userName(),
            'mikrotik_connection_id' => null,
            'type' => $this->faker->randomElement(['hotspot', 'pppoe']),
            'ip_pool_mode' => $this->faker->randomElement(['group_only', 'sql']),
            'ip_pool_name' => 'pool-'.$this->faker->word(),
            'ip_address' => $ip,
            'netmask' => '255.255.255.0',
            'range_start' => null,
            'range_end' => null,
            'dns_servers' => '8.8.8.8,8.8.4.4',
            'parent_queue' => 'parent',
            'host_min' => $ip,
            'host_max' => $this->faker->ipv4(),
        ];
    }
}
