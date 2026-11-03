<?php

use App\Models\User;
use Database\Seeders\CivicLedgerDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('allows administrators to create coordinator and signer accounts that must change the initial password', function (string $role) {
    $this->seed(CivicLedgerDemoSeeder::class);
    $administrator = User::where('email', 'admin@civicledger.test')->firstOrFail();
    $email = "{$role}@new-staff.example.test";

    $this->actingAs($administrator)
        ->post(route('admin.users.store'), [
            'first_name' => 'New',
            'last_name' => 'Staff',
            'email' => $email,
            'role' => $role,
            'password' => 'temporary-password-123',
            'password_confirmation' => 'temporary-password-123',
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success', 'Staff account created.');

    $staff = User::where('email', $email)->firstOrFail();
    $this->assertDatabaseHas('users', [
        'id' => $staff->id,
        'status' => 'active',
        'must_change_password' => true,
    ]);
    $this->assertDatabaseHas('user_roles', [
        'user_id' => $staff->id,
        'role_id' => $staff->roles()->where('code', $role)->value('roles.id'),
    ]);
    $this->assertAuthenticatedAs($administrator);

    $this->post(route('logout'))->assertRedirect(route('home'));
    $this->post(route('login.store'), [
        'email' => $email,
        'password' => 'temporary-password-123',
    ])->assertRedirect(route('password.change'));

    $this->get(route('dashboard'))->assertRedirect(route('password.change'));
    $this->put(route('password.update'), [
        'password' => 'permanent-password-456',
        'password_confirmation' => 'permanent-password-456',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($staff);
    $this->assertDatabaseHas('users', [
        'id' => $staff->id,
        'must_change_password' => false,
    ]);
    expect(Hash::check('permanent-password-456', $staff->refresh()->password))->toBeTrue();
})->with([
    'coordinator' => 'coordinator',
    'signer' => 'signer',
]);

it('rejects staff account creation for non-administrators', function () {
    $this->seed(CivicLedgerDemoSeeder::class);
    $student = User::where('email', 'student1@civicledger.test')->firstOrFail();

    $this->actingAs($student)
        ->post(route('admin.users.store'), [
            'first_name' => 'Unauthorized',
            'last_name' => 'Staff',
            'email' => 'unauthorized-staff@example.test',
            'role' => 'coordinator',
            'password' => 'temporary-password-123',
            'password_confirmation' => 'temporary-password-123',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'unauthorized-staff@example.test']);
});

it('does not allow administrators to provision any role other than coordinator or signer', function () {
    $this->seed(CivicLedgerDemoSeeder::class);
    $administrator = User::where('email', 'admin@civicledger.test')->firstOrFail();

    $this->actingAs($administrator)
        ->post(route('admin.users.store'), [
            'first_name' => 'Invalid',
            'last_name' => 'Role',
            'email' => 'invalid-role@example.test',
            'role' => 'admin',
            'password' => 'temporary-password-123',
            'password_confirmation' => 'temporary-password-123',
        ])
        ->assertSessionHasErrors('role');

    $this->assertDatabaseMissing('users', ['email' => 'invalid-role@example.test']);
});
