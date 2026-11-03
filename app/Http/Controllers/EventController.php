<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Registration;
use App\Models\Section;
use App\Models\User;
use App\Models\UserSectionAssignment;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class EventController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $manages = $user->hasPermission('events.manage');
        $isAdmin = $user->hasPermission('users.manage');
        $isSigner = $user->hasPermission('certificates.issue');

        $query = match (true) {
            $isAdmin || $isSigner => Event::query(),
            $manages => Event::coordinatedBy($user),
            default => Event::visibleToStudent($user),
        };

        $events = $query
            ->with('section:id,code')
            ->withExists(['registrations as registered' => fn ($q) => $q
                ->where('student_id', $user->id)->where('status', Registration::REGISTERED)])
            ->orderBy('starts_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Events/Index', [
            'events' => $events,
            'canCreate' => Gate::allows('create', Event::class),
            'isAdmin' => $isAdmin,
            'isSigner' => $isSigner && ! $isAdmin,
        ]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', Event::class);

        return Inertia::render('Events/Editor', ['event' => null, 'sections' => $this->scopedSections($request->user())]);
    }

    public function store(StoreEventRequest $request)
    {
        Gate::authorize('create', Event::class);

        $data = $this->scoped($request->user(), $request->validated());

        $event = DB::transaction(function () use ($request, $data) {
            $event = Event::create($data + ['coordinator_id' => $request->user()->id, 'status' => Event::STATUS_DRAFT]);

            // One default session spanning the event; extra sessions via events.sessions.store.
            $event->sessions()->create([
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'status' => EventSession::STATUS_SCHEDULED,
            ]);

            $this->audit->log($request->user(), 'event.created', $event, null, ['title' => $event->title]);

            return $event;
        });

        return redirect()->route('events.show', $event)->with('success', 'Draft created.');
    }

    public function show(Request $request, Event $event)
    {
        Gate::authorize('view', $event);

        $user = $request->user();
        $manages = Gate::allows('manage', $event);

        $event->load(['section:id,code', 'program:id,code,name', 'sessions']);

        return Inertia::render('Events/Show', [
            'event' => $event,
            'canManage' => $manages,
            'canRegister' => Gate::allows('register', $event),
            'registration' => Registration::where('event_id', $event->id)->where('student_id', $user->id)->first(),
            // F2.4: roster visible to authorized coordinators only.
            'roster' => $manages
                ? Registration::where('event_id', $event->id)->with('student:id,first_name,middle_name,last_name,email')->orderBy('created_at')->paginate(50)
                : null,
        ]);
    }

    public function edit(Request $request, Event $event)
    {
        Gate::authorize('manage', $event);

        return Inertia::render('Events/Editor', ['event' => $event, 'sections' => $this->scopedSections($request->user())]);
    }

    public function update(StoreEventRequest $request, Event $event)
    {
        Gate::authorize('manage', $event);
        abort_if(in_array($event->status, [Event::STATUS_CANCELLED, Event::STATUS_COMPLETED], true), 422, 'This event can no longer be edited.');

        $before = $event->only(['title', 'venue', 'starts_at', 'ends_at', 'section_id']);
        $event->update($this->scoped($request->user(), $request->validated()));
        $this->audit->log($request->user(), 'event.updated', $event, $before, $event->only(array_keys($before)));

        return redirect()->route('events.show', $event)->with('success', 'Event updated.');
    }

    public function publish(Request $request, Event $event)
    {
        return $this->transition($request, $event, Event::STATUS_PUBLISHED, [Event::STATUS_DRAFT]);
    }

    public function cancel(Request $request, Event $event)
    {
        return $this->transition($request, $event, Event::STATUS_CANCELLED, [Event::STATUS_DRAFT, Event::STATUS_PUBLISHED]);
    }

    public function complete(Request $request, Event $event)
    {
        return $this->transition($request, $event, Event::STATUS_COMPLETED, [Event::STATUS_PUBLISHED]);
    }

    private function transition(Request $request, Event $event, string $to, array $from)
    {
        Gate::authorize('manage', $event);
        abort_unless(in_array($event->status, $from, true), 422, "Cannot move an event from {$event->status} to {$to}.");

        DB::transaction(function () use ($request, $event, $to) {
            $before = ['status' => $event->status];
            $event->update(['status' => $to]);

            // Closing the event closes any open QR sessions.
            if (in_array($to, [Event::STATUS_CANCELLED, Event::STATUS_COMPLETED], true)) {
                $event->sessions()->where('status', EventSession::STATUS_OPEN)->update(['status' => EventSession::STATUS_CLOSED]);
            }

            $this->audit->log($request->user(), "event.{$to}", $event, $before, ['status' => $to]);
        });

        return back()->with('success', "Event {$to}.");
    }

    /** Coordinators can only target their own sections / programs (F1.2). */
    private function scoped(User $user, array $data): array
    {
        if (! empty($data['section_id'])) {
            abort_unless($user->assignedSectionIds(UserSectionAssignment::COORDINATOR)->contains((int) $data['section_id']), 403, 'That section is outside your assignment.');
            $data['program_id'] = Section::findOrFail($data['section_id'])->program_id;
        } else {
            abort_unless($user->assignedProgramIds(UserSectionAssignment::COORDINATOR)->contains((int) $data['program_id']), 403, 'That program is outside your assignment.');
            $data['section_id'] = null;
        }

        return $data;
    }

    private function scopedSections(User $user)
    {
        return Section::with(['program:id,code,name', 'term:id,code'])
            ->whereIn('id', $user->assignedSectionIds(UserSectionAssignment::COORDINATOR))
            ->get();
    }
}
