<?php

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\User;

it('records an activity log entry when a user name changes', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create(['name' => 'Original Name']);

    $this->actingAs($actor);

    $target->update(['name' => 'Renamed Person']);

    $log = ActivityLog::query()
        ->where('subject_type', User::class)
        ->where('subject_id', $target->id)
        ->where('event', 'updated')
        ->latest()
        ->first();

    expect($log)->not->toBeNull();
    expect($log->causer_id)->toBe($actor->id);
    expect(data_get($log->properties->toArray(), 'old.name'))->toBe('Original Name');
    expect(data_get($log->properties->toArray(), 'attributes.name'))->toBe('Renamed Person');
});

it('records an activity log entry when an employee status changes', function () {
    $actor = User::factory()->create();
    $user = User::factory()->create();
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'status' => \App\Enums\EmployeeStatus::Active,
    ]);

    $this->actingAs($actor);

    $employee->update(['status' => \App\Enums\EmployeeStatus::Suspended]);

    $log = ActivityLog::query()
        ->where('subject_type', Employee::class)
        ->where('subject_id', $employee->id)
        ->where('event', 'updated')
        ->latest()
        ->first();

    expect($log)->not->toBeNull();
    expect($log->causer_id)->toBe($actor->id);
});

it('records an activity log entry when a customer status changes', function () {
    $actor = User::factory()->create();
    $user = User::factory()->create();
    $customer = Customer::factory()->create([
        'user_id' => $user->id,
        'status' => \App\Enums\CustomerStatus::Active,
    ]);

    $this->actingAs($actor);

    $customer->update(['status' => \App\Enums\CustomerStatus::Suspended]);

    $log = ActivityLog::query()
        ->where('subject_type', Customer::class)
        ->where('subject_id', $customer->id)
        ->where('event', 'updated')
        ->latest()
        ->first();

    expect($log)->not->toBeNull();
    expect($log->causer_id)->toBe($actor->id);
});

it('does not log an empty update when no tracked field changes', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create(['name' => 'Stable Name']);

    $this->actingAs($actor);

    $countBefore = ActivityLog::query()
        ->where('subject_type', User::class)
        ->where('subject_id', $target->id)
        ->where('event', 'updated')
        ->count();

    $target->update(['name' => 'Stable Name']);

    $countAfter = ActivityLog::query()
        ->where('subject_type', User::class)
        ->where('subject_id', $target->id)
        ->where('event', 'updated')
        ->count();

    expect($countAfter)->toBe($countBefore);
});
