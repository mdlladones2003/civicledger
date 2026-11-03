<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Event;
use App\Models\User;
use App\Models\UserSectionAssignment;
use App\Services\CertificateService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ReportService $reports, CertificateService $certificates)
    {
        $user = $request->user();

        return match (true) {
            $user->hasPermission('users.manage') => Inertia::render('Admin/Dashboard', [
                'summary' => [
                    'users' => User::count(),
                    'events' => Event::count(),
                    'certificates' => Certificate::count(),
                ],
            ]),
            $user->hasPermission('events.manage') => Inertia::render('Coordinator/Dashboard', [
                'summary' => $reports->coordinatorSummary($user),
            ]),
            $user->hasPermission('certificates.issue') => Inertia::render('Signer/Dashboard', [
                'summary' => [
                    'events' => Event::count(),
                    'eligible_candidates' => $certificates->eligibleCandidates($user)->count(),
                    'issued_certificates' => Certificate::whereIn(
                        'student_id',
                        UserSectionAssignment::query()
                            ->whereIn('section_id', $user->assignedSectionIds(UserSectionAssignment::SIGNER))
                            ->where('scope_role', UserSectionAssignment::STUDENT)
                            ->select('user_id'),
                    )->count(),
                ],
            ]),
            default => Inertia::render('Student/Dashboard', [
                'summary' => $reports->studentSummary($user),
            ]),
        };
    }
}
