import { Form, Link } from '@inertiajs/react';
import {
    Table as ShadcnTable,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    ArrowLeft,
    ArrowRight,
    Award,
    BarChart3,
    CalendarDays,
    Check,
    ClipboardCheck,
    Clock3,
    Fingerprint,
    HeartHandshake,
    Info,
    KeyRound,
    LayoutDashboard,
    LogOut,
    LockKeyhole,
    MapPin,
    Menu,
    Moon,
    QrCode,
    Server,
    ShieldCheck,
    ShieldQuestion,
    ShieldX,
    Sun,
    UserRound,
    Users,
    X,
} from 'lucide-react';

import { object, rows, text, person } from '@/lib/app';

import { Status, Panel, PageHeading, PageNav, NativeSelect, Table, Cell, Empty } from '@/components/app/Primitives';
import { AuthField } from '@/components/auth/AuthLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import { AppShell } from '@/components/app/AppShell';

import type { Data } from '@/types/app';

function AccountEmail({ user }: { user: Data }) {
    if (rows(user.roles).some((role) => text(role.code) === 'admin')) {
        return <span className="text-muted-foreground">Hidden for administrator accounts</span>;
    }

    return <span className="wrap-anywhere">{text(user.email)}</span>;
}

function AccountRoles({ user }: { user: Data }) {
    const roleLabels = rows(user.roles).map((role) => text(role.label)).join(', ');

    return roleLabels ? <>{roleLabels}</> : <span className="text-muted-foreground">No roles assigned</span>;
}

function scrollToAccounts() {
    document.getElementById('accounts')?.scrollIntoView();
}

function ManageAccountLink({ user }: { user: Data }) {
    return (
        <Link className="flex min-h-11 w-fit items-center font-semibold text-civic underline underline-offset-4" href={`/admin/users/${text(user.id)}`} aria-label={`Manage ${person(user)}`}>
            Manage
        </Link>
    );
}

export default function AdminUsers({ props }: { props: Data }) {
    const users = rows(props.users);
    const search = text(object(props.filters).q, '');

    return (
        <AppShell title="User administration">
            <PageHeading eyebrow="Administration" title="Users" description="Create coordinator and signer accounts, and manage active accounts, roles, and assigned scope." />
            <Panel title="Create a coordinator or signer account">
                <Form action="/admin/users" method="post" resetOnSuccess>
                    {({ errors, processing }) => (
                        <div className="grid gap-5">
                            <p className="text-sm leading-6 text-muted-foreground">
                                Share the initial password directly with the staff member. They must replace it before accessing CivicLedger.
                            </p>
                            <div className="grid gap-4">
                                <div className="grid items-start gap-4 sm:grid-cols-3">
                                    <AuthField label="First name" name="first_name" />
                                    <AuthField label="Middle name (optional)" name="middle_name" required={false} />
                                    <AuthField label="Last name" name="last_name" />
                                </div>
                                <div className="grid items-start gap-4 sm:grid-cols-2">
                                    <AuthField label="Email address" name="email" type="email" />
                                    <div className="grid gap-2">
                                        <Label htmlFor="role" className="text-sm font-medium text-foreground">Account role</Label>
                                        <NativeSelect
                                            id="role"
                                            name="role"
                                            required
                                            defaultValue=""
                                            aria-invalid={Boolean(errors.role)}
                                            aria-describedby={errors.role ? 'role-error' : undefined}
                                        >
                                            <option value="" disabled>Select a role</option>
                                            <option value="coordinator">Coordinator</option>
                                            <option value="signer">Signer</option>
                                        </NativeSelect>
                                        {errors.role && <p id="role-error" className="text-sm text-destructive">{errors.role}</p>}
                                    </div>
                                    <AuthField label="Initial password (at least 12 characters)" name="password" type="password" autoComplete="new-password" minLength={12} />
                                    <AuthField label="Confirm initial password" name="password_confirmation" type="password" autoComplete="new-password" minLength={12} />
                                </div>
                            </div>
                            <Button type="submit" size="lg" disabled={processing} className="min-h-11 w-full sm:w-fit">
                                {processing ? 'Creating account...' : 'Create staff account'}
                            </Button>
                        </div>
                    )}
                </Form>
            </Panel>
            <div id="accounts" className="min-w-0 scroll-mt-32 lg:scroll-mt-20">
                <Panel title="Accounts">
                    <Form action="/admin/users" method="get" options={{ preserveScroll: true }} onSuccess={scrollToAccounts} className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end">
                        {({ processing }) => (
                            <>
                                <div className="grid min-w-0 flex-1 gap-2">
                                    <Label htmlFor="q" className="text-sm font-medium text-foreground">Search by name or email</Label>
                                    <Input id="q" name="q" type="search" defaultValue={search} className="h-11 rounded-lg px-3 text-sm font-normal" />
                                </div>
                                <div className="flex gap-2">
                                    <Button type="submit" variant="outline" size="lg" disabled={processing} className="min-h-11 flex-1 sm:flex-none">Search</Button>
                                    {search !== '' && (
                                        <Button asChild variant="ghost" size="lg" className="min-h-11 flex-1 sm:flex-none">
                                            <Link href="/admin/users" preserveScroll onSuccess={scrollToAccounts}>Clear</Link>
                                        </Button>
                                    )}
                                </div>
                            </>
                        )}
                    </Form>
                    {users.length === 0 ? <Empty>{search === '' ? 'No user accounts found.' : `No accounts match “${search}”.`}</Empty> : (
                        <>
                            <ul role="list" className="divide-y md:hidden" aria-label="Accounts">
                                {users.map((user) => (
                                    <li key={text(user.id)} className="grid gap-2 py-4 first:pt-0 last:pb-0">
                                        <div className="flex items-start justify-between gap-3">
                                            <p className="min-w-0 pt-1 wrap-anywhere">{person(user)}</p>
                                            <Status value={user.status} />
                                        </div>
                                        <dl className="grid gap-1 text-muted-foreground">
                                            <div><dt className="inline font-medium text-foreground">Email: </dt><dd className="inline"><AccountEmail user={user} /></dd></div>
                                            <div><dt className="inline font-medium text-foreground">Roles: </dt><dd className="inline"><AccountRoles user={user} /></dd></div>
                                        </dl>
                                        <ManageAccountLink user={user} />
                                    </li>
                                ))}
                            </ul>
                            <div className="hidden md:block">
                                <Table headers={['Name', 'Email', 'Status', 'Roles', 'Actions']}>
                                    {users.map((user) => (
                                        <TableRow key={text(user.id)} className="[&>td]:whitespace-normal">
                                            <Cell>{person(user)}</Cell>
                                            <Cell><div className="min-w-56"><AccountEmail user={user} /></div></Cell>
                                            <Cell><Status value={user.status} /></Cell>
                                            <Cell><AccountRoles user={user} /></Cell>
                                            <Cell><ManageAccountLink user={user} /></Cell>
                                        </TableRow>
                                    ))}
                                </Table>
                            </div>
                        </>
                    )}
                    <PageNav page={object(props.users)} label="Account pages" scrollTargetId="accounts" />
                </Panel>
            </div>
        </AppShell>
    );
}
