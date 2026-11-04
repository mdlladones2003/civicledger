import { Link } from '@inertiajs/react';

import {
    Table as ShadcnTable,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

import { object, rows, text, hours, person } from '@/lib/app';

import { Status, Panel, PageHeading, ActionForm, Table, Cell, Empty } from '@/components/app/Primitives';

import { AppShell } from '@/components/app/AppShell';

import type { Data } from '@/types/app';

export default function Certificates({ props, signer, admin = false, coordinator = false }: { props: Data; signer: boolean; admin?: boolean; coordinator?: boolean }) {
    const certificatePage = object(signer ? props.issued : props.certificates);
    const certificates = rows(signer ? props.issued : props.certificates);
    const candidates = rows(props.candidates);
    const previousPage = typeof certificatePage.prev_page_url === 'string' ? certificatePage.prev_page_url : null;
    const nextPage = typeof certificatePage.next_page_url === 'string' ? certificatePage.next_page_url : null;
    const currentPage = text(certificatePage.current_page, '1');
    const lastPage = text(certificatePage.last_page, '1');

    return (
        <AppShell title="Certificates">
            <PageHeading eyebrow="Service recognition" title={admin ? 'All certificates' : signer ? 'Certificate issuance' : 'Certificates'} description="Certificate validity and integrity proof are reported as separate statuses." />
            {signer && (
                <Panel title="Eligible candidates">
                    {candidates.length === 0 ? <Empty>No eligible students are ready for a certificate.</Empty> : (
                        <Table headers={['Student', 'Term', 'Approved hours', '']}>
                            {candidates.map((candidate) => (
                                <TableRow key={`${text(candidate.student_id)}-${text(candidate.term_id)}`}>
                                    <Cell>{text(candidate.student_name)}</Cell>
                                    <Cell>{text(candidate.term_code)}</Cell>
                                    <Cell>{hours(candidate.approved_minutes)}</Cell>
                                    <Cell>
                                        <ActionForm action="/certificates" button="Issue certificate">
                                            <input type="hidden" name="student_id" value={text(candidate.student_id)} />
                                            <input type="hidden" name="term_id" value={text(candidate.term_id)} />
                                        </ActionForm>
                                    </Cell>
                                </TableRow>
                            ))}
                        </Table>
                    )}
                </Panel>
            )}
            <Panel title={admin || signer || coordinator ? 'Issued certificates' : 'Your certificates'}>
                {certificates.length === 0 ? <Empty>No certificates have been issued yet.</Empty> : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {certificates.map((certificate) => (
                            <article key={text(certificate.id)} className="grid gap-3 rounded-xl border border-border bg-card p-4">
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <h2 className="font-semibold text-foreground">{text(certificate.certificate_number, 'Service certificate')}</h2>
                                    <Status value={certificate.status} />
                                </div>
                                {(signer || admin || coordinator) && <p className="text-sm text-muted-foreground">{person(certificate.student)}</p>}
                                <p className="text-sm text-muted-foreground">{text(object(certificate.term).code)} · {hours(certificate.approved_minutes)} approved</p>
                                {Boolean(certificate.public_url) && (
                                    <a className="font-semibold text-civic underline" href={text(certificate.public_url)} target="_blank" rel="noreferrer">Open public verification</a>
                                )}
                                {Boolean(certificate.proof) && (
                                    <p className="text-sm text-muted-foreground">Proof: <Status value={object(certificate.proof).proof_status} /> · Validity: <Status value={object(certificate.proof).validity_status} /></p>
                                )}
                            </article>
                        ))}
                    </div>
                )}
                {(admin || signer || coordinator) && (previousPage || nextPage) && (
                    <nav className="mt-6 flex items-center justify-between gap-4 border-t border-border pt-4" aria-label="Certificate pages">
                        {previousPage ? (
                            <Link className="font-semibold text-civic underline underline-offset-4" href={previousPage}>Previous</Link>
                        ) : <span className="text-sm text-muted-foreground">Previous</span>}
                        <span className="text-sm text-muted-foreground">Page {currentPage} of {lastPage}</span>
                        {nextPage ? (
                            <Link className="font-semibold text-civic underline underline-offset-4" href={nextPage}>Next</Link>
                        ) : <span className="text-sm text-muted-foreground">Next</span>}
                    </nav>
                )}
            </Panel>
        </AppShell>
    );
}
