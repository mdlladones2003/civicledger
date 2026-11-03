<?php

use App\Models\Event;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\CivicLedgerDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CivicLedgerDemoSeeder::class);
});

it('gives signers a dashboard and lets them view created events without management access', function () {
    $signer = User::where('email', 'signer@civicledger.test')->firstOrFail();
    $event = Event::firstOrFail();
    $draft = Event::create([
        'coordinator_id' => $event->coordinator_id,
        'program_id' => $event->program_id,
        'section_id' => $event->section_id,
        'title' => 'Coordinator-created draft',
        'venue' => 'Test venue',
        'component' => 'CWTS',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
        'status' => Event::STATUS_DRAFT,
    ]);

    $this->actingAs($signer)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Signer/Dashboard', false)
            ->where('summary.events', 2)
            ->where('summary.eligible_candidates', 0)
            ->where('summary.issued_certificates', 1));

    $this->actingAs($signer)
        ->get('/events')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Index')
            ->where('isSigner', true)
            ->where('canCreate', false)
            ->has('events.data', 2));

    $this->actingAs($signer)
        ->get("/events/{$draft->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Show')
            ->where('canManage', false)
            ->where('canRegister', false));
});

it('does not offer or allow account deletion for coordinators and signers', function () {
    foreach (['coordinator@civicledger.test', 'signer@civicledger.test'] as $email) {
        $user = User::where('email', $email)->firstOrFail();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Profile')
                ->where('canDeleteAccount', false));

        $this->actingAs($user)
            ->delete('/account', ['current_password' => 'password'])
            ->assertForbidden();
    }
});

it('shows past events to new and unassigned students without opening drafts or past registration', function () {
    $newStudent = User::where('email', 'student1@civicledger.test')->firstOrFail();
    $oldStudent = User::factory()->create();
    $oldStudent->roles()->attach(Role::where('code', 'student')->value('id'));

    $section = Section::firstOrFail();
    $coordinatorId = User::where('email', 'coordinator@civicledger.test')->value('id');
    $pastEvent = Event::create([
        'coordinator_id' => $coordinatorId,
        'program_id' => $section->program_id,
        'section_id' => $section->id,
        'title' => 'Past community event',
        'venue' => 'Community hall',
        'component' => 'CWTS',
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subDay(),
        'status' => Event::STATUS_COMPLETED,
    ]);

    $draft = Event::create([
        'coordinator_id' => $coordinatorId,
        'program_id' => $section->program_id,
        'section_id' => $section->id,
        'title' => 'Private historical draft',
        'venue' => 'Community hall',
        'component' => 'CWTS',
        'starts_at' => now()->subDays(4),
        'ends_at' => now()->subDays(3),
        'status' => Event::STATUS_DRAFT,
    ]);

    foreach ([$newStudent, $oldStudent] as $student) {
        $expectedEvents = $student->is($newStudent) ? 2 : 1;
        $response = $this->actingAs($student)
            ->get('/events')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Index')
                ->has('events.data', $expectedEvents)
                ->where('events.data.0.title', 'Past community event'));

        if ($student->is($newStudent)) {
            $response->assertInertia(fn (Assert $page) => $page
                ->where('events.data.1.title', 'Community Clean-up Drive (Demo)'));
        }

        $this->get("/events/{$pastEvent->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Show')
                ->where('canRegister', false));

        $this->post("/events/{$pastEvent->id}/register")
            ->assertForbidden();
    }

    $this->actingAs($oldStudent)
        ->get("/events/{$draft->id}")
        ->assertForbidden();
});
