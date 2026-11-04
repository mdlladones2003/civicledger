<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Registration;
use App\Models\Role;
use App\Models\Section;
use App\Models\Term;
use App\Models\User;
use App\Models\UserSectionAssignment;
use App\Services\CertificateService;
use App\Services\ServiceRecordService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Synthetic users, roles, permissions, sections, one event, attendance, and (with the fake chain driver)
 * a preconfirmed proof + certificate for the demo fallback. All demo passwords: "password".
 */
class CivicLedgerDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('CivicLedgerDemoSeeder must not run in production.');
        }

        // Run proof jobs inline so the seeded proofs are confirmed immediately (fake driver).
        config(['queue.default' => 'sync']);

        $permissions = collect([
            'events.manage' => 'Create and manage events in assigned scope',
            'attendance.manual' => 'Manual attendance fallback / corrections',
            'hours.review' => 'Approve, reject or request revision of hours',
            'reports.view' => 'View scoped reports',
            'certificates.issue' => 'Issue certificates',
            'certificates.revoke' => 'Revoke or supersede certificates',
            'users.manage' => 'Manage users, roles and assignments',
            'audit.view' => 'View the audit log',
        ])->map(fn ($label, $code) => Permission::firstOrCreate(['code' => $code], ['label' => $label]));

        $matrix = [
            'student' => ['Student', []],
            'coordinator' => ['Coordinator', ['events.manage', 'attendance.manual', 'hours.review', 'reports.view']],
            'signer' => ['Certificate signer', ['certificates.issue', 'certificates.revoke']],
            'admin' => ['Administrator', ['users.manage', 'audit.view']],
        ];

        $roles = [];
        foreach ($matrix as $code => [$label, $codes]) {
            $roles[$code] = Role::firstOrCreate(['code' => $code], ['label' => $label]);
            $roles[$code]->permissions()->sync($permissions->only($codes)->pluck('id')->all());
        }

        $term = Term::firstOrCreate(['code' => '2026-T1'], ['starts_at' => '2026-08-01', 'ends_at' => '2026-12-20']);
        $program = Program::firstOrCreate(['code' => 'NSTP-CWTS'], ['name' => 'NSTP – Civic Welfare Training Service']);
        $section = Section::firstOrCreate(['program_id' => $program->id, 'term_id' => $term->id, 'code' => 'CWTS-A']);

        $make = function (string $email, string $firstName, ?string $middleName, string $lastName, string $role) use ($roles): User {
            $user = User::firstOrNew(['email' => $email]);
            $user->forceFill([
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ])->save();
            $user->roles()->syncWithoutDetaching([$roles[$role]->id]);

            return $user;
        };

        $admin = $make('admin@civicledger.test', 'Alex', null, 'Admin', 'admin');
        $coordinator = $make('coordinator@civicledger.test', 'Carla', null, 'Coordinator', 'coordinator');
        $signer = $make('signer@civicledger.test', 'Sam', null, 'Signer', 'signer');
        $students = [
            $make('student1@civicledger.test', 'Sofia', null, 'Reyes', 'student'),
            $make('student2@civicledger.test', 'Miguel', null, 'Santos', 'student'),
            $make('student3@civicledger.test', 'Ana', null, 'Cruz', 'student'),
        ];

        $assign = fn (User $u, string $scope) => UserSectionAssignment::firstOrCreate(
            ['user_id' => $u->id, 'section_id' => $section->id, 'scope_role' => $scope]
        );

        $assign($coordinator, UserSectionAssignment::COORDINATOR);
        $assign($signer, UserSectionAssignment::SIGNER);
        foreach ($students as $s) {
            $assign($s, UserSectionAssignment::STUDENT);
        }

        // One published event with an open session.
        $event = Event::firstOrCreate(
            ['title' => 'Community Clean-up Drive (Demo)', 'coordinator_id' => $coordinator->id],
            [
                'program_id' => $program->id,
                'section_id' => $section->id,
                'venue' => 'Barangay Hall Grounds',
                'component' => 'CWTS',
                'starts_at' => now()->subHours(5),
                'ends_at' => now()->addHours(2),
                'status' => Event::STATUS_PUBLISHED,
            ],
        );

        $session = EventSession::firstOrCreate(
            ['event_id' => $event->id],
            ['starts_at' => $event->starts_at, 'ends_at' => $event->ends_at, 'status' => EventSession::STATUS_OPEN],
        );

        foreach ($students as $s) {
            Registration::firstOrCreate(['event_id' => $event->id, 'student_id' => $s->id], ['status' => Registration::REGISTERED]);
        }

        // Students 1 and 2 completed attendance; student 3 only registered (shows an empty roster state).
        $records = app(ServiceRecordService::class);
        $approved = [];

        foreach (array_slice($students, 0, 2) as $i => $s) {
            $attendance = Attendance::firstOrCreate(
                ['session_id' => $session->id, 'student_id' => $s->id],
                [
                    'check_in_at' => now()->subHours(4),
                    'check_out_at' => now()->subHours(2)->subMinutes($i * 15), // 120 / 105 minutes
                    'source' => Attendance::SOURCE_QR,
                    'status' => Attendance::STATUS_COMPLETED,
                ],
            );

            $record = $records->openClaim($attendance);

            // Student 1 approved (proof confirmed via the fake client); student 2 stays pending for the live demo.
            if ($i === 0) {
                $approved[] = $records->approve($record, $coordinator, 'Seeded demo approval');
            }
        }

        // Seeding again must not try to issue a second active certificate.
        $alreadyIssued = Certificate::where('student_id', $students[0]->id)
            ->where('term_id', $term->id)
            ->where('status', Certificate::ISSUED)
            ->exists();

        if ($approved && ! $alreadyIssued) {
            app(CertificateService::class)->issue($signer, $students[0], $term);
        }

        $this->command?->info('Demo accounts (password "password"): admin@, coordinator@, signer@, student1@..student3@civicledger.test');
    }
}
