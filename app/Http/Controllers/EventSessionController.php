<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventSession;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class EventSessionController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function store(Request $request, Event $event)
    {
        Gate::authorize('manage', $event);

        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);

        $session = $event->sessions()->create([
            'starts_at' => Carbon::parse($data['starts_at'])->utc(),
            'ends_at' => Carbon::parse($data['ends_at'])->utc(),
            'status' => EventSession::STATUS_SCHEDULED,
        ]);
        $this->audit->log($request->user(), 'session.created', $session, null, $session->only(['starts_at', 'ends_at']));

        return back()->with('success', 'Session added.');
    }

    public function open(Request $request, EventSession $session)
    {
        $session->load('event');
        Gate::authorize('manage', $session->event);
        abort_unless($session->event->status === Event::STATUS_PUBLISHED, 422, 'Publish the event before opening attendance.');
        abort_unless($session->status === EventSession::STATUS_SCHEDULED, 422, 'Only a scheduled session can be opened.');

        $session->update(['status' => EventSession::STATUS_OPEN]);
        $this->audit->log($request->user(), 'session.opened', $session, ['status' => EventSession::STATUS_SCHEDULED], ['status' => EventSession::STATUS_OPEN]);

        return redirect()->route('sessions.qr', $session);
    }

    public function close(Request $request, EventSession $session)
    {
        $session->load('event');
        Gate::authorize('manage', $session->event);
        abort_unless($session->status === EventSession::STATUS_OPEN, 422, 'Only an open session can be closed.');

        $session->update(['status' => EventSession::STATUS_CLOSED]);
        $this->audit->log($request->user(), 'session.closed', $session, ['status' => EventSession::STATUS_OPEN], ['status' => EventSession::STATUS_CLOSED]);

        return back()->with('success', 'Attendance closed.');
    }
}
