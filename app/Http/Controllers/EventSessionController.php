<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventSession;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $session = $event->sessions()->create($data + ['status' => EventSession::STATUS_SCHEDULED]);
        $this->audit->log($request->user(), 'session.created', $session, null, $session->only(['starts_at', 'ends_at']));

        return back()->with('success', 'Session added.');
    }

    public function open(Request $request, EventSession $session)
    {
        $session->load('event');
        Gate::authorize('manage', $session->event);
        abort_unless(
            ! $session->hasEnded(),
            422,
            'This event or attendance session has ended. Attendance cannot be opened.',
        );
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

    public function pauseQr(Request $request, EventSession $session)
    {
        DB::transaction(function () use ($request, $session): void {
            $session = EventSession::query()->with('event')->lockForUpdate()->findOrFail($session->id);
            Gate::authorize('manage', $session->event);
            $this->ensureQrCanBeControlled($session);

            if ($session->qr_challenge_enabled) {
                $session->update(['qr_challenge_enabled' => false]);
                $invalidatedChallenges = $session->challenges()->delete();
                $this->audit->log(
                    $request->user(),
                    'session.qr_paused',
                    $session,
                    ['qr_challenge_enabled' => true],
                    [
                        'qr_challenge_enabled' => false,
                        'invalidated_challenges' => $invalidatedChallenges,
                    ],
                );
            }
        });

        return back()->with('success', 'QR attendance stopped. Existing QR codes can no longer be used.');
    }

    public function resumeQr(Request $request, EventSession $session)
    {
        DB::transaction(function () use ($request, $session): void {
            $session = EventSession::query()->with('event')->lockForUpdate()->findOrFail($session->id);
            Gate::authorize('manage', $session->event);
            $this->ensureQrCanBeControlled($session);

            if (! $session->qr_challenge_enabled) {
                $session->update([
                    'qr_challenge_enabled' => true,
                    'qr_challenge_mode' => EventSession::QR_MODE_CHECK_OUT,
                ]);
                $this->audit->log(
                    $request->user(),
                    'session.qr_resumed',
                    $session,
                    ['qr_challenge_enabled' => false],
                    [
                        'qr_challenge_enabled' => true,
                        'qr_challenge_mode' => EventSession::QR_MODE_CHECK_OUT,
                    ],
                );
            }
        });

        return back()->with('success', 'QR attendance resumed. A new challenge will be shown.');
    }

    private function ensureQrCanBeControlled(EventSession $session): void
    {
        abort_unless(
            $session->status === EventSession::STATUS_OPEN,
            422,
            'QR challenges can only be controlled while attendance is open.',
        );
        abort_unless(
            ! $session->hasEnded(),
            422,
            'This event or attendance session has ended. QR challenges are unavailable.',
        );
        abort_unless(
            $session->event->status === Event::STATUS_PUBLISHED,
            422,
            'Publish the event before controlling QR attendance.',
        );
    }
}
