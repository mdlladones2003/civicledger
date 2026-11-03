import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

import { object, rows, text, date, displayEventTitle } from '@/lib/app';

import { Status, PageHeading, ButtonLink, Empty } from '@/components/app/Primitives';

import { AppShell } from '@/components/app/AppShell';

import type { Data } from '@/types/app';

export default function EventsIndex({ props }: { props: Data }) {
    const eventPage = object(props.events);
    const events = rows(props.events);
    const previousPage = typeof eventPage.prev_page_url === 'string' ? eventPage.prev_page_url : null;
    const nextPage = typeof eventPage.next_page_url === 'string' ? eventPage.next_page_url : null;

    return (
        <AppShell title="Events">
            <PageHeading
                eyebrow="Community service"
                title="Events"
                description={props.isAdmin || props.isSigner ? 'Browse all events across CivicLedger.' : 'Browse upcoming opportunities and past community events.'}
                action={props.canCreate ? <ButtonLink href="/events/create">Create event</ButtonLink> : undefined}
            />
            {events.length === 0 ? <Empty>{props.isAdmin || props.isSigner ? 'There are no events to show right now.' : 'There are no eligible events to show right now.'}</Empty> : (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {events.map((event) => (
                        <article key={text(event.id)} className="grid content-start gap-4 rounded-xl border border-border bg-card p-5">
                            <div className="flex items-start justify-between gap-3">
                                <h2 className="text-lg font-semibold text-foreground">{displayEventTitle(event.title)}</h2>
                                <Status value={event.status} />
                            </div>
                            {text(event.description, '').trim() !== '' && (
                                <p className="text-sm leading-6 text-muted-foreground">{text(event.description, '')}</p>
                            )}
                            <dl className="grid gap-2 text-sm text-muted-foreground">
                                <div><dt className="inline font-medium text-foreground">When: </dt><dd className="inline">{date(event.starts_at, { dateStyle: 'medium', timeStyle: 'short' })}</dd></div>
                                <div><dt className="inline font-medium text-foreground">Where: </dt><dd className="inline">{text(event.venue)}</dd></div>
                                <div><dt className="inline font-medium text-foreground">Section: </dt><dd className="inline">{text(object(event.section).code)}</dd></div>
                            </dl>
                            <Link className="flex w-fit items-center gap-2 font-semibold text-civic underline decoration-civic/40 underline-offset-4" href={`/events/${text(event.id)}`}>
                                View event details
                                <ArrowRight className="size-4" aria-hidden="true" />
                            </Link>
                        </article>
                    ))}
                </div>
            )}
            {(previousPage || nextPage) && (
                <nav className="flex items-center justify-between gap-4 border-t border-border pt-4" aria-label="Event pages">
                    {previousPage ? (
                        <Link className="font-semibold text-civic underline underline-offset-4" href={previousPage}>Previous</Link>
                    ) : <span className="text-sm text-muted-foreground">Previous</span>}
                    <span className="text-sm text-muted-foreground">Page {text(eventPage.current_page, '1')} of {text(eventPage.last_page, '1')}</span>
                    {nextPage ? (
                        <Link className="font-semibold text-civic underline underline-offset-4" href={nextPage}>Next</Link>
                    ) : <span className="text-sm text-muted-foreground">Next</span>}
                </nav>
            )}
        </AppShell>
    );
}
