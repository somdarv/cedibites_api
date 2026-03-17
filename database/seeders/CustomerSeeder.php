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
                    'name' => fake()->name(),
                    'username' => 'customer'.$i,
                    'phone' => '+233'.fake()->numerify('#########'),
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
                    'full_address' => fake()->streetAddress().', Accra',
                    'note' => fake()->optional()->sentence(),
                    'latitude' => fake()->latitude(5.5, 5.7),
                    'longitude' => fake()->longitude(-0.3, -0.1),
                    'is_default' => true,
                ]
            );

            // Create office address for some customers
            if (fake()->boolean(40)) {
                Address::updateOrCreate(
                    ['customer_id' => $customer->id, 'label' => 'Office'],
                    [
                        'full_address' => fake()->streetAddress().', Accra',
                        'latitude' => fake()->latitude(5.5, 5.7),
                        'longitude' => fake()->longitude(-0.3, -0.1),
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
                'guest_session_id' => 'guest-'.time().'-'.fake()->unique()->regexify('[a-z0-9]{15}'),
            ]);

            // Create address for guest customer
            Address::create([
                'customer_id' => $customer->id,
                'label' => 'Delivery Address',
                'full_address' => fake()->streetAddress().', Accra',
                'note' => fake()->optional()->sentence(),
                'latitude' => fake()->latitude(5.5, 5.7),
                'longitude' => fake()->longitude(-0.3, -0.1),
                'is_default' => true,
            ]);
        }
    }
}
