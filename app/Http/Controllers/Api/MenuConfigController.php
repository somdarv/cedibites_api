<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuConfigController extends Controller
{
    /**
     * Get the menu config.
     */
    public function show(): JsonResponse
    {
        $config = MenuConfig::query()->first();

        if (! $config) {
            return response()->success($this->defaultConfig());
        }

        return response()->success($config->config);
    }

    /**
     * Update the menu config.
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'config' => ['required', 'array'],
            'config.categories' => ['nullable', 'array'],
            'config.categories.*' => ['string'],
            'config.optionTemplates' => ['nullable', 'array'],
            'config.addOns' => ['nullable', 'array'],
            'config.branch' => ['nullable', 'array'],
        ]);

        $config = MenuConfig::query()->first();

        if (! $config) {
            $config = MenuConfig::create(['config' => $request->config]);
        } else {
            $config->update(['config' => $request->config]);
            activity('admin')
                ->causedBy($request->user())
                ->performedOn($config)
                ->event('menu_config_updated')
                ->log('Menu config updated');
        }

        return response()->success($config->config);
    }

    /**
     * Default config matching frontend DEFAULT_CONFIG.
     */
    protected function defaultConfig(): array
    {
        return [
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
    }
}
