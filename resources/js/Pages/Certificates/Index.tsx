import { Link } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Table as ShadcnTable,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';

import { object, rows, text, hours, person } from '@/lib/app';

import { Status, Panel, PageHeading, ActionForm, Table, Cell, Empty } from '@/components/app/Primitives';

import { AppShell } from '@/components/app/AppShell';

import type { Data } from '@/types/app';

function CertificateActionDialog({ certificate, action }: { certificate: Data; action: 'revoke' | 'supersede' }) {
    const number = text(certificate.certificate_number);
    const revoking = action === 'revoke';

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button type="button" variant={revoking ? 'destructive' : 'outline'} className="min-h-11">
                    {revoking ? 'Revoke' : 'Supersede'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{revoking ? `Revoke certificate ${number}?` : `Supersede certificate ${number}?`}</DialogTitle>
                    <DialogDescription>
                        {revoking
                            ? 'It will no longer verify as valid. This action is recorded and can’t be undone.'
                            : 'A replacement is issued from the student’s current approved hours, and this certificate will show as superseded. This action is recorded and can’t be undone.'}
                    </DialogDescription>
                </DialogHeader>
                <ActionForm
                    action={`/certificates/${text(certificate.id)}/${action}`}
                    button={revoking ? 'Revoke certificate' : 'Issue replacement'}
                    variant={revoking ? 'destructive' : 'default'}
                >
                    <Label className="grid gap-1.5 text-sm font-medium text-foreground">
                        <span>Reason</span>
                        <Textarea name="reason" minLength={10} maxLength={500} rows={3} required />
                    </Label>
                </ActionForm>
            </DialogContent>
        </Dialog>
    );
}

export default function Certificates({ props, signer, admin = false }: { props: Data; signer: boolean; admin?: boolean }) {
    const certificatePage = object(signer ? props.issued : props.certificates);
    const certificates = rows(signer ? props.issued : props.certificates);
    const candidates = rows(props.candidates);
    const previousPage = typeof certificatePage.prev_page_url === 'string' ? certificatePage.prev_page_url : null;
    const nextPage = typeof certificatePage.next_page_url === 'string' ? certificatePage.next_page_url : null;
    const currentPage = text(certificatePage.current_page, '1');
    const lastPage = text(certificatePage.last_page, '1');

    return (
        <AppShell title="Certificates">
            <PageHeading eyebrow="Service recognition" title={admin ? 'All certificates' : signer ? 'Certificate issuance' : 'My certificates'} description="Certificate validity and integrity proof are reported as separate statuses." />
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
            <Panel title={admin ? 'Issued certificates' : signer ? 'Issued certificates' : 'Your certificates'}>
                {certificates.length === 0 ? <Empty>No certificates have been issued yet.</Empty> : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {certificates.map((certificate) => (
                            <article key={text(certificate.id)} className="grid gap-3 rounded-xl border border-border bg-card p-4">
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <h2 className="font-semibold text-foreground">{text(certificate.certificate_number, 'Service certificate')}</h2>
                                    <Status value={certificate.status} />
                                </div>
                                <p className="text-sm text-muted-foreground">Supplementary record, not an official completion record unless adopted by the school.</p>
                                {(signer || admin) && <p className="text-sm text-muted-foreground">{person(certificate.student)}</p>}
                                <p className="text-sm text-muted-foreground">{text(object(certificate.term).code)} · {hours(certificate.approved_minutes)} approved</p>
                                {Boolean(certificate.public_url) && (
                                    <a className="font-semibold text-civic underline" href={text(certificate.public_url)} target="_blank" rel="noreferrer">Open public verification</a>
                                )}
                                {Boolean(certificate.proof) && (
                                    <p className="text-sm text-muted-foreground">Proof: <Status value={object(certificate.proof).proof_status} /> · Validity: <Status value={object(certificate.proof).validity_status} /></p>
                                )}
                                {signer && Boolean(props.canRevoke) && certificate.status === 'issued' && (
                                    <div className="flex flex-wrap gap-2">
                                        <CertificateActionDialog certificate={certificate} action="supersede" />
                                        <CertificateActionDialog certificate={certificate} action="revoke" />
                                    </div>
                                )}
                            </article>
                        ))}
                    </div>
                )}
                {(admin || signer) && (previousPage || nextPage) && (
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
