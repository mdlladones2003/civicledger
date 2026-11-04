<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Program;
use App\Models\Registration;
use App\Models\User;
use App\Services\QrChallengeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function openSessionForQrChallenge(): EventSession
{
    $coordinator = User::factory()->create();
    $program = Program::create([
        'code' => 'TEST',
        'name' => 'Test Program',
    ]);
    $event = Event::create([
        'coordinator_id' => $coordinator->id,
        'program_id' => $program->id,
        'title' => 'Community Service',
        'starts_at' => now(),
        'ends_at' => now()->addHour(),
        'status' => Event::STATUS_PUBLISHED,
    ]);

    return EventSession::create([
        'event_id' => $event->id,
        'starts_at' => $event->starts_at,
        'ends_at' => $event->ends_at,
        'status' => EventSession::STATUS_OPEN,
    ]);
}

it('returns the remaining lifetime of a valid QR challenge without recording attendance', function () {
    $this->travelTo(Carbon::parse('2026-10-24 09:00:00'));
    config(['civicledger.qr.ttl_seconds' => 30]);
    $session = openSessionForQrChallenge();
    $challenge = app(QrChallengeService::class)->issue($session);
    $student = User::factory()->create();

    $this->actingAs($student)
        ->postJson(route('attendance.scan.challenge'), ['token' => $challenge['token']])
        ->assertOk()
        ->assertExactJson(['remaining_seconds' => 30]);

    $this->assertDatabaseCount('attendance', 0);
});

it('rejects an expired QR challenge when checking its remaining lifetime', function () {
    config(['civicledger.qr.ttl_seconds' => 30]);
    $session = openSessionForQrChallenge();
    $challenge = app(QrChallengeService::class)->issue($session);
    $this->travel(31)->seconds();

    $this->actingAs(User::factory()->create())
        ->postJson(route('attendance.scan.challenge'), ['token' => $challenge['token']])
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'This QR code has expired. Scan the code currently shown on the coordinator\'s screen.',
        ]);
});

it('requires authentication to check a QR challenge lifetime', function () {
    $this->postJson(route('attendance.scan.challenge'), ['token' => 'unused'])
        ->assertUnauthorized();
});

it('accepts the TRD payload field and records check-in and check-out with server timestamps', function () {
    $session = openSessionForQrChallenge();
    $student = User::factory()->create();
    Registration::create([
        'event_id' => $session->event_id,
        'student_id' => $student->id,
        'status' => Registration::REGISTERED,
    ]);
    $qr = app(QrChallengeService::class);
    $checkInChallenge = $qr->issue($session);

    $checkIn = $this->actingAs($student)
        ->postJson(route('attendance.scan.store'), ['payload' => $checkInChallenge['token']]);

    $checkIn
        ->assertOk()
        ->assertJsonPath('result', 'checked_in')
        ->assertJsonPath('event.title', 'Community Service');
    $this->assertDatabaseHas('attendance', [
        'session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'checked_in',
    ]);

    $this->travel(31)->seconds();
    $session->update(['qr_challenge_mode' => EventSession::QR_MODE_CHECK_OUT]);
    $checkOutChallenge = $qr->issue($session);
    $checkOut = $this->postJson(route('attendance.scan.store'), ['payload' => $checkOutChallenge['token']]);

    $checkOut
        ->assertOk()
        ->assertJsonPath('result', 'checked_out')
        ->assertJsonPath('event.title', 'Community Service');
    $this->assertDatabaseHas('attendance', [
        'session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'completed',
    ]);
});

it('returns a standard JSON error envelope for an invalid attendance QR', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('attendance.scan.store'), ['payload' => 'invalid-payload'])
        ->assertBadRequest()
        ->assertJsonPath('error.code', 'qr_invalid')
        ->assertJsonPath('error.retryable', false)
        ->assertJsonStructure(['error' => ['code', 'message', 'retryable', 'request_id']]);
});

it('returns the TRD error envelope when the scan payload is missing', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('attendance.scan.store'))
        ->assertBadRequest()
        ->assertJsonPath('error.code', 'qr_invalid')
        ->assertJsonStructure(['error' => ['code', 'message', 'retryable', 'request_id']]);
});

it('returns an expired QR response with the TRD status and error code', function () {
    $session = openSessionForQrChallenge();
    $challenge = app(QrChallengeService::class)->issue($session);
    $this->travel(31)->seconds();

    $this->actingAs(User::factory()->create())
        ->postJson(route('attendance.scan.store'), ['payload' => $challenge['token']])
        ->assertStatus(410)
        ->assertJsonPath('error.code', 'qr_expired')
        ->assertJsonPath('error.retryable', true);
});

it('returns a not-registered JSON error without creating attendance', function () {
    $session = openSessionForQrChallenge();
    $challenge = app(QrChallengeService::class)->issue($session);

    $this->actingAs(User::factory()->create())
        ->postJson(route('attendance.scan.store'), ['payload' => $challenge['token']])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'not_registered');

    $this->assertDatabaseCount('attendance', 0);
});

it('does not allow a check-in QR challenge to check a student out', function () {
    $session = openSessionForQrChallenge();
    $student = User::factory()->create();
    Registration::create([
        'event_id' => $session->event_id,
        'student_id' => $student->id,
        'status' => Registration::REGISTERED,
    ]);

    $checkInChallenge = app(QrChallengeService::class)->issue($session);

    $this->actingAs($student)
        ->postJson(route('attendance.scan.store'), ['payload' => $checkInChallenge['token']])
        ->assertOk()
        ->assertJsonPath('result', 'checked_in');

    $secondCheckInChallenge = app(QrChallengeService::class)->issue($session);

    $this->actingAs($student)
        ->postJson(route('attendance.scan.store'), ['payload' => $secondCheckInChallenge['token']])
        ->assertConflict()
        ->assertJsonPath('error.code', 'invalid_state');
});

it('rejects QR checkout when a manually recorded check-in is in the future', function () {
    $session = openSessionForQrChallenge();
    $student = User::factory()->create();
    Registration::create([
        'event_id' => $session->event_id,
        'student_id' => $student->id,
        'status' => Registration::REGISTERED,
    ]);
    Attendance::create([
        'session_id' => $session->id,
        'student_id' => $student->id,
        'check_in_at' => now()->addHour(),
        'source' => Attendance::SOURCE_MANUAL,
        'status' => Attendance::STATUS_CHECKED_IN,
    ]);
    $session->update(['qr_challenge_mode' => EventSession::QR_MODE_CHECK_OUT]);
    $challenge = app(QrChallengeService::class)->issue($session);

    $this->actingAs($student)
        ->postJson(route('attendance.scan.store'), ['payload' => $challenge['token']])
        ->assertConflict()
        ->assertJsonPath('error.code', 'invalid_state');
});
