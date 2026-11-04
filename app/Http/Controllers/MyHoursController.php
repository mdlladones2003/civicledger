<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** S06: students see only their own attendance and hours (F1.1). Rejected rows show status, never invented credit. */
class MyHoursController extends Controller
{
    public function index(Request $request, ReportService $reports)
    {
        $user = $request->user();

        return Inertia::render('Student/MyHours', [
            'summary' => $reports->studentSummary($user),
            'attendance' => Attendance::where('student_id', $user->id)
                ->with(['session.event:id,title,starts_at', 'serviceRecord:id,attendance_id,status,approved_minutes,review_note'])
                ->latest('check_in_at')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }
}
