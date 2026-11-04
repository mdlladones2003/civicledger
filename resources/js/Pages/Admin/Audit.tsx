import { Form, Link } from '@inertiajs/react';

import { TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import { object, rows, text, date, person, statusLabel } from '@/lib/app';

import { Panel, PageHeading, PageNav, NativeSelect, Table, Cell, Empty } from '@/components/app/Primitives';

import { AppShell } from '@/components/app/AppShell';

import type { Data } from '@/types/app';

function actionLabel(action: unknown): string {
    return statusLabel(text(action).replaceAll('.', ' '));
}

function recordLabel(log: Data): string {
    return `${statusLabel(text(log.entity_type))} #${text(log.entity_id)}`;
}

function changeValue(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (Array.isArray(value)) {
        return value.length > 0 ? value.map(changeValue).join(', ') : '—';
    }

    return typeof value === 'object' ? JSON.stringify(value) : String(value);
}

function changedFields(log: Data): { field: string; before: unknown; after: unknown }[] {
    const before = object(log.before_json);
    const after = object(log.after_json);

    return [...new Set([...Object.keys(before), ...Object.keys(after)])]
        .filter((field) => JSON.stringify(before[field]) !== JSON.stringify(after[field]))
        .map((field) => ({ field, before: before[field], after: after[field] }));
}

function AuditChanges({ log }: { log: Data }) {
    const changes = changedFields(log);

    if (changes.length === 0) {
        return <span className="text-muted-foreground">No field changes</span>;
    }

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button type="button" variant="outline" className="min-h-11">View changes</Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{actionLabel(log.action)}</DialogTitle>
                    <DialogDescription>
                        {recordLabel(log)} · {person(log.actor)} · {date(log.created_at, { dateStyle: 'medium', timeStyle: 'short' })}
                    </DialogDescription>
                </DialogHeader>
                <dl className="grid gap-4 text-sm">
                    {changes.map((change) => (
                        <div key={change.field} className="grid gap-2 border-t border-border pt-3">
                            <dt className="text-foreground">{statusLabel(change.field)}</dt>
                            <dd className="grid gap-3 sm:grid-cols-2">
                                <div className="min-w-0">
                                    <p className="text-xs text-muted-foreground">Before</p>
                                    <p className="wrap-anywhere">{changeValue(change.before)}</p>
                                </div>
                                <div className="min-w-0">
                                    <p className="text-xs text-muted-foreground">After</p>
                                    <p className="wrap-anywhere">{changeValue(change.after)}</p>
                                </div>
                            </dd>
                        </div>
                    ))}
                </dl>
            </DialogContent>
        </Dialog>
    );
}

export default function Audit({ props }: { props: Data }) {
    const logs = rows(props.logs);
    const logPage = object(props.logs);
    const filters = object(props.filters);
    const actors = rows(props.actors);
    const actions = Array.isArray(props.actions) ? props.actions.map((action) => text(action)) : [];
    const hasFilters = Object.values(filters).some((value) => value !== null && value !== '');

    return (
        <AppShell title="Audit log">
            <PageHeading eyebrow="Administration" title="Audit log" description="A read-only record of consequential actions." />
            <Panel title="Filter activity">
                <Form action="/admin/audit" method="get" className="grid gap-4">
                    {({ processing }) => (
                        <>
                            <div className="grid items-start gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="actor" className="text-sm font-medium text-foreground">Actor</Label>
                                    <NativeSelect id="actor" name="actor" defaultValue={text(filters.actor, '')}>
                                        <option value="">Anyone</option>
                                        {actors.map((actor) => <option key={text(actor.id)} value={text(actor.id)}>{person(actor)}</option>)}
                                    </NativeSelect>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="action" className="text-sm font-medium text-foreground">Action</Label>
                                    <NativeSelect id="action" name="action" defaultValue={text(filters.action, '')}>
                                        <option value="">Any action</option>
                                        {actions.map((action) => <option key={action} value={action}>{actionLabel(action)}</option>)}
                                    </NativeSelect>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="from" className="text-sm font-medium text-foreground">From</Label>
                                    <Input id="from" name="from" type="date" defaultValue={text(filters.from, '')} className="h-10 rounded-lg px-3 text-sm font-normal" />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="to" className="text-sm font-medium text-foreground">To</Label>
                                    <Input id="to" name="to" type="date" defaultValue={text(filters.to, '')} className="h-10 rounded-lg px-3 text-sm font-normal" />
                                </div>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <Button type="submit" size="lg" disabled={processing} className="min-h-11 w-full sm:w-fit">Apply filters</Button>
                                {hasFilters && (
                                    <Button asChild variant="ghost" size="lg" className="min-h-11 w-full sm:w-fit">
                                        <Link href="/admin/audit">Clear filters</Link>
                                    </Button>
                                )}
                            </div>
                        </>
                    )}
                </Form>
            </Panel>
            <Panel title="Recent activity">
                {logs.length === 0 ? <Empty>{hasFilters ? 'No audit events match these filters.' : 'No audit events found.'}</Empty> : (
                    <>
                        <ul role="list" className="divide-y md:hidden" aria-label="Recent activity">
                            {logs.map((log) => (
                                <li key={text(log.id)} className="grid gap-2 py-4 first:pt-0 last:pb-0">
                                    <Badge variant="outline" className="h-auto whitespace-normal">{actionLabel(log.action)}</Badge>
                                    <dl className="grid gap-1 text-muted-foreground">
                                        <div><dt className="inline font-medium text-foreground">When: </dt><dd className="inline">{date(log.created_at, { dateStyle: 'medium', timeStyle: 'short' })}</dd></div>
                                        <div><dt className="inline font-medium text-foreground">Actor: </dt><dd className="inline">{person(log.actor)}</dd></div>
                                        <div><dt className="inline font-medium text-foreground">Record: </dt><dd className="inline wrap-anywhere">{recordLabel(log)}</dd></div>
                                    </dl>
                                    <AuditChanges log={log} />
                                </li>
                            ))}
                        </ul>
                        <div className="hidden md:block">
                            <Table headers={['When', 'Actor', 'Action', 'Record', 'Changes']}>
                                {logs.map((log) => (
                                    <TableRow key={text(log.id)} className="[&>td]:whitespace-normal">
                                        <Cell>{date(log.created_at, { dateStyle: 'medium', timeStyle: 'short' })}</Cell>
                                        <Cell>{person(log.actor)}</Cell>
                                        <Cell><Badge variant="outline" className="h-auto whitespace-normal">{actionLabel(log.action)}</Badge></Cell>
                                        <Cell>{recordLabel(log)}</Cell>
                                        <Cell><AuditChanges log={log} /></Cell>
                                    </TableRow>
                                ))}
                            </Table>
                        </div>
                    </>
                )}
                <PageNav page={logPage} label="Audit log pages" />
            </Panel>
        </AppShell>
    );
}
