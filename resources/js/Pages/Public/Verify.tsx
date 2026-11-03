import { Head, Link } from '@inertiajs/react';
import { Fingerprint, Info, Server, ShieldCheck, ShieldQuestion, ShieldX, X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { BrandMark } from '@/components/app/BrandMark';
import { Status } from '@/components/app/Primitives';
import { ThemeToggle } from '@/components/app/ThemeToggle';
import { date, hours, object, statusLabel, text } from '@/lib/app';
import type { Data } from '@/types/app';

export default function PublicVerify({ props }: { props: Data }) {
    const certificate = object(props.certificate);
    const evidence = object(props.evidence);
    const state = text(props.state, 'not_found');
    const verified = state === 'verified' || state === 'valid';
    const notFound = state === 'not_found';
    const failed = ['mismatch', 'revoked', 'failed'].includes(state);
    const stateText = notFound ? 'Certificate not found'
        : verified ? 'Record integrity confirmed'
            : state === 'pending' ? 'Proof is still pending'
                : state === 'unavailable' ? 'Verification is temporarily unavailable'
                    : state === 'revoked' ? 'Certificate revoked'
                        : state === 'superseded' ? 'Certificate superseded'
                            : state === 'mismatch' ? 'Record does not match its proof'
                                : `Verification status: ${statusLabel(state)}`;
    const stateMessage = verified
        ? ''
        : state === 'pending'
            ? 'The proof has not been confirmed yet. Check again later; a pending result is not a successful verification.'
            : state === 'unavailable'
                ? 'The verification service could not confirm the proof right now. Try again later; this is not a verification result.'
                : 'This result does not confirm record integrity or physical participation. Certificates are supplementary and do not replace official school records.';
    const StateIcon = notFound ? ShieldQuestion
        : verified ? ShieldCheck
            : state === 'mismatch' ? ShieldX
                : state === 'unavailable' ? Info
                    : state === 'revoked' ? X
                        : Fingerprint;

    return (
        <div className="flex min-h-screen flex-col bg-background">
            <Head title="Certificate verification" />
            <header className="sticky top-0 z-30 border-b bg-background">
                <div className="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6">
                    <Link className="flex items-center gap-3 text-lg font-semibold" href="/"><BrandMark />CivicLedger</Link>
                    <Badge className="ml-2 hidden sm:inline-flex" variant="outline">Public verification</Badge>
                    <div className="ml-auto flex items-center gap-2">
                        <ThemeToggle />
                        <Button asChild size="sm" variant="outline">
                            <Link href="/login">Staff sign in</Link>
                        </Button>
                    </div>
                </div>
            </header>
            <main className="mx-auto grid w-full max-w-3xl flex-1 content-start gap-5 px-4 py-8 sm:px-6 sm:py-12">
                <Card aria-live="polite">
                    <CardHeader className="items-center border-b bg-muted/30 px-6 py-8 text-center">
                        <span className={`mb-2 flex size-14 items-center justify-center rounded-full border ${verified ? 'border-status-success-border bg-status-success text-status-success-foreground' : failed ? 'border-status-danger-border bg-status-danger text-status-danger-foreground' : state === 'pending' || state === 'unavailable' ? 'border-status-pending-border bg-status-pending text-status-pending-foreground' : 'border-status-neutral-border bg-status-neutral text-status-neutral-foreground'}`}>
                            <StateIcon className="size-7" />
                        </span>
                        <p className="text-sm text-muted-foreground">Verification result</p>
                        <CardTitle className="text-2xl">{stateText}</CardTitle>
                        <Status value={verified ? 'verified' : state} />
                        {(notFound || stateMessage) && (
                            <p className="max-w-xl text-sm leading-6 text-muted-foreground">
                                {notFound ? 'We could not find a certificate matching this link.' : stateMessage}
                            </p>
                        )}
                    </CardHeader>
                    {!notFound && (
                        <CardContent className="p-6">
                            <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-[minmax(120px,0.7fr)_minmax(0,1.3fr)]">
                                <dt className="text-sm text-muted-foreground">Certificate</dt><dd className="break-all text-sm">{text(certificate.number)}</dd>
                                <dt className="text-sm text-muted-foreground">Holder</dt><dd className="text-sm">{text(certificate.holder)}</dd>
                                <dt className="text-sm text-muted-foreground">Program term</dt><dd className="text-sm">{text(certificate.term)}</dd>
                                <dt className="text-sm text-muted-foreground">Approved service</dt><dd className="text-sm">{hours(Number(certificate.hours) * 60)}</dd>
                                <dt className="text-sm text-muted-foreground">Issued</dt><dd className="text-sm">{date(certificate.issued_at)}</dd>
                                <dt className="text-sm text-muted-foreground">Validity</dt><dd><Status value={certificate.status} /></dd>
                                {Boolean(certificate.revoked_at) && (
                                    <><dt className="text-sm text-muted-foreground">Revoked</dt><dd className="text-sm">{date(certificate.revoked_at)}</dd></>
                                )}
                            </dl>
                        </CardContent>
                    )}
                </Card>

                {Object.keys(evidence).length > 0 && (
                    <Card>
                        <details>
                            <summary className="flex cursor-pointer list-none items-center gap-3 px-6 py-4 font-medium marker:content-none">
                                <Server className="size-4 text-muted-foreground" />
                                <span>Technical evidence</span>
                                <span className="ml-auto text-xs font-normal text-muted-foreground">network · contract · transaction</span>
                            </summary>
                            <CardContent className="border-t p-6">
                                <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-[minmax(120px,0.7fr)_minmax(0,1.3fr)]">
                                    <dt className="text-sm text-muted-foreground">Proof</dt><dd><Status value={evidence.proof_status} /></dd>
                                    <dt className="text-sm text-muted-foreground">Validity</dt><dd><Status value={evidence.validity_status} /></dd>
                                    <dt className="text-sm text-muted-foreground">Network</dt><dd className="text-sm">{text(evidence.chain_id)}</dd>
                                    <dt className="text-sm text-muted-foreground">Contract</dt><dd className="break-all text-sm">{text(evidence.contract_address)}</dd>
                                    <dt className="text-sm text-muted-foreground">Transaction</dt><dd className="break-all text-sm">{text(evidence.tx_hash)}</dd>
                                </dl>
                            </CardContent>
                        </details>
                    </Card>
                )}

            </main>
            <footer className="border-t px-4 py-5 text-center text-xs text-muted-foreground">
                Verification is read-only. Use the unique link provided with an issued certificate.
            </footer>
        </div>
    );
}
