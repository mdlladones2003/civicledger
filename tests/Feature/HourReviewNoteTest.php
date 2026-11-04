<?php

use App\Models\ServiceRecord;
use App\Models\User;
use Database\Seeders\CivicLedgerDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CivicLedgerDemoSeeder::class);
    $this->coordinator = User::where('email', 'coordinator@civicledger.test')->firstOrFail();
    $this->student = User::where('email', 'student2@civicledger.test')->firstOrFail();
    $this->record = ServiceRecord::where('student_id', $this->student->id)->firstOrFail();
});

it('shows the student the coordinator reason after a negative decision', function (string $routeName, string $expectedStatus) {
    $response = $this->actingAs($this->coordinator)
        ->post(route($routeName, $this->record), ['reason' => 'No check-out was recorded for this session.']);

    $response->assertRedirect(route('hours.review.index'));
    expect($this->record->refresh()->status)->toBe($expectedStatus);
    expect($this->record->approved_minutes)->toBe(0);

    $this->actingAs($this->student)
        ->get(route('me.hours'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/MyHours', false)
            ->where('attendance.data.0.service_record.status', $expectedStatus)
            ->where('attendance.data.0.service_record.review_note', 'No check-out was recorded for this session.'));
})->with([
    'rejected' => ['hours.reject', ServiceRecord::REJECTED],
    'revision requested' => ['hours.revise', ServiceRecord::REVISION_REQUESTED],
]);

it('requires a reason before rejecting a claim', function () {
    $response = $this->actingAs($this->coordinator)
        ->post(route('hours.reject', $this->record), ['reason' => '']);

    $response->assertSessionHasErrors(['reason' => 'The reason field is required.']);
    expect($this->record->refresh()->status)->toBe(ServiceRecord::PENDING);
    expect($this->record->review_note)->toBeNull();
});

it('clears the earlier note once the corrected claim is approved', function () {
    $this->actingAs($this->coordinator)
        ->post(route('hours.revise', $this->record), ['reason' => 'Please confirm the check-out time.']);

    $response = $this->actingAs($this->coordinator)
        ->post(route('hours.approve', $this->record));

    $response->assertRedirect(route('hours.review.index'));
    expect($this->record->refresh()->status)->toBe(ServiceRecord::APPROVED);
    expect($this->record->review_note)->toBeNull();
});
