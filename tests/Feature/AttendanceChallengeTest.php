<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Program;
use App\Models\QrChallenge;
use App\Models\Registration;
use App\Models\User;
use App\Services\QrChallengeService;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

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

function registeredStudentForQrChallenge(EventSession $session): User
{
    $student = User::factory()->create();
    Registration::create([
        'event_id' => $session->event_id,
        'student_id' => $student->id,
        'status' => Registration::REGISTERED,
    ]);

    return $student;
}

function currentQrToken(EventSession $session): string
{
    return app(QrChallengeService::class)->issue($session)['token'];
}

function scanFromPage(EventSession $session, User $student): TestResponse
{
    return test()->actingAs($student)
        ->from(route('attendance.scan'))
        ->post(route('attendance.scan.store'), ['token' => currentQrToken($session)]);
}

function scanFromApi(EventSession $session, User $student): TestResponse
{
    return test()->actingAs($student)
        ->postJson(route('attendance.scan.store'), ['payload' => currentQrToken($session)]);
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
            'message' => 'This code has expired. Ask the coordinator to show the current code.',
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
        ->assertJsonPath('error.message', 'This isn\'t a CivicLedger attendance code. Scan the code shown by your coordinator.')
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
        ->assertJsonPath('error.code', 'not_registered')
        ->assertJsonPath('error.message', 'You need to register for this event before scanning.');

    $this->assertDatabaseCount('attendance', 0);
});

it('redirects the page back with the check-in time after a first scan', function () {
    $this->travelTo(Carbon::parse('2026-10-24 09:00:00'));
    $session = openSessionForQrChallenge();
    $student = registeredStudentForQrChallenge($session);

    $response = scanFromPage($session, $student);

    $response
        ->assertRedirect(route('attendance.scan'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('scan', ['action' => 'check_in', 'at' => '2026-10-24T09:00:00+00:00']);
    $this->assertDatabaseHas('attendance', [
        'session_id' => $session->id,
        'student_id' => $student->id,
        'status' => Attendance::STATUS_CHECKED_IN,
    ]);
});

it('redirects the page back with the check-out time after a second scan', function () {
    $this->travelTo(Carbon::parse('2026-10-24 09:00:00'));
    $session = openSessionForQrChallenge();
    $student = registeredStudentForQrChallenge($session);
    scanFromPage($session, $student);
    $this->travelTo(Carbon::parse('2026-10-24 11:00:00'));

    $response = scanFromPage($session, $student);

    $response
        ->assertRedirect(route('attendance.scan'))
        ->assertSessionHas('scan', ['action' => 'check_out', 'at' => '2026-10-24T11:00:00+00:00']);
    $this->assertDatabaseHas('attendance', [
        'student_id' => $student->id,
        'status' => Attendance::STATUS_COMPLETED,
    ]);
});

it('shows the page the original check-out time and changes nothing on a scan after check-out', function () {
    $this->travelTo(Carbon::parse('2026-10-24 09:00:00'));
    $session = openSessionForQrChallenge();
    $student = registeredStudentForQrChallenge($session);
    scanFromPage($session, $student);
    $this->travelTo(Carbon::parse('2026-10-24 11:00:00'));
    scanFromPage($session, $student);
    $this->travelTo(Carbon::parse('2026-10-24 11:30:00'));

    $response = scanFromPage($session, $student);

    $response->assertSessionHas('scan', ['action' => 'already_checked_out', 'at' => '2026-10-24T11:00:00+00:00']);
    expect(Attendance::sole()->check_out_at->toIso8601String())->toBe('2026-10-24T11:00:00+00:00');
});

it('shows the page the original check-in time and changes nothing on a second scan too soon', function () {
    config(['civicledger.qr.min_checkout_seconds' => 30]);
    $this->travelTo(Carbon::parse('2026-10-24 09:00:00'));
    $session = openSessionForQrChallenge();
    $student = registeredStudentForQrChallenge($session);
    scanFromPage($session, $student);
    $this->travelTo(Carbon::parse('2026-10-24 09:00:29'));

    $response = scanFromPage($session, $student);

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('scan', ['action' => 'already_checked_in', 'at' => '2026-10-24T09:00:00+00:00']);
    $attendance = Attendance::sole();
    expect($attendance->status)->toBe(Attendance::STATUS_CHECKED_IN);
    expect($attendance->check_out_at)->toBeNull();
});

it('returns the page to the form with the reason when a scan is rejected', function () {
    $session = openSessionForQrChallenge();
    $student = registeredStudentForQrChallenge($session);
    $expiredToken = currentQrToken($session);
    $this->travel(31)->seconds();

    $response = $this->actingAs($student)
        ->from(route('attendance.scan'))
        ->post(route('attendance.scan.store'), ['token' => $expiredToken]);

    $response
        ->assertRedirect(route('attendance.scan'))
        ->assertSessionHasErrors(['token' => 'This code has expired. Ask the coordinator to show the current code.'])
        ->assertSessionMissing('scan');
    $this->assertDatabaseCount('attendance', 0);
});

it('returns the check-out time, not the check-in time, when an API scan checks out', function () {
    $this->travelTo(Carbon::parse('2026-10-24 09:00:00'));
    $session = openSessionForQrChallenge();
    $student = registeredStudentForQrChallenge($session);
    scanFromApi($session, $student);
    $this->travelTo(Carbon::parse('2026-10-24 11:00:00'));

    $response = scanFromApi($session, $student);

    $response
        ->assertOk()
        ->assertJsonPath('result', 'checked_out')
        ->assertJsonPath('at', '2026-10-24T11:00:00.000000Z');
});

it('returns the original check-out time and changes nothing on an API scan after check-out', function () {
    $this->travelTo(Carbon::parse('2026-10-24 09:00:00'));
    $session = openSessionForQrChallenge();
    $student = registeredStudentForQrChallenge($session);
    scanFromApi($session, $student);
    $this->travelTo(Carbon::parse('2026-10-24 11:00:00'));
    scanFromApi($session, $student);
    $this->travelTo(Carbon::parse('2026-10-24 11:30:00'));

    $response = scanFromApi($session, $student);

    $response
        ->assertOk()
        ->assertJsonPath('result', 'already_checked_out')
        ->assertJsonPath('at', '2026-10-24T11:00:00.000000Z');
    expect(Attendance::sole()->check_out_at->toIso8601String())->toBe('2026-10-24T11:00:00+00:00');
});

it('returns already checked in with the original time and no second row for attendance that was rejected', function () {
    $this->travelTo(Carbon::parse('2026-10-24 09:00:00'));
    $session = openSessionForQrChallenge();
    $student = registeredStudentForQrChallenge($session);
    Attendance::create([
        'session_id' => $session->id,
        'student_id' => $student->id,
        'check_in_at' => '2026-10-24 08:00:00',
        'source' => Attendance::SOURCE_QR,
        'status' => Attendance::STATUS_REJECTED,
    ]);

    $response = scanFromApi($session, $student);

    $response
        ->assertOk()
        ->assertJsonPath('result', 'already_checked_in')
        ->assertJsonPath('at', '2026-10-24T08:00:00.000000Z');
    $this->assertDatabaseCount('attendance', 1);
    expect(Attendance::sole()->status)->toBe(Attendance::STATUS_REJECTED);
});

it('returns the invalid state error and changes nothing on a second API scan too soon', function () {
    config(['civicledger.qr.min_checkout_seconds' => 30]);
    $this->travelTo(Carbon::parse('2026-10-24 09:00:00'));
    $session = openSessionForQrChallenge();
    $student = registeredStudentForQrChallenge($session);
    scanFromApi($session, $student);
    $this->travelTo(Carbon::parse('2026-10-24 09:00:29'));

    $response = scanFromApi($session, $student);

    $response
        ->assertConflict()
        ->assertJsonPath('error.code', 'invalid_state')
        ->assertJsonPath('error.message', 'You just checked in. Wait a moment before scanning to check out.')
        ->assertJsonPath('error.retryable', true);
    $attendance = Attendance::sole();
    expect($attendance->status)->toBe(Attendance::STATUS_CHECKED_IN);
    expect($attendance->check_out_at)->toBeNull();
});

it('rejects an attendance QR whose text was edited, without recording attendance', function () {
    $session = openSessionForQrChallenge();
    $student = registeredStudentForQrChallenge($session);
    $token = json_decode(base64_decode(currentQrToken($session)), true);
    $token['value'] = base64_encode('{"s":1,"n":"edited","e":4102444800}');
    $editedToken = base64_encode(json_encode($token));

    $response = $this->actingAs($student)
        ->postJson(route('attendance.scan.store'), ['payload' => $editedToken]);

    $response->assertBadRequest()->assertJsonPath('error.code', 'qr_invalid');
    $this->assertDatabaseCount('attendance', 0);
});

it('rejects an attendance QR encrypted with another key, without recording attendance', function () {
    $session = openSessionForQrChallenge();
    $student = registeredStudentForQrChallenge($session);
    $cipher = config('app.cipher');
    $forgedToken = (new Encrypter(Encrypter::generateKey($cipher), $cipher))->encryptString(json_encode([
        's' => $session->id,
        'n' => Str::random(40),
        'e' => now()->addMinute()->getTimestamp(),
    ]));

    $response = $this->actingAs($student)
        ->postJson(route('attendance.scan.store'), ['payload' => $forgedToken]);

    $response->assertBadRequest()->assertJsonPath('error.code', 'qr_invalid');
    $this->assertDatabaseCount('attendance', 0);
});

it('rejects a correctly encrypted attendance QR whose nonce was never issued', function () {
    $session = openSessionForQrChallenge();
    $student = registeredStudentForQrChallenge($session);
    currentQrToken($session);
    $unknownToken = Crypt::encryptString(json_encode([
        's' => $session->id,
        'n' => Str::random(40),
        'e' => now()->addMinute()->getTimestamp(),
    ]));

    $response = $this->actingAs($student)
        ->postJson(route('attendance.scan.store'), ['payload' => $unknownToken]);

    $response->assertBadRequest()->assertJsonPath('error.code', 'qr_invalid');
    $this->assertDatabaseCount('attendance', 0);
});

it('rejects a scan after the session closes, without recording attendance', function () {
    $session = openSessionForQrChallenge();
    $student = registeredStudentForQrChallenge($session);
    $token = currentQrToken($session);
    $session->update(['status' => EventSession::STATUS_CLOSED]);

    $response = $this->actingAs($student)
        ->postJson(route('attendance.scan.store'), ['payload' => $token]);

    $response
        ->assertConflict()
        ->assertJsonPath('error.code', 'session_closed')
        ->assertJsonPath('error.message', 'Attendance for this session is not open.');
    $this->assertDatabaseCount('attendance', 0);
});

it('rejects a scan after the event is cancelled, without recording attendance', function () {
    $session = openSessionForQrChallenge();
    $student = registeredStudentForQrChallenge($session);
    $token = currentQrToken($session);
    $session->event->update(['status' => Event::STATUS_CANCELLED]);

    $response = $this->actingAs($student)
        ->postJson(route('attendance.scan.store'), ['payload' => $token]);

    $response->assertConflict()->assertJsonPath('error.code', 'session_closed');
    $this->assertDatabaseCount('attendance', 0);
});

it('deletes only challenges more than a day past their expiry when the daily schedule runs', function () {
    config(['civicledger.qr.ttl_seconds' => 30]);
    $session = openSessionForQrChallenge();
    $this->travelTo(Carbon::parse('2026-10-22 09:00:00'));
    currentQrToken($session);
    $this->travelTo(Carbon::parse('2026-10-23 09:00:00'));
    currentQrToken($session);
    $this->travelTo(Carbon::parse('2026-10-24 00:00:00'));

    $this->artisan('schedule:run')->assertSuccessful();

    expect(QrChallenge::sole()->expires_at->toIso8601String())->toBe('2026-10-23T09:00:30+00:00');
});
