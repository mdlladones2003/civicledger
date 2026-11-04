<?php

use App\Models\Permission;
use App\Models\Program;
use App\Models\Role;
use App\Models\Section;
use App\Models\Term;
use App\Models\User;
use App\Models\UserSectionAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('lets coordinators create programs, terms, and sections', function () {
    $permission = Permission::create([
        'code' => 'events.manage',
        'label' => 'Create and manage events in assigned scope',
    ]);
    $role = Role::create(['code' => 'coordinator', 'label' => 'Coordinator']);
    $role->permissions()->attach($permission);
    $coordinator = User::factory()->create(['status' => 'active']);
    $coordinator->roles()->attach($role);

    $this->actingAs($coordinator)
        ->post(route('coordinator.programs.store'), [
            'code' => 'NSTP-CWTS',
            'name' => 'Civic Welfare Training Service',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Program NSTP-CWTS created.');

    $program = Program::where('code', 'NSTP-CWTS')->firstOrFail();
    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $coordinator->id,
        'action' => 'program.created',
        'entity_id' => $program->id,
    ]);

    $this->post(route('coordinator.terms.store'), [
        'code' => '2027-T1',
        'starts_at' => '2027-01-01',
        'ends_at' => '2027-05-31',
    ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Term 2027-T1 created.');

    $term = Term::where('code', '2027-T1')->firstOrFail();
    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $coordinator->id,
        'action' => 'term.created',
        'entity_id' => $term->id,
    ]);

    $this->post(route('coordinator.sections.store'), [
        'program_id' => $program->id,
        'term_id' => $term->id,
        'code' => 'CWTS-A',
    ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Section CWTS-A created and added to your section selection.');

    $this->assertDatabaseHas('user_section_assignments', [
        'user_id' => $coordinator->id,
        'section_id' => Section::where('code', 'CWTS-A')->value('id'),
        'scope_role' => UserSectionAssignment::COORDINATOR,
    ]);

    $this->get(route('coordinator.section-assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Coordinator/SectionAssignments')
            ->where('programs.0.code', 'NSTP-CWTS')
            ->where('terms.0.code', '2027-T1')
            ->where('sections.0.code', 'CWTS-A'));
});

it('forbids users without coordinator permission from creating programs, terms, or sections', function () {
    $user = User::factory()->create(['status' => 'active']);
    $program = Program::create(['code' => 'NSTP-CWTS', 'name' => 'Civic Welfare Training Service']);
    $term = Term::create([
        'code' => '2027-T1',
        'starts_at' => '2027-01-01',
        'ends_at' => '2027-05-31',
    ]);

    $this->actingAs($user)
        ->post(route('coordinator.programs.store'), [
            'code' => 'OTHER',
            'name' => 'Other Program',
        ])
        ->assertForbidden();

    $this->post(route('coordinator.terms.store'), [
        'code' => '2027-T2',
        'starts_at' => '2027-06-01',
        'ends_at' => '2027-10-31',
    ])
        ->assertForbidden();

    $this->post(route('coordinator.sections.store'), [
        'program_id' => $program->id,
        'term_id' => $term->id,
        'code' => 'CWTS-A',
    ])
        ->assertForbidden();

    $this->assertDatabaseCount('programs', 1);
    $this->assertDatabaseCount('terms', 1);
    $this->assertDatabaseCount('sections', 0);
});
