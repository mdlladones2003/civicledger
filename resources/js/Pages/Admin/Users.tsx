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

import { Status, Panel, PageHeading, Table, Cell, Empty } from '@/components/app/Primitives';
import { AuthField } from '@/components/auth/AuthLayout';
import { Button } from '@/components/ui/button';
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

export default function AdminUsers({ props }: { props: Data }) {
    const users = rows(props.users);
    const accountPage = object(props.users);
    const previousPage = typeof accountPage.prev_page_url === 'string' ? accountPage.prev_page_url : null;
    const nextPage = typeof accountPage.next_page_url === 'string' ? accountPage.next_page_url : null;

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
                                        <select
                                            id="role"
                                            name="role"
                                            required
                                            defaultValue=""
                                            aria-invalid={Boolean(errors.role)}
                                            aria-describedby={errors.role ? 'role-error' : undefined}
                                            className="h-10 w-full min-w-0 rounded-lg border border-input bg-transparent px-3 text-sm text-foreground transition-colors duration-150 ease-cl-out outline-none *:bg-card focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring aria-invalid:border-destructive aria-invalid:ring-2 aria-invalid:ring-destructive/30 dark:bg-input/30 dark:aria-invalid:border-destructive/50"
                                        >
                                            <option value="" disabled>Select a role</option>
                                            <option value="coordinator">Coordinator</option>
                                            <option value="signer">Signer</option>
                                        </select>
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
                    {users.length === 0 ? <Empty>No user accounts found.</Empty> : (
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
                                    </li>
                                ))}
                            </ul>
                            <div className="hidden md:block">
                                <Table headers={['Name', 'Email', 'Status', 'Roles']}>
                                    {users.map((user) => (
                                        <TableRow key={text(user.id)} className="[&>td]:whitespace-normal">
                                            <Cell>{person(user)}</Cell>
                                            <Cell><div className="min-w-56"><AccountEmail user={user} /></div></Cell>
                                            <Cell><Status value={user.status} /></Cell>
                                            <Cell><AccountRoles user={user} /></Cell>
                                        </TableRow>
                                    ))}
                                </Table>
                            </div>
                        </>
                    )}
                    {(previousPage || nextPage) && (
                        <nav className="mt-6 flex items-center justify-between gap-4 border-t border-border pt-4" aria-label="Account pages">
                            {previousPage ? (
                                <Link className="-mx-2 flex min-h-11 items-center rounded-lg px-2 font-semibold text-civic underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" href={`${previousPage}#accounts`}>Previous</Link>
                            ) : <span className="text-sm text-muted-foreground">Previous</span>}
                            <span className="text-sm text-muted-foreground">Page {text(accountPage.current_page, '1')} of {text(accountPage.last_page, '1')}</span>
                            {nextPage ? (
                                <Link className="-mx-2 flex min-h-11 items-center rounded-lg px-2 font-semibold text-civic underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" href={`${nextPage}#accounts`}>Next</Link>
                            ) : <span className="text-sm text-muted-foreground">Next</span>}
                        </nav>
                    )}
                </Panel>
            </div>
        </AppShell>
    );
}
