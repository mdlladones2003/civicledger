<?php

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Role;
use App\Models\Section;
use App\Models\Term;
use App\Models\User;
use App\Models\UserSectionAssignment;
use App\Services\QrChallengeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{coordinator: User, event: Event, session: EventSession}
 */
function createCoordinatorSessionContext(
    string $sessionStatus = EventSession::STATUS_SCHEDULED,
    string $eventStatus = Event::STATUS_PUBLISHED,
    ?Carbon $eventEndsAt = null,
    ?Carbon $sessionEndsAt = null,
): array {
    $permission = Permission::create([
        'code' => 'events.manage',
        'label' => 'Create and manage events in assigned scope',
    ]);
    $role = Role::create(['code' => 'coordinator', 'label' => 'Coordinator']);
    $role->permissions()->attach($permission);
    $coordinator = User::factory()->create(['status' => 'active']);
    $coordinator->roles()->attach($role);

    $program = Program::create(['code' => 'TEST', 'name' => 'Test Program']);
    $term = Term::create([
        'code' => 'TEST-TERM',
        'starts_at' => now()->toDateString(),
        'ends_at' => now()->addYear()->toDateString(),
    ]);
    $section = Section::create([
        'program_id' => $program->id,
        'term_id' => $term->id,
        'code' => 'TEST-A',
    ]);
    UserSectionAssignment::create([
        'user_id' => $coordinator->id,
        'section_id' => $section->id,
        'scope_role' => UserSectionAssignment::COORDINATOR,
    ]);

    $event = Event::create([
        'coordinator_id' => $coordinator->id,
        'program_id' => $program->id,
        'section_id' => $section->id,
        'title' => 'Community Service',
        'starts_at' => now(),
        'ends_at' => $eventEndsAt ?? now()->addHour(),
        'status' => $eventStatus,
    ]);
    $session = EventSession::create([
        'event_id' => $event->id,
        'starts_at' => $event->starts_at,
        'ends_at' => $sessionEndsAt ?? $event->ends_at,
        'status' => $sessionStatus,
    ]);

    return compact('coordinator', 'event', 'session');
}

it('lets the coordinator open a scheduled session before issuing a live QR challenge', function () {
    config(['civicledger.qr.ttl_seconds' => 30]);
    ['coordinator' => $coordinator, 'session' => $session] = createCoordinatorSessionContext();

    $this->actingAs($coordinator)
        ->getJson(route('sessions.qr.challenge', $session))
        ->assertConflict()
        ->assertJsonPath('message', 'Attendance is not open. Ask the coordinator to open this session.');

    $this->assertDatabaseCount('qr_challenges', 0);

    $this->post(route('sessions.open', $session))
        ->assertRedirect(route('sessions.qr', $session));

    $this->assertDatabaseHas('event_sessions', [
        'id' => $session->id,
        'status' => EventSession::STATUS_OPEN,
    ]);

    $response = $this->actingAs($coordinator)
        ->getJson(route('sessions.qr.challenge', $session))
        ->assertOk()
        ->assertHeaderContains('Cache-Control', 'no-store')
        ->assertJsonStructure(['token', 'expires_at', 'ttl'])
        ->assertJsonPath('ttl', 30);

    $this->assertDatabaseCount('qr_challenges', 1);

    $this->actingAs(User::factory()->create())
        ->postJson(route('attendance.scan.challenge'), ['token' => $response->json('token')])
        ->assertOk()
        ->assertJsonStructure(['remaining_seconds']);
});

it('lets the coordinator stop and resume QR attendance without closing the session', function () {
    $this->freezeTime();
    ['coordinator' => $coordinator, 'session' => $session] = createCoordinatorSessionContext(
        sessionStatus: EventSession::STATUS_OPEN,
    );
    config(['civicledger.qr.ttl_seconds' => 30]);
    $existingChallenge = app(QrChallengeService::class)->issue($session);

    $this->actingAs($coordinator)
        ->post(route('sessions.qr.pause', $session))
        ->assertRedirect()
        ->assertSessionHas('success', 'QR attendance stopped. Existing QR codes can no longer be used.');

    $this->assertDatabaseHas('event_sessions', [
        'id' => $session->id,
        'status' => EventSession::STATUS_OPEN,
        'qr_challenge_enabled' => false,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $coordinator->id,
        'action' => 'session.qr_paused',
        'entity_id' => $session->id,
    ]);
    $this->assertDatabaseCount('qr_challenges', 0);

    $this->getJson(route('sessions.qr.challenge', $session))
        ->assertConflict()
        ->assertJsonPath('message', 'QR attendance is temporarily stopped by the coordinator. Wait until a new code is shown.');

    $this->actingAs(User::factory()->create())
        ->postJson(route('attendance.scan.store'), ['payload' => $existingChallenge['token']])
        ->assertConflict()
        ->assertJsonPath('error.code', 'session_closed')
        ->assertJsonPath('error.message', 'QR attendance is temporarily stopped by the coordinator. Wait until a new code is shown.');

    $this->actingAs($coordinator)
        ->post(route('sessions.qr.resume', $session))
        ->assertRedirect()
        ->assertSessionHas('success', 'QR attendance resumed. A new challenge will be shown.');

    $this->assertDatabaseHas('event_sessions', [
        'id' => $session->id,
        'status' => EventSession::STATUS_OPEN,
        'qr_challenge_enabled' => true,
        'qr_challenge_mode' => EventSession::QR_MODE_CHECK_OUT,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $coordinator->id,
        'action' => 'session.qr_resumed',
        'entity_id' => $session->id,
    ]);

    $newChallenge = $this->getJson(route('sessions.qr.challenge', $session))
        ->assertOk()
        ->assertJsonStructure(['token', 'expires_at', 'ttl'])
        ->json('token');

    $this->actingAs(User::factory()->create())
        ->postJson(route('attendance.scan.store'), ['payload' => $existingChallenge['token']])
        ->assertConflict()
        ->assertJsonPath('error.code', 'invalid_state');

    expect($newChallenge)->not->toBe($existingChallenge['token']);
});

it('forbids users without event management permission from pausing QR attendance', function () {
    ['session' => $session] = createCoordinatorSessionContext(
        sessionStatus: EventSession::STATUS_OPEN,
    );
    $user = User::factory()->create(['status' => 'active']);

    $this->actingAs($user)
        ->post(route('sessions.qr.pause', $session))
        ->assertForbidden();

    $this->assertDatabaseHas('event_sessions', [
        'id' => $session->id,
        'status' => EventSession::STATUS_OPEN,
        'qr_challenge_enabled' => true,
    ]);
});

it('does not resume QR attendance after the event or session has ended', function () {
    ['coordinator' => $coordinator, 'session' => $session] = createCoordinatorSessionContext(
        sessionStatus: EventSession::STATUS_OPEN,
        eventEndsAt: now()->addMinute(),
        sessionEndsAt: now()->subSecond(),
    );
    $session->update(['qr_challenge_enabled' => false]);

    $this->actingAs($coordinator)
        ->post(route('sessions.qr.resume', $session))
        ->assertUnprocessable()
        ->assertSeeText('This event or attendance session has ended. QR challenges are unavailable.');

    $this->assertDatabaseHas('event_sessions', [
        'id' => $session->id,
        'status' => EventSession::STATUS_OPEN,
        'qr_challenge_enabled' => false,
    ]);
});

it('does not issue or accept QR challenges after an event or session ends', function () {
    $this->freezeTime();
    config(['civicledger.qr.ttl_seconds' => 3600]);
    [
        'coordinator' => $coordinator,
        'event' => $event,
        'session' => $session,
    ] = createCoordinatorSessionContext(
        sessionStatus: EventSession::STATUS_OPEN,
        eventEndsAt: now()->addMinutes(20),
        sessionEndsAt: now()->addMinutes(10),
    );

    $response = $this->actingAs($coordinator)
        ->getJson(route('sessions.qr.challenge', $session))
        ->assertOk()
        ->assertJsonPath('ttl', 600);
    $token = $response->json('token');

    $session->update(['ends_at' => now()->subSecond()]);

    $this->actingAs($coordinator)
        ->getJson(route('sessions.qr.challenge', $session))
        ->assertConflict()
        ->assertJsonPath('message', 'This event or attendance session has ended. QR challenges are unavailable.');

    $session->update(['ends_at' => now()->addMinutes(30)]);
    $event->update(['ends_at' => now()->subSecond()]);

    $this->actingAs($coordinator)
        ->getJson(route('sessions.qr.challenge', $session))
        ->assertConflict()
        ->assertJsonPath('message', 'This event or attendance session has ended. QR challenges are unavailable.');

    $this->actingAs($coordinator)
        ->get(route('sessions.qr', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/LiveQr')
            ->where('session.attendance_ended', true));

    $this->actingAs($coordinator)
        ->get(route('events.show', $event))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Show')
            ->where('event.sessions.0.attendance_ended', true));

    $this->actingAs(User::factory()->create())
        ->postJson(route('attendance.scan.store'), ['payload' => $token])
        ->assertConflict()
        ->assertJsonPath('error.code', 'session_closed')
        ->assertJsonPath('error.message', 'This event or attendance session has ended. Check-in and check-out are no longer available.');

    $this->assertDatabaseCount('qr_challenges', 1);

    $session->update(['status' => EventSession::STATUS_SCHEDULED]);

    $this->actingAs($coordinator)
        ->postJson(route('sessions.open', $session))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This event or attendance session has ended. Attendance cannot be opened.');

    $this->assertDatabaseHas('event_sessions', [
        'id' => $session->id,
        'status' => EventSession::STATUS_SCHEDULED,
    ]);
});
