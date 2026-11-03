import React, { useState, type ReactNode } from 'react';
import { Button, buttonVariants } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

import { object, rows, text } from '@/lib/app';

import { Panel, PageHeading, csrfToken, Field } from '@/components/app/Primitives';

import { AppShell } from '@/components/app/AppShell';

import type { Data } from '@/types/app';

export default function EventEditor({ props }: { props: Data }) {
    const event = object(props.event);
    const sections = rows(props.sections);
    const editing = Boolean(event.id);
    const [sectionId, setSectionId] = useState(text(event.section_id, 'program'));

    return (
        <AppShell title={editing ? 'Edit event' : 'Create event'}>
            <PageHeading eyebrow="Event management" title={editing ? 'Edit event' : 'Create an event'} description="Share clear details so students know how to take part." />
            <Panel title="Event information">
                <form action={editing ? `/events/${text(event.id)}` : '/events'} method="post" className="grid gap-4 md:grid-cols-2">
                    <input type="hidden" name="_token" value={csrfToken()} />
                    <input type="hidden" name="_method" value={editing ? 'put' : 'post'} />
                    <input type="hidden" name="program_id" value={text(event.program_id ?? object(sections[0]?.program).id, '')} />
                    <div className="md:col-span-2"><Field label="Event title" name="title" value={event.title} required /></div>
                    <Field label="Venue" name="venue" value={event.venue} required />
                    <Field label="Service component" name="component" value={event.component} required />
                    <Field label="Starts at" name="starts_at" type="datetime-local" value={event.starts_at} required />
                    <Field label="Ends at" name="ends_at" type="datetime-local" value={event.ends_at} required />
                    <Label className="grid gap-1.5 text-sm font-medium text-foreground md:col-span-2">
                        <span>Program / section</span>
                        <input type="hidden" name="section_id" value={sectionId === 'program' ? '' : sectionId} />
                        <Select value={sectionId} onValueChange={setSectionId}>
                            <SelectTrigger className="h-11 w-full rounded-xl px-3 text-base font-normal">
                                <SelectValue placeholder="Program-wide event" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="program">Program-wide event</SelectItem>
                                {sections.map((section) => (
                                    <SelectItem key={text(section.id)} value={text(section.id)}>
                                        {text(section.code)} · {text(object(section.program).name)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Label>
                    <Label className="grid gap-1.5 text-sm font-medium text-foreground md:col-span-2">
                        <span>Description</span>
                        <Textarea name="description" rows={4} defaultValue={text(event.description, '')} className="text-base font-normal" />
                    </Label>
                    <Button type="submit" size="lg" className="min-h-11 w-fit md:col-span-2">{editing ? 'Save changes' : 'Save draft'}</Button>
                </form>
            </Panel>
        </AppShell>
    );
}
