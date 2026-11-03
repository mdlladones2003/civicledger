import { useEffect, useState, type ReactNode } from 'react';
import { Head, Link } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    Clock3,
    Info,
    CalendarDays,
    ClipboardCheck,
    LogOut,
    LayoutDashboard,
    Moon,
    ShieldCheck,
    ShieldX,
    UserRound,
} from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Toast } from '@/components/ui/toast';
import { BrandMark } from '@/components/app/BrandMark';
import { Status } from '@/components/app/Primitives';
import { ThemeToggle } from '@/components/app/ThemeToggle';
import { useTheme } from '@/components/app/ThemeProvider';

type ThemeMode = 'light' | 'dark';

type Swatch = {
    label: string;
    hex: string;
};

const palette: Record<ThemeMode, Swatch[]> = {
    light: [
        { label: 'Background', hex: '#F8FAFC' },
        { label: 'Card', hex: '#FFFFFF' },
        { label: 'Primary / deep navy', hex: '#123047' },
        { label: 'Secondary / muted', hex: '#E8EDF3' },
        { label: 'Accent surface', hex: '#E3F1EF' },
        { label: 'Accent text', hex: '#0B4F4A' },
        { label: 'Civic teal / links', hex: '#0F766E' },
        { label: 'Text / muted slate', hex: '#475569' },
        { label: 'Border', hex: '#DBE2EA' },
        { label: 'Input', hex: '#DBE2EA' },
        { label: 'Focus ring', hex: '#0F766E' },
        { label: 'Destructive', hex: '#991B1B' },
        { label: 'Sidebar', hex: '#123047' },
        { label: 'Sidebar text', hex: '#FFFFFF' },
        { label: 'Sidebar muted text', hex: '#CBD5E1' },
        { label: 'Sidebar active surface', hex: '#1F4055' },
        { label: 'Sidebar border', hex: '#29485D' },
        { label: 'Sidebar focus ring', hex: '#5FD0C4' },
        { label: 'Success fill', hex: '#E3F1EF' },
        { label: 'Success text', hex: '#0B4F4A' },
        { label: 'Pending fill', hex: '#FEF3C7' },
        { label: 'Pending text', hex: '#8A4A05' },
        { label: 'Pending icon / border', hex: '#D97706' },
        { label: 'Danger fill', hex: '#FEE2E2' },
        { label: 'Danger text', hex: '#991B1B' },
        { label: 'Danger icon / border', hex: '#B91C1C' },
        { label: 'Neutral fill', hex: '#E8EDF3' },
        { label: 'Neutral text', hex: '#334155' },
        { label: 'Neutral icon / border', hex: '#CBD5E1' },
    ],
    dark: [
        { label: 'Background', hex: '#0A1722' },
        { label: 'Card', hex: '#0F2130' },
        { label: 'Primary / civic teal', hex: '#5FD0C4' },
        { label: 'Primary button text', hex: '#06222B' },
        { label: 'Secondary', hex: '#1A2B39' },
        { label: 'Muted', hex: '#172B3B' },
        { label: 'Accent surface', hex: '#153C43' },
        { label: 'Accent text', hex: '#BDF4EB' },
        { label: 'Main text', hex: '#E8EEF4' },
        { label: 'Border', hex: '#213A4B' },
        { label: 'Input', hex: '#30495A' },
        { label: 'Focus ring / links', hex: '#5FD0C4' },
        { label: 'Destructive', hex: '#FCA5A5' },
        { label: 'Sidebar', hex: '#0F2130' },
        { label: 'Sidebar text', hex: '#E8EEF4' },
        { label: 'Sidebar muted text', hex: '#B7C7D3' },
        { label: 'Sidebar active surface', hex: '#1A3445' },
        { label: 'Sidebar border', hex: '#213A4B' },
        { label: 'Sidebar focus ring', hex: '#5FD0C4' },
        { label: 'Success fill', hex: '#14312F' },
        { label: 'Success text', hex: '#BFE9E2' },
        { label: 'Pending fill', hex: '#3B2E1B' },
        { label: 'Pending text', hex: '#FFE1A6' },
        { label: 'Pending icon / border', hex: '#D99A36' },
        { label: 'Danger fill', hex: '#3A2024' },
        { label: 'Danger text', hex: '#FFC2C2' },
        { label: 'Danger icon / border', hex: '#E27A7A' },
        { label: 'Neutral fill', hex: '#1A2B39' },
        { label: 'Neutral text', hex: '#C5D0DA' },
        { label: 'Neutral icon / border', hex: '#597083' },
    ],
};

const contrastPairs = [
    { label: 'Body text on background', foreground: '--foreground', background: '--background' },
    { label: 'Muted text on background', foreground: '--muted-foreground', background: '--background' },
    { label: 'Link on background', foreground: '--link', background: '--background' },
    { label: 'Body text on card', foreground: '--card-foreground', background: '--card' },
    { label: 'Muted text on card', foreground: '--muted-foreground', background: '--card' },
    { label: 'Link on card', foreground: '--link', background: '--card' },
    { label: 'Muted text on muted surface', foreground: '--muted-foreground', background: '--muted' },
    { label: 'Accent text on accent surface', foreground: '--accent-foreground', background: '--accent' },
    { label: 'Secondary text on secondary surface', foreground: '--secondary-foreground', background: '--secondary' },
    { label: 'Primary button text', foreground: '--primary-foreground', background: '--primary' },
    { label: 'Destructive button text', foreground: '--destructive-foreground', background: '--destructive' },
    { label: 'Sidebar text', foreground: '--sidebar-foreground', background: '--sidebar' },
    { label: 'Sidebar muted text', foreground: '--sidebar-muted', background: '--sidebar' },
    { label: 'Sidebar active item', foreground: '--sidebar-accent-foreground', background: '--sidebar-accent' },
    { label: 'Sidebar brand mark', foreground: '--sidebar-primary-foreground', background: '--sidebar-primary' },
    { label: 'Success status', foreground: '--status-success-foreground', background: '--status-success' },
    { label: 'Pending status', foreground: '--status-pending-foreground', background: '--status-pending' },
    { label: 'Danger status', foreground: '--status-danger-foreground', background: '--status-danger' },
    { label: 'Neutral status', foreground: '--status-neutral-foreground', background: '--status-neutral' },
];

const statusGroups = [
    {
        title: 'Approval',
        statuses: ['Pending', 'Approved', 'Rejected', 'Revision requested'],
    },
    {
        title: 'Proof',
        statuses: ['Queued', 'Submitted', 'Confirmed', 'Failed'],
    },
    {
        title: 'Validity',
        statuses: ['Current', 'Superseded', 'Revoked'],
    },
    {
        title: 'Public check',
        statuses: ['Verified', 'Mismatch', 'Unavailable', 'Not found'],
    },
];

function colorChannels(color: string): [number, number, number] {
    const hex = color.match(/^#([\da-f]{6})$/i);

    if (hex) {
        return [
            Number.parseInt(hex[1].slice(0, 2), 16),
            Number.parseInt(hex[1].slice(2, 4), 16),
            Number.parseInt(hex[1].slice(4, 6), 16),
        ];
    }

    const rgb = color.match(/^rgba?\(([^)]+)\)$/i);

    if (rgb) {
        const channels = rgb[1].split(/[,\s/]+/).filter(Boolean).slice(0, 3).map(Number);

        if (channels.length === 3 && channels.every(Number.isFinite)) {
            return [channels[0], channels[1], channels[2]];
        }
    }

    throw new Error(`Unsupported computed color: ${color}`);
}

function luminance(color: string): number {
    const channels = colorChannels(color).map((channel) => {
        const value = channel / 255;

        return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
}

function contrast(foreground: string, background: string): number {
    const first = luminance(foreground);
    const second = luminance(background);

    return (Math.max(first, second) + 0.05) / (Math.min(first, second) + 0.05);
}

function ContrastTable() {
    const { theme } = useTheme();
    const [ratios, setRatios] = useState<number[] | null>(null);

    useEffect(() => {
        const frame = window.requestAnimationFrame(() => {
            const styles = window.getComputedStyle(document.documentElement);
            const values = contrastPairs.map(({ foreground, background }) => (
                contrast(
                    styles.getPropertyValue(foreground).trim(),
                    styles.getPropertyValue(background).trim(),
                )
            ));

            setRatios(values);
        });

        return () => window.cancelAnimationFrame(frame);
    }, [theme]);

    return (
        <div className="grid gap-3">
            <p className="text-sm text-muted-foreground">
                Ratios update with the active {theme} theme. Normal text passes at 4.5:1 or higher.
            </p>
            <div className="overflow-x-auto rounded-[10px] border border-border">
                <div className="w-full overflow-x-auto">
                    <table className="w-full min-w-[560px] text-left text-sm md:min-w-0">
                        <thead>
                        <tr className="border-b border-border bg-muted">
                            <th className="px-4 py-3 font-normal">Text pair</th>
                            <th className="px-4 py-3 font-normal">Contrast</th>
                            <th className="px-4 py-3 font-normal">WCAG AA</th>
                        </tr>
                    </thead>
                    <tbody>
                        {contrastPairs.map((pair, index) => {
                            const ratio = ratios?.[index];
                            const passes = ratio !== undefined && ratio >= 4.5;

                            return (
                                <tr key={pair.label} className="border-b border-border last:border-0">
                                    <th scope="row" className="px-4 py-3 font-normal">{pair.label}</th>
                                    <td className="px-4 py-3 tabular-nums">{ratio ? `${ratio.toFixed(2)}:1` : 'Calculating'}</td>
                                    <td className={`px-4 py-3 ${passes ? 'text-foreground' : 'text-muted-foreground'}`}>
                                        {ratio === undefined ? '—' : passes ? 'Pass · 4.5:1' : 'Review'}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    );
}

function SwatchGrid({ colors }: { colors: Swatch[] }) {
    return (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {colors.map(({ label, hex }) => (
                <div key={label} className="flex min-h-16 items-center gap-3 rounded-[10px] border border-border bg-card p-3">
                    <span
                        className="size-10 shrink-0 rounded-[10px] border border-border"
                        style={{ backgroundColor: hex }}
                        aria-hidden="true"
                    />
                    <span className="grid gap-1">
                        <span className="text-sm">{label}</span>
                        <code className="text-xs text-muted-foreground">{hex}</code>
                    </span>
                </div>
            ))}
        </div>
    );
}

function SectionTitle({ title, description }: { title: string; description: string }) {
    return (
        <div className="grid gap-2">
            <h2 className="text-2xl tracking-tight">{title}</h2>
            <p className="max-w-3xl text-sm leading-6 text-muted-foreground">{description}</p>
        </div>
    );
}

function ApprovalDetailSample() {
    return (
        <div className="grid gap-5">
            <div className="grid gap-3 sm:flex sm:items-start sm:justify-between">
                <div className="grid gap-1">
                    <p className="text-sm text-muted-foreground">Coordinator · Hour review</p>
                    <h3 className="text-xl tracking-tight">Review service record</h3>
                    <p className="text-sm text-muted-foreground">Community garden clean-up · 14 October 2026</p>
                </div>
                <Status value="pending" />
            </div>
            <Alert variant="pending">
                <Clock3 aria-hidden="true" />
                <AlertTitle>Attendance is ready for review</AlertTitle>
                <AlertDescription>Check the submitted times and derived duration before recording a decision.</AlertDescription>
            </Alert>
            <Card>
                <CardHeader>
                    <CardTitle>Attendance details</CardTitle>
                    <CardDescription>Student identity is abbreviated in this sample.</CardDescription>
                </CardHeader>
                <CardContent>
                    <dl className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-1"><dt className="text-sm text-muted-foreground">Student</dt><dd>A. Santos</dd></div>
                        <div className="grid gap-1"><dt className="text-sm text-muted-foreground">Section</dt><dd>NSTP · Section 2</dd></div>
                        <div className="grid gap-1"><dt className="text-sm text-muted-foreground">Check-in</dt><dd>8:05 AM</dd></div>
                        <div className="grid gap-1"><dt className="text-sm text-muted-foreground">Check-out</dt><dd>12:05 PM</dd></div>
                        <div className="grid gap-1"><dt className="text-sm text-muted-foreground">Derived duration</dt><dd>4 hours</dd></div>
                        <div className="grid gap-1"><dt className="text-sm text-muted-foreground">Integrity proof</dt><dd><Status value="queued" /></dd></div>
                    </dl>
                </CardContent>
            </Card>
            <div className="flex flex-wrap gap-2">
                <Button>Approve hours</Button>
                <Button variant="outline">Request revision</Button>
                <Button variant="destructive">Reject hours</Button>
            </div>
        </div>
    );
}

function VerificationSample() {
    return (
        <div className="grid gap-4 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <ShieldCheck className="size-5 text-status-success-border" aria-hidden="true" />
                        <Status value="verified" />
                    </div>
                    <CardTitle>Record integrity confirmed</CardTitle>
                    <CardDescription>The record matches its confirmed commitment.</CardDescription>
                </CardHeader>
                <CardContent>
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <div className="grid gap-1"><dt className="text-muted-foreground">Certificate</dt><dd>CL-NSTP-2026-0042</dd></div>
                        <div className="grid gap-1"><dt className="text-muted-foreground">Holder</dt><dd>A. Santos</dd></div>
                        <div className="grid gap-1"><dt className="text-muted-foreground">Approved service</dt><dd>32 hours</dd></div>
                        <div className="grid gap-1"><dt className="text-muted-foreground">Validity</dt><dd><Status value="current" /></dd></div>
                    </dl>
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <ShieldX className="size-5 text-status-danger-border" aria-hidden="true" />
                        <Status value="mismatch" />
                    </div>
                    <CardTitle>Record does not match its proof</CardTitle>
                    <CardDescription>This check did not confirm record integrity.</CardDescription>
                </CardHeader>
                <CardContent>
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <div className="grid gap-1"><dt className="text-muted-foreground">Certificate</dt><dd>CL-NSTP-2026-0042</dd></div>
                        <div className="grid gap-1"><dt className="text-muted-foreground">Holder</dt><dd>A. Santos</dd></div>
                        <div className="grid gap-1"><dt className="text-muted-foreground">Integrity</dt><dd>Mismatch reported</dd></div>
                        <div className="grid gap-1"><dt className="text-muted-foreground">Next step</dt><dd>Contact the issuing office</dd></div>
                    </dl>
                </CardContent>
            </Card>
        </div>
    );
}

function SidebarPreview() {
    const navigation = [
        { label: 'Dashboard', icon: LayoutDashboard, active: true },
        { label: 'Events', icon: CalendarDays, active: false },
        { label: 'Review hours', icon: ClipboardCheck, active: false },
    ];

    return (
        <aside className="flex min-h-[32rem] w-full max-w-72 flex-col gap-6 rounded-[10px] border border-sidebar-border bg-sidebar p-4 text-sidebar-foreground" aria-label="Sidebar theme preview">
            <div className="flex items-center gap-3 px-2 py-1 text-lg">
                <BrandMark variant="sidebar" />
                CivicLedger
            </div>
            <div className="grid gap-2">
                <p className="px-2 text-xs text-sidebar-muted">Workspace</p>
                <nav className="grid gap-1" aria-label="Sidebar preview navigation">
                    {navigation.map(({ label, icon: Icon, active }) => (
                        <div
                            key={label}
                            aria-current={active ? 'page' : undefined}
                            className={`flex min-h-10 items-center gap-3 rounded-[10px] px-3 py-2 text-sm ${active ? 'bg-sidebar-accent text-sidebar-accent-foreground' : 'text-sidebar-muted'}`}
                        >
                            <Icon className="size-4" aria-hidden="true" />
                            <span>{label}</span>
                        </div>
                    ))}
                </nav>
            </div>
            <div className="mt-auto grid gap-3">
                <div className="flex items-center gap-2 border-t border-sidebar-border pt-3">
                    <span className="flex size-9 items-center justify-center rounded-full bg-sidebar-accent text-sidebar-accent-foreground" aria-hidden="true">
                        <UserRound className="size-4" />
                    </span>
                    <span className="min-w-0 flex-1 truncate text-sm">Alex Santos</span>
                    <span className="flex size-10 items-center justify-center rounded-[10px] text-sidebar-muted" aria-hidden="true">
                        <Moon className="size-4" />
                    </span>
                    <span className="flex size-10 items-center justify-center rounded-[10px] text-sidebar-muted" aria-hidden="true">
                        <LogOut className="size-4" />
                    </span>
                </div>
            </div>
        </aside>
    );
}

function ThemePreview({ mode, children }: { mode: ThemeMode; children: ReactNode }) {
    return (
        <div className={`theme-${mode} grid content-start gap-5 rounded-xl border border-border bg-background p-4 text-foreground sm:p-6`}>
            <p className="text-sm text-muted-foreground">{mode === 'light' ? 'Light theme' : 'Dark theme'} · sample screen</p>
            {children}
        </div>
    );
}

export default function DesignSystem() {
    return (
        <>
            <Head title="Design system" />
            <header className="border-b border-border bg-background">
                <div className="mx-auto flex min-h-16 max-w-7xl items-center gap-3 px-4 sm:px-6">
                    <Link className="flex items-center gap-3 text-lg" href="/">
                        <BrandMark />
                        CivicLedger
                    </Link>
                    <span className="text-sm text-muted-foreground">Design system</span>
                    <div className="ml-auto flex items-center gap-2">
                        <ThemeToggle />
                        <Button asChild variant="outline">
                            <Link href="/">Home</Link>
                        </Button>
                    </div>
                </div>
            </header>

            <main className="mx-auto grid w-full max-w-7xl gap-12 px-cl-page py-10 sm:py-14">
                <section className="grid gap-4">
                    <p className="text-sm text-muted-foreground">CivicLedger · Interface reference</p>
                    <h1 className="font-display text-cl-head tracking-tight">A clear record, at every step.</h1>
                    <p className="max-w-3xl text-cl-body leading-7 text-muted-foreground">
                        A shared interface system for student service hours, staff review, certificate issuance, and public verification. Color reinforces a written status; it never replaces it.
                    </p>
                </section>

                <section className="grid gap-6" aria-labelledby="palette-heading">
                    <SectionTitle
                        title="Palette"
                        description="Navy carries the workspace and primary action. Teal marks links, focus, and confirmed integrity. Status colors remain reserved for record state."
                    />
                    <h3 id="palette-heading" className="sr-only">Light and dark color palettes</h3>
                    <div className="grid gap-6 xl:grid-cols-2">
                        <div className="theme-light grid content-start gap-4 rounded-xl border border-border bg-background p-4 text-foreground sm:p-6">
                            <h3 className="text-lg">Light theme</h3>
                            <SwatchGrid colors={palette.light} />
                        </div>
                        <div className="theme-dark grid content-start gap-4 rounded-xl border border-border bg-background p-4 text-foreground sm:p-6">
                            <h3 className="text-lg">Dark theme</h3>
                            <SwatchGrid colors={palette.dark} />
                        </div>
                    </div>
                    <Card>
                        <CardHeader>
                            <CardTitle>Live contrast checks</CardTitle>
                            <CardDescription>Measured from the active theme’s CSS variables.</CardDescription>
                        </CardHeader>
                        <CardContent><ContrastTable /></CardContent>
                    </Card>
                </section>

                <section className="grid gap-6" aria-labelledby="components-heading">
                    <SectionTitle
                        title="Components and states"
                        description="Controls keep the same 10px corners, visible two-pixel focus ring, and 40px minimum interactive target."
                    />
                    <h3 id="components-heading" className="sr-only">Component examples</h3>
                    <div className="grid gap-6 xl:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Buttons</CardTitle>
                                <CardDescription>Primary action is navy in light theme and teal in dark theme.</CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-wrap items-center gap-3">
                                <Button>Default</Button>
                                <Button variant="secondary">Secondary</Button>
                                <Button variant="outline">Outline</Button>
                                <Button variant="destructive">Destructive</Button>
                                <Button disabled>Disabled</Button>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Alerts</CardTitle>
                                <CardDescription>Each state uses an icon, a heading, and explanatory text.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                <Alert variant="success">
                                    <CheckCircle2 aria-hidden="true" />
                                    <AlertTitle>Hours approved</AlertTitle>
                                    <AlertDescription>The approved time now counts toward the service total.</AlertDescription>
                                </Alert>
                                <Alert variant="pending">
                                    <Clock3 aria-hidden="true" />
                                    <AlertTitle>Review pending</AlertTitle>
                                    <AlertDescription>A coordinator has not recorded a decision yet.</AlertDescription>
                                </Alert>
                                <Alert variant="danger">
                                    <AlertCircle aria-hidden="true" />
                                    <AlertTitle>Record mismatch</AlertTitle>
                                    <AlertDescription>The submitted record does not match its confirmed commitment.</AlertDescription>
                                </Alert>
                                <Alert variant="neutral">
                                    <Info aria-hidden="true" />
                                    <AlertTitle>Proof queued</AlertTitle>
                                    <AlertDescription>The integrity commitment is waiting to be submitted.</AlertDescription>
                                </Alert>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Status badges</CardTitle>
                                <CardDescription>Approval, proof, validity, and public checks use separate vocabularies.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-5">
                                {statusGroups.map(({ title, statuses }) => (
                                    <div key={title} className="grid gap-2">
                                        <h3 className="text-sm text-muted-foreground">{title}</h3>
                                        <div className="flex flex-wrap gap-2">
                                            {statuses.map((status) => <Status key={status} value={status} />)}
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Inputs</CardTitle>
                                <CardDescription>The error state is associated with its visible message.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid max-w-md gap-2">
                                <Label htmlFor="sample-email">Email address</Label>
                                <Input
                                    id="sample-email"
                                    type="email"
                                    defaultValue="not-an-email"
                                    aria-invalid="true"
                                    aria-describedby="sample-email-error"
                                />
                                <p id="sample-email-error" className="flex items-center gap-2 text-sm text-status-danger-foreground">
                                    <AlertCircle className="size-4 text-status-danger-border" aria-hidden="true" />
                                    Enter a valid email address.
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Dialog</CardTitle>
                                <CardDescription>Opens on request, focuses its content, and returns focus to the trigger.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Dialog>
                                    <DialogTrigger asChild>
                                        <Button variant="outline">Open dialog</Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>Approve service hours?</DialogTitle>
                                            <DialogDescription>
                                                The reviewed attendance duration will be added to the student’s approved service total.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <DialogFooter>
                                            <DialogClose asChild><Button variant="outline">Cancel</Button></DialogClose>
                                            <DialogClose asChild><Button>Approve hours</Button></DialogClose>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Toast</CardTitle>
                                <CardDescription>Short confirmation with an icon and readable message.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Toast title="Changes saved" variant="success">
                                    The service record is ready for approval.
                                </Toast>
                            </CardContent>
                        </Card>

                        <Card className="xl:col-span-2">
                            <CardHeader>
                                <CardTitle>Selected table row</CardTitle>
                                <CardDescription>Selection uses a quiet neutral surface, separate from status color.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableCaption>Recent service records assigned to this coordinator.</TableCaption>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Student</TableHead>
                                            <TableHead>Activity</TableHead>
                                            <TableHead>Submitted</TableHead>
                                            <TableHead>Approval</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow data-state="selected">
                                            <TableCell>A. Santos</TableCell>
                                            <TableCell>Community garden clean-up</TableCell>
                                            <TableCell>14 Oct 2026</TableCell>
                                            <TableCell><Status value="pending" /></TableCell>
                                        </TableRow>
                                        <TableRow>
                                            <TableCell>M. Reyes</TableCell>
                                            <TableCell>Reading support</TableCell>
                                            <TableCell>13 Oct 2026</TableCell>
                                            <TableCell><Status value="approved" /></TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </div>
                </section>

                <section className="grid gap-6" aria-labelledby="sidebar-heading">
                    <SectionTitle
                        title="Sidebar navigation"
                        description="Dedicated sidebar colors keep labels, active navigation, brand mark, and account controls legible on the deep navy surface in both themes."
                    />
                    <h3 id="sidebar-heading" className="sr-only">Light and dark sidebar previews</h3>
                    <div className="grid gap-4 md:grid-cols-2">
                        <ThemePreview mode="light"><SidebarPreview /></ThemePreview>
                        <ThemePreview mode="dark"><SidebarPreview /></ThemePreview>
                    </div>
                </section>

                <section className="grid gap-6" aria-labelledby="sample-screens-heading">
                    <SectionTitle
                        title="Sample screens"
                        description="The same record details remain readable in both themes. Public verification distinguishes a confirmed match from a mismatch."
                    />
                    <h3 id="sample-screens-heading" className="sr-only">Coordinator approval and public verification in light and dark themes</h3>
                    <div className="grid gap-6">
                        <div className="grid gap-3">
                            <h3 className="text-lg">Coordinator approval detail</h3>
                            <div className="grid gap-4 xl:grid-cols-2">
                                <ThemePreview mode="light"><ApprovalDetailSample /></ThemePreview>
                                <ThemePreview mode="dark"><ApprovalDetailSample /></ThemePreview>
                            </div>
                        </div>
                        <div className="grid gap-3">
                            <h3 className="text-lg">Public certificate verification</h3>
                            <div className="grid gap-4 xl:grid-cols-2">
                                <ThemePreview mode="light"><VerificationSample /></ThemePreview>
                                <ThemePreview mode="dark"><VerificationSample /></ThemePreview>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
            <footer className="border-t border-border px-cl-page py-6 text-center text-sm text-muted-foreground">
                CivicLedger · Interface reference
            </footer>
        </>
    );
}
