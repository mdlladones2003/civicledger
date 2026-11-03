import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Table as ShadcnTable,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

import { object, rows, text, date, person, displayEventTitle } from '@/lib/app';

import { Status, Panel, PageHeading, ActionForm, Table, Cell, Empty } from '@/components/app/Primitives';

import { AppShell } from '@/components/app/AppShell';

import type { Data } from '@/types/app';

export default function EventShow({ props }: { props: Data }) {
    const event = object(props.event);
    const registration = object(props.registration);
    const sessions = rows(event.sessions);
    const roster = rows(props.roster);

    return (
        <AppShell title={displayEventTitle(event.title)}>
            <PageHeading
                eyebrow="Event details"
                title={displayEventTitle(event.title)}
                description={text(event.description, 'Event information and participation details.')}
                action={props.canManage ? (
                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild variant="outline">
                            <Link href={`/events/${text(event.id)}/edit`}>Edit event</Link>
                        </Button>
                        {event.status === 'draft' && (
                            <ActionForm action={`/events/${text(event.id)}/publish`} button="Publish event" />
                        )}
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
                    {sessions.length === 0 ? <Empty>No sessions have been scheduled.</Empty> : (
                        <Table headers={['Session', 'Start', 'End', 'Status', 'Actions']}>
                            {sessions.map((session) => (
                                <TableRow key={text(session.id)}>
                                    <Cell>{text(session.id, 'Session')}</Cell>
                                    <Cell>{date(session.starts_at, { dateStyle: 'medium', timeStyle: 'short' })}</Cell>
                                    <Cell>{date(session.ends_at, { dateStyle: 'medium', timeStyle: 'short' })}</Cell>
                                    <Cell><Status value={session.status} /></Cell>
                                    <Cell><Link className="font-semibold text-civic underline" href={`/sessions/${text(session.id)}/qr`}>Open live roster</Link></Cell>
                                </TableRow>
                            ))}
                        </Table>
                    )}
                </Panel>
            )}
            {Boolean(props.canManage) && (
                <Panel title="Registered participants">
                    {roster.length === 0 ? <Empty>No registrations have been recorded.</Empty> : (
                        <Table headers={['Participant', 'Registration', '']}>
                            {roster.map((entry) => (
                                <TableRow key={text(entry.id)}>
                                    <Cell>{person(entry.student)}</Cell>
                                    <Cell><Status value={entry.status} /></Cell>
                                    <Cell>{date(entry.created_at)}</Cell>
                                </TableRow>
                            ))}
                        </Table>
                    )}
                </Panel>
            )}
        </AppShell>
    );
}
