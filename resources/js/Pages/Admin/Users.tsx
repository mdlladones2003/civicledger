import { Form } from '@inertiajs/react';
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

import { rows, text, person } from '@/lib/app';

import { Status, Panel, PageHeading, Table, Cell, Empty } from '@/components/app/Primitives';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import { AppShell } from '@/components/app/AppShell';

import type { Data } from '@/types/app';

function StaffAccountField({ name, label, type = 'text', errors }: {
    name: string;
    label: string;
    type?: string;
    errors: Record<string, string>;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                type={type}
                required={name !== 'middle_name'}
                autoComplete={type === 'password' ? 'new-password' : undefined}
                minLength={type === 'password' ? 12 : undefined}
                className="h-10 rounded-lg px-3 text-sm font-normal"
            />
            {errors[name] && <p className="text-sm text-destructive">{errors[name]}</p>}
        </div>
    );
}

export default function AdminUsers({ props }: { props: Data }) {
    const users = rows(props.users);

    return (
        <AppShell title="User administration">
            <PageHeading eyebrow="Administration" title="Users" description="Create coordinator and signer accounts, and manage active accounts, roles, and assigned scope." />
            <Panel title="Create a coordinator or signer account">
                <Form action="/admin/users" method="post">
                    {({ errors, processing }) => (
                        <div className="grid gap-5">
                            <p className="text-sm leading-6 text-muted-foreground">
                                Share the initial password directly with the staff member. They must replace it before accessing CivicLedger.
                            </p>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <StaffAccountField name="first_name" label="First name" errors={errors} />
                                <StaffAccountField name="middle_name" label="Middle name (optional)" errors={errors} />
                                <StaffAccountField name="last_name" label="Last name" errors={errors} />
                                <StaffAccountField name="email" label="Email address" type="email" errors={errors} />
                                <div className="grid gap-2">
                                    <Label htmlFor="role">Account role</Label>
                                    <select
                                        id="role"
                                        name="role"
                                        required
                                        defaultValue=""
                                        aria-invalid={Boolean(errors.role)}
                                        className="h-10 rounded-lg border border-input bg-background px-3 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    >
                                        <option value="" disabled>Select a role</option>
                                        <option value="coordinator">Coordinator</option>
                                        <option value="signer">Signer</option>
                                    </select>
                                    {errors.role && <p className="text-sm text-destructive">{errors.role}</p>}
                                </div>
                                <StaffAccountField name="password" label="Initial password" type="password" errors={errors} />
                                <StaffAccountField name="password_confirmation" label="Confirm initial password" type="password" errors={errors} />
                            </div>
                            <Button type="submit" disabled={processing} className="w-fit">
                                {processing ? 'Creating account...' : 'Create staff account'}
                            </Button>
                        </div>
                    )}
                </Form>
            </Panel>
            <Panel title="Accounts">
                {users.length === 0 ? <Empty>No user accounts found.</Empty> : (
                    <Table headers={['Name', 'Email', 'Status', 'Roles']}>
                        {users.map((user) => (
                            <TableRow key={text(user.id)}>
                                <Cell>{person(user)}</Cell>
                                <Cell>{rows(user.roles).some((role) => text(role.code) === 'admin') ? 'Hidden for administrator accounts' : text(user.email)}</Cell>
                                <Cell><Status value={user.status} /></Cell>
                                <Cell>{rows(user.roles).map((role) => text(role.label)).join(', ') || 'No roles assigned'}</Cell>
                            </TableRow>
                        ))}
                    </Table>
                )}
            </Panel>
        </AppShell>
    );
}
