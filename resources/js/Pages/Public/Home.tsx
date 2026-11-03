import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Status } from '@/components/app/Primitives';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { BrandMark } from '@/components/app/BrandMark';
import { ThemeToggle } from '@/components/app/ThemeToggle';
import type { SharedProps } from '@/types/app';

const journey = [
    {
        title: 'Plan',
        description: 'Coordinators publish activities for eligible sections; students register.',
    },
    {
        title: 'Record',
        description: 'Students check in and out using a rotating QR challenge.',
    },
    {
        title: 'Review',
        description: 'Coordinators review attendance and approve eligible hours.',
    },
    {
        title: 'Issue and verify',
        description: 'Authorized signers issue supplementary certificates that anyone can check.',
    },
];

function SectionHeading({ title, description }: { title: string; description: string }) {
    return (
        <div className="mb-8 max-w-2xl space-y-2">
            <h2 className="font-display text-cl-head font-semibold tracking-tight">{title}</h2>
            <p className="text-sm leading-6 text-muted-foreground sm:text-base">{description}</p>
        </div>
    );
}

export default function Home() {
    const { auth } = usePage<SharedProps>().props;
    const workspaceHref = auth?.user ? '/dashboard' : '/login';
    const workspaceLabel = auth?.user ? 'Open workspace' : 'Sign in to CivicLedger';

    return (
        <>
            <Head title="Service hours, accounted for." />
            <a className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:bg-background focus:p-3" href="#main">
                Skip to content
            </a>
            <header className="sticky top-0 z-30 border-b bg-background">
                <div className="mx-auto flex h-16 max-w-7xl items-center gap-4 px-cl-page">
                    <Link className="flex shrink-0 items-center gap-3 text-lg font-semibold" href="/">
                        <BrandMark />
                        CivicLedger
                    </Link>
                    <nav className="ml-auto hidden items-center gap-6 text-sm text-muted-foreground lg:flex" aria-label="Primary">
                        <a className="transition-colors duration-150 ease-cl-out hover:text-foreground" href="#what">The record</a>
                        <a className="transition-colors duration-150 ease-cl-out hover:text-foreground" href="#how">How it works</a>
                    </nav>
                    <div className="ml-auto flex items-center gap-2 lg:ml-0">
                        <ThemeToggle />
                        <Button asChild size="sm">
                            <Link href={workspaceHref}>{auth?.user ? 'Workspace' : 'Sign in'}</Link>
                        </Button>
                    </div>
                </div>
            </header>

            <main id="main">
                <section className="border-b">
                    <div className="mx-auto grid max-w-7xl gap-8 px-cl-page py-cl-section lg:grid-cols-[minmax(0,1.7fr)_minmax(17rem,0.75fr)] lg:items-end">
                        <h1 className="font-display text-[clamp(3.25rem,8vw,7.5rem)] font-semibold leading-[0.98] tracking-[-0.06em]">
                            Service hours,
                            <span className="block">accounted for.</span>
                        </h1>
                        <div className="grid max-w-xl gap-5 lg:justify-self-end">
                            <p className="text-cl-lead leading-7 text-muted-foreground">
                                CivicLedger follows school service records from event registration and attendance through staff review and certificate checks.
                            </p>
                            <Button asChild size="lg" className="w-fit">
                                <Link href={workspaceHref}>{workspaceLabel}</Link>
                            </Button>
                        </div>
                    </div>
                </section>

                <section className="px-cl-page py-cl-section" id="what">
                    <div className="mx-auto grid max-w-7xl items-start gap-cl-gutter lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
                        <div>
                            <SectionHeading
                                title="One record, separate checks"
                                description="Attendance, hour approval, proof status, and certificate validity answer different questions."
                            />
                            <p className="max-w-xl text-sm leading-6 text-muted-foreground">
                                Only approved hours count toward official totals. A matching integrity proof shows that a record has not changed since it was committed; it does not prove attendance.
                            </p>
                        </div>
                        <Card data-ui="service-record-preview" className="w-full">
                            <CardHeader className="flex flex-row items-start justify-between gap-4 space-y-0">
                                <div className="grid gap-1">
                                    <CardTitle>Service record</CardTitle>
                                    <CardDescription>Illustrative interface · synthetic example</CardDescription>
                                </div>
                                <Status value="pending" />
                            </CardHeader>
                            <CardContent>
                                <dl className="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                                    <div className="grid gap-1">
                                        <dt className="text-sm text-muted-foreground">Attendance</dt>
                                        <dd className="text-sm font-medium">Submitted for review</dd>
                                    </div>
                                    <div className="grid gap-1">
                                        <dt className="text-sm text-muted-foreground">Approved hours</dt>
                                        <dd className="text-sm font-medium">Not counted yet</dd>
                                    </div>
                                    <div className="grid gap-1">
                                        <dt className="text-sm text-muted-foreground">Integrity proof</dt>
                                        <dd className="text-sm font-medium">Not anchored</dd>
                                    </div>
                                    <div className="grid gap-1">
                                        <dt className="text-sm text-muted-foreground">Certificate</dt>
                                        <dd className="text-sm font-medium">Not issued</dd>
                                    </div>
                                </dl>
                            </CardContent>
                            <CardFooter className="text-sm leading-6 text-muted-foreground">
                                Approval, proof, and certificate status stay distinct.
                            </CardFooter>
                        </Card>
                    </div>
                </section>

                <section className="px-cl-page py-cl-section" id="how">
                    <div className="mx-auto max-w-7xl">
                        <SectionHeading
                            title="From event to certificate"
                            description="A clear sequence keeps activity, attendance, approval, and verification connected."
                        />
                        <ol className="grid gap-x-10 gap-y-8 sm:grid-cols-2 xl:grid-cols-4">
                            {journey.map(({ title, description }) => (
                                <li key={title} className="grid content-start gap-2">
                                    <h3 className="text-lg font-medium tracking-tight">{title}</h3>
                                    <p className="text-sm leading-6 text-muted-foreground">{description}</p>
                                </li>
                            ))}
                        </ol>
                    </div>
                </section>

                <section className="border-t px-cl-page py-cl-section">
                    <div className="mx-auto flex max-w-7xl flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                        <div className="max-w-2xl space-y-2">
                            <h2 className="font-display text-2xl font-semibold tracking-tight">Manage school service records</h2>
                            <p className="text-sm leading-6 text-muted-foreground">
                                Sign in to open the role-scoped workspace. Public checks are available from issued certificates.
                            </p>
                        </div>
                        <Button asChild size="lg" className="w-fit">
                            <Link href={workspaceHref}>{workspaceLabel}</Link>
                        </Button>
                    </div>
                </section>
            </main>
            <footer className="border-t px-cl-page py-6 text-xs leading-5 text-muted-foreground">
                <div className="mx-auto max-w-7xl">
                    <span>CivicLedger</span>
                </div>
            </footer>
        </>
    );
}
