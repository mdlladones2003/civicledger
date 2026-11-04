<?php

use App\Models\Attendance;
use App\Models\AttendanceAdjustment;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\ServiceRecord;
use App\Models\User;
use Database\Seeders\CivicLedgerDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CivicLedgerDemoSeeder::class);
    $this->coordinator = User::where('email', 'coordinator@civicledger.test')->firstOrFail();
    $this->event = Event::where('coordinator_id', $this->coordinator->id)->firstOrFail();
    $this->openSession = EventSession::where('event_id', $this->event->id)->firstOrFail();
    $this->unscannedStudent = User::where('email', 'student3@civicledger.test')->firstOrFail();
});

function eventWithStatus(Event $source, string $status): Event
{
    return Event::create([
        'coordinator_id' => $source->coordinator_id,
        'program_id' => $source->program_id,
        'section_id' => $source->section_id,
        'title' => "A {$status} event",
        'venue' => 'Community hall',
        'component' => 'CWTS',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
        'status' => $status,
    ]);
}

function scheduledSession(Event $event): EventSession
{
    return $event->sessions()->create([
        'starts_at' => $event->starts_at,
        'ends_at' => $event->ends_at,
        'status' => EventSession::STATUS_SCHEDULED,
    ]);
}

it('cancels a published event, closes its open sessions and records the change', function () {
    $this->actingAs($this->coordinator)
        ->from(route('events.show', $this->event))
        ->post(route('events.cancel', $this->event))
        ->assertRedirect(route('events.show', $this->event))
        ->assertSessionHas('success', 'Event cancelled.');

    $this->assertDatabaseHas('events', ['id' => $this->event->id, 'status' => Event::STATUS_CANCELLED]);
    $this->assertDatabaseHas('event_sessions', ['id' => $this->openSession->id, 'status' => EventSession::STATUS_CLOSED]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'event.cancelled', 'entity_id' => $this->event->id]);
});

it('completes a published event and then blocks further edits', function () {
    $this->actingAs($this->coordinator)
        ->post(route('events.complete', $this->event))
        ->assertSessionHas('success', 'Event completed.');

    $this->assertDatabaseHas('events', ['id' => $this->event->id, 'status' => Event::STATUS_COMPLETED]);
    $this->assertDatabaseHas('event_sessions', ['id' => $this->openSession->id, 'status' => EventSession::STATUS_CLOSED]);

    $this->actingAs($this->coordinator)
        ->put(route('events.update', $this->event), [
            'title' => 'Changed afterwards',
            'venue' => 'Elsewhere',
            'component' => 'CWTS',
            'program_id' => $this->event->program_id,
            'section_id' => $this->event->section_id,
            'starts_at' => now()->toIso8601String(),
            'ends_at' => now()->addHour()->toIso8601String(),
        ])
        ->assertStatus(422);
});

it('refuses to complete a draft or cancel a finished event', function (string $status, string $routeName) {
    $event = eventWithStatus($this->event, $status);

    $this->actingAs($this->coordinator)
        ->post(route($routeName, $event))
        ->assertStatus(422);

    $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => $status]);
})->with([
    'completing a draft' => [Event::STATUS_DRAFT, 'events.complete'],
    'cancelling a completed event' => [Event::STATUS_COMPLETED, 'events.cancel'],
    'cancelling a cancelled event' => [Event::STATUS_CANCELLED, 'events.cancel'],
]);

it('stores a timestamp with an offset as the same instant in UTC when adding a session', function () {
    $this->actingAs($this->coordinator)
        ->post(route('events.sessions.store', $this->event), [
            'starts_at' => '2026-12-01T09:00:00+08:00',
            'ends_at' => '2026-12-01T11:00:00+08:00',
        ])
        ->assertSessionHas('success', 'Session added.');

    $session = EventSession::where('event_id', $this->event->id)->latest('id')->firstOrFail();
    expect($session->status)->toBe(EventSession::STATUS_SCHEDULED)
        ->and($session->starts_at->utc()->toDateTimeString())->toBe('2026-12-01 01:00:00')
        ->and($session->ends_at->utc()->toDateTimeString())->toBe('2026-12-01 03:00:00');
});

it('rejects a session that ends before it starts', function () {
    $sessionCount = EventSession::count();

    $this->actingAs($this->coordinator)
        ->post(route('events.sessions.store', $this->event), [
            'starts_at' => '2026-12-01T11:00:00+08:00',
            'ends_at' => '2026-12-01T09:00:00+08:00',
        ])
        ->assertSessionHasErrors('ends_at');

    expect(EventSession::count())->toBe($sessionCount);
});

it('opens a scheduled session on a published event and shows its live screen', function () {
    $session = scheduledSession($this->event);

    $this->actingAs($this->coordinator)
        ->post(route('sessions.open', $session))
        ->assertRedirect(route('sessions.qr', $session));

    $this->assertDatabaseHas('event_sessions', ['id' => $session->id, 'status' => EventSession::STATUS_OPEN]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'session.opened', 'entity_id' => $session->id]);

    $this->actingAs($this->coordinator)
        ->get(route('sessions.qr', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/LiveQr')
            ->where('session.status', EventSession::STATUS_OPEN)
            ->where('session.event.status', Event::STATUS_PUBLISHED));
});

it('refuses to open a session before its event is published', function () {
    $session = scheduledSession(eventWithStatus($this->event, Event::STATUS_DRAFT));

    $this->actingAs($this->coordinator)
        ->post(route('sessions.open', $session))
        ->assertStatus(422);

    $this->assertDatabaseHas('event_sessions', ['id' => $session->id, 'status' => EventSession::STATUS_SCHEDULED]);
});

it('issues a QR challenge for an open session and stops once it is closed', function () {
    $challenge = $this->actingAs($this->coordinator)
        ->getJson(route('sessions.qr.challenge', $this->openSession))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->json();

    expect($challenge['token'])->toBeString()->not->toBeEmpty()
        ->and($challenge['ttl'])->toBe(30);

    $this->actingAs($this->coordinator)
        ->post(route('sessions.close', $this->openSession))
        ->assertSessionHas('success', 'Attendance closed.');

    $this->assertDatabaseHas('event_sessions', ['id' => $this->openSession->id, 'status' => EventSession::STATUS_CLOSED]);

    $this->actingAs($this->coordinator)
        ->getJson(route('sessions.qr.challenge', $this->openSession))
        ->assertConflict();
});

it('records a manual entry with its reason and opens a claim', function () {
    $this->actingAs($this->coordinator)
        ->post(route('sessions.attendance.manual', $this->openSession), [
            'student_id' => $this->unscannedStudent->id,
            'check_in_at' => '2026-10-20T09:00:00+08:00',
            'check_out_at' => '2026-10-20T11:00:00+08:00',
            'reason' => 'Phone battery died during the event.',
        ])
        ->assertSessionHas('success', 'Manual attendance recorded.');

    $attendance = Attendance::where('session_id', $this->openSession->id)->where('student_id', $this->unscannedStudent->id)->firstOrFail();
    expect($attendance->source)->toBe(Attendance::SOURCE_MANUAL)
        ->and($attendance->check_in_at->utc()->toDateTimeString())->toBe('2026-10-20 01:00:00');
    expect(AttendanceAdjustment::where('attendance_id', $attendance->id)->value('reason'))->toBe('Phone battery died during the event.');
    $this->assertDatabaseHas('service_records', ['attendance_id' => $attendance->id, 'status' => ServiceRecord::PENDING]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.manual_created', 'entity_id' => $attendance->id]);
});

it('rejects a manual entry with a short reason', function () {
    $this->actingAs($this->coordinator)
        ->post(route('sessions.attendance.manual', $this->openSession), [
            'student_id' => $this->unscannedStudent->id,
            'check_in_at' => '2026-10-20T09:00:00+08:00',
            'reason' => 'too short',
        ])
        ->assertSessionHasErrors(['reason' => 'The reason field must be at least 10 characters.']);

    $this->assertDatabaseMissing(Attendance::class, ['session_id' => $this->openSession->id, 'student_id' => $this->unscannedStudent->id]);
});

it('rejects a second manual entry for a student who already has attendance', function () {
    $student = User::where('email', 'student1@civicledger.test')->firstOrFail();
    $attendanceCount = Attendance::count();

    $this->actingAs($this->coordinator)
        ->post(route('sessions.attendance.manual', $this->openSession), [
            'student_id' => $student->id,
            'check_in_at' => '2026-10-20T09:00:00+08:00',
            'reason' => 'Trying to record this student twice.',
        ])
        ->assertSessionHasErrors(['student_id' => 'Attendance already exists for this student. Use an adjustment instead.']);

    expect(Attendance::count())->toBe($attendanceCount);
});

it('forbids students from recording manual attendance', function () {
    $this->actingAs($this->unscannedStudent)
        ->post(route('sessions.attendance.manual', $this->openSession), [
            'student_id' => $this->unscannedStudent->id,
            'check_in_at' => '2026-10-20T09:00:00+08:00',
            'reason' => 'Recording my own attendance.',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing(Attendance::class, ['session_id' => $this->openSession->id, 'student_id' => $this->unscannedStudent->id]);
});

it('corrects attendance keeping the earlier times, and sends an approved claim back to review', function () {
    $record = ServiceRecord::where('status', ServiceRecord::APPROVED)->firstOrFail();
    $attendance = $record->attendance;
    $originalCheckIn = $attendance->check_in_at->utc()->toIso8601String();

    $this->actingAs($this->coordinator)
        ->patch(route('attendance.adjust', $attendance), [
            'check_in_at' => '2026-10-20T09:00:00+08:00',
            'check_out_at' => '2026-10-20T10:30:00+08:00',
            'reason' => 'Scanner clock was wrong at the venue.',
        ])
        ->assertSessionHas('success', 'Attendance corrected; any approved claim is back in review.');

    expect($attendance->refresh()->check_in_at->utc()->toDateTimeString())->toBe('2026-10-20 01:00:00');
    $adjustment = AttendanceAdjustment::where('attendance_id', $attendance->id)->latest('id')->firstOrFail();
    expect($adjustment->reason)->toBe('Scanner clock was wrong at the venue.')
        ->and($adjustment->old_json['check_in_at'])->toBe($originalCheckIn);
    expect($record->refresh()->status)->not->toBe(ServiceRecord::APPROVED);
});

it('offers the correction on the review page only to staff who manage attendance for that event', function () {
    $record = ServiceRecord::where('status', ServiceRecord::PENDING)->firstOrFail();

    $this->actingAs($this->coordinator)
        ->get(route('hours.review.show', $record))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Hours/ReviewDetail')
            ->where('canCorrect', true));

    $this->actingAs($record->student)
        ->patch(route('attendance.adjust', $record->attendance), [
            'check_in_at' => '2026-10-20T09:00:00+08:00',
            'reason' => 'Changing my own check-in time.',
        ])
        ->assertForbidden();
});

it('filters the review queue by status', function () {
    $this->actingAs($this->coordinator)
        ->get(route('hours.review.index', ['status' => ServiceRecord::APPROVED]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Hours/ReviewQueue')
            ->where('status', ServiceRecord::APPROVED)
            ->has('records.data', 1)
            ->where('records.data.0.status', ServiceRecord::APPROVED));
});
