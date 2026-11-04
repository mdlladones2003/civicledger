<?php

use App\Jobs\AnchorProofJob;
use App\Models\Attendance;
use App\Models\BlockchainProof;
use App\Models\Certificate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Section;
use App\Models\ServiceRecord;
use App\Models\Term;
use App\Models\User;
use App\Models\UserSectionAssignment;
use App\Services\AttendanceService;
use App\Services\ServiceRecordService;
use Database\Seeders\CivicLedgerDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CivicLedgerDemoSeeder::class);
    $this->signer = User::where('email', 'signer@civicledger.test')->firstOrFail();
    $this->coordinator = User::where('email', 'coordinator@civicledger.test')->firstOrFail();
    $this->certified = User::where('email', 'student1@civicledger.test')->firstOrFail();
    $this->student = User::where('email', 'student2@civicledger.test')->firstOrFail();
    $this->term = Term::where('code', '2026-T1')->firstOrFail();
});

function approveSeededClaim(User $student, User $coordinator): ServiceRecord
{
    return app(ServiceRecordService::class)->approve(
        ServiceRecord::where('student_id', $student->id)->firstOrFail(),
        $coordinator,
    );
}

function userWithOnlyPermission(string $permissionCode): User
{
    $role = Role::create(['code' => 'limited', 'label' => 'Limited']);
    $role->permissions()->attach(Permission::where('code', $permissionCode)->firstOrFail());
    $user = User::factory()->create(['status' => 'active']);
    $user->roles()->attach($role);
    UserSectionAssignment::create([
        'user_id' => $user->id,
        'section_id' => Section::firstOrFail()->id,
        'scope_role' => UserSectionAssignment::SIGNER,
    ]);

    return $user;
}

it('issues a certificate once the approved records of the student are confirmed on chain', function () {
    approveSeededClaim($this->student, $this->coordinator);

    $response = $this->actingAs($this->signer)
        ->from(route('certificates.index'))
        ->post(route('certificates.store'), ['student_id' => $this->student->id, 'term_id' => $this->term->id]);

    $response
        ->assertRedirect(route('certificates.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Certificate issued. Proof queued.');
    $certificate = Certificate::where('student_id', $this->student->id)->firstOrFail();
    expect($certificate->status)->toBe(Certificate::ISSUED);
    expect($certificate->approved_minutes)->toBe(105);
    $this->assertDatabaseHas('blockchain_proofs', ['certificate_id' => $certificate->id]);
    $this->assertDatabaseHas('certificate_events', ['certificate_id' => $certificate->id, 'event_type' => 'issued']);
});

it('rejects issuance while an approved record has an unconfirmed proof', function (string $proofStatus) {
    approveSeededClaim($this->student, $this->coordinator);
    BlockchainProof::whereHas('serviceRecordVersion.serviceRecord', fn ($records) => $records->where('student_id', $this->student->id))
        ->update(['proof_status' => $proofStatus]);

    $response = $this->actingAs($this->signer)
        ->post(route('certificates.store'), ['student_id' => $this->student->id, 'term_id' => $this->term->id]);

    $response->assertSessionHasErrors(['student_id' => 'Not eligible yet: blockchain confirmation is still pending for 1 approved record.']);
    $this->assertDatabaseMissing('certificates', ['student_id' => $this->student->id]);
})->with([
    'queued' => [BlockchainProof::QUEUED],
    'submitted' => [BlockchainProof::SUBMITTED],
    'failed' => [BlockchainProof::FAILED],
]);

it('rejects issuance when only an earlier version of a corrected record is confirmed', function () {
    approveSeededClaim($this->student, $this->coordinator);
    $attendance = Attendance::where('student_id', $this->student->id)->firstOrFail();
    app(AttendanceService::class)->adjust(
        $this->coordinator,
        $attendance,
        $attendance->check_in_at,
        $attendance->check_out_at,
        'Corrected after reviewing the sign-out sheet.',
    );
    Queue::fake([AnchorProofJob::class]);
    approveSeededClaim($this->student, $this->coordinator);

    $response = $this->actingAs($this->signer)
        ->post(route('certificates.store'), ['student_id' => $this->student->id, 'term_id' => $this->term->id]);

    $response->assertSessionHasErrors(['student_id' => 'Not eligible yet: blockchain confirmation is still pending for 1 approved record.']);
    $this->assertDatabaseMissing('certificates', ['student_id' => $this->student->id]);
    Queue::assertPushed(AnchorProofJob::class, 1);
});

it('shows the minute shortfall when a student lacks enough approved minutes', function () {
    config(['civicledger.certificate.min_minutes' => 120]);
    approveSeededClaim($this->student, $this->coordinator);

    $response = $this->actingAs($this->signer)
        ->post(route('certificates.store'), ['student_id' => $this->student->id, 'term_id' => $this->term->id]);

    $response->assertSessionHasErrors(['student_id' => 'Not eligible: 15 more approved minutes needed this term.']);
    $this->assertDatabaseMissing('certificates', ['student_id' => $this->student->id]);
});

it('tells the signer to supersede when the term already has an active certificate', function () {
    $response = $this->actingAs($this->signer)
        ->post(route('certificates.store'), ['student_id' => $this->certified->id, 'term_id' => $this->term->id]);

    $response->assertSessionHasErrors(['student_id' => 'An active certificate already exists. Supersede it instead.']);
    $this->assertDatabaseCount('certificates', 1);
});

it('lists a student as a candidate only after their approved records are confirmed', function () {
    approveSeededClaim($this->student, $this->coordinator);

    $this->actingAs($this->signer)
        ->get(route('certificates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Certificates/Signer', false)
            ->has('candidates', 1)
            ->where('candidates.0.student_id', $this->student->id));

    BlockchainProof::whereHas('serviceRecordVersion.serviceRecord', fn ($records) => $records->where('student_id', $this->student->id))
        ->update(['proof_status' => BlockchainProof::SUBMITTED]);

    $this->actingAs($this->signer)
        ->get(route('certificates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('candidates', 0));
});

it('offers revoke and supersede actions only to signers holding the revoke permission', function () {
    $this->actingAs($this->signer)
        ->get(route('certificates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canRevoke', true));

    $this->actingAs(userWithOnlyPermission('certificates.issue'))
        ->get(route('certificates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canRevoke', false));
});

it('replaces the active certificate and records the reason when superseding', function () {
    $original = Certificate::where('student_id', $this->certified->id)->firstOrFail();

    $response = $this->actingAs($this->signer)
        ->from(route('certificates.index'))
        ->post(route('certificates.supersede', $original), ['reason' => 'Corrected hours after coordinator review.']);

    $response
        ->assertRedirect(route('certificates.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Certificate superseded. Proof queued for the replacement.');
    expect($original->refresh()->status)->toBe(Certificate::SUPERSEDED);
    $replacement = Certificate::where('supersedes_certificate_id', $original->id)->firstOrFail();
    expect($replacement->status)->toBe(Certificate::ISSUED);
    expect($replacement->student_id)->toBe($this->certified->id);
    $this->assertDatabaseHas('certificate_events', [
        'certificate_id' => $original->id,
        'event_type' => 'superseded',
        'reason' => 'Corrected hours after coordinator review.',
    ]);
    $this->assertDatabaseHas('blockchain_proofs', ['certificate_id' => $original->id, 'validity_status' => BlockchainProof::SUPERSEDED]);
    $this->assertDatabaseHas('blockchain_proofs', ['certificate_id' => $replacement->id, 'validity_status' => BlockchainProof::CURRENT]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'certificate.superseded']);
});

it('requires a reason of at least 10 characters to supersede', function () {
    $original = Certificate::where('student_id', $this->certified->id)->firstOrFail();

    $response = $this->actingAs($this->signer)
        ->post(route('certificates.supersede', $original), ['reason' => 'Too short']);

    $response->assertSessionHasErrors(['reason' => 'The reason field must be at least 10 characters.']);
    expect($original->refresh()->status)->toBe(Certificate::ISSUED);
    $this->assertDatabaseCount('certificates', 1);
});

it('rejects superseding a certificate that is no longer active', function () {
    $original = Certificate::where('student_id', $this->certified->id)->firstOrFail();
    $original->update(['status' => Certificate::REVOKED, 'revoked_at' => now()]);

    $response = $this->actingAs($this->signer)
        ->post(route('certificates.supersede', $original), ['reason' => 'Corrected hours after coordinator review.']);

    $response->assertSessionHasErrors(['certificate' => 'Only an active certificate can be superseded.']);
    $this->assertDatabaseCount('certificates', 1);
});

it('forbids superseding without certificate permissions', function () {
    $original = Certificate::where('student_id', $this->certified->id)->firstOrFail();

    $response = $this->actingAs($this->coordinator)
        ->post(route('certificates.supersede', $original), ['reason' => 'Corrected hours after coordinator review.']);

    $response->assertForbidden();
    expect($original->refresh()->status)->toBe(Certificate::ISSUED);
});

it('forbids superseding by a signer who cannot issue the replacement', function () {
    $original = Certificate::where('student_id', $this->certified->id)->firstOrFail();

    $response = $this->actingAs(userWithOnlyPermission('certificates.revoke'))
        ->post(route('certificates.supersede', $original), ['reason' => 'Corrected hours after coordinator review.']);

    $response->assertForbidden();
    expect($original->refresh()->status)->toBe(Certificate::ISSUED);
});

it('forbids superseding by a signer outside the section of the student', function () {
    $original = Certificate::where('student_id', $this->certified->id)->firstOrFail();
    $outsideSigner = User::factory()->create(['status' => 'active']);
    $outsideSigner->roles()->attach(Role::where('code', 'signer')->firstOrFail());

    $response = $this->actingAs($outsideSigner)
        ->post(route('certificates.supersede', $original), ['reason' => 'Corrected hours after coordinator review.']);

    $response->assertForbidden();
    expect($original->refresh()->status)->toBe(Certificate::ISSUED);
});
