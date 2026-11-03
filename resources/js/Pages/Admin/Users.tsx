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

import { AppShell } from '@/components/app/AppShell';

import type { Data } from '@/types/app';

export default function AdminUsers({ props }: { props: Data }) {
    const users = rows(props.users);

    return (
        <AppShell title="User administration">
            <PageHeading eyebrow="Administration" title="Users" description="Manage active accounts, roles, and assigned scope." />
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
