<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSectionAssignment;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SectionAssignmentController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function store(Request $request, User $user)
    {
        $data = $request->validate([
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'scope_role' => ['required', Rule::in(UserSectionAssignment::SCOPE_ROLES)],
        ]);

        $assignment = UserSectionAssignment::firstOrCreate(['user_id' => $user->id] + $data);

        if ($assignment->wasRecentlyCreated) {
            $this->audit->log($request->user(), 'assignment.created', $assignment, null, $assignment->only(['user_id', 'section_id', 'scope_role']));
        }

        return back()->with('success', 'Assignment saved.');
    }

    public function destroy(Request $request, UserSectionAssignment $assignment)
    {
        $before = $assignment->only(['user_id', 'section_id', 'scope_role']);

        // Log first: the audit target must exist when validated.
        $this->audit->log($request->user(), 'assignment.removed', $assignment, $before, null);
        $assignment->delete();

        return back()->with('success', 'Assignment removed.');
    }
}
