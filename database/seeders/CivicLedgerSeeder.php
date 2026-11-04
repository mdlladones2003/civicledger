<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Role;
use App\Models\Section;
use App\Models\Term;
use App\Models\User;
use App\Models\UserSectionAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class CivicLedgerSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('CivicLedgerSeeder must not run in production.');
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
        $programDefinitions = [
            'NSTP-CWTS' => [
                'name' => 'NSTP – Civic Welfare Training Service',
                'sections' => ['CWTS-A', 'CWTS-B'],
            ],
            'NSTP-LTS' => [
                'name' => 'NSTP – Literacy Training Service',
                'sections' => ['LTS-A', 'LTS-B'],
            ],
            'NSTP-ROTC' => [
                'name' => 'NSTP – Reserve Officers’ Training Corps',
                'sections' => ['ROTC-A', 'ROTC-B'],
            ],
        ];
        $programs = [];
        $programSections = [];

        foreach ($programDefinitions as $programCode => $definition) {
            $programs[$programCode] = Program::firstOrCreate(
                ['code' => $programCode],
                ['name' => $definition['name']],
            );

            foreach ($definition['sections'] as $sectionCode) {
                $programSections[$programCode][$sectionCode] = Section::firstOrCreate([
                    'program_id' => $programs[$programCode]->id,
                    'term_id' => $term->id,
                    'code' => $sectionCode,
                ]);
            }
        }

        $program = $programs['NSTP-CWTS'];
        $section = $programSections['NSTP-CWTS']['CWTS-A'];
        $eventSections = ['CWTS-B' => $programSections['NSTP-CWTS']['CWTS-B']];

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

        $make('admin@civicledger.test', 'Alex', null, 'Admin', 'admin');
        $coordinator = $make('coordinator@civicledger.test', 'Carla', null, 'Coordinator', 'coordinator');
        $signer = $make('signer@civicledger.test', 'Sam', null, 'Signer', 'signer');
        $students = [
            $make('student1@civicledger.test', 'Sofia', null, 'Reyes', 'student'),
            $make('student2@civicledger.test', 'Miguel', null, 'Santos', 'student'),
        ];

        $assign = fn (User $u, string $scope) => UserSectionAssignment::firstOrCreate(
            ['user_id' => $u->id, 'section_id' => $section->id, 'scope_role' => $scope]
        );

        $assign($coordinator, UserSectionAssignment::COORDINATOR);
        $assign($signer, UserSectionAssignment::SIGNER);
        foreach ($students as $s) {
            $assign($s, UserSectionAssignment::STUDENT);
        }

        foreach ($eventSections as $eventSection) {
            UserSectionAssignment::firstOrCreate([
                'user_id' => $coordinator->id,
                'section_id' => $eventSection->id,
                'scope_role' => UserSectionAssignment::COORDINATOR,
            ]);
        }

        // Coordinators manually open the scheduled session when attendance begins.
        $event = Event::firstOrCreate(
            ['title' => 'Coastal Mangrove Tree Planting', 'coordinator_id' => $coordinator->id],
            [
                'program_id' => $program->id,
                'section_id' => $section->id,
                'description' => 'A student-led coastal rehabilitation initiative focused on planting mangrove saplings to protect marine life and prevent shoreline erosion.',
                'venue' => 'Barangay Coastal Mangrove Site',
                'component' => 'CWTS',
                'starts_at' => now()->subHours(5),
                'ends_at' => now()->addHours(2),
                'status' => Event::STATUS_PUBLISHED,
            ],
        );

        $session = EventSession::firstOrCreate(
            ['event_id' => $event->id],
            ['starts_at' => $event->starts_at, 'ends_at' => $event->ends_at, 'status' => EventSession::STATUS_SCHEDULED],
        );

        $upcomingEvents = [
            [
                'section' => 'CWTS-B',
                'title' => 'Coastal Mangrove Tree Planting',
                'description' => 'A student-led coastal rehabilitation initiative focused on planting mangrove saplings to protect marine life and prevent shoreline erosion.',
                'venue' => 'Barangay Coastal Mangrove Site',
                'starts_at' => '2026-11-12 00:00:00',
                'ends_at' => '2026-11-12 03:00:00',
            ],
        ];

        foreach ($upcomingEvents as $upcomingEvent) {
            $upcomingEventModel = Event::firstOrCreate(
                [
                    'title' => $upcomingEvent['title'],
                    'coordinator_id' => $coordinator->id,
                    'section_id' => $eventSections[$upcomingEvent['section']]->id,
                ],
                [
                    'program_id' => $program->id,
                    'description' => $upcomingEvent['description'],
                    'venue' => $upcomingEvent['venue'],
                    'component' => 'CWTS',
                    'starts_at' => $upcomingEvent['starts_at'],
                    'ends_at' => $upcomingEvent['ends_at'],
                    'status' => Event::STATUS_PUBLISHED,
                ],
            );

            EventSession::firstOrCreate(
                ['event_id' => $upcomingEventModel->id],
                [
                    'starts_at' => $upcomingEventModel->starts_at,
                    'ends_at' => $upcomingEventModel->ends_at,
                    'status' => EventSession::STATUS_SCHEDULED,
                ],
            );
        }

        $this->command?->info('Accounts (password "password"): admin@, coordinator@, signer@, student1@..student2@civicledger.test');
    }
}
