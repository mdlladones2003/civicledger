import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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

import { object, rows, text, date, hours, person } from '@/lib/app';

import { Status, Panel, PageHeading, ActionForm } from '@/components/app/Primitives';

import { AppShell } from '@/components/app/AppShell';
import { AttendanceDialog } from '@/components/app/AttendanceDialog';

import type { Data } from '@/types/app';

export default function ReviewDetail({ props }: { props: Data }) {
    const record = object(props.record);
    const attendance = object(record.attendance);
    const event = object(object(attendance.session).event);
    const adjustments = rows(attendance.adjustments);

    return (
        <AppShell title="Review service record">
            <PageHeading eyebrow="Hours review" title="Review service record" description="Confirm the derived attendance duration before recording a decision." />
            <div className="grid gap-6 lg:grid-cols-[1fr_360px]">
                <Panel title="Submitted record">
                    <dl className="grid gap-4 sm:grid-cols-2">
                        <div><dt className="text-sm text-muted-foreground">Student</dt><dd className="mt-1">{person(record.student)}</dd></div>
                        <div><dt className="text-sm text-muted-foreground">Event</dt><dd className="mt-1">{text(event.title)}</dd></div>
                        <div><dt className="text-sm text-muted-foreground">Check-in</dt><dd className="mt-1">{date(attendance.check_in_at, { dateStyle: 'medium', timeStyle: 'short' })}</dd></div>
                        <div><dt className="text-sm text-muted-foreground">Check-out</dt><dd className="mt-1">{date(attendance.check_out_at, { dateStyle: 'medium', timeStyle: 'short' })}</dd></div>
                        <div><dt className="text-sm text-muted-foreground">Derived duration</dt><dd className="mt-1 text-lg font-semibold">{hours(props.derived_minutes)}</dd></div>
                        <div><dt className="text-sm text-muted-foreground">Approval status</dt><dd className="mt-1"><Status value={record.status} /></dd></div>
                    </dl>
                    {Boolean(props.canCorrect) && (
                        <div className="mt-5 border-t border-border pt-4">
                            <AttendanceDialog
                                key={`${text(attendance.check_in_at, '')}-${text(attendance.check_out_at, '')}`}
                                mode="correct"
                                attendance={attendance}
                                studentName={person(record.student)}
                            />
                        </div>
                    )}
                </Panel>
                {Boolean(props.canReview) && (
                    <Panel title="Record a decision">
                        <div className="grid gap-5">
                            {(['approve', 'reject', 'revise'] as const).map((action) => (
                                <ActionForm key={action} action={`/hours/review/${text(record.id)}/${action}`} button={action === 'revise' ? 'Request revision' : `${action[0].toUpperCase()}${action.slice(1)} hours`}>
                                    <Label className="grid gap-1.5 text-sm font-medium capitalize text-foreground">
                                        <span>{action === 'revise' ? 'Revision notes' : 'Reason'}</span>
                                        <Textarea
                                            name="reason"
                                            minLength={action === 'approve' ? undefined : 5}
                                            maxLength={500}
                                            rows={2}
                                            required={action !== 'approve'}
                                            className="normal-case"
                                        />
                                    </Label>
                                </ActionForm>
                            ))}
                        </div>
                    </Panel>
                )}
            </div>
            {adjustments.length > 0 && (
                <Panel title="Correction history">
                    <ul role="list" className="divide-y">
                        {adjustments.map((adjustment) => {
                            const oldTimes = object(adjustment.old_json);
                            const newTimes = object(adjustment.new_json);

                            return (
                                <li key={text(adjustment.id)} className="grid gap-1 py-3 first:pt-0 last:pb-0">
                                    <p>{date(adjustment.created_at, { dateStyle: 'medium', timeStyle: 'short' })} · {person(adjustment.actor)}</p>
                                    <p className="wrap-anywhere text-muted-foreground">{text(adjustment.reason)}</p>
                                    <p className="text-muted-foreground">
                                        Check-in {date(oldTimes.check_in_at, { dateStyle: 'medium', timeStyle: 'short' })} → {date(newTimes.check_in_at, { dateStyle: 'medium', timeStyle: 'short' })}
                                    </p>
                                    <p className="text-muted-foreground">
                                        Check-out {date(oldTimes.check_out_at, { dateStyle: 'medium', timeStyle: 'short' })} → {date(newTimes.check_out_at, { dateStyle: 'medium', timeStyle: 'short' })}
                                    </p>
                                </li>
                            );
                        })}
                    </ul>
                </Panel>
            )}
        </AppShell>
    );
}
