import { useEffect, useState } from 'react';
import { Form, usePoll } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';
import {
    Table as ShadcnTable,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    ArrowLeft,
    ArrowRight,
    Award,
    BarChart3,
    CalendarDays,
    Check,
    ClipboardCheck,
    Clock3,
    Fingerprint,
    HeartHandshake,
    Info,
    KeyRound,
    LayoutDashboard,
    LogOut,
    LockKeyhole,
    LoaderCircle,
    MapPin,
    Menu,
    Moon,
    QrCode,
    Server,
    ShieldCheck,
    ShieldQuestion,
    ShieldX,
    Sun,
    UserRound,
    Users,
    X,
} from 'lucide-react';

import { object, rows, text, date } from '@/lib/app';

import { Status, Metric, MetricGroup, Panel, PageHeading, Table, Cell, Empty, ActionForm } from '@/components/app/Primitives';

import { AppShell } from '@/components/app/AppShell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import type { Data } from '@/types/app';

export default function LiveRoster({ props }: { props: Data }) {
    usePoll(10000, { only: ['roster', 'session'] }, { mode: 'rest' });

    const session = object(props.session);
    const event = object(session.event);
    const roster = rows(props.roster);
    const sessionId = text(session.id);
    const challengeTtl = Number(props.ttl);
    const qrChallengeMode = text(session.qr_challenge_mode, 'check_in');
    const challengeLabel = qrChallengeMode === 'check_out' ? 'Check-out' : 'Check-in';
    const manualCandidates = roster.filter((entry) => entry.attendance_id === null);
    const [challengeToken, setChallengeToken] = useState<string | null>(null);
    const [remainingSeconds, setRemainingSeconds] = useState<number | null>(null);
    const [challengeError, setChallengeError] = useState('');
    const [challengeLoading, setChallengeLoading] = useState(true);

    useEffect(() => {
        if (session.status !== 'open'
            || session.attendance_ended === true
            || session.qr_challenge_enabled === false) {
            return;
        }

        const controller = new AbortController();
        let refreshTimer: number | undefined;
        let countdownTimer: number | undefined;
        let active = true;

        const scheduleRefresh = (delay: number) => {
            refreshTimer = window.setTimeout(() => {
                void loadChallenge();
            }, delay);
        };

        const loadChallenge = async () => {
            let challengeUnavailable = false;

            try {
                const response = await fetch(`/sessions/${sessionId}/qr/challenge`, {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                const result: unknown = await response.json();

                if (typeof result !== 'object' || result === null) {
                    throw new Error('The server returned an invalid QR challenge response.');
                }

                const payload = result as Record<string, unknown>;

                if (!response.ok) {
                    challengeUnavailable = response.status === 409;
                    throw new Error(typeof payload.message === 'string'
                        ? payload.message
                        : 'The live QR challenge could not be loaded.');
                }

                if (typeof payload.token !== 'string'
                    || typeof payload.ttl !== 'number'
                    || !Number.isInteger(payload.ttl)
                    || payload.ttl < 1) {
                    throw new Error('The server did not return a valid live QR challenge.');
                }

                if (!active) {
                    return;
                }

                challengeUnavailable = payload.ttl < challengeTtl;

                if (countdownTimer !== undefined) {
                    window.clearInterval(countdownTimer);
                }

                setChallengeToken(payload.token);
                setRemainingSeconds(payload.ttl);
                setChallengeError('');
                let seconds = payload.ttl;
                countdownTimer = window.setInterval(() => {
                    seconds = Math.max(0, seconds - 1);
                    setRemainingSeconds(seconds);

                    if (seconds === 0) {
                        setChallengeToken(null);

                        if (countdownTimer !== undefined) {
                            window.clearInterval(countdownTimer);
                        }
                    }
                }, 1000);

                if (!challengeUnavailable) {
                    scheduleRefresh(Math.max((payload.ttl - 5) * 1000, 5000));
                } else {
                    scheduleRefresh(payload.ttl * 1000);
                }
            } catch (error) {
                if (!active) {
                    return;
                }

                if (challengeUnavailable) {
                    setChallengeToken(null);
                    setRemainingSeconds(null);

                    if (countdownTimer !== undefined) {
                        window.clearInterval(countdownTimer);
                    }
                }

                setChallengeError(error instanceof Error
                    ? error.message
                    : 'The live QR challenge could not be loaded. Check your connection and try again.');

                if (!challengeUnavailable) {
                    scheduleRefresh(5000);
                }
            } finally {
                if (active) {
                    setChallengeLoading(false);
                }
            }
        };

        void loadChallenge();

        return () => {
            active = false;
            controller.abort();

            if (refreshTimer !== undefined) {
                window.clearTimeout(refreshTimer);
            }

            if (countdownTimer !== undefined) {
                window.clearInterval(countdownTimer);
            }
        };
    }, [challengeTtl, sessionId, session.attendance_ended, session.qr_challenge_enabled, session.status]);

    return (
        <AppShell title="Live attendance">
            <PageHeading
                eyebrow="Live attendance"
                title={text(event.title, 'Event roster')}
                description="The coordinator must manually open attendance for this session. Students scan the current code once to check in, then scan the latest code again when leaving to check out."
            />
            <MetricGroup columns={3}>
                <Metric label="Registered" value={roster.length} />
                <Metric label="Checked in" value={roster.filter((entry) => entry.status === 'checked_in' || entry.status === 'completed').length} />
                <Metric label="Session status" value={<Status value={session.status} />} />
            </MetricGroup>
            <Panel title={`${challengeLabel} QR challenge`}>
                <div className="grid justify-items-center gap-4 text-center">
                    {session.status === 'open' && session.attendance_ended !== true && (
                        <div className="grid justify-items-center gap-2">
                            <ActionForm
                                action={`/sessions/${sessionId}/qr/${session.qr_challenge_enabled === false ? 'resume' : 'pause'}`}
                                button={session.qr_challenge_enabled === false
                                    ? qrChallengeMode === 'check_out'
                                        ? 'Resume check-out QR challenge'
                                        : 'Start check-out QR challenge'
                                    : 'Stop QR challenge'}
                            />
                        </div>
                    )}
                    {session.attendance_ended === true ? (
                        <p className="text-sm text-muted-foreground" role="status">
                            This event or attendance session has ended. QR challenges are unavailable.
                        </p>
                    ) : session.status !== 'open' ? (
                        <p className="text-sm text-muted-foreground" role="status">
                            Attendance is not open. A coordinator must manually select Open attendance from the event sessions before a QR challenge is displayed.
                        </p>
                    ) : session.qr_challenge_enabled === false ? (
                        <p className="text-sm text-muted-foreground" role="status">
                            The QR challenge is stopped. Students cannot {challengeLabel.toLowerCase()} by QR until you resume it.
                        </p>
                    ) : challengeToken ? (
                        <>
                            <div className="rounded-xl border bg-white p-4 shadow-sm">
                                <QRCodeSVG
                                    value={challengeToken}
                                    size={256}
                                    level="M"
                                    title="Rotating attendance QR challenge"
                                />
                            </div>
                            <div className="flex items-center gap-2 text-sm text-muted-foreground" role="status" aria-live="polite">
                                <LoaderCircle className="size-4 animate-spin motion-reduce:animate-none" aria-hidden="true" />
                                <span className="tabular-nums">{remainingSeconds ?? 0}s</span>
                            </div>
                            {challengeError && (
                                <p className="text-sm text-destructive" role="alert">
                                    {challengeError} The current code will remain visible until it expires.
                                </p>
                            )}
                        </>
                    ) : (
                        <p
                            className={challengeError ? 'text-sm text-destructive' : 'text-sm text-muted-foreground'}
                            role={challengeError ? 'alert' : 'status'}
                        >
                            {challengeError || (challengeLoading
                                ? 'Loading the current QR challenge…'
                                : 'The QR challenge is refreshing…')}
                        </p>
                    )}
                </div>
            </Panel>
            <Panel title="Roster">
                {roster.length === 0 ? <Empty>No students are registered for this session.</Empty> : (
                    <Table headers={['Student', 'Attendance', 'Check-in', 'Check-out']}>
                        {roster.map((entry) => (
                            <TableRow key={text(entry.student_id)}>
                                <Cell>{text(entry.name)}</Cell>
                                <Cell><Status value={entry.status} /></Cell>
                                <Cell>{date(entry.check_in_at, { dateStyle: 'medium', timeStyle: 'short' })}</Cell>
                                <Cell>{date(entry.check_out_at, { dateStyle: 'medium', timeStyle: 'short' })}</Cell>
                            </TableRow>
                        ))}
                    </Table>
                )}
            </Panel>
            {Boolean(props.canManualAttend) && (
                <Panel title="Manual attendance entry">
                    {manualCandidates.length === 0 ? (
                        <Empty>All registered students already have attendance records.</Empty>
                    ) : (
                        <Form action={`/sessions/${sessionId}/attendance/manual`} method="post">
                            {({ errors, processing }) => (
                                <div className="grid gap-4">
                                    <p className="text-sm text-muted-foreground">
                                        Add attendance for a student who could not scan the QR code. A reason is required for audit purposes.
                                    </p>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="manual-student">Student</Label>
                                            <select
                                                id="manual-student"
                                                name="student_id"
                                                required
                                                defaultValue=""
                                                className="h-10 rounded-lg border border-input bg-background px-3 text-sm text-foreground"
                                            >
                                                <option value="" disabled>Select a student</option>
                                                {manualCandidates.map((entry) => (
                                                    <option key={text(entry.student_id)} value={text(entry.student_id)}>
                                                        {text(entry.name)}
                                                    </option>
                                                ))}
                                            </select>
                                            {errors.student_id && <p className="text-sm text-destructive">{errors.student_id}</p>}
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="manual-check-in">Check-in time</Label>
                                            <Input id="manual-check-in" name="check_in_at" type="datetime-local" required />
                                            {errors.check_in_at && <p className="text-sm text-destructive">{errors.check_in_at}</p>}
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="manual-check-out">Check-out time (optional)</Label>
                                            <Input id="manual-check-out" name="check_out_at" type="datetime-local" />
                                            {errors.check_out_at && <p className="text-sm text-destructive">{errors.check_out_at}</p>}
                                        </div>
                                        <div className="grid gap-2 sm:col-span-2">
                                            <Label htmlFor="manual-reason">Reason</Label>
                                            <Input
                                                id="manual-reason"
                                                name="reason"
                                                required
                                                minLength={10}
                                                maxLength={500}
                                                placeholder="Explain why manual attendance was necessary"
                                            />
                                            {errors.reason && <p className="text-sm text-destructive">{errors.reason}</p>}
                                        </div>
                                    </div>
                                    <Button type="submit" disabled={processing} className="w-fit">
                                        Record manual attendance
                                    </Button>
                                </div>
                            )}
                        </Form>
                    )}
                </Panel>
            )}
        </AppShell>
    );
}
