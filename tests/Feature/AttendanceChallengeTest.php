<?php

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Program;
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
