import { Link, usePage } from '@inertiajs/react';
import { AppLogo } from '@/components/app-logo';
import { NAV_SECTIONS, type NavSection } from '@/lib/nav';
import { useAuth } from '@/hooks/use-auth';
import { cn } from '@/lib/utils';

function useVisibleSections(): NavSection[] {
    const { can } = useAuth();
    return NAV_SECTIONS.map((section) => ({
        ...section,
        items: section.items.filter(
            (item) => !item.permissions || item.permissions.some((p) => can(p)),
        ),
    })).filter((section) => section.items.length > 0);
}

export function NavLinks({ onNavigate }: { onNavigate?: () => void }) {
    const sections = useVisibleSections();
    const page = usePage();
    const currentComponent = page.component;

    const isActive = (activeMatch?: string[], routeName?: string) => {
        try {
            if (routeName && route().current(routeName)) return true;
            return activeMatch?.some((m) => route().current(m + '*')) ?? false;
        } catch {
            return false;
        }
    };

    void currentComponent;

    return (
        <nav className="flex flex-1 flex-col gap-6 px-3 py-4">
            {sections.map((section, i) => (
                <div key={i} className="flex flex-col gap-1">
                    {section.heading && (
                        <p className="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-sidebar-foreground/50">
                            {section.heading}
                        </p>
                    )}
                    {section.items.map((item) => {
                        const active = isActive(item.activeMatch, item.routeName);
                        const Icon = item.icon;
                        return (
                            <Link
                                key={item.routeName}
                                href={route(item.routeName)}
                                onClick={onNavigate}
                                className={cn(
                                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                    active
                                        ? 'bg-sidebar-primary text-sidebar-primary-foreground shadow-sm'
                                        : 'text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                                )}
                            >
                                <Icon className="size-4 shrink-0" />
                                {item.label}
                            </Link>
                        );
                    })}
                </div>
            ))}
        </nav>
    );
}

export function NavSidebar() {
    return (
        <aside className="hidden w-64 shrink-0 flex-col border-r border-sidebar-border bg-sidebar md:flex">
            <div className="flex h-16 items-center border-b border-sidebar-border px-5">
                <Link href={route('dashboard')}>
                    <AppLogo />
                </Link>
            </div>
            <NavLinks />
            <div className="border-t border-sidebar-border px-5 py-3 text-xs text-sidebar-foreground/50">
                PhilSouth Builders Inc.
            </div>
        </aside>
    );
}
