import type { FormEvent } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import {
    Table as ShadcnTable,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

import { object, rows, text, date, isoFromLocal, person, displayEventTitle } from '@/lib/app';

import { Status, Panel, PageHeading, PageNav, ActionForm, Table, Cell, Empty } from '@/components/app/Primitives';
import { AuthField } from '@/components/auth/AuthLayout';

import { AppShell } from '@/components/app/AppShell';

import type { Data } from '@/types/app';

function EventLifecycleDialog({ eventId, action }: { eventId: string; action: 'complete' | 'cancel' }) {
    const cancelling = action === 'cancel';

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button type="button" variant={cancelling ? 'destructive' : 'outline'} size="lg" className="min-h-11">
                    {cancelling ? 'Cancel event' : 'Mark as completed'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{cancelling ? 'Cancel this event?' : 'Mark this event as completed?'}</DialogTitle>
                    <DialogDescription>
                        {cancelling
                            ? 'Registration closes, open sessions close, and students see the event as cancelled. Recorded attendance is kept. This can’t be undone.'
                            : 'Open sessions close and the event can no longer be edited. Recorded attendance is kept. This can’t be undone.'}
                    </DialogDescription>
                </DialogHeader>
                <ActionForm
                    action={`/events/${eventId}/${action}`}
                    button={cancelling ? 'Cancel event' : 'Mark as completed'}
                    variant={cancelling ? 'destructive' : 'default'}
                />
            </DialogContent>
        </Dialog>
    );
}

function AddSessionForm({ eventId }: { eventId: string }) {
    const form = useForm({ starts_at: '', ends_at: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform((data) => ({
            starts_at: isoFromLocal(data.starts_at),
            ends_at: isoFromLocal(data.ends_at),
        }));
        form.post(`/events/${eventId}/sessions`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <form onSubmit={submit} className="mt-6 grid gap-4 border-t border-border pt-4">
            <p className="text-sm font-medium text-foreground">Add a session</p>
            <div className="grid items-start gap-4 sm:grid-cols-2">
                <AuthField
                    label="Starts at"
                    name="starts_at"
                    type="datetime-local"
                    value={form.data.starts_at}
                    onChange={(event) => form.setData('starts_at', event.target.value)}
                    error={form.errors.starts_at ?? null}
                />
                <AuthField
                    label="Ends at"
                    name="ends_at"
                    type="datetime-local"
                    value={form.data.ends_at}
                    onChange={(event) => form.setData('ends_at', event.target.value)}
                    error={form.errors.ends_at ?? null}
                />
            </div>
            <Button type="submit" variant="outline" size="lg" disabled={form.processing} className="min-h-11 w-full sm:w-fit">
                {form.processing ? 'Adding...' : 'Add session'}
            </Button>
        </form>
    );
}

function SessionActions({ session, isEventPublished }: { session: Data; isEventPublished: boolean }) {
    const sessionId = text(session.id);

    return (
        <div className="flex flex-wrap items-center gap-3">
            {session.status === 'scheduled' && isEventPublished && <ActionForm action={`/sessions/${sessionId}/open`} button="Open session" />}
            <Link className="flex min-h-11 items-center font-semibold text-civic underline underline-offset-4" href={`/sessions/${sessionId}/qr`}>
                {session.status === 'open' ? 'Show QR and roster' : 'View roster'}
            </Link>
        </div>
    );
}

export default function EventShow({ props }: { props: Data }) {
    const event = object(props.event);
    const eventId = text(event.id);
    const registration = object(props.registration);
    const sessions = rows(event.sessions);
    const roster = rows(props.roster);
    const isDraft = event.status === 'draft';
    const isPublished = event.status === 'published';
    const isFinished = !isDraft && !isPublished;

    return (
        <AppShell title={displayEventTitle(event.title)}>
            <PageHeading
                eyebrow="Event details"
                title={displayEventTitle(event.title)}
                description={text(event.description, 'Event information and participation details.')}
                action={props.canManage ? (
                    <div className="flex flex-wrap items-center gap-2">
                        {!isFinished && (
                            <Button asChild variant="outline" size="lg" className="min-h-11">
                                <Link href={`/events/${eventId}/edit`}>Edit event</Link>
                            </Button>
                        )}
                        {isDraft && <ActionForm action={`/events/${eventId}/publish`} button="Publish event" />}
                        {isPublished && <EventLifecycleDialog eventId={eventId} action="complete" />}
                        {!isFinished && <EventLifecycleDialog eventId={eventId} action="cancel" />}
                    </div>
                ) : undefined}
            />
            <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
                <Panel title="About this event">
                    <dl className="grid gap-4 sm:grid-cols-2">
                        <div><dt className="text-sm text-muted-foreground">Date and time</dt><dd className="mt-1">{date(event.starts_at, { dateStyle: 'full', timeStyle: 'short' })}</dd></div>
                        <div><dt className="text-sm text-muted-foreground">Venue</dt><dd className="mt-1">{text(event.venue)}</dd></div>
                        <div><dt className="text-sm text-muted-foreground">Program</dt><dd className="mt-1">{text(object(event.program).name)}</dd></div>
                        <div><dt className="text-sm text-muted-foreground">Status</dt><dd className="mt-1"><Status value={event.status} /></dd></div>
                    </dl>
                </Panel>
                {!Boolean(props.canManage) && Boolean(props.canRegister) && (
                    <Panel title="Your registration">
                        {registration.status === 'registered' ? (
                            <div className="grid gap-3">
                                <p className="text-sm text-muted-foreground">You are registered for this event.</p>
                                <ActionForm action={`/events/${text(event.id)}/register`} method="delete" button="Cancel registration" />
                            </div>
                        ) : (
                            <ActionForm action={`/events/${text(event.id)}/register`} button="Register for event" />
                        )}
                    </Panel>
                )}
            </div>
            {Boolean(props.canManage) && (
                <Panel title="Event sessions">
                    {isDraft && <p className="mb-4 text-sm leading-6 text-muted-foreground">Publish this event to open its sessions for attendance.</p>}
                    {sessions.length === 0 ? <Empty>No sessions have been scheduled.</Empty> : (
                        <>
                            <ul role="list" className="divide-y md:hidden" aria-label="Event sessions">
                                {sessions.map((session, index) => (
                                    <li key={text(session.id)} className="grid gap-2 py-4 first:pt-0 last:pb-0">
                                        <div className="flex items-start justify-between gap-3">
                                            <p className="pt-1">Session {index + 1}</p>
                                            <Status value={session.status} />
                                        </div>
                                        <dl className="grid gap-1 text-muted-foreground">
                                            <div><dt className="inline font-medium text-foreground">Start: </dt><dd className="inline">{date(session.starts_at, { dateStyle: 'medium', timeStyle: 'short' })}</dd></div>
                                            <div><dt className="inline font-medium text-foreground">End: </dt><dd className="inline">{date(session.ends_at, { dateStyle: 'medium', timeStyle: 'short' })}</dd></div>
                                        </dl>
                                        <SessionActions session={session} isEventPublished={isPublished} />
                                    </li>
                                ))}
                            </ul>
                            <div className="hidden md:block">
                                <Table headers={['Session', 'Start', 'End', 'Status', 'Actions']}>
                                    {sessions.map((session, index) => (
                                        <TableRow key={text(session.id)} className="[&>td]:whitespace-normal">
                                            <Cell>Session {index + 1}</Cell>
                                            <Cell>{date(session.starts_at, { dateStyle: 'medium', timeStyle: 'short' })}</Cell>
                                            <Cell>{date(session.ends_at, { dateStyle: 'medium', timeStyle: 'short' })}</Cell>
                                            <Cell><Status value={session.status} /></Cell>
                                            <Cell><SessionActions session={session} isEventPublished={isPublished} /></Cell>
                                        </TableRow>
                                    ))}
                                </Table>
                            </div>
                        </>
                    )}
                    {!isFinished && <AddSessionForm eventId={eventId} />}
                </Panel>
            )}
            {Boolean(props.canManage) && (
                <Panel title="Registered participants">
                    {roster.length === 0 ? <Empty>No registrations have been recorded.</Empty> : (
                        <>
                            <ul role="list" className="divide-y md:hidden" aria-label="Registered participants">
                                {roster.map((entry) => (
                                    <li key={text(entry.id)} className="grid gap-2 py-4 first:pt-0 last:pb-0">
                                        <div className="flex items-start justify-between gap-3">
                                            <p className="min-w-0 pt-1 wrap-anywhere">{person(entry.student)}</p>
                                            <Status value={entry.status} />
                                        </div>
                                        <p className="text-muted-foreground">Registered {date(entry.created_at)}</p>
                                    </li>
                                ))}
                            </ul>
                            <div className="hidden md:block">
                                <Table headers={['Participant', 'Registration', 'Registered on']}>
                                    {roster.map((entry) => (
                                        <TableRow key={text(entry.id)} className="[&>td]:whitespace-normal">
                                            <Cell>{person(entry.student)}</Cell>
                                            <Cell><Status value={entry.status} /></Cell>
                                            <Cell>{date(entry.created_at)}</Cell>
                                        </TableRow>
                                    ))}
                                </Table>
                            </div>
                        </>
                    )}
                    <PageNav page={object(props.roster)} label="Participant pages" />
                </Panel>
            )}
        </AppShell>
    );
}
