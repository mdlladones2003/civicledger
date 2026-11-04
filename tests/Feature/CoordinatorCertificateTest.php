<?php

use App\Models\Certificate;
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

it('shows issued certificates for students in the coordinator scope', function () {
    $permission = Permission::create(['code' => 'events.manage', 'label' => 'Manage events']);
    $coordinatorRole = Role::create(['code' => 'coordinator', 'label' => 'Coordinator']);
    $coordinatorRole->permissions()->attach($permission);
    $coordinator = User::factory()->create();
    $coordinator->roles()->attach($coordinatorRole);
    $signer = User::factory()->create();
    $studentInScope = User::factory()->create();
    $studentOutsideScope = User::factory()->create();
    $program = Program::create(['code' => 'NSTP-CWTS', 'name' => 'Civic Welfare Training Service']);
    $term = Term::create(['code' => '2026-T1', 'starts_at' => '2026-08-01', 'ends_at' => '2026-12-20']);
    $sectionInScope = Section::create(['program_id' => $program->id, 'term_id' => $term->id, 'code' => 'CWTS-A']);
    $sectionOutsideScope = Section::create(['program_id' => $program->id, 'term_id' => $term->id, 'code' => 'CWTS-B']);
    UserSectionAssignment::create([
        'user_id' => $coordinator->id,
        'section_id' => $sectionInScope->id,
        'scope_role' => UserSectionAssignment::COORDINATOR,
    ]);
    UserSectionAssignment::create([
        'user_id' => $studentInScope->id,
        'section_id' => $sectionInScope->id,
        'scope_role' => UserSectionAssignment::STUDENT,
    ]);
    UserSectionAssignment::create([
        'user_id' => $studentOutsideScope->id,
        'section_id' => $sectionOutsideScope->id,
        'scope_role' => UserSectionAssignment::STUDENT,
    ]);
    Certificate::create([
        'student_id' => $studentInScope->id,
        'term_id' => $term->id,
        'issued_by' => $signer->id,
        'public_token' => 'in-scope-certificate-token',
        'certificate_number' => 'CERT-IN-SCOPE',
        'approved_minutes' => 120,
        'status' => Certificate::ISSUED,
        'issued_at' => now(),
    ]);
    Certificate::create([
        'student_id' => $studentOutsideScope->id,
        'term_id' => $term->id,
        'issued_by' => $signer->id,
        'public_token' => 'out-of-scope-certificate-token',
        'certificate_number' => 'CERT-OUT-SCOPE',
        'approved_minutes' => 120,
        'status' => Certificate::ISSUED,
        'issued_at' => now(),
    ]);

    $this->actingAs($coordinator)
        ->get(route('certificates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Certificates/Index')
            ->has('certificates.data', 1)
            ->where('certificates.data.0.certificate_number', 'CERT-IN-SCOPE'));
});
