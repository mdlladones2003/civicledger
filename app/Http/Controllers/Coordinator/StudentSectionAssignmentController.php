<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Section;
use App\Models\Term;
use App\Models\UserSectionAssignment;
use App\Services\AuditLogger;
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
            'students' => UserSectionAssignment::query()
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

    public function storeProgram(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:programs,code'],
            'name' => ['required', 'string', 'max:150'],
        ]);

        $program = DB::transaction(function () use ($validated, $request): Program {
            $program = Program::create($validated);
            $this->audit->log($request->user(), 'program.created', $program, null, $program->only(['code', 'name']));

            return $program;
        });

        return back()->with('success', "Program {$program->code} created.");
    }

    public function storeTerm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:terms,code'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
        ]);

        $term = DB::transaction(function () use ($validated, $request): Term {
            $term = Term::create($validated);
            $this->audit->log($request->user(), 'term.created', $term, null, $term->only(['code', 'starts_at', 'ends_at']));

            return $term;
        });

        return back()->with('success', "Term {$term->code} created.");
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
}
