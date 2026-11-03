<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Section;
use App\Models\Term;
use App\Models\User;
use App\Models\UserSectionAssignment;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StudentSectionAssignmentController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        $sectionIds = $request->user()->assignedSectionIds(UserSectionAssignment::COORDINATOR);

        return Inertia::render('Coordinator/SectionAssignments', [
            'sections' => Section::query()
                ->with(['program:id,code', 'term:id,code'])
                ->whereIn('id', $sectionIds)
                ->orderBy('code')
                ->get(['id', 'program_id', 'term_id', 'code']),
            'programs' => Program::query()->orderBy('code')->get(['id', 'code', 'name']),
            'terms' => Term::query()->orderByDesc('starts_at')->get(['id', 'code', 'starts_at', 'ends_at']),
            'assignments' => UserSectionAssignment::query()
                ->with([
                    'user:id,first_name,middle_name,last_name,email',
                    'section:id,code,program_id,term_id',
                    'section.program:id,code',
                    'section.term:id,code',
                ])
                ->where('scope_role', UserSectionAssignment::STUDENT)
                ->whereIn('section_id', $sectionIds)
                ->orderByDesc('created_at')
                ->paginate(25),
        ]);
    }

    public function searchStudents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
        ]);
        $query = trim($validated['q']);

        if (mb_strlen($query) < 2) {
            throw ValidationException::withMessages([
                'q' => 'Enter at least two characters to search.',
            ]);
        }

        $students = $this->activeStudents();

        foreach (preg_split('/\s+/', $query) ?: [] as $term) {
            $students->where(fn (Builder $query) => $query
                ->where('first_name', 'like', "%{$term}%")
                ->orWhere('middle_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%"));
        }

        return response()->json([
            'students' => $students
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->limit(10)
                ->get(['id', 'first_name', 'middle_name', 'last_name', 'email'])
                ->map(fn (User $student): array => [
                    'id' => $student->id,
                    'name' => $student->full_name,
                    'email' => $student->email,
                ]),
        ]);
    }

    public function storeSection(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'program_id' => ['required', 'integer', 'exists:programs,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'code' => ['required', 'string', 'max:30'],
        ]);

        if (Section::query()
            ->where('program_id', $validated['program_id'])
            ->where('term_id', $validated['term_id'])
            ->where('code', $validated['code'])
            ->exists()) {
            throw ValidationException::withMessages([
                'code' => 'That section already exists for the selected program and term.',
            ]);
        }

        $section = DB::transaction(function () use ($validated, $request): Section {
            $section = Section::create($validated);
            $assignment = UserSectionAssignment::create([
                'user_id' => $request->user()->id,
                'section_id' => $section->id,
                'scope_role' => UserSectionAssignment::COORDINATOR,
            ]);

            $this->audit->log($request->user(), 'section.created', $section, null, $section->only([
                'program_id',
                'term_id',
                'code',
            ]));
            $this->audit->log(
                $request->user(),
                'assignment.created',
                $assignment,
                null,
                $assignment->only(['user_id', 'section_id', 'scope_role']),
            );

            return $section;
        });

        return back()->with('success', "Section {$section->code} created and added to your section selection.");
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:users,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
        ]);

        $sectionIds = $request->user()->assignedSectionIds(UserSectionAssignment::COORDINATOR);
        abort_unless($sectionIds->contains((int) $validated['section_id']), 403, 'That section is outside your assignment.');

        $student = $this->activeStudents()
            ->whereKey($validated['student_id'])
            ->first();

        if (! $student) {
            throw ValidationException::withMessages([
                'student_id' => 'Select an active student account.',
            ]);
        }

        $assignment = UserSectionAssignment::firstOrCreate([
            'user_id' => $student->id,
            'section_id' => (int) $validated['section_id'],
            'scope_role' => UserSectionAssignment::STUDENT,
        ]);

        if ($assignment->wasRecentlyCreated) {
            $this->audit->log(
                $request->user(),
                'assignment.created',
                $assignment,
                null,
                $assignment->only(['user_id', 'section_id', 'scope_role']),
            );
        }

        return back()->with('success', $assignment->wasRecentlyCreated
            ? 'Student assigned to section.'
            : 'Student is already assigned to this section.');
    }

    private function activeStudents(): Builder
    {
        return User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn (Builder $query) => $query->where('code', 'student'));
    }
}
