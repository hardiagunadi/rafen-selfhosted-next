<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SubscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default subscription plans
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Cocok untuk ISP kecil yang baru memulai',
                'price' => 100000,
                'duration_days' => 30,
                'max_mikrotik' => 3,
                'max_ppp_users' => 100,
                'features' => [
                    'Manajemen hingga 3 Mikrotik',
                    'Hingga 100 PPP Users',
                    'FreeRADIUS Integration',
                    'Invoice Otomatis',
                    'Support via Email',
                ],
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'Untuk ISP yang sedang berkembang',
                'price' => 250000,
                'duration_days' => 30,
                'max_mikrotik' => 10,
                'max_ppp_users' => 500,
                'features' => [
                    'Manajemen hingga 10 Mikrotik',
                    'Hingga 500 PPP Users',
                    'FreeRADIUS Integration',
                    'Invoice Otomatis',
                    'Pembayaran QRIS & VA',
                    'VPN Access',
                    'Support Prioritas',
                ],
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Solusi lengkap untuk ISP besar',
                'price' => 500000,
                'duration_days' => 30,
                'max_mikrotik' => -1,
                'max_ppp_users' => -1,
                'features' => [
                    'Mikrotik Unlimited',
                    'PPP Users Unlimited',
                    'FreeRADIUS Integration',
                    'Invoice Otomatis',
                    'Pembayaran QRIS & VA',
                    'VPN Dedicated',
                    'Support 24/7',
                    'Custom Branding',
                    'API Access',
                ],
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }

        // Create super admin if not exists
        $superAdmin = User::where('email', 'admin@rafen.id')->first();

        if (! $superAdmin) {
            User::create([
                'name' => 'Super Admin',
                'email' => 'admin@rafen.id',
                'password' => Hash::make('password'),
                'role' => 'administrator',
                'is_super_admin' => true,
                'subscription_status' => 'active',
                'subscription_method' => User::SUBSCRIPTION_METHOD_MONTHLY,
                'registered_at' => now(),
            ]);
        } else {
            $superAdmin->update(['is_super_admin' => true]);
        }
    }
}
