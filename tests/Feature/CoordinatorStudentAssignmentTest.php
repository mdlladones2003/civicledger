<?php

use App\Models\Program;
use App\Models\Role;
use App\Models\Section;
use App\Models\Term;
use App\Models\User;
use App\Models\UserSectionAssignment;
use Database\Seeders\CivicLedgerDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('lets a coordinator assign a student to a section in their scope', function () {
    $this->seed(CivicLedgerDemoSeeder::class);
    $coordinator = User::where('email', 'coordinator@civicledger.test')->firstOrFail();
    $section = Section::firstOrFail();
    $student = User::factory()->create(['email' => 'new-student@example.test']);
    $student->roles()->attach(Role::where('code', 'student')->firstOrFail());

    $this->actingAs($coordinator)
        ->get(route('coordinator.section-assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Coordinator/SectionAssignments')
            ->has('sections', 1)
            ->where('sections.0.id', $section->id)
            ->has('assignments.data', 3));

    $this->post(route('coordinator.section-assignments.store'), [
        'student_id' => $student->id,
        'section_id' => $section->id,
    ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Student assigned to section.');

    $this->assertDatabaseHas('user_section_assignments', [
        'user_id' => $student->id,
        'section_id' => $section->id,
        'scope_role' => UserSectionAssignment::STUDENT,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $coordinator->id,
        'action' => 'assignment.created',
        'entity_type' => 'user_section_assignments',
    ]);
});

it('prevents a coordinator from assigning a student outside their section scope', function () {
    $this->seed(CivicLedgerDemoSeeder::class);
    $coordinator = User::where('email', 'coordinator@civicledger.test')->firstOrFail();
    $section = Section::firstOrFail();
    $otherSection = Section::create([
        'program_id' => $section->program_id,
        'term_id' => $section->term_id,
        'code' => 'CWTS-B',
    ]);
    $student = User::where('email', 'student3@civicledger.test')->firstOrFail();

    $this->actingAs($coordinator)
        ->post(route('coordinator.section-assignments.store'), [
            'student_id' => $student->id,
            'section_id' => $otherSection->id,
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('user_section_assignments', [
        'user_id' => $student->id,
        'section_id' => $otherSection->id,
        'scope_role' => UserSectionAssignment::STUDENT,
    ]);
});

it('only assigns accounts with the student role', function () {
    $this->seed(CivicLedgerDemoSeeder::class);
    $coordinator = User::where('email', 'coordinator@civicledger.test')->firstOrFail();
    $section = Section::firstOrFail();
    $signer = User::where('email', 'signer@civicledger.test')->firstOrFail();

    $this->actingAs($coordinator)
        ->post(route('coordinator.section-assignments.store'), [
            'student_id' => $signer->id,
            'section_id' => $section->id,
        ])
        ->assertSessionHasErrors([
            'student_id' => 'Select an active student account.',
        ]);

    $this->assertDatabaseMissing('user_section_assignments', [
        'user_id' => $signer->id,
        'section_id' => $section->id,
        'scope_role' => UserSectionAssignment::STUDENT,
    ]);
});

it('does not expose section assignment management to students or signers', function (string $email) {
    $this->seed(CivicLedgerDemoSeeder::class);
    $user = User::where('email', $email)->firstOrFail();

    $this->actingAs($user)
        ->get(route('coordinator.section-assignments.index'))
        ->assertForbidden();

    $this->getJson(route('coordinator.students.search', ['q' => 'Sofia']))
        ->assertForbidden();

    $section = Section::firstOrFail();
    $this->post(route('coordinator.sections.store'), [
        'program_id' => $section->program_id,
        'term_id' => $section->term_id,
        'code' => 'CWTS-C',
    ])->assertForbidden();

    $this->post(route('coordinator.section-assignments.store'), [
        'student_id' => User::where('email', 'student3@civicledger.test')->value('id'),
        'section_id' => $section->id,
    ])->assertForbidden();
})->with([
    'student' => 'student1@civicledger.test',
    'signer' => 'signer@civicledger.test',
]);

it('keeps duplicate student section assignments idempotent', function () {
    $this->seed(CivicLedgerDemoSeeder::class);
    $coordinator = User::where('email', 'coordinator@civicledger.test')->firstOrFail();
    $student = User::where('email', 'student3@civicledger.test')->firstOrFail();
    $section = Section::firstOrFail();

    $this->actingAs($coordinator)
        ->post(route('coordinator.section-assignments.store'), [
            'student_id' => $student->id,
            'section_id' => $section->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Student is already assigned to this section.');

    $this->assertDatabaseCount('user_section_assignments', 5);
    $this->assertDatabaseMissing('audit_logs', [
        'actor_id' => $coordinator->id,
        'action' => 'assignment.created',
    ]);
});

it('lets coordinators create a section and assign a student to the new section', function () {
    $this->seed(CivicLedgerDemoSeeder::class);
    $coordinator = User::where('email', 'coordinator@civicledger.test')->firstOrFail();
    $student = User::where('email', 'student3@civicledger.test')->firstOrFail();
    $program = Program::firstOrFail();
    $term = Term::firstOrFail();

    $this->actingAs($coordinator)
        ->post(route('coordinator.sections.store'), [
            'program_id' => $program->id,
            'term_id' => $term->id,
            'code' => 'CWTS-B',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Section CWTS-B created and added to your section selection.');

    $section = Section::where('code', 'CWTS-B')->firstOrFail();
    $this->assertDatabaseHas('user_section_assignments', [
        'user_id' => $coordinator->id,
        'section_id' => $section->id,
        'scope_role' => UserSectionAssignment::COORDINATOR,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $coordinator->id,
        'action' => 'section.created',
        'entity_type' => 'sections',
        'entity_id' => $section->id,
    ]);

    $this->get(route('coordinator.section-assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sections.1.code', 'CWTS-B')
            ->where('sections.1.id', $section->id));

    $this->post(route('coordinator.section-assignments.store'), [
        'student_id' => $student->id,
        'section_id' => $section->id,
    ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Student assigned to section.');

    $this->assertDatabaseHas('user_section_assignments', [
        'user_id' => $student->id,
        'section_id' => $section->id,
        'scope_role' => UserSectionAssignment::STUDENT,
    ]);
});

it('rejects duplicate section codes within the same program and term', function () {
    $this->seed(CivicLedgerDemoSeeder::class);
    $coordinator = User::where('email', 'coordinator@civicledger.test')->firstOrFail();
    $section = Section::firstOrFail();

    $this->actingAs($coordinator)
        ->post(route('coordinator.sections.store'), [
            'program_id' => $section->program_id,
            'term_id' => $section->term_id,
            'code' => $section->code,
        ])
        ->assertSessionHasErrors('code');

    $this->assertDatabaseCount('sections', 1);
});

it('searches student accounts by name and returns matching student choices', function () {
    $this->seed(CivicLedgerDemoSeeder::class);
    $coordinator = User::where('email', 'coordinator@civicledger.test')->firstOrFail();

    $this->actingAs($coordinator)
        ->getJson(route('coordinator.students.search', ['q' => 'Sofia Reyes']))
        ->assertOk()
        ->assertJsonCount(1, 'students')
        ->assertJsonPath('students.0.name', 'Sofia Reyes')
        ->assertJsonPath('students.0.email', 'student1@civicledger.test');
});

it('does not return suspended students in name search', function () {
    $this->seed(CivicLedgerDemoSeeder::class);
    $coordinator = User::where('email', 'coordinator@civicledger.test')->firstOrFail();
    $student = User::where('email', 'student1@civicledger.test')->firstOrFail();
    $student->forceFill(['status' => 'suspended'])->save();

    $this->actingAs($coordinator)
        ->getJson(route('coordinator.students.search', ['q' => 'Sofia']))
        ->assertOk()
        ->assertJsonCount(0, 'students');
});
