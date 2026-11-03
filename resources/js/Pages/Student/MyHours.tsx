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

import { object, rows, text, date, hours, displayEventTitle } from '@/lib/app';

import { Status, Metric, MetricGroup, Panel, PageHeading, Table, Cell, Empty } from '@/components/app/Primitives';

import { AppShell } from '@/components/app/AppShell';

import type { Data } from '@/types/app';

export default function MyHours({ props }: { props: Data }) {
    const summary = object(props.summary);
    const attendance = rows(props.attendance);

    return (
        <AppShell title="My service hours">
            <PageHeading eyebrow="Student records" title="My hours" description="See attendance activity and the latest status of each service claim." />
            <MetricGroup>
                <Metric label="Approved hours" value={hours(summary.official_minutes)} />
                <Metric label="Awaiting review" value={text(object(summary.claims).pending, '0')} />
                <Metric label="Approved claims" value={text(object(summary.claims).approved, '0')} />
                <Metric label="Registered events" value={text(summary.registered_events, '0')} />
            </MetricGroup>
            <Panel title="Attendance history">
                {attendance.length === 0 ? <Empty>No attendance records yet. Your check-ins will appear here.</Empty> : (
                    <Table headers={['Event', 'Check-in', 'Check-out', 'Claim status', 'Approved time']}>
                        {attendance.map((entry) => {
                            const session = object(entry.session);
                            const event = object(session.event);
                            const record = object(entry.service_record ?? entry.serviceRecord);

                            return (
                                <TableRow key={text(entry.id)}>
                                    <Cell>{displayEventTitle(event.title)}</Cell>
                                    <Cell>{date(entry.check_in_at, { dateStyle: 'medium', timeStyle: 'short' })}</Cell>
                                    <Cell>{date(entry.check_out_at, { dateStyle: 'medium', timeStyle: 'short' })}</Cell>
                                    <Cell><Status value={record.status ?? 'pending'} /></Cell>
                                    <Cell>{record.approved_minutes ? hours(record.approved_minutes) : '—'}</Cell>
                                </TableRow>
                            );
                        })}
                    </Table>
                )}
            </Panel>
        </AppShell>
    );
}
