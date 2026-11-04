import { useState, type FormEvent } from 'react';
import { Link, router, useForm } from '@inertiajs/react';

import { object, person, rows, statusLabel, text } from '@/lib/app';

import { ActionForm, Empty, PageHeading, Panel, Status } from '@/components/app/Primitives';
import { AuthField } from '@/components/auth/AuthLayout';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';

import { AppShell } from '@/components/app/AppShell';

import type { Data } from '@/types/app';

const checkboxRowClass = 'flex min-h-11 items-center gap-3 rounded-lg text-sm text-foreground has-disabled:text-muted-foreground';

function AccountDetails({ user }: { user: Data }) {
    const form = useForm({
        first_name: text(user.first_name, ''),
        middle_name: text(user.middle_name, ''),
        last_name: text(user.last_name, ''),
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.patch(`/admin/users/${text(user.id)}`);
    }

    return (
        <Panel title="Account details">
            <form onSubmit={submit} className="grid gap-5">
                <div className="grid items-start gap-4 sm:grid-cols-3">
                    <AuthField
                        label="First name"
                        name="first_name"
                        value={form.data.first_name}
                        onChange={(event) => form.setData('first_name', event.target.value)}
                        error={form.errors.first_name ?? null}
                    />
                    <AuthField
                        label="Middle name (optional)"
                        name="middle_name"
                        required={false}
                        value={form.data.middle_name}
                        onChange={(event) => form.setData('middle_name', event.target.value)}
                        error={form.errors.middle_name ?? null}
                    />
                    <AuthField
                        label="Last name"
                        name="last_name"
                        value={form.data.last_name}
                        onChange={(event) => form.setData('last_name', event.target.value)}
                        error={form.errors.last_name ?? null}
                    />
                </div>
                <Button type="submit" size="lg" disabled={form.processing} className="min-h-11 w-full sm:w-fit">
                    {form.processing ? 'Saving...' : 'Save details'}
                </Button>
            </form>
        </Panel>
    );
}

function AccountRoles({ user, roles, isCurrentUser }: { user: Data; roles: Data[]; isCurrentUser: boolean }) {
    const assignedRoles = rows(user.roles);
    const form = useForm({ role_ids: assignedRoles.map((role) => Number(role.id)) });
    const [confirmingRemoval, setConfirmingRemoval] = useState(false);
    const removedRoles = assignedRoles.filter((role) => !form.data.role_ids.includes(Number(role.id)));

    function save() {
        form.put(`/admin/users/${text(user.id)}/roles`, {
            onFinish: () => setConfirmingRemoval(false),
        });
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (removedRoles.length > 0) {
            setConfirmingRemoval(true);

            return;
        }

        save();
    }

    function toggleRole(roleId: number, isSelected: boolean) {
        form.setData('role_ids', isSelected
            ? [...form.data.role_ids, roleId]
            : form.data.role_ids.filter((selectedRoleId) => selectedRoleId !== roleId));
    }

    return (
        <Panel title="Roles">
            <form onSubmit={submit} className="grid gap-5">
                <fieldset className="grid gap-1 sm:grid-cols-2">
                    <legend className="sr-only">Roles for {person(user)}</legend>
                    {roles.map((role) => {
                        const roleId = Number(role.id);
                        const isOwnAdministratorRole = isCurrentUser && text(role.code) === 'admin';

                        return (
                            <label key={roleId} className={checkboxRowClass}>
                                <input
                                    type="checkbox"
                                    checked={form.data.role_ids.includes(roleId)}
                                    disabled={isOwnAdministratorRole}
                                    onChange={(event) => toggleRole(roleId, event.target.checked)}
                                    className="size-4 rounded border-input accent-primary"
                                />
                                {text(role.label)}
                            </label>
                        );
                    })}
                </fieldset>
                {isCurrentUser && (
                    <p className="text-sm leading-6 text-muted-foreground">
                        You can’t remove your own administrator role, so CivicLedger always keeps an active administrator.
                    </p>
                )}
                {form.errors.role_ids && <p className="text-sm text-destructive">{form.errors.role_ids}</p>}
                <Button type="submit" size="lg" disabled={form.processing} className="min-h-11 w-full sm:w-fit">
                    {form.processing ? 'Saving...' : 'Save roles'}
                </Button>
            </form>
            <Dialog open={confirmingRemoval} onOpenChange={setConfirmingRemoval}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Remove {removedRoles.map((role) => text(role.label)).join(', ')} from {person(user)}?</DialogTitle>
                        <DialogDescription>
                            They lose the related access on their next request. Their section assignments stay on record but have no effect unless the role is granted again.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline" size="lg" className="min-h-11">Keep roles</Button>
                        </DialogClose>
                        <Button type="button" variant="destructive" size="lg" disabled={form.processing} className="min-h-11" onClick={save}>
                            {form.processing ? 'Removing...' : 'Remove roles'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Panel>
    );
}

function SectionAssignments({ user, sections, scopeRoles }: { user: Data; sections: Data[]; scopeRoles: string[] }) {
    const assignments = rows(user.section_assignments);
    const heldRoleCodes = rows(user.roles).map((role) => text(role.code));
    const [savingAssignment, setSavingAssignment] = useState<string | null>(null);

    function toggleAssignment(sectionId: number, scopeRole: string, assignmentId: unknown) {
        if (savingAssignment !== null) {
            return;
        }

        const options = {
            preserveScroll: true,
            onStart: () => setSavingAssignment(`${sectionId}-${scopeRole}`),
            onFinish: () => setSavingAssignment(null),
        };

        if (assignmentId) {
            router.delete(`/admin/assignments/${text(assignmentId)}`, options);

            return;
        }

        router.post(`/admin/users/${text(user.id)}/assignments`, { section_id: sectionId, scope_role: scopeRole }, options);
    }

    return (
        <Panel title="Section assignments">
            {sections.length === 0 ? <Empty>No sections have been set up yet.</Empty> : (
                <div className="grid gap-4">
                    <p className="text-sm leading-6 text-muted-foreground">
                        Changes apply immediately. Grant a role above before assigning its sections.
                    </p>
                    <ul role="list" className="divide-y" aria-label="Sections">
                        {sections.map((section) => {
                            const sectionId = Number(section.id);
                            const sectionLabel = [text(section.code), text(object(section.program).code), text(object(section.term).code)].join(' · ');

                            return (
                                <li key={sectionId} className="py-3 first:pt-0 last:pb-0">
                                    <fieldset className="grid gap-1 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:gap-6">
                                        <legend className="sr-only">{sectionLabel}</legend>
                                        <p className="wrap-anywhere text-sm text-foreground" aria-hidden="true">{sectionLabel}</p>
                                        <div className="flex flex-wrap gap-x-6">
                                            {scopeRoles.map((scopeRole) => {
                                                const assignment = assignments.find((candidate) => Number(candidate.section_id) === sectionId && candidate.scope_role === scopeRole);
                                                const isSaving = savingAssignment === `${sectionId}-${scopeRole}`;

                                                return (
                                                    <label key={scopeRole} className={checkboxRowClass}>
                                                        <input
                                                            type="checkbox"
                                                            checked={isSaving ? !assignment : Boolean(assignment)}
                                                            disabled={!assignment && !isSaving && !heldRoleCodes.includes(scopeRole)}
                                                            onChange={() => toggleAssignment(sectionId, scopeRole, assignment?.id)}
                                                            className="size-4 rounded border-input accent-primary"
                                                        />
                                                        {statusLabel(scopeRole)}
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    </fieldset>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            )}
        </Panel>
    );
}

function AccountStatus({ user, isCurrentUser }: { user: Data; isCurrentUser: boolean }) {
    const updateUrl = `/admin/users/${text(user.id)}`;
    const isActive = user.status === 'active';

    return (
        <Panel title="Account status" action={<Status value={user.status} />}>
            {isCurrentUser ? (
                <p className="text-sm leading-6 text-muted-foreground">You can’t suspend your own account.</p>
            ) : isActive ? (
                <div className="grid gap-4">
                    <p className="text-sm leading-6 text-muted-foreground">
                        A suspended account is signed out on its next request and can’t sign in until it is reactivated. Its records are kept.
                    </p>
                    <Dialog>
                        <DialogTrigger asChild>
                            <Button type="button" variant="destructive" size="lg" className="min-h-11 w-full sm:w-fit">Suspend account</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Suspend {person(user)}?</DialogTitle>
                                <DialogDescription>They’ll be signed out on their next request and can’t sign in until you reactivate the account.</DialogDescription>
                            </DialogHeader>
                            <ActionForm action={updateUrl} method="patch" button="Suspend account" variant="destructive">
                                <input type="hidden" name="status" value="suspended" />
                            </ActionForm>
                        </DialogContent>
                    </Dialog>
                </div>
            ) : (
                <div className="grid gap-4">
                    <p className="text-sm leading-6 text-muted-foreground">This account can’t sign in until it is reactivated.</p>
                    <ActionForm action={updateUrl} method="patch" button="Reactivate account">
                        <input type="hidden" name="status" value="active" />
                    </ActionForm>
                </div>
            )}
        </Panel>
    );
}

export default function AdminUserDetail({ props }: { props: Data }) {
    const user = object(props.user);
    const isCurrentUser = Boolean(props.isCurrentUser);
    const email = typeof user.email === 'string' ? user.email : 'Email hidden for administrator accounts';

    return (
        <AppShell title="Manage account">
            <PageHeading
                eyebrow="Administration"
                title={person(user)}
                description={email}
                action={(
                    <Button asChild variant="outline" size="lg" className="min-h-11 w-fit">
                        <Link href="/admin/users">Back to users</Link>
                    </Button>
                )}
            />
            <AccountDetails user={user} />
            <AccountRoles user={user} roles={rows(props.roles)} isCurrentUser={isCurrentUser} />
            <SectionAssignments user={user} sections={rows(props.sections)} scopeRoles={Array.isArray(props.scopeRoles) ? props.scopeRoles.map((scopeRole) => text(scopeRole)) : []} />
            <AccountStatus user={user} isCurrentUser={isCurrentUser} />
        </AppShell>
    );
}
