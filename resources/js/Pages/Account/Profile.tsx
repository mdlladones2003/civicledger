import { object, text } from '@/lib/app';

import { Panel, PageHeading, ActionForm, Field } from '@/components/app/Primitives';

import { AppShell } from '@/components/app/AppShell';

import type { Data } from '@/types/app';

export default function AccountProfile({ props }: { props: Data }) {
    const user = object(props.user);

    return (
        <AppShell title="Your account">
            <PageHeading eyebrow="Account settings" title="Your account" description="Manage your account details and access." />
            <Panel title="Personal details">
                <dl className="grid gap-4 sm:grid-cols-2">
                    <div><dt className="text-sm text-muted-foreground">Name</dt><dd className="mt-1">{text(user.full_name)}</dd></div>
                    <div><dt className="text-sm text-muted-foreground">Email</dt><dd className="mt-1">{text(user.email)}</dd></div>
                </dl>
            </Panel>
            {props.canDeleteAccount !== false && (
                <Panel title="Delete account">
                    <div className="grid gap-4">
                        <p className="text-sm leading-6 text-muted-foreground">
                            Your personal details will be anonymized, access will be revoked, and civic service history will be retained without identifying you.
                        </p>
                        <ActionForm action="/account" method="delete" button="Delete my account">
                            <Field label="Current password" name="current_password" type="password" required />
                        </ActionForm>
                    </div>
                </Panel>
            )}
        </AppShell>
    );
}
