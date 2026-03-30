<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OltConnection>
 */
class OltConnectionFactory extends Factory
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
            'vendor' => 'hsgq',
            'name' => 'OLT '.$this->faker->unique()->bothify('HSGQ-##??'),
            'olt_model' => 'HSGQ GPON 8 PON',
            'host' => $this->faker->ipv4(),
            'snmp_port' => 161,
            'snmp_version' => '2c',
            'snmp_community' => 'public',
            'snmp_write_community' => 'private',
            'snmp_timeout' => 5,
            'snmp_retries' => 1,
            'is_active' => true,
            'oid_serial' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.2',
            'oid_onu_name' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.3',
            'oid_rx_onu' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.4',
            'oid_tx_onu' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.5',
            'oid_rx_olt' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.6',
            'oid_tx_olt' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.7',
            'oid_distance' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.9',
            'oid_status' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.8',
            'oid_reboot_onu' => '1.3.6.1.4.1.5875.800.3.1.1.1.1.19',
        ];
    }
}
