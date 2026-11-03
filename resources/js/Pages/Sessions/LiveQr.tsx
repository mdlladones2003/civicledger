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

import { object, rows, text, date } from '@/lib/app';

import { Status, Metric, MetricGroup, Panel, PageHeading, Table, Cell, Empty } from '@/components/app/Primitives';

import { AppShell } from '@/components/app/AppShell';

import type { Data } from '@/types/app';

export default function LiveRoster({ props }: { props: Data }) {
    const session = object(props.session);
    const event = object(session.event);
    const roster = rows(props.roster);

    return (
        <AppShell title="Live attendance">
            <PageHeading eyebrow="Live attendance" title={text(event.title, 'Event roster')} description={`QR challenges expire after ${text(props.ttl, '30')} seconds. Refresh the live code through the challenge endpoint before each check-in.`} />
            <MetricGroup columns={3}>
                <Metric label="Registered" value={roster.length} />
                <Metric label="Checked in" value={roster.filter((entry) => entry.status === 'checked_in' || entry.status === 'completed').length} />
                <Metric label="Session status" value={<Status value={session.status} />} />
            </MetricGroup>
            <Panel title="Roster">
                {roster.length === 0 ? <Empty>No students are registered for this session.</Empty> : (
                    <Table headers={['Student', 'Attendance', 'Check-in', 'Check-out']}>
                        {roster.map((entry) => (
                            <TableRow key={text(entry.student_id)}>
                                <Cell>{text(entry.name)}</Cell>
                                <Cell><Status value={entry.status} /></Cell>
                                <Cell>{date(entry.check_in_at, { dateStyle: 'medium', timeStyle: 'short' })}</Cell>
                                <Cell>{date(entry.check_out_at, { dateStyle: 'medium', timeStyle: 'short' })}</Cell>
                            </TableRow>
                        ))}
                    </Table>
                )}
            </Panel>
        </AppShell>
    );
}
