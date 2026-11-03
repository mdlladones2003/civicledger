<?php

use App\Models\Certificate;
use App\Models\Event;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\CivicLedgerDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CivicLedgerDemoSeeder::class);
    $this->admin = User::where('email', 'admin@civicledger.test')->firstOrFail();
});

it('shows administrators a dashboard with system-wide totals', function () {
    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard', false)
            ->where('summary.users', User::count())
            ->where('summary.events', Event::count())
            ->where('summary.certificates', Certificate::count()));
});

it('shows administrators all events and certificates without edit or issuance access', function () {
    $event = Event::firstOrFail();
    Event::create([
        'coordinator_id' => $event->coordinator_id,
        'program_id' => $event->program_id,
        'section_id' => $event->section_id,
        'title' => 'Administrator-visible draft',
        'venue' => 'Test venue',
        'component' => 'CWTS',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
        'status' => Event::STATUS_DRAFT,
    ]);

    $this->actingAs($this->admin)
        ->get('/events')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Index')
            ->where('isAdmin', true)
            ->where('canCreate', false)
            ->has('events.data', 2)
            ->where('events.data.0.title', 'Community Clean-up Drive (Demo)')
            ->where('events.data.1.title', 'Administrator-visible draft'));

    $this->actingAs($this->admin)
        ->get('/events/'.$event->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Show')
            ->where('canManage', false));

    $this->actingAs($this->admin)
        ->get('/certificates')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Certificates/Index')
            ->has('certificates.data', Certificate::count())
            ->where('certificates.data.0.student.first_name', 'Sofia'));
});

it('paginates the administrator certificate list across all students', function () {
    $student = User::where('email', 'student1@civicledger.test')->firstOrFail();
    $term = Term::firstOrFail();

    foreach (range(1, 21) as $number) {
        Certificate::create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'issued_by' => $this->admin->id,
            'public_token' => str_pad((string) $number, 64, '0', STR_PAD_LEFT),
            'certificate_number' => sprintf('TEST-%03d', $number),
            'approved_minutes' => 120,
            'status' => Certificate::ISSUED,
            'issued_at' => now(),
        ]);
    }

    $this->actingAs($this->admin)
        ->get('/certificates?page=2')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Certificates/Index')
            ->where('certificates.current_page', 2)
            ->has('certificates.data', 2)
            ->where('certificates.prev_page_url', url('/certificates?page=1')));
});

it('hides administrator emails and blocks account deletion', function () {
    $this->actingAs($this->admin)
        ->get('/admin/users')
        ->assertOk()
        ->assertDontSee('admin@civicledger.test')
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Users'));

    $this->actingAs($this->admin)
        ->get('/profile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Account/Profile')
            ->where('canDeleteAccount', false));

    $this->actingAs($this->admin)
        ->delete('/account', ['current_password' => 'password'])
        ->assertForbidden();
});
