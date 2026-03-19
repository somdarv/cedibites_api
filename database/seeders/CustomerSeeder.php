<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        // Create registered customers
        for ($i = 1; $i <= 10; $i++) {
            $user = User::updateOrCreate(
                ['email' => 'customer'.$i.'@example.com'],
                [
                    'name' => 'Customer '.$i,
                    'username' => 'customer'.$i,
                    'phone' => '+2335'.str_pad($i, 8, '0', STR_PAD_LEFT),
                    'password' => bcrypt('password'),
                ]
            );

            // No role assignment - customers don't need roles/permissions

            $customer = Customer::updateOrCreate(
                ['user_id' => $user->id],
                ['is_guest' => false]
            );

            // Create home address
            Address::updateOrCreate(
                ['customer_id' => $customer->id, 'label' => 'Home'],
                [
                    'full_address' => $i.' Main Street, Accra',
                    'note' => null,
                    'latitude' => 5.6 + ($i * 0.001),
                    'longitude' => -0.2 + ($i * 0.001),
                    'is_default' => true,
                ]
            );

            // Create office address for some customers
            if ($i % 3 === 0) {
                Address::updateOrCreate(
                    ['customer_id' => $customer->id, 'label' => 'Office'],
                    [
                        'full_address' => $i.' Business Ave, Accra',
                        'latitude' => 5.6 + ($i * 0.002),
                        'longitude' => -0.2 + ($i * 0.002),
                        'is_default' => false,
                    ]
                );
            }
        }

        // Create guest customers (customers without user accounts)
        for ($i = 1; $i <= 5; $i++) {
            $customer = Customer::create([
                'user_id' => null,
                'is_guest' => true,
                'guest_session_id' => 'guest-'.time().'-'.substr(md5((string) $i), 0, 15),
            ]);

            // Create address for guest customer
            Address::create([
                'customer_id' => $customer->id,
                'label' => 'Delivery Address',
                'full_address' => $i.' Guest Street, Accra',
                'note' => null,
                'latitude' => 5.6 + ($i * 0.001),
                'longitude' => -0.2 + ($i * 0.001),
                'is_default' => true,
            ]);
        }
    }
}
