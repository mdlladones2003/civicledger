import { Form } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { TableRow } from '@/components/ui/table';
import { AppShell } from '@/components/app/AppShell';
import { Empty, PageHeading, Panel, Table, Cell } from '@/components/app/Primitives';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { object, rows, text } from '@/lib/app';
import type { Data } from '@/types/app';

type StudentOption = {
    id: number;
    name: string;
    email: string;
};

function isStudentOption(value: unknown): value is StudentOption {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    return 'id' in value
        && typeof value.id === 'number'
        && 'name' in value
        && typeof value.name === 'string'
        && 'email' in value
        && typeof value.email === 'string';
}

export default function SectionAssignments({ props }: { props: Data }) {
    const sections = rows(props.sections);
    const programs = rows(props.programs);
    const terms = rows(props.terms);
    const assignments = rows(props.assignments);
    const [studentSearch, setStudentSearch] = useState('');
    const [selectedStudent, setSelectedStudent] = useState<StudentOption | null>(null);
    const [studentMatches, setStudentMatches] = useState<StudentOption[]>([]);
    const [searchingStudents, setSearchingStudents] = useState(false);
    const [studentSearchError, setStudentSearchError] = useState('');

    useEffect(() => {
        const query = studentSearch.trim();

        if (selectedStudent || query.length < 2) {
            setStudentMatches([]);
            setSearchingStudents(false);
            setStudentSearchError('');

            return;
        }

        setStudentMatches([]);
        setSearchingStudents(true);
        const controller = new AbortController();
        const timeout = window.setTimeout(async () => {
            setStudentSearchError('');

            try {
                const response = await fetch(`/coordinator/students/search?q=${encodeURIComponent(query)}`, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error('Student search failed. Try again.');
                }

                const result: unknown = await response.json();

                if (
                    typeof result !== 'object'
                    || result === null
                    || !('students' in result)
                    || !Array.isArray(result.students)
                    || !result.students.every(isStudentOption)
                ) {
                    throw new Error('Student search returned an invalid response.');
                }

                setStudentMatches(result.students);
            } catch (error) {
                if (error instanceof Error && error.name === 'AbortError') {
                    return;
                }

                setStudentSearchError(error instanceof Error ? error.message : 'Student search failed. Try again.');
                setStudentMatches([]);
            } finally {
                if (!controller.signal.aborted) {
                    setSearchingStudents(false);
                }
            }
        }, 250);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [studentSearch, selectedStudent]);

    return (
        <AppShell title="Student section assignments">
            <PageHeading
                eyebrow="Coordinator"
                title="Student section assignments"
                description="Assign existing student accounts to sections in your coordinator scope."
            />
            <Panel title="Create a section">
                {programs.length === 0 || terms.length === 0 ? (
                    <Empty>Programs and terms must be created by an administrator before you can add a section.</Empty>
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
            <Panel title="Assign a student">
                {sections.length === 0 ? (
                    <Empty>Create a section above to add it to your section selection.</Empty>
                ) : (
                    <Form action="/coordinator/section-assignments" method="post">
                        {({ errors, processing }) => (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="relative grid content-start gap-2">
                                    <Label htmlFor="student-search">Find a student by name</Label>
                                    <Input
                                        id="student-search"
                                        type="search"
                                        role="combobox"
                                        aria-autocomplete="list"
                                        aria-expanded={studentMatches.length > 0}
                                        aria-controls="student-search-results"
                                        autoComplete="off"
                                        value={studentSearch}
                                        onChange={(event) => {
                                            setSelectedStudent(null);
                                            setStudentSearch(event.target.value);
                                        }}
                                        placeholder="Type a student's name"
                                        required
                                        aria-invalid={Boolean(errors.student_id || studentSearchError)}
                                        className="h-10 rounded-lg px-3 text-sm font-normal"
                                    />
                                    {studentMatches.length > 0 && (
                                        <ul id="student-search-results" role="listbox" className="absolute inset-x-0 top-full z-10 mt-1 max-h-60 overflow-y-auto rounded-lg border border-border bg-popover p-1 text-popover-foreground shadow-md">
                                            {studentMatches.map((student) => (
                                                <li key={student.id} role="option" aria-selected={selectedStudent?.id === student.id}>
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setSelectedStudent(student);
                                                            setStudentSearch(student.name);
                                                            setStudentMatches([]);
                                                        }}
                                                        className="grid w-full gap-0.5 rounded-md px-3 py-2 text-left text-sm hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                    >
                                                        <span className="font-medium">{student.name}</span>
                                                        <span className="text-muted-foreground">{student.email}</span>
                                                    </button>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                    {studentSearch.trim().length > 0 && studentSearch.trim().length < 2 && (
                                        <p className="text-sm text-muted-foreground">Enter at least two characters to search.</p>
                                    )}
                                    {searchingStudents && <p role="status" className="text-sm text-muted-foreground">Searching students...</p>}
                                    {!searchingStudents && studentSearch.trim().length >= 2 && studentMatches.length === 0 && !studentSearchError && !selectedStudent && (
                                        <p className="text-sm text-muted-foreground">No matching students found.</p>
                                    )}
                                    {(errors.student_id || studentSearchError) && (
                                        <p className="text-sm text-destructive">{errors.student_id ?? studentSearchError}</p>
                                    )}
                                    {selectedStudent && <input type="hidden" name="student_id" value={selectedStudent.id} />}
                                </div>
                                <div className="grid content-start gap-2">
                                    <Label htmlFor="section-id">Section</Label>
                                    <select
                                        id="section-id"
                                        name="section_id"
                                        required
                                        defaultValue=""
                                        aria-invalid={Boolean(errors.section_id)}
                                        className="h-10 rounded-lg border border-input bg-background px-3 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    >
                                        <option value="" disabled>Select a section</option>
                                        {sections.map((section) => (
                                            <option key={text(section.id)} value={text(section.id)}>
                                                {text(section.code)} · {text(object(section.program).code)} · {text(object(section.term).code)}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.section_id && <p className="text-sm text-destructive">{errors.section_id}</p>}
                                </div>
                                <Button type="submit" disabled={processing} className="w-fit sm:col-span-2">
                                    {processing ? 'Assigning student...' : 'Assign student'}
                                </Button>
                            </div>
                        )}
                    </Form>
                )}
            </Panel>
            <Panel title="Students in your sections">
                {assignments.length === 0 ? (
                    <Empty>No students are assigned to your sections yet.</Empty>
                ) : (
                    <Table headers={['Student', 'Email', 'Section', 'Program', 'Term']}>
                        {assignments.map((assignment) => {
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
            </Panel>
        </AppShell>
    );
}
