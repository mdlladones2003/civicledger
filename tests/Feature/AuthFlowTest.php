<?php

use App\Models\Program;
use App\Models\Section;
use App\Models\Term;
use App\Models\User;
use App\Models\UserSectionAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the login and registration pages', function () {
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

    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login', false));

    $this->get('/register')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Register')
            ->has('sections', 1)
            ->where('sections.0.id', $section->id)
            ->where('sections.0.code', 'CWTS-A')
            ->where('sections.0.program.code', 'NSTP')
            ->where('sections.0.term.code', '2026-T1'));
});

it('authenticates an active user and logs them out', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);

    $this->post('/logout')
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

it('rejects invalid credentials with a generic error', function () {
    $this->post('/login', [
        'email' => 'unknown@example.test',
        'password' => 'incorrect-password',
    ])->assertSessionHasErrors([
        'email' => 'These credentials do not match our records.',
    ]);

    $this->assertGuest();
});

it('does not authenticate suspended users', function () {
    $user = User::factory()->create();
    $user->forceFill(['status' => 'suspended'])->save();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors([
        'email' => 'These credentials do not match our records.',
    ]);

    $this->assertGuest();
});

it('registers a student account and ignores submitted role and status fields', function () {
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

    $this->post('/register', [
        'first_name' => 'Jordan',
        'middle_name' => '',
        'last_name' => 'Lee',
        'email' => 'jordan@example.test',
        'password' => 'strong-password-123',
        'password_confirmation' => 'strong-password-123',
        'section_id' => $section->id,
        'status' => 'suspended',
        'roles' => ['admin'],
        'role' => 'coordinator',
    ])->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'jordan@example.test')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'status' => 'active',
        'middle_name' => null,
    ]);
    expect($user->roles()->pluck('code')->all())->toBe(['student']);
    $this->assertDatabaseHas('roles', ['code' => 'student', 'label' => 'Student']);
    $this->assertDatabaseHas('user_section_assignments', [
        'user_id' => $user->id,
        'section_id' => $section->id,
        'scope_role' => UserSectionAssignment::STUDENT,
    ]);
});

it('requires a valid section when a student registers', function () {
    $this->post('/register', [
        'first_name' => 'Jordan',
        'last_name' => 'Lee',
        'email' => 'jordan@example.test',
        'password' => 'strong-password-123',
        'password_confirmation' => 'strong-password-123',
    ])->assertSessionHasErrors('section_id');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'jordan@example.test']);
});

it('rejects a section id that does not exist during student registration', function () {
    $this->post('/register', [
        'first_name' => 'Jordan',
        'last_name' => 'Lee',
        'email' => 'jordan@example.test',
        'password' => 'strong-password-123',
        'password_confirmation' => 'strong-password-123',
        'section_id' => 9999,
    ])->assertSessionHasErrors('section_id');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'jordan@example.test']);
});

it('rejects registration when the email is already used', function () {
    $user = User::factory()->create(['email' => 'taken@example.test']);

    $this->post('/register', [
        'first_name' => 'Jordan',
        'last_name' => 'Lee',
        'email' => $user->email,
        'password' => 'strong-password-123',
        'password_confirmation' => 'strong-password-123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('requires a confirmed password of at least twelve characters during registration', function () {
    $this->post('/register', [
        'first_name' => 'Jordan',
        'last_name' => 'Lee',
        'email' => 'jordan@example.test',
        'password' => 'short',
        'password_confirmation' => 'different',
    ])->assertSessionHasErrors('password');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'jordan@example.test']);
});

it('returns only the current account details from me', function () {
    $user = User::factory()->create([
        'first_name' => 'Jordan',
        'middle_name' => null,
        'last_name' => 'Lee',
        'email' => 'jordan@example.test',
    ]);

    $this->actingAs($user)
        ->getJson('/me')
        ->assertOk()
        ->assertExactJson([
            'user' => [
                'id' => $user->id,
                'first_name' => 'Jordan',
                'middle_name' => null,
                'last_name' => 'Lee',
                'full_name' => 'Jordan Lee',
                'email' => 'jordan@example.test',
                'status' => 'active',
            ],
        ]);
});

it('requires authentication to access me', function () {
    $this->getJson('/me')->assertUnauthorized();
});

it('anonymizes and revokes an account while retaining its audit history', function () {
    $user = User::factory()->create();
    $roleId = DB::table('roles')->insertGetId([
        'code' => 'student',
        'label' => 'Student',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('user_roles')->insert([
        'user_id' => $user->id,
        'role_id' => $roleId,
    ]);
    $historyId = DB::table('audit_logs')->insertGetId([
        'actor_id' => $user->id,
        'action' => 'history.recorded',
        'entity_type' => 'service_records',
        'entity_id' => 1,
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->delete('/account', ['current_password' => 'password'])
        ->assertRedirect(route('home'));

    $user->refresh();
    $this->assertGuest();
    $this->assertModelExists($user);
    $this->assertDatabaseHas('audit_logs', ['id' => $historyId, 'actor_id' => $user->id]);
    $this->assertDatabaseHas('audit_logs', ['actor_id' => $user->id, 'action' => 'account.deleted']);
    $this->assertDatabaseMissing('user_roles', ['user_id' => $user->id]);
    expect($user->full_name)->toBe('Deleted Account')
        ->and($user->email)->toBe("deleted+{$user->id}@civicledger.invalid")
        ->and($user->status)->toBe('suspended');
});

it('keeps the account active when account deletion password confirmation fails', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete('/account', ['current_password' => 'wrong-password'])
        ->assertSessionHasErrors('current_password');

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseMissing('audit_logs', [
        'actor_id' => $user->id,
        'action' => 'account.deleted',
    ]);
});
