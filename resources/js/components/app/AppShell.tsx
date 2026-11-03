import { type ReactNode } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Award,
    BarChart3,
    CalendarDays,
    ChevronDown,
    ClipboardCheck,
    Clock3,
    Fingerprint,
    LayoutDashboard,
    LogOut,
    QrCode,
    UserRound,
    Users,
} from 'lucide-react';

import { BrandMark } from '@/components/app/BrandMark';

import { ThemeToggle } from '@/components/app/ThemeToggle';

import { csrfToken } from '@/components/app/Primitives';

import type { SharedProps } from '@/types/app';

export function AppShell({ title, children }: { title: string; children: ReactNode }) {
    const page = usePage<SharedProps>();
    const { auth, errors, flash } = page.props;
    const permissions = auth?.user?.permissions ?? {};
    const links = [
        { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard, visible: true },
        { label: 'Events', href: '/events', icon: CalendarDays, visible: true },
        { label: 'Scan attendance', href: '/attendance/scan', icon: QrCode, visible: !permissions['users.manage'] && !permissions['hours.review'] && !permissions['certificates.issue'] },
        { label: 'My hours', href: '/my/hours', icon: Clock3, visible: !permissions['users.manage'] && !permissions['hours.review'] && !permissions['certificates.issue'] },
        { label: 'Review hours', href: '/hours/review', icon: ClipboardCheck, visible: Boolean(permissions['hours.review']) },
        { label: 'Proof status', href: '/proofs', icon: Fingerprint, visible: Boolean(permissions['hours.review']) },
        { label: 'Certificates', href: '/certificates', icon: Award, visible: true },
        { label: 'Reports', href: '/reports', icon: BarChart3, visible: Boolean(permissions['reports.view']) },
        { label: 'Users', href: '/admin/users', icon: Users, visible: Boolean(permissions['users.manage']) },
        { label: 'Audit log', href: '/admin/audit', icon: Clock3, visible: Boolean(permissions['audit.view']) },
    ].filter((link) => link.visible);
    const currentPath = page.url.split('?')[0];
    const userName = [auth?.user?.first_name, auth?.user?.last_name]
        .filter((name): name is string => typeof name === 'string' && name.length > 0)
        .join(' ') || 'Your account';
    const initials = [auth?.user?.first_name?.[0], auth?.user?.last_name?.[0]]
        .filter((initial): initial is string => typeof initial === 'string')
        .join('');
    const navigationItems = links.map((link) => {
        const Icon = link.icon;
        const isCurrent = currentPath === link.href || currentPath.startsWith(`${link.href}/`);

        return (
            <Link
                key={link.href}
                href={link.href}
                aria-current={isCurrent ? 'page' : undefined}
                className={`flex min-h-10 items-center gap-2 rounded-lg px-3 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${isCurrent ? 'bg-muted text-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}
            >
                <Icon className="size-4" aria-hidden="true" />
                {link.label}
            </Link>
        );
    });

    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <Head title={title} />
            <header className="sticky top-0 z-20 border-b border-border bg-background">
                <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex min-h-16 min-w-0 items-center gap-3">
                        <Link href="/" className="flex shrink-0 items-center gap-2 text-base font-semibold text-foreground">
                            <BrandMark variant="sidebar" />
                            <span className="hidden sm:inline">CivicLedger</span>
                        </Link>
                        <span className="hidden min-w-0 truncate text-sm font-medium md:inline">{title}</span>
                        <div className="ml-auto flex min-w-0 items-center gap-1">
                            {auth?.user && (
                                <>
                                    <Link
                                        href="/profile"
                                        className="flex min-w-0 items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-foreground transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        aria-label={`Profile: ${userName}`}
                                    >
                                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium text-foreground">
                                            {initials || <UserRound className="size-4" aria-hidden="true" />}
                                        </span>
                                        <span className="max-w-20 truncate sm:max-w-48">{userName}</span>
                                    </Link>
                                    <ThemeToggle />
                                    <form action="/logout" method="post">
                                        <input type="hidden" name="_token" value={csrfToken()} />
                                        <Button type="submit" variant="ghost" size="icon" aria-label="Sign out" title="Sign out">
                                            <LogOut />
                                        </Button>
                                    </form>
                                </>
                            )}
                        </div>
                    </div>
                </div>
                <details className="group border-t border-border lg:hidden">
                    <summary className="flex min-h-11 cursor-pointer list-none items-center justify-between px-4 text-sm font-medium marker:hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:px-6">
                        Workspace navigation
                        <ChevronDown className="size-4 transition-transform group-open:rotate-180" aria-hidden="true" />
                    </summary>
                    <nav className="grid gap-1 px-4 pb-4 sm:px-6" aria-label="Workspace navigation">
                        {navigationItems}
                    </nav>
                </details>
            </header>
            <div className="mx-auto flex min-h-0 w-full max-w-7xl flex-1">
                <aside className="hidden w-56 shrink-0 border-r border-border lg:block">
                    <nav className="sticky top-16 grid max-h-[calc(100vh-4rem)] gap-1 overflow-y-auto py-6 pr-4" aria-label="Workspace sidebar">
                        {navigationItems}
                    </nav>
                </aside>
                <div className="flex min-w-0 flex-1 flex-col">
                    <main className="mx-auto grid w-full flex-1 content-start gap-6 p-4 pt-6 sm:p-6 lg:px-8 lg:pt-8">
                        {flash?.success && (
                            <Alert variant="success" role="status" className="mb-6">
                                <AlertTitle>Success</AlertTitle>
                                <AlertDescription>{flash.success}</AlertDescription>
                            </Alert>
                        )}
                        {Object.keys(errors ?? {}).length > 0 && (
                            <Alert variant="destructive" className="mb-6">
                                <AlertTitle>Please check the following</AlertTitle>
                                <AlertDescription>
                                    <ul className="list-inside list-disc">
                                        {Object.values(errors ?? {}).map((message, index) => <li key={`${index}-${message}`}>{message}</li>)}
                                    </ul>
                                </AlertDescription>
                            </Alert>
                        )}
                        {children}
                    </main>
                    <footer className="border-t px-4 py-4 text-center text-xs text-muted-foreground sm:px-6">
                        CivicLedger records approved service hours. Approval, proof, and certificate validity are shown separately.
                    </footer>
                </div>
            </div>
        </div>
    );
}
