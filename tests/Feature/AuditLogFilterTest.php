<?php

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\CivicLedgerDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function auditEntry(User $actor, string $action, string $createdAt): AuditLog
{
    $log = new AuditLog([
        'actor_id' => $actor->id,
        'action' => $action,
        'entity_type' => 'users',
        'entity_id' => $actor->id,
        'before_json' => ['status' => 'active'],
        'after_json' => ['status' => 'suspended'],
    ]);
    $log->created_at = Carbon::parse($createdAt);
    $log->save();

    return $log;
}

beforeEach(function () {
    $this->seed(CivicLedgerDemoSeeder::class);
    $this->admin = User::where('email', 'admin@civicledger.test')->firstOrFail();
    $this->coordinator = User::where('email', 'coordinator@civicledger.test')->firstOrFail();
    AuditLog::query()->delete();
});

it('filters activity by actor and offers only people who have acted', function () {
    $coordinatorEntry = auditEntry($this->coordinator, 'event.updated', '2026-10-20 03:00:00');
    auditEntry($this->admin, 'event.updated', '2026-10-20 03:00:00');

    $this->actingAs($this->admin)
        ->get(route('admin.audit.index', ['actor' => $this->coordinator->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Audit')
            ->has('logs.data', 1)
            ->where('logs.data.0.id', $coordinatorEntry->id)
            ->where('logs.data.0.before_json.status', 'active')
            ->where('logs.data.0.after_json.status', 'suspended')
            ->has('actors', 2)
            ->where('filters.actor', (string) $this->coordinator->id));
});

it('filters activity by action and lists each action once', function () {
    auditEntry($this->admin, 'user.updated', '2026-10-20 03:00:00');
    auditEntry($this->admin, 'user.updated', '2026-10-20 04:00:00');
    $roleChange = auditEntry($this->admin, 'user.roles_changed', '2026-10-20 03:00:00');

    $this->actingAs($this->admin)
        ->get(route('admin.audit.index', ['action' => 'user.roles_changed']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.id', $roleChange->id)
            ->where('actions', ['user.roles_changed', 'user.updated']));
});

it('treats date filters as whole days in Asia/Manila', function () {
    auditEntry($this->admin, 'user.updated', '2026-10-19 15:59:59');
    $startOfDay = auditEntry($this->admin, 'user.updated', '2026-10-19 16:00:00');
    $endOfDay = auditEntry($this->admin, 'user.updated', '2026-10-20 15:59:59');
    auditEntry($this->admin, 'user.updated', '2026-10-20 16:00:00');

    $this->actingAs($this->admin)
        ->get(route('admin.audit.index', ['from' => '2026-10-20', 'to' => '2026-10-20']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('logs.data', 2)
            ->where('logs.data.0.id', $endOfDay->id)
            ->where('logs.data.1.id', $startOfDay->id));
});

it('accepts an end date without a start date', function () {
    $entry = auditEntry($this->admin, 'user.updated', '2026-10-20 03:00:00');
    auditEntry($this->admin, 'user.updated', '2026-10-25 03:00:00');

    $this->actingAs($this->admin)
        ->get(route('admin.audit.index', ['to' => '2026-10-21']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.id', $entry->id));
});

it('rejects an end date before the start date with a readable message', function () {
    $this->actingAs($this->admin)
        ->from(route('admin.audit.index'))
        ->get(route('admin.audit.index', ['from' => '2026-10-21', 'to' => '2026-10-20']))
        ->assertRedirect(route('admin.audit.index'))
        ->assertSessionHasErrors(['to' => 'The end date field must be a date after or equal to start date.']);
});

it('rejects a malformed date', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.audit.index', ['from' => 'yesterday']))
        ->assertSessionHasErrors('from');
});

it('forbids staff without the audit permission', function () {
    $this->actingAs($this->coordinator)
        ->get(route('admin.audit.index'))
        ->assertForbidden();
});
