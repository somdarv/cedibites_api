<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;
use App\Notifications\StaffAccountCreatedNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->branch = Branch::factory()->create();
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::Admin->value);
});

test('new staff user receives StaffAccountCreatedNotification when employee is created', function () {
    Notification::fake();

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/admin/employees', [
            'name' => 'Jane Doe',
            'email' => 'jane@cedibites.com',
            'phone' => '0241234567',
            'branch_ids' => [$this->branch->id],
            'role' => Role::Employee->value,
        ]);

    $response->assertSuccessful();

    $newUser = User::where('email', 'jane@cedibites.com')->first();
    expect($newUser)->not->toBeNull();

    Notification::assertSentTo(
        $newUser,
        StaffAccountCreatedNotification::class,
        function (StaffAccountCreatedNotification $notification) {
            return strlen($notification->temporaryPassword) >= 12;
        }
    );
});
