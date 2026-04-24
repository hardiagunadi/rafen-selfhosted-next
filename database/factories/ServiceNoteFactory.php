<?php

namespace Database\Factories;

use App\Models\PppUser;
use App\Models\ServiceNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceNote>
 */
class ServiceNoteFactory extends Factory
{
    public function definition(): array
    {
        $noteDate = fake()->dateTimeBetween('-7 days', 'now');
        $amount = fake()->numberBetween(50000, 300000);

        return [
            'owner_id' => User::factory(),
            'ppp_user_id' => null,
            'created_by' => null,
            'paid_by' => null,
            'note_type' => 'perbaikan',
            'document_number' => 'NTA-'.fake()->unique()->numerify('########'),
            'document_title' => 'NOTA BIAYA PERBAIKAN',
            'summary_title' => 'RINCIAN PERBAIKAN',
            'service_type' => 'pppoe',
            'status' => 'paid',
            'note_date' => $noteDate,
            'customer_id' => 'CUST-'.fake()->numerify('#####'),
            'customer_name' => fake()->name(),
            'customer_phone' => '628'.fake()->numerify('##########'),
            'customer_address' => fake()->address(),
            'package_name' => 'Paket Home',
            'item_lines' => [
                ['label' => 'Biaya Layanan', 'amount' => $amount],
            ],
            'subtotal' => $amount,
            'total' => $amount,
            'payment_method' => 'cash',
            'transfer_accounts' => null,
            'show_service_section' => true,
            'cash_received' => $amount,
            'notes' => fake()->sentence(),
            'footer' => fake()->sentence(),
            'paid_at' => $noteDate,
            'printed_at' => $noteDate,
        ];
    }

    public function forOwner(User $owner): static
    {
        return $this->state(fn (): array => [
            'owner_id' => $owner->id,
        ]);
    }

    public function forPppUser(PppUser $pppUser): static
    {
        return $this->state(fn (): array => [
            'owner_id' => $pppUser->owner_id,
            'ppp_user_id' => $pppUser->id,
            'customer_id' => $pppUser->customer_id,
            'customer_name' => $pppUser->customer_name,
            'customer_phone' => $pppUser->nomor_hp,
            'customer_address' => $pppUser->alamat,
            'service_type' => $pppUser->tipe_service ?: 'pppoe',
            'package_name' => $pppUser->profile?->name,
        ]);
    }
}
