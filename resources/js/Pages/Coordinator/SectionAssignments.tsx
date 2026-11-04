import { Form, Link } from '@inertiajs/react';
import { Tabs as TabsPrimitive } from 'radix-ui';
import { TableRow } from '@/components/ui/table';
import { AppShell } from '@/components/app/AppShell';
import { Empty, PageHeading, Panel, Table, Cell } from '@/components/app/Primitives';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { date, object, rows, text } from '@/lib/app';
import type { Data } from '@/types/app';

export default function SectionAssignments({ props }: { props: Data }) {
    const sections = rows(props.sections);
    const programs = rows(props.programs);
    const terms = rows(props.terms);
    const studentsPage = object(props.students);
    const students = rows(studentsPage);
    const previousPage = typeof studentsPage.prev_page_url === 'string' ? studentsPage.prev_page_url : null;
    const nextPage = typeof studentsPage.next_page_url === 'string' ? studentsPage.next_page_url : null;

    return (
        <AppShell title="Coordinator academic setup">
            <PageHeading
                eyebrow="Coordinator"
                title="Academic setup"
                description="Create programs, terms, and sections, then review students who selected your sections during registration."
            />
            <TabsPrimitive.Root defaultValue="programs" className="grid gap-4">
                <TabsPrimitive.List
                    aria-label="Coordinator management"
                    className="flex w-full gap-1 overflow-x-auto rounded-lg bg-muted p-1"
                >
                    <TabsPrimitive.Trigger
                        value="programs"
                        className="flex min-h-11 shrink-0 items-center gap-2 rounded-md px-4 text-sm text-muted-foreground outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring data-[state=active]:bg-background data-[state=active]:text-foreground"
                    >
                        Programs <span className="tabular-nums text-muted-foreground">{text(programs.length)}</span>
                    </TabsPrimitive.Trigger>
                    <TabsPrimitive.Trigger
                        value="terms"
                        className="flex min-h-11 shrink-0 items-center gap-2 rounded-md px-4 text-sm text-muted-foreground outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring data-[state=active]:bg-background data-[state=active]:text-foreground"
                    >
                        Terms <span className="tabular-nums text-muted-foreground">{text(terms.length)}</span>
                    </TabsPrimitive.Trigger>
                    <TabsPrimitive.Trigger
                        value="sections"
                        className="flex min-h-11 shrink-0 items-center gap-2 rounded-md px-4 text-sm text-muted-foreground outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring data-[state=active]:bg-background data-[state=active]:text-foreground"
                    >
                        Sections <span className="tabular-nums text-muted-foreground">{text(sections.length)}</span>
                    </TabsPrimitive.Trigger>
                    <TabsPrimitive.Trigger
                        value="students"
                        className="flex min-h-11 shrink-0 items-center gap-2 rounded-md px-4 text-sm text-muted-foreground outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring data-[state=active]:bg-background data-[state=active]:text-foreground"
                    >
                        Students
                    </TabsPrimitive.Trigger>
                </TabsPrimitive.List>

                <TabsPrimitive.Content value="programs" className="grid gap-4 outline-none focus-visible:ring-2 focus-visible:ring-ring">
                    <Panel title="Create a program">
                        <Form action="/coordinator/programs" method="post">
                            {({ errors, processing }) => (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid content-start gap-2">
                                        <Label htmlFor="program-code">Program code</Label>
                                        <Input
                                            id="program-code"
                                            name="code"
                                            required
                                            maxLength={30}
                                            aria-invalid={Boolean(errors.code)}
                                            className="h-10 rounded-lg px-3 text-sm font-normal"
                                        />
                                        {errors.code && <p className="text-sm text-destructive">{errors.code}</p>}
                                    </div>
                                    <div className="grid content-start gap-2">
                                        <Label htmlFor="program-name">Program name</Label>
                                        <Input
                                            id="program-name"
                                            name="name"
                                            required
                                            maxLength={150}
                                            aria-invalid={Boolean(errors.name)}
                                            className="h-10 rounded-lg px-3 text-sm font-normal"
                                        />
                                        {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                                    </div>
                                    <Button type="submit" disabled={processing} className="w-fit sm:col-span-2">
                                        {processing ? 'Creating program...' : 'Create program'}
                                    </Button>
                                </div>
                            )}
                        </Form>
                    </Panel>
                    <Panel title={`Programs · ${text(programs.length)}`}>
                        {programs.length === 0 ? (
                            <Empty>No programs yet. Create one to start organizing sections.</Empty>
                        ) : (
                            <Table headers={['Code', 'Program name']}>
                                {programs.map((program) => (
                                    <TableRow key={text(program.id)}>
                                        <Cell>{text(program.code)}</Cell>
                                        <Cell>{text(program.name)}</Cell>
                                    </TableRow>
                                ))}
                            </Table>
                        )}
                    </Panel>
                </TabsPrimitive.Content>

                <TabsPrimitive.Content value="terms" className="grid gap-4 outline-none focus-visible:ring-2 focus-visible:ring-ring">
                    <Panel title="Create a term">
                        <Form action="/coordinator/terms" method="post">
                            {({ errors, processing }) => (
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <div className="grid content-start gap-2">
                                        <Label htmlFor="term-code">Term code</Label>
                                        <Input
                                            id="term-code"
                                            name="code"
                                            required
                                            maxLength={30}
                                            aria-invalid={Boolean(errors.code)}
                                            className="h-10 rounded-lg px-3 text-sm font-normal"
                                        />
                                        {errors.code && <p className="text-sm text-destructive">{errors.code}</p>}
                                    </div>
                                    <div className="grid content-start gap-2">
                                        <Label htmlFor="term-starts-at">Start date</Label>
                                        <Input
                                            id="term-starts-at"
                                            name="starts_at"
                                            type="date"
                                            required
                                            aria-invalid={Boolean(errors.starts_at)}
                                            className="h-10 rounded-lg px-3 text-sm font-normal"
                                        />
                                        {errors.starts_at && <p className="text-sm text-destructive">{errors.starts_at}</p>}
                                    </div>
                                    <div className="grid content-start gap-2">
                                        <Label htmlFor="term-ends-at">End date</Label>
                                        <Input
                                            id="term-ends-at"
                                            name="ends_at"
                                            type="date"
                                            required
                                            aria-invalid={Boolean(errors.ends_at)}
                                            className="h-10 rounded-lg px-3 text-sm font-normal"
                                        />
                                        {errors.ends_at && <p className="text-sm text-destructive">{errors.ends_at}</p>}
                                    </div>
                                    <Button type="submit" disabled={processing} className="w-fit sm:col-span-2 lg:col-span-3">
                                        {processing ? 'Creating term...' : 'Create term'}
                                    </Button>
                                </div>
                            )}
                        </Form>
                    </Panel>
                    <Panel title={`Terms · ${text(terms.length)}`}>
                        {terms.length === 0 ? (
                            <Empty>No terms yet. Create a term to start organizing sections.</Empty>
                        ) : (
                            <Table headers={['Term', 'Starts', 'Ends']}>
                                {terms.map((term) => (
                                    <TableRow key={text(term.id)}>
                                        <Cell>{text(term.code)}</Cell>
                                        <Cell>{date(term.starts_at)}</Cell>
                                        <Cell>{date(term.ends_at)}</Cell>
                                    </TableRow>
                                ))}
                            </Table>
                        )}
                    </Panel>
                </TabsPrimitive.Content>

                <TabsPrimitive.Content value="sections" className="grid gap-4 outline-none focus-visible:ring-2 focus-visible:ring-ring">
                    <Panel title="Create a section">
                        {programs.length === 0 || terms.length === 0 ? (
                            <Empty>Create a program and a term above before adding a section.</Empty>
                        ) : (
                            <Form action="/coordinator/sections" method="post">
                                {({ errors, processing }) => (
                                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr]">
                                        <div className="grid content-start gap-2">
                                            <Label htmlFor="program-id">Program</Label>
                                            <select
                                                id="program-id"
                                                name="program_id"
                                                required
                                                defaultValue=""
                                                aria-invalid={Boolean(errors.program_id)}
                                                className="h-10 rounded-lg border border-input bg-background px-3 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            >
                                                <option value="" disabled>Select a program</option>
                                                {programs.map((program) => (
                                                    <option key={text(program.id)} value={text(program.id)}>
                                                        {text(program.code)} · {text(program.name)}
                                                    </option>
                                                ))}
                                            </select>
                                            {errors.program_id && <p className="text-sm text-destructive">{errors.program_id}</p>}
                                        </div>
                                        <div className="grid content-start gap-2">
                                            <Label htmlFor="term-id">Term</Label>
                                            <select
                                                id="term-id"
                                                name="term_id"
                                                required
                                                defaultValue=""
                                                aria-invalid={Boolean(errors.term_id)}
                                                className="h-10 rounded-lg border border-input bg-background px-3 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            >
                                                <option value="" disabled>Select a term</option>
                                                {terms.map((term) => (
                                                    <option key={text(term.id)} value={text(term.id)}>
                                                        {text(term.code)}
                                                    </option>
                                                ))}
                                            </select>
                                            {errors.term_id && <p className="text-sm text-destructive">{errors.term_id}</p>}
                                        </div>
                                        <div className="grid content-start gap-2">
                                            <Label htmlFor="section-code">Section name</Label>
                                            <Input
                                                id="section-code"
                                                name="code"
                                                required
                                                maxLength={30}
                                                aria-invalid={Boolean(errors.code)}
                                                className="h-10 rounded-lg px-3 text-sm font-normal"
                                            />
                                            {errors.code && <p className="text-sm text-destructive">{errors.code}</p>}
                                        </div>
                                        <Button type="submit" disabled={processing} className="w-fit sm:col-span-2 lg:col-span-3">
                                            {processing ? 'Creating section...' : 'Create section'}
                                        </Button>
                                    </div>
                                )}
                            </Form>
                        )}
                    </Panel>
                    <Panel title={`Sections · ${text(sections.length)}`}>
                        {sections.length === 0 ? (
                            <Empty>No sections in your coordinator scope yet.</Empty>
                        ) : (
                            <Table headers={['Section', 'Program', 'Term']}>
                                {sections.map((section) => (
                                    <TableRow key={text(section.id)}>
                                        <Cell>{text(section.code)}</Cell>
                                        <Cell>{text(object(section.program).code)}</Cell>
                                        <Cell>{text(object(section.term).code)}</Cell>
                                    </TableRow>
                                ))}
                            </Table>
                        )}
                    </Panel>
                </TabsPrimitive.Content>

                <TabsPrimitive.Content value="students" className="grid gap-4 outline-none focus-visible:ring-2 focus-visible:ring-ring">
                    <Panel title={`Students in your sections · ${text(studentsPage.total, String(students.length))}`}>
                        {students.length === 0 ? (
                            <Empty>No students have selected your sections during registration yet.</Empty>
                        ) : (
                            <Table headers={['Student', 'Email', 'Section', 'Program', 'Term']}>
                                {students.map((assignment) => {
                                    const student = object(assignment.user);
                                    const section = object(assignment.section);

                                    return (
                                        <TableRow key={text(assignment.id)}>
                                            <Cell>{[text(student.first_name, ''), text(student.middle_name, ''), text(student.last_name, '')].filter(Boolean).join(' ')}</Cell>
                                            <Cell>{text(student.email)}</Cell>
                                            <Cell>{text(section.code)}</Cell>
                                            <Cell>{text(object(section.program).code)}</Cell>
                                            <Cell>{text(object(section.term).code)}</Cell>
                                        </TableRow>
                                    );
                                })}
                            </Table>
                        )}
                        {(previousPage || nextPage) && (
                            <nav className="mt-4 flex items-center justify-between gap-4 border-t border-border pt-4" aria-label="Student assignment pages">
                                {previousPage ? (
                                    <Link className="font-semibold text-civic underline underline-offset-4" href={previousPage} preserveState>Previous</Link>
                                ) : <span className="text-sm text-muted-foreground">Previous</span>}
                                <span className="text-sm text-muted-foreground">Page {text(studentsPage.current_page, '1')} of {text(studentsPage.last_page, '1')}</span>
                                {nextPage ? (
                                    <Link className="font-semibold text-civic underline underline-offset-4" href={nextPage} preserveState>Next</Link>
                                ) : <span className="text-sm text-muted-foreground">Next</span>}
                            </nav>
                        )}
                    </Panel>
                </TabsPrimitive.Content>
            </TabsPrimitive.Root>
        </AppShell>
    );
}
