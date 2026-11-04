<?php

use App\Models\BlockchainProof;
use App\Models\Certificate;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\CivicLedgerDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('returns the same successful page for an unknown public certificate token', function () {
    $response = $this->get(route('verify.show', 'unknown-public-token'));

    $response
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Verify')
            ->where('state', 'not_found')
            ->where('certificate', null)
            ->where('evidence', null));
});

it('returns a non-enumerable JSON result for an unknown public certificate token', function () {
    $response = $this->getJson(route('api.verify', 'unknown-public-token'));

    $response
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson([
            'result' => 'not_found',
            'validity' => null,
            'certificate' => null,
            'proof' => null,
            'disclaimer' => 'Integrity checks show a record has not been altered since approval. They do not prove actual participation. This is a supplementary record, not an official completion record unless adopted by the school.',
        ]);
});

it('returns only minimized certificate details from the public JSON verifier', function () {
    $student = User::factory()->create([
        'first_name' => 'Sofia',
        'middle_name' => null,
        'last_name' => 'Reyes',
    ]);
    $issuer = User::factory()->create();
    $term = Term::create([
        'code' => '2026-1',
        'starts_at' => '2026-08-01',
        'ends_at' => '2026-12-20',
    ]);
    $certificate = Certificate::create([
        'student_id' => $student->id,
        'term_id' => $term->id,
        'issued_by' => $issuer->id,
        'public_token' => 'known-public-token',
        'certificate_number' => 'CL-2026-000001',
        'approved_minutes' => 183,
        'status' => Certificate::ISSUED,
        'issued_at' => '2026-10-25 12:00:00',
    ]);
    BlockchainProof::create([
        'certificate_id' => $certificate->id,
        'opaque_record_key' => '0x'.str_repeat('1', 64),
        'commitment' => '0x'.str_repeat('2', 64),
        'chain_id' => 11155111,
        'contract_address' => '0x'.str_repeat('3', 40),
        'proof_status' => BlockchainProof::QUEUED,
        'validity_status' => BlockchainProof::CURRENT,
    ]);

    $response = $this->getJson(route('api.verify', $certificate->public_token));

    $response
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPaths([
            'result' => 'pending',
            'validity' => 'current',
            'certificate.number' => 'CL-2026-000001',
            'certificate.term' => '2026-1',
            'certificate.approved_hours' => 3.05,
            'certificate.holder' => 'S*** R***',
            'proof.status' => BlockchainProof::QUEUED,
        ])
        ->assertJsonMissingPaths([
            'certificate.student_id',
            'certificate.email',
            'attendance',
        ]);
});

it('reports a mismatch when a confirmed proof is missing from the chain', function () {
    $student = User::factory()->create();
    $term = Term::create([
        'code' => '2026-1',
        'starts_at' => '2026-08-01',
        'ends_at' => '2026-12-20',
    ]);
    $certificate = Certificate::create([
        'student_id' => $student->id,
        'term_id' => $term->id,
        'issued_by' => User::factory()->create()->id,
        'public_token' => 'known-public-token',
        'certificate_number' => 'CL-2026-000001',
        'approved_minutes' => 183,
        'status' => Certificate::ISSUED,
        'issued_at' => '2026-10-25 12:00:00',
    ]);
    BlockchainProof::create([
        'certificate_id' => $certificate->id,
        'opaque_record_key' => '0x'.str_repeat('1', 64),
        'commitment' => '0x'.str_repeat('2', 64),
        'chain_id' => 11155111,
        'contract_address' => '0x'.str_repeat('3', 40),
        'tx_hash' => '0x'.str_repeat('4', 64),
        'proof_status' => BlockchainProof::CONFIRMED,
        'validity_status' => BlockchainProof::CURRENT,
    ]);

    $response = $this->getJson(route('api.verify', $certificate->public_token));

    $response->assertOk()->assertJsonPath('result', 'mismatch');
});

it('verifies an untouched certificate whose proof matches the chain', function () {
    $this->seed(CivicLedgerDemoSeeder::class);
    $certificate = Certificate::firstOrFail();

    $response = $this->getJson(route('api.verify', $certificate->public_token));

    $response->assertOk()->assertJsonPath('result', 'verified');
});

it('reports a mismatch after a certificate value is edited directly in the database', function () {
    $this->seed(CivicLedgerDemoSeeder::class);
    $certificate = Certificate::firstOrFail();
    DB::table('certificates')->where('id', $certificate->id)->update(['approved_minutes' => 999]);

    $response = $this->getJson(route('api.verify', $certificate->public_token));

    $response->assertOk()->assertJsonPath('result', 'mismatch');
});

it('shows staff a mismatch after approved minutes are edited directly in the database', function () {
    $this->seed(CivicLedgerDemoSeeder::class);
    $proof = BlockchainProof::whereNotNull('service_record_version_id')->firstOrFail();
    DB::table('service_records')->where('id', $proof->serviceRecordVersion->service_record_id)->update(['approved_minutes' => 999]);

    $response = $this->actingAs(User::where('email', 'coordinator@civicledger.test')->firstOrFail())
        ->get(route('proofs.show', $proof));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Proofs/Show', false)
            ->where('verification', 'mismatch'));
});
