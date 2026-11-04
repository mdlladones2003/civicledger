import { useEffect, useState } from 'react';
import { Link, router, usePoll } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { TableRow } from '@/components/ui/table';

import { object, rows, text, date } from '@/lib/app';

import { ActionForm, Status, Metric, MetricGroup, Panel, PageHeading, Table, Cell, Empty } from '@/components/app/Primitives';

import { AppShell } from '@/components/app/AppShell';
import { AttendanceDialog } from '@/components/app/AttendanceDialog';

import type { Data } from '@/types/app';

/** A code is replaced this long before it expires, so a student who has just scanned it still has time to submit. */
const REFRESH_MARGIN_SECONDS = 5;
const RETRY_SECONDS = 10;

type Challenge = { token: string; ttl: number; receivedAt: number };

function LiveQrCode({ sessionId }: { sessionId: string }) {
    const [challenge, setChallenge] = useState<Challenge | null>(null);
    const [hasFailed, setHasFailed] = useState(false);
    const [retryCount, setRetryCount] = useState(0);
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        let isCurrent = true;
        let refreshTimer: number | undefined;

        async function load() {
            window.clearTimeout(refreshTimer);

            // Each request creates a challenge row, so a hidden tab waits and resumes when it is visible again.
            if (document.hidden) {
                return;
            }

            try {
                const response = await fetch(`/sessions/${sessionId}/qr/challenge`, {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { Accept: 'application/json' },
                });

                if (!isCurrent) {
                    return;
                }

                if (response.status === 409) {
                    router.reload({ only: ['session'] });

                    return;
                }

                if (!response.ok) {
                    throw new Error('The QR challenge could not be refreshed.');
                }

                const payload = await response.json() as { token: string; ttl: number };

                if (!isCurrent) {
                    return;
                }

                setChallenge({ token: payload.token, ttl: payload.ttl, receivedAt: Date.now() });
                setHasFailed(false);
                refreshTimer = window.setTimeout(load, Math.max(payload.ttl - REFRESH_MARGIN_SECONDS, REFRESH_MARGIN_SECONDS) * 1000);
            } catch {
                if (isCurrent) {
                    setHasFailed(true);
                    refreshTimer = window.setTimeout(load, RETRY_SECONDS * 1000);
                }
            }
        }

        function resumeWhenVisible() {
            if (!document.hidden) {
                load();
            }
        }

        load();
        document.addEventListener('visibilitychange', resumeWhenVisible);

        return () => {
            isCurrent = false;
            window.clearTimeout(refreshTimer);
            document.removeEventListener('visibilitychange', resumeWhenVisible);
        };
    }, [sessionId, retryCount]);

    useEffect(() => {
        const clock = window.setInterval(() => setNow(Date.now()), 1000);

        return () => window.clearInterval(clock);
    }, []);

    const elapsedSeconds = challenge ? (now - challenge.receivedAt) / 1000 : 0;
    const isExpired = challenge !== null && elapsedSeconds >= challenge.ttl;
    const refreshWindow = challenge ? Math.max(challenge.ttl - REFRESH_MARGIN_SECONDS, REFRESH_MARGIN_SECONDS) : 1;
    const secondsUntilRefresh = Math.max(0, Math.ceil(refreshWindow - elapsedSeconds));

    return (
        <div className="grid justify-items-center gap-4">
            {hasFailed && (
                <Alert variant="destructive" className="max-w-md">
                    <AlertTitle>Can’t refresh the code</AlertTitle>
                    <AlertDescription>
                        <p>Check your connection. Trying again automatically.</p>
                        <Button type="button" variant="outline" className="min-h-11" onClick={() => setRetryCount((count) => count + 1)}>Retry now</Button>
                    </AlertDescription>
                </Alert>
            )}
            <div className="w-full max-w-md rounded-xl bg-white p-3">
                {challenge && !isExpired ? (
                    <QRCodeSVG
                        value={challenge.token}
                        size={512}
                        level="L"
                        marginSize={4}
                        bgColor="#FFFFFF"
                        fgColor="#000000"
                        title="Attendance QR code"
                        role="img"
                        className="h-auto w-full"
                    />
                ) : (
                    <div className="aspect-square w-full animate-pulse rounded-lg bg-muted motion-reduce:animate-none" aria-label="Loading the QR code" role="img" />
                )}
            </div>
            {challenge && !isExpired && (
                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                    <svg viewBox="0 0 36 36" className="size-5 -rotate-90" aria-hidden="true">
                        <circle cx="18" cy="18" r="15" fill="none" strokeWidth="5" className="stroke-muted" />
                        <circle cx="18" cy="18" r="15" fill="none" strokeWidth="5" pathLength={100} strokeDasharray={`${(secondsUntilRefresh / refreshWindow) * 100} 100`} className="stroke-primary" />
                    </svg>
                    Refreshes in {secondsUntilRefresh}s
                </p>
            )}
        </div>
    );
}

function CloseSessionDialog({ sessionId }: { sessionId: string }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button type="button" variant="destructive" size="lg" className="min-h-11">Close session</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Close this session?</DialogTitle>
                    <DialogDescription>The QR code stops working and students can no longer scan. A closed session can’t be reopened, but you can still correct its attendance.</DialogDescription>
                </DialogHeader>
                <ActionForm action={`/sessions/${sessionId}/close`} button="Close session" variant="destructive" />
            </DialogContent>
        </Dialog>
    );
}

function RosterAction({ entry, sessionId }: { entry: Data; sessionId: string }) {
    const studentName = text(entry.name);

    if (entry.attendance_id) {
        return (
            <AttendanceDialog
                key={`${text(entry.attendance_id)}-${text(entry.check_in_at, '')}-${text(entry.check_out_at, '')}`}
                mode="correct"
                attendance={{ id: entry.attendance_id, check_in_at: entry.check_in_at, check_out_at: entry.check_out_at }}
                studentName={studentName}
            />
        );
    }

    return <AttendanceDialog mode="manual" sessionId={sessionId} studentId={entry.student_id} studentName={studentName} />;
}

function AttendanceFlags({ entry, isOpen }: { entry: Data; isOpen: boolean }) {
    return (
        <div className="flex flex-wrap gap-1.5">
            <Status value={entry.status} />
            {entry.source === 'manual' && <Badge variant="outline">Manual</Badge>}
            {!isOpen && Boolean(entry.check_in_at) && !entry.check_out_at && <Badge variant="destructive">Missing check-out</Badge>}
        </div>
    );
}

export default function LiveRoster({ props }: { props: Data }) {
    usePoll(10000, { only: ['roster'] }, { mode: 'rest' });

    const session = object(props.session);
    const event = object(session.event);
    const roster = rows(props.roster);
    const sessionId = text(session.id);
    const isOpen = session.status === 'open';
    const isScheduled = session.status === 'scheduled';
    const isEventPublished = event.status === 'published';

    return (
        <AppShell title="Live attendance">
            <PageHeading
                eyebrow="Live attendance"
                title={text(event.title, 'Event roster')}
                description={isOpen ? 'Students scan this code to check in, and again to check out. It refreshes automatically.' : undefined}
                action={(
                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild variant="outline" size="lg" className="min-h-11">
                            <Link href={`/events/${text(event.id)}`}>Back to event</Link>
                        </Button>
                        {isScheduled && isEventPublished && <ActionForm action={`/sessions/${sessionId}/open`} button="Open session" />}
                        {isOpen && <CloseSessionDialog sessionId={sessionId} />}
                    </div>
                )}
            />
            <Panel title="Attendance QR code">
                {isOpen ? <LiveQrCode sessionId={sessionId} /> : (
                    <Empty>
                        {!isScheduled
                            ? 'This session is closed, so its QR code is no longer available.'
                            : isEventPublished
                                ? 'Open this session to show the QR code.'
                                : event.status === 'draft'
                                    ? 'Publish the event before opening this session.'
                                    : 'This event has finished, so this session can’t be opened.'}
                    </Empty>
                )}
            </Panel>
            <MetricGroup columns={3}>
                <Metric label="Registered" value={roster.length} />
                <Metric label="Checked in" value={roster.filter((entry) => entry.status === 'checked_in' || entry.status === 'completed').length} />
                <Metric label="Session status" value={<Status value={session.status} />} />
            </MetricGroup>
            <Panel title="Roster">
                {roster.length === 0 ? <Empty>No students are registered for this session.</Empty> : (
                    <>
                        <ul role="list" className="divide-y md:hidden" aria-label="Roster">
                            {roster.map((entry) => (
                                <li key={text(entry.student_id)} className="grid gap-2 py-4 first:pt-0 last:pb-0">
                                    <p className="wrap-anywhere">{text(entry.name)}</p>
                                    <AttendanceFlags entry={entry} isOpen={isOpen} />
                                    <dl className="grid gap-1 text-muted-foreground">
                                        <div><dt className="inline font-medium text-foreground">Check-in: </dt><dd className="inline">{date(entry.check_in_at, { dateStyle: 'medium', timeStyle: 'short' })}</dd></div>
                                        <div><dt className="inline font-medium text-foreground">Check-out: </dt><dd className="inline">{date(entry.check_out_at, { dateStyle: 'medium', timeStyle: 'short' })}</dd></div>
                                    </dl>
                                    {!isScheduled && <RosterAction entry={entry} sessionId={sessionId} />}
                                </li>
                            ))}
                        </ul>
                        <div className="hidden md:block">
                            <Table headers={['Student', 'Attendance', 'Check-in', 'Check-out', 'Actions']}>
                                {roster.map((entry) => (
                                    <TableRow key={text(entry.student_id)} className="[&>td]:whitespace-normal">
                                        <Cell>{text(entry.name)}</Cell>
                                        <Cell><AttendanceFlags entry={entry} isOpen={isOpen} /></Cell>
                                        <Cell>{date(entry.check_in_at, { dateStyle: 'medium', timeStyle: 'short' })}</Cell>
                                        <Cell>{date(entry.check_out_at, { dateStyle: 'medium', timeStyle: 'short' })}</Cell>
                                        <Cell>{!isScheduled && <RosterAction entry={entry} sessionId={sessionId} />}</Cell>
                                    </TableRow>
                                ))}
                            </Table>
                        </div>
                    </>
                )}
            </Panel>
        </AppShell>
    );
}
