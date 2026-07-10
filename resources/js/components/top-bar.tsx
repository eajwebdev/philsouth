import * as React from 'react';
import { Link } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import { Sheet, SheetContent, SheetTrigger, SheetTitle } from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import { AppLogo } from '@/components/app-logo';
import { NavLinks } from '@/components/nav-sidebar';
import { SiteSwitcher } from '@/components/site-switcher';
import { ThemeToggle } from '@/components/theme-toggle';
import { UserMenu } from '@/components/user-menu';

export function TopBar() {
    const [mobileOpen, setMobileOpen] = React.useState(false);

    return (
        <header className="sticky top-0 z-30 flex h-16 items-center gap-2 border-b border-border bg-background/85 px-4 backdrop-blur-md sm:px-6">
            {/* Mobile menu */}
            <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
                <SheetTrigger asChild>
                    <Button variant="ghost" size="icon-sm" className="md:hidden" aria-label="Open menu">
                        <Menu className="size-5" />
                    </Button>
                </SheetTrigger>
                <SheetContent side="left" className="w-72 bg-sidebar p-0">
                    <SheetTitle className="sr-only">Navigation</SheetTitle>
                    <div className="flex h-16 items-center border-b border-sidebar-border px-5">
                        <AppLogo />
                    </div>
                    <NavLinks onNavigate={() => setMobileOpen(false)} />
                </SheetContent>
            </Sheet>

            <div className="md:hidden">
                <Link href={route('dashboard')}>
                    <AppLogo showWordmark={false} />
                </Link>
            </div>

            <div className="hidden sm:block">
                <SiteSwitcher />
            </div>

            <div className="ml-auto flex items-center gap-1.5">
                <ThemeToggle />
                <UserMenu />
            </div>
        </header>
    );
}
