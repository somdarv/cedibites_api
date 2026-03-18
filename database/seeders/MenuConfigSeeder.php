<?php

namespace Database\Seeders;

use App\Models\MenuConfig;
use Illuminate\Database\Seeder;

class MenuConfigSeeder extends Seeder
{
    public function run(): void
    {
        $config = MenuConfig::first();

        $defaultConfig = [
            'categories' => ['Basic Meals', 'Budget Bowls', 'Combos', 'Top Ups', 'Drinks'],
            'optionTemplates' => [
                ['id' => 'tpl-sm-lg', 'name' => 'Small / Large', 'options' => [['label' => 'Small', 'price' => ''], ['label' => 'Large', 'price' => '']]],
                ['id' => 'tpl-plain-assorted', 'name' => 'Plain / Assorted', 'options' => [['label' => 'Plain', 'price' => ''], ['label' => 'Assorted', 'price' => '']]],
                ['id' => 'tpl-full-half-quarter', 'name' => 'Full / Half / Quarter', 'options' => [['label' => 'Full', 'price' => ''], ['label' => 'Half', 'price' => ''], ['label' => 'Quarter', 'price' => '']]],
                ['id' => 'tpl-350ml-500ml', 'name' => '350ml / 500ml', 'options' => [['label' => '350ml', 'price' => ''], ['label' => '500ml', 'price' => '']]],
            ],
            'addOns' => [
                ['id' => 'addon-drumsticks', 'name' => 'Drumsticks', 'price' => 12, 'perPiece' => true],
                ['id' => 'addon-tilapia', 'name' => 'Charcoal Grilled Tilapia', 'price' => 60, 'perPiece' => false],
            ],
            'branch' => [
                'isOpen' => true,
                'orderTypes' => ['delivery' => true, 'pickup' => true, 'dineIn' => false],
                'paymentMethods' => ['momo' => true, 'cashDelivery' => true, 'cashPickup' => true],
                'hours' => array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], ['open' => '09:00', 'close' => '22:00', 'closed' => false]),
            ],
        ];

        MenuConfig::updateOrCreate(
            ['id' => 1],
            ['config' => $defaultConfig]
        );
    }
}
