import { useId, useState, type FormEvent } from 'react';
import { useForm } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

import { dateTimeLocal, isoFromLocal, text } from '@/lib/app';

import type { Data } from '@/types/app';

type AttendanceDialogProps =
    | { mode: 'manual'; sessionId: unknown; studentId: unknown; studentName: string }
    | { mode: 'correct'; attendance: Data; studentName: string };

function FieldError({ id, message }: { id: string; message?: string }) {
    return message ? <p id={id} className="text-sm text-destructive">{message}</p> : null;
}

/** F3.3 manual entry and F3.5 correction share the same times + mandatory reason form. */
export function AttendanceDialog(props: AttendanceDialogProps) {
    const correcting = props.mode === 'correct';
    const attendance = correcting ? props.attendance : {};
    const fieldId = useId();
    const [isOpen, setIsOpen] = useState(false);
    const form = useForm({
        student_id: correcting ? '' : text(props.studentId, ''),
        check_in_at: dateTimeLocal(attendance.check_in_at) ?? '',
        check_out_at: dateTimeLocal(attendance.check_out_at) ?? '',
        reason: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform((data) => ({
            ...(correcting ? {} : { student_id: data.student_id }),
            check_in_at: isoFromLocal(data.check_in_at),
            check_out_at: isoFromLocal(data.check_out_at),
            reason: data.reason,
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setIsOpen(false);
                form.reset('reason');
            },
        };

        if (correcting) {
            form.patch(`/attendance/${text(props.attendance.id)}/adjust`, options);

            return;
        }

        form.post(`/sessions/${text(props.sessionId)}/attendance/manual`, options);
    }

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogTrigger asChild>
                <Button type="button" variant="outline" className="min-h-11" aria-label={`${correcting ? 'Correct attendance' : 'Add manual entry'} for ${props.studentName}`}>
                    {correcting ? 'Correct' : 'Add manual entry'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{correcting ? `Correct attendance for ${props.studentName}` : `Add manual entry for ${props.studentName}`}</DialogTitle>
                    <DialogDescription>
                        {correcting
                            ? 'The earlier times stay in this record’s history. A claim that was already approved goes back to review.'
                            : 'Use this only when the student could not scan. The entry and your reason are recorded.'}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <FieldError id={`${fieldId}-student`} message={form.errors.student_id} />
                    <div className="grid gap-2">
                        <Label htmlFor={`${fieldId}-in`} className="text-sm font-medium text-foreground">Check-in time</Label>
                        <Input
                            id={`${fieldId}-in`}
                            type="datetime-local"
                            required
                            value={form.data.check_in_at}
                            onChange={(event) => form.setData('check_in_at', event.target.value)}
                            aria-invalid={Boolean(form.errors.check_in_at)}
                            aria-describedby={form.errors.check_in_at ? `${fieldId}-in-error` : undefined}
                            className="h-10 rounded-lg px-3 text-sm font-normal"
                        />
                        <FieldError id={`${fieldId}-in-error`} message={form.errors.check_in_at} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${fieldId}-out`} className="text-sm font-medium text-foreground">Check-out time (optional)</Label>
                        <Input
                            id={`${fieldId}-out`}
                            type="datetime-local"
                            value={form.data.check_out_at}
                            onChange={(event) => form.setData('check_out_at', event.target.value)}
                            aria-invalid={Boolean(form.errors.check_out_at)}
                            aria-describedby={form.errors.check_out_at ? `${fieldId}-out-error` : undefined}
                            className="h-10 rounded-lg px-3 text-sm font-normal"
                        />
                        <FieldError id={`${fieldId}-out-error`} message={form.errors.check_out_at} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${fieldId}-reason`} className="text-sm font-medium text-foreground">Reason (at least 10 characters)</Label>
                        <Textarea
                            id={`${fieldId}-reason`}
                            required
                            minLength={10}
                            maxLength={500}
                            rows={3}
                            value={form.data.reason}
                            onChange={(event) => form.setData('reason', event.target.value)}
                            aria-invalid={Boolean(form.errors.reason)}
                            aria-describedby={form.errors.reason ? `${fieldId}-reason-error` : undefined}
                        />
                        <FieldError id={`${fieldId}-reason-error`} message={form.errors.reason} />
                    </div>
                    <Button type="submit" size="lg" disabled={form.processing} className="min-h-11 w-full sm:w-fit">
                        {form.processing ? 'Saving...' : correcting ? 'Save correction' : 'Save manual entry'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
