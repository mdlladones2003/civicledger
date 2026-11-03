import { PageHeading, ButtonLink } from '@/components/app/Primitives';

import { AppShell } from '@/components/app/AppShell';

export default function NotFoundPage() {
    return (
        <AppShell title="Page unavailable">
            <PageHeading title="Page unavailable" description="This screen is not part of the current CivicLedger MVP frontend." />
            <ButtonLink href="/dashboard">Return to overview</ButtonLink>
        </AppShell>
    );
}
