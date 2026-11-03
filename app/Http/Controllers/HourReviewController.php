<?php

namespace App\Http\Controllers;

use App\Http\Requests\DecisionRequest;
use App\Models\ServiceRecord;
use App\Services\ServiceRecordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class HourReviewController extends Controller
{
    public function __construct(private ServiceRecordService $records) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->string('status', ServiceRecord::PENDING)->toString();

        $items = ServiceRecord::query()
            ->whereHas('attendance.session.event', fn ($q) => $q->coordinatedBy($user))
            ->where('student_id', '!=', $user->id) // own claims are never reviewable
            ->when(in_array($status, [ServiceRecord::PENDING, ServiceRecord::APPROVED, ServiceRecord::REJECTED, ServiceRecord::REVISION_REQUESTED], true),
                fn ($q) => $q->where('status', $status))
            ->with(['student:id,first_name,middle_name,last_name', 'attendance.session.event:id,title'])
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Hours/ReviewQueue', ['records' => $items, 'status' => $status]);
    }

    public function show(ServiceRecord $serviceRecord)
    {
        Gate::authorize('view', $serviceRecord);

        $serviceRecord->load([
            'student:id,first_name,middle_name,last_name',
            'attendance.adjustments.actor:id,first_name,middle_name,last_name',
            'attendance.session.event:id,title,starts_at',
            'versions.proof',
        ]);

        return Inertia::render('Hours/ReviewDetail', [
            'record' => $serviceRecord,
            'derived_minutes' => $serviceRecord->attendance->durationMinutes(),
            'canReview' => Gate::allows('review', $serviceRecord),
        ]);
    }

    public function approve(DecisionRequest $request, ServiceRecord $serviceRecord)
    {
        Gate::authorize('review', $serviceRecord);
        $this->records->approve($serviceRecord, $request->user(), $request->validated('reason'));

        return redirect()->route('hours.review.index')->with('success', 'Hours approved. Proof queued.');
    }

    public function reject(DecisionRequest $request, ServiceRecord $serviceRecord)
    {
        Gate::authorize('review', $serviceRecord);
        $this->records->reject($serviceRecord, $request->user(), $request->validated('reason'));

        return redirect()->route('hours.review.index')->with('success', 'Claim rejected.');
    }

    public function revise(DecisionRequest $request, ServiceRecord $serviceRecord)
    {
        Gate::authorize('review', $serviceRecord);
        $this->records->requestRevision($serviceRecord, $request->user(), $request->validated('reason'));

        return redirect()->route('hours.review.index')->with('success', 'Revision requested.');
    }
}
