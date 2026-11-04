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

/**
 * @return array{coordinator: User, section: Section}
 */
function createCoordinatorSection(): array
{
    $permission = Permission::create([
        'code' => 'events.manage',
        'label' => 'Create and manage events in assigned scope',
    ]);
    $role = Role::create(['code' => 'coordinator', 'label' => 'Coordinator']);
    $role->permissions()->attach($permission);
    $coordinator = User::factory()->create(['status' => 'active']);
    $coordinator->roles()->attach($role);

    $program = Program::create(['code' => 'NSTP', 'name' => 'NSTP']);
    $term = Term::create([
        'code' => '2026-T1',
        'starts_at' => '2026-08-01',
        'ends_at' => '2026-12-20',
    ]);
    $section = Section::create([
        'program_id' => $program->id,
        'term_id' => $term->id,
        'code' => 'CWTS-A',
    ]);
    UserSectionAssignment::create([
        'user_id' => $coordinator->id,
        'section_id' => $section->id,
        'scope_role' => UserSectionAssignment::COORDINATOR,
    ]);

    return compact('coordinator', 'section');
}

it('shows students who selected the coordinator section during registration', function () {
    ['coordinator' => $coordinator, 'section' => $section] = createCoordinatorSection();
    $otherSection = Section::create([
        'program_id' => $section->program_id,
        'term_id' => $section->term_id,
        'code' => 'CWTS-B',
    ]);

    $this->post('/register', [
        'first_name' => 'Jordan',
        'last_name' => 'Lee',
        'email' => 'jordan@example.test',
        'password' => 'strong-password-123',
        'password_confirmation' => 'strong-password-123',
        'section_id' => $section->id,
    ])->assertRedirect(route('dashboard'));

    $student = User::query()->where('email', 'jordan@example.test')->firstOrFail();
    $this->post('/logout')->assertRedirect(route('home'));

    $this->post('/register', [
        'first_name' => 'Taylor',
        'last_name' => 'Ng',
        'email' => 'taylor@example.test',
        'password' => 'strong-password-123',
        'password_confirmation' => 'strong-password-123',
        'section_id' => $otherSection->id,
    ])->assertRedirect(route('dashboard'));
    $this->post('/logout')->assertRedirect(route('home'));

    $this->actingAs($coordinator)
        ->get(route('coordinator.section-assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Coordinator/SectionAssignments')
            ->has('students.data', 1)
            ->where('students.data.0.user.email', 'jordan@example.test')
            ->where('students.data.0.section.id', $section->id));

    $this->assertDatabaseHas('user_section_assignments', [
        'user_id' => $student->id,
        'section_id' => $section->id,
        'scope_role' => UserSectionAssignment::STUDENT,
    ]);
});

it('does not expose coordinator endpoints for searching or assigning students', function () {
    ['coordinator' => $coordinator, 'section' => $section] = createCoordinatorSection();
    $student = User::factory()->create();

    $this->actingAs($coordinator)
        ->getJson('/coordinator/students/search?q=Jordan')
        ->assertNotFound();

    $this->post('/coordinator/section-assignments', [
        'student_id' => $student->id,
        'section_id' => $section->id,
    ])->assertMethodNotAllowed();

    $this->assertDatabaseCount('user_section_assignments', 1);
});

it('still rejects duplicate section codes within the same program and term', function () {
    ['coordinator' => $coordinator, 'section' => $section] = createCoordinatorSection();

    $this->actingAs($coordinator)
        ->post(route('coordinator.sections.store'), [
            'program_id' => $section->program_id,
            'term_id' => $section->term_id,
            'code' => $section->code,
        ])
        ->assertSessionHasErrors('code');

    $this->assertDatabaseCount('sections', 1);
});
