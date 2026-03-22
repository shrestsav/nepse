<?php

use App\Enums\SyncMode;
use App\Enums\SyncStatus;
use App\Jobs\RunNepseSync;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('sync start validates the requested mode', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('dashboard.sync.store'), [
            'mode' => 'invalid',
        ])
        ->assertSessionHasErrors('mode');
});

test('full sync cannot be started from the dashboard', function () {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('dashboard.sync.store'), [
            'mode' => SyncMode::Full->value,
        ])
        ->assertSessionHasErrors('mode');

    Queue::assertNothingPushed();
    expect(SyncLog::query()->count())->toBe(0);
});

test('sync start queues a sync log and job', function () {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('dashboard.sync.store'), [
            'mode' => SyncMode::Smart->value,
        ])
        ->assertRedirect(route('dashboard.sync'));

    Queue::assertPushed(RunNepseSync::class, 1);

    $syncLog = SyncLog::query()->latest('id')->first();

    expect($syncLog)->not->toBeNull()
        ->and($syncLog->type)->toBe(SyncMode::Smart)
        ->and($syncLog->status)->toBe(SyncStatus::Queued)
        ->and($syncLog->start)->not->toBeNull();
});

test('sync start is blocked while another sync is running', function () {
    Queue::fake();

    SyncLog::factory()->running()->create([
        'type' => SyncMode::Full,
        'status' => SyncStatus::Running,
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('dashboard.sync.store'), [
            'mode' => SyncMode::Live->value,
        ])
        ->assertRedirect(route('dashboard.sync'));

    Queue::assertNothingPushed();
    expect(SyncLog::query()->count())->toBe(1);
});
