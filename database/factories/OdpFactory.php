<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Odp>
 */
class OdpFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'code' => strtoupper($this->faker->bothify('ODP-###??')),
            'name' => 'ODP '.$this->faker->city(),
            'area' => $this->faker->city(),
            'latitude' => $this->faker->latitude(-11, 6),
            'longitude' => $this->faker->longitude(95, 141),
            'capacity_ports' => $this->faker->numberBetween(8, 48),
            'status' => $this->faker->randomElement(['active', 'inactive', 'maintenance']),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
