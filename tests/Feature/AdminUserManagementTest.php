<?php

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use App\Models\UserSectionAssignment;
use Database\Seeders\CivicLedgerDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CivicLedgerDemoSeeder::class);
    $this->admin = User::where('email', 'admin@civicledger.test')->firstOrFail();
    $this->student = User::where('email', 'student1@civicledger.test')->firstOrFail();
});

it('shows the manage page with the account, its roles and assignments, and every role and section to choose from', function () {
    $coordinator = User::where('email', 'coordinator@civicledger.test')->firstOrFail();
    $section = Section::firstOrFail();

    $this->actingAs($this->admin)
        ->get(route('admin.users.show', $coordinator))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/UserDetail')
            ->where('user.email', 'coordinator@civicledger.test')
            ->where('user.roles.0.code', 'coordinator')
            ->where('user.section_assignments.0.section_id', $section->id)
            ->where('user.section_assignments.0.scope_role', UserSectionAssignment::COORDINATOR)
            ->has('roles', 4)
            ->where('sections.0.code', 'CWTS-A')
            ->where('sections.0.term.code', '2026-T1')
            ->where('scopeRoles', UserSectionAssignment::SCOPE_ROLES)
            ->where('isCurrentUser', false));
});

it('flags the signed-in administrator and keeps administrator emails off the manage page', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.users.show', $this->admin))
        ->assertOk()
        ->assertDontSee('admin@civicledger.test')
        ->assertInertia(fn (Assert $page) => $page
            ->where('isCurrentUser', true)
            ->missing('user.email'));
});

it('forbids non-administrators from opening the manage page', function () {
    $this->actingAs($this->student)
        ->get(route('admin.users.show', $this->admin))
        ->assertForbidden();
});

it('finds an account by its full name across the name columns', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['q' => '  Sofia   Reyes ']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.email', 'student1@civicledger.test')
            ->where('filters.q', 'Sofia Reyes'));
});

it('finds a non-administrator account by email', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['q' => 'student2@']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.first_name', 'Miguel'));
});

it('does not reveal an administrator account when searching for its hidden email', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['q' => 'admin@civicledger.test']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 0));
});

it('renames an account and records the before and after values', function () {
    $this->actingAs($this->admin)
        ->from(route('admin.users.show', $this->student))
        ->patch(route('admin.users.update', $this->student), [
            'first_name' => 'Sofia Marie',
            'middle_name' => '',
            'last_name' => 'Reyes',
        ])
        ->assertRedirect(route('admin.users.show', $this->student))
        ->assertSessionHas('success', 'User updated.');

    $this->assertDatabaseHas('users', ['id' => $this->student->id, 'first_name' => 'Sofia Marie', 'middle_name' => null]);
    $log = AuditLog::where('action', 'user.updated')->where('entity_id', $this->student->id)->firstOrFail();
    expect($log->actor_id)->toBe($this->admin->id)
        ->and($log->before_json['first_name'])->toBe('Sofia')
        ->and($log->after_json['first_name'])->toBe('Sofia Marie');
});

it('rejects a blank first name without changing the account', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.users.update', $this->student), ['first_name' => ''])
        ->assertSessionHasErrors(['first_name' => 'The first name field is required.']);

    $this->assertDatabaseHas('users', ['id' => $this->student->id, 'first_name' => 'Sofia']);
});

it('signs a suspended account out on its next request and lets it back in once reactivated', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.users.update', $this->student), ['status' => 'suspended'])
        ->assertSessionHas('success', 'User updated.');

    $this->assertDatabaseHas('users', ['id' => $this->student->id, 'status' => 'suspended']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.updated', 'entity_id' => $this->student->id, 'actor_id' => $this->admin->id]);

    $this->actingAs($this->student->refresh())
        ->get('/dashboard')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->actingAs($this->admin)
        ->patch(route('admin.users.update', $this->student), ['status' => 'active']);

    $this->actingAs($this->student->refresh())
        ->get('/dashboard')
        ->assertOk();
});

it('refuses to let administrators suspend their own account', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.users.update', $this->admin), ['status' => 'suspended'])
        ->assertStatus(422);

    $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'status' => 'active']);
});

it('replaces the roles of an account and records the before and after roles', function () {
    $coordinatorRoleId = Role::where('code', 'coordinator')->value('id');
    $signerRoleId = Role::where('code', 'signer')->value('id');

    $this->actingAs($this->admin)
        ->put(route('admin.users.roles', $this->student), ['role_ids' => [$coordinatorRoleId, $signerRoleId]])
        ->assertSessionHas('success', 'Roles updated.');

    expect($this->student->roles()->pluck('code')->sort()->values()->all())->toBe(['coordinator', 'signer']);
    $log = AuditLog::where('action', 'user.roles_changed')->where('entity_id', $this->student->id)->firstOrFail();
    expect($log->before_json['roles'])->toBe(['student'])
        ->and($log->after_json['roles'])->toBe(['coordinator', 'signer']);
});

it('refuses to let administrators remove their own administrator role, so one always remains', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.users.roles', $this->admin), ['role_ids' => [Role::where('code', 'student')->value('id')]])
        ->assertStatus(422);

    expect($this->admin->roles()->pluck('code')->all())->toBe(['admin']);
});

it('assigns a section and removes the assignment, recording both and changing access immediately', function () {
    $coordinator = User::where('email', 'coordinator@civicledger.test')->firstOrFail();
    $event = Event::where('coordinator_id', $coordinator->id)->firstOrFail();
    $assignment = UserSectionAssignment::where('user_id', $coordinator->id)->firstOrFail();

    $this->actingAs($this->admin)
        ->delete(route('admin.assignments.destroy', $assignment))
        ->assertSessionHas('success', 'Assignment removed.');

    $this->assertModelMissing($assignment);
    $this->assertDatabaseHas('audit_logs', ['action' => 'assignment.removed', 'entity_id' => $assignment->id]);
    $this->actingAs($coordinator)->get(route('events.edit', $event))->assertForbidden();

    $this->actingAs($this->admin)
        ->post(route('admin.users.assignments.store', $coordinator), [
            'section_id' => $assignment->section_id,
            'scope_role' => UserSectionAssignment::COORDINATOR,
        ])
        ->assertSessionHas('success', 'Assignment saved.');

    $this->assertDatabaseHas('audit_logs', ['action' => 'assignment.created', 'actor_id' => $this->admin->id]);
    $this->actingAs($coordinator)->get(route('events.edit', $event))->assertOk();
});

it('rejects an assignment with an unknown scope role', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.assignments.store', $this->student), [
            'section_id' => Section::firstOrFail()->id,
            'scope_role' => 'admin',
        ])
        ->assertSessionHasErrors('scope_role');

    expect($this->student->sectionAssignments()->where('scope_role', 'admin')->exists())->toBeFalse();
});

it('forbids non-administrators from changing accounts, roles and assignments', function () {
    $requests = [
        fn () => $this->patch(route('admin.users.update', $this->admin), ['status' => 'suspended']),
        fn () => $this->put(route('admin.users.roles', $this->student), ['role_ids' => [Role::where('code', 'admin')->value('id')]]),
        fn () => $this->post(route('admin.users.assignments.store', $this->student), ['section_id' => Section::firstOrFail()->id, 'scope_role' => 'coordinator']),
    ];

    foreach ($requests as $request) {
        $this->actingAs($this->student);
        $request()->assertForbidden();
    }

    expect($this->student->roles()->pluck('code')->all())->toBe(['student']);
    $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'status' => 'active']);
});
