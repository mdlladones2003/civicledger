import { Head, Link, usePage } from '@inertiajs/react';
import {
    Table as ShadcnTable,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

import { object, rows, text } from '@/lib/app';

import { Status, Metric, MetricGroup, Panel, PageHeading, ActionForm, Table, Cell, Empty } from '@/components/app/Primitives';

import { AppShell } from '@/components/app/AppShell';

import type { Data } from '@/types/app';

export default function Proofs({ props, detail }: { props: Data; detail: boolean }) {
    if (detail) {
        const proof = object(props.proof);

        return (
            <AppShell title="Proof details">
                <PageHeading eyebrow="Integrity proof" title="Proof status" description="A submitted transaction is not considered confirmed until the expected receipt and confirmations are verified." />
                <MetricGroup columns={3}>
                    <Metric label="Proof" value={<Status value={proof.proof_status} />} />
                    <Metric label="Validity" value={<Status value={proof.validity_status} />} />
                    <Metric label="Verification result" value={text(props.verification)} />
                </MetricGroup>
                <Panel title="Technical evidence">
                    <dl className="grid gap-4 break-all sm:grid-cols-2">
                        <div><dt className="text-sm text-muted-foreground">Network ID</dt><dd className="mt-1">{text(proof.chain_id)}</dd></div>
                        <div><dt className="text-sm text-muted-foreground">Transaction</dt><dd className="mt-1 break-all text-xs">{text(proof.tx_hash)}</dd></div>
                    </dl>
                    {proof.proof_status === 'failed' && (
                        <div className="mt-5"><ActionForm action={`/proofs/${text(proof.id)}/retry`} button="Retry proof submission" /></div>
                    )}
                </Panel>
            </AppShell>
        );
    }

    const proofs = rows(props.proofs);

    return (
        <AppShell title="Proofs">
            <PageHeading eyebrow="Integrity proofs" title="Proof processing" description="Track queued, submitted, confirmed, and failed integrity proofs without conflating them with approval." />
            <Panel title="Proof records">
                {proofs.length === 0 ? <Empty>No proof records are available in your scope.</Empty> : (
                    <Table headers={['Record', 'Approval', 'Proof', 'Validity', '']}>
                        {proofs.map((entry) => (
                            <TableRow key={text(entry.id)}>
                                <Cell>{text(object(object(entry.service_record_version).service_record).id, `Proof ${text(entry.id)}`)}</Cell>
                                <Cell><Status value={object(object(entry.service_record_version).service_record).status} /></Cell>
                                <Cell><Status value={entry.proof_status} /></Cell>
                                <Cell><Status value={entry.validity_status} /></Cell>
                                <Cell><Link className="font-semibold text-civic underline" href={`/proofs/${text(entry.id)}`}>Inspect</Link></Cell>
                            </TableRow>
                        ))}
                    </Table>
                )}
            </Panel>
        </AppShell>
    );
}
