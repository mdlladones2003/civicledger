<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Program;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Term;
use App\Models\User;
use App\Models\UserSectionAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('allows a student to cancel a registration before attendance is recorded', function () {
    ['student' => $student, 'event' => $event] = createStudentRegistrationContext();

    $this->actingAs($student)
        ->get(route('events.show', $event))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Show')
            ->where('canCancelRegistration', true));

    $this->delete(route('events.unregister', $event))
        ->assertRedirect();

    $this->assertDatabaseHas('registrations', [
        'event_id' => $event->id,
        'student_id' => $student->id,
        'status' => Registration::CANCELLED,
    ]);
});

it('hides cancellation and forbids cancelling after attendance is recorded', function () {
    ['student' => $student, 'event' => $event, 'session' => $session] = createStudentRegistrationContext();
    Attendance::create([
        'session_id' => $session->id,
        'student_id' => $student->id,
        'check_in_at' => now()->subMinutes(30),
        'source' => Attendance::SOURCE_QR,
        'status' => Attendance::STATUS_CHECKED_IN,
    ]);

    $this->actingAs($student)
        ->get(route('events.show', $event))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Show')
            ->where('canCancelRegistration', false));

    $this->delete(route('events.unregister', $event))
        ->assertUnprocessable()
        ->assertSee('You cannot cancel after attendance has been recorded.');

    $this->assertDatabaseHas('registrations', [
        'event_id' => $event->id,
        'student_id' => $student->id,
        'status' => Registration::REGISTERED,
    ]);
});

/**
 * @return array{student: User, event: Event, session: EventSession}
 */
function createStudentRegistrationContext(): array
{
    $student = User::factory()->create();
    $coordinator = User::factory()->create();
    $program = Program::create(['code' => 'TEST', 'name' => 'Test program']);
    $term = Term::create([
        'code' => 'TEST-TERM',
        'starts_at' => '2026-01-01',
        'ends_at' => '2026-12-31',
    ]);
    $section = Section::create([
        'program_id' => $program->id,
        'term_id' => $term->id,
        'code' => 'TEST-A',
    ]);
    UserSectionAssignment::create([
        'user_id' => $student->id,
        'section_id' => $section->id,
        'scope_role' => UserSectionAssignment::STUDENT,
    ]);
    $event = Event::create([
        'coordinator_id' => $coordinator->id,
        'program_id' => $program->id,
        'section_id' => $section->id,
        'title' => 'Test event',
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(2),
        'status' => Event::STATUS_PUBLISHED,
    ]);
    $session = EventSession::create([
        'event_id' => $event->id,
        'starts_at' => $event->starts_at,
        'ends_at' => $event->ends_at,
        'status' => EventSession::STATUS_SCHEDULED,
    ]);
    Registration::create([
        'event_id' => $event->id,
        'student_id' => $student->id,
        'status' => Registration::REGISTERED,
    ]);

    return compact('student', 'event', 'session');
}
