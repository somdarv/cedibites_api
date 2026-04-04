<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemSettingController extends Controller
{
    public function __construct(protected SystemSettingService $settings) {}

    /**
     * GET /admin/settings — list all settings.
     */
    public function index(): JsonResponse
    {
        $settings = DB::table('system_settings')->get()->map(fn ($row) => [
            'key' => $row->key,
            'value' => $row->value,
            'type' => $row->type,
            'description' => $row->description,
        ]);

        return response()->json(['data' => $settings]);
    }

    /**
     * GET /admin/settings/{key} — get one setting.
     */
    public function show(string $key): JsonResponse
    {
        $row = DB::table('system_settings')->where('key', $key)->first();

        if (! $row) {
            return response()->json(['message' => 'Setting not found.'], 404);
        }

        return response()->json([
            'data' => [
                'key' => $row->key,
                'value' => $this->settings->get($row->key),
                'type' => $row->type,
                'description' => $row->description,
            ],
        ]);
    }

    /**
     * PUT /admin/settings/{key} — update a setting.
     */
    public function update(Request $request, string $key): JsonResponse
    {
        $row = DB::table('system_settings')->where('key', $key)->first();

        if (! $row) {
            return response()->json(['message' => 'Setting not found.'], 404);
        }

        $validated = $request->validate([
            'value' => ['required'],
        ]);

        $this->settings->set($key, $validated['value'], $row->type);

        activity('settings')
            ->causedBy($request->user())
            ->withProperties(['key' => $key, 'value' => $validated['value']])
            ->event('updated')
            ->log("System setting '{$key}' updated");

        return response()->json([
            'message' => 'Setting updated.',
            'data' => [
                'key' => $key,
                'value' => $this->settings->get($key),
                'type' => $row->type,
            ],
        ]);
    }

    /**
     * POST /admin/settings — create a new setting.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:255', 'unique:system_settings,key'],
            'value' => ['required'],
            'type' => ['required', 'string', 'in:boolean,string,integer,json'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        DB::table('system_settings')->insert([
            'key' => $validated['key'],
            'value' => is_bool($validated['value']) ? ($validated['value'] ? 'true' : 'false') : (string) $validated['value'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Setting created.',
            'data' => [
                'key' => $validated['key'],
                'value' => $this->settings->get($validated['key']),
                'type' => $validated['type'],
                'description' => $validated['description'] ?? null,
            ],
        ], 201);
    }
}
