import * as React from 'react';
import { router, usePage } from '@inertiajs/react';
import { Bell, ClipboardList, Check, X, Truck, CheckCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

interface NotificationItem {
    id: string;
    title: string;
    message: string;
    url: string | null;
    icon: string;
    read: boolean;
    at: string;
}
interface Shared {
    notifications?: { unread: number; items: NotificationItem[] };
}

const ICONS: Record<string, typeof Bell> = {
    bell: Bell,
    'clipboard-list': ClipboardList,
    check: Check,
    x: X,
    truck: Truck,
};

export function NotificationBell() {
    const { notifications } = usePage<Shared>().props;
    const unread = notifications?.unread ?? 0;
    const items = notifications?.items ?? [];
    const [open, setOpen] = React.useState(false);

    const markRead = (id?: string) =>
        router.post(route('notifications.read', id ? { id } : {}), {}, { preserveScroll: true, preserveState: true });

    const openItem = (n: NotificationItem) => {
        setOpen(false);
        if (!n.read) markRead(n.id);
        if (n.url) router.visit(n.url);
    };

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon-sm" className="relative" aria-label={`Notifications${unread ? ` (${unread} unread)` : ''}`}>
                    <Bell className="size-5" />
                    {unread > 0 && (
                        <span className="absolute -right-0.5 -top-0.5 flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-semibold leading-4 text-destructive-foreground">
                            {unread > 9 ? '9+' : unread}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80 p-0">
                <div className="flex items-center justify-between border-b px-3 py-2">
                    <span className="text-sm font-semibold">Notifications</span>
                    {unread > 0 && (
                        <button
                            onClick={() => markRead()}
                            className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                        >
                            <CheckCheck className="size-3.5" /> Mark all read
                        </button>
                    )}
                </div>
                <div className="max-h-96 overflow-y-auto">
                    {items.length === 0 ? (
                        <p className="px-3 py-8 text-center text-sm text-muted-foreground">You're all caught up.</p>
                    ) : (
                        items.map((n) => {
                            const Icon = ICONS[n.icon] ?? Bell;
                            return (
                                <button
                                    key={n.id}
                                    onClick={() => openItem(n)}
                                    className={cn(
                                        'flex w-full items-start gap-3 border-b px-3 py-2.5 text-left last:border-0 hover:bg-muted/60',
                                        !n.read && 'bg-primary/5',
                                    )}
                                >
                                    <span className={cn(
                                        'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg',
                                        n.read ? 'bg-muted text-muted-foreground' : 'bg-primary/10 text-primary',
                                    )}>
                                        <Icon className="size-4" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="flex items-center gap-2">
                                            <span className="truncate text-sm font-medium">{n.title}</span>
                                            {!n.read && <span className="size-1.5 shrink-0 rounded-full bg-primary" />}
                                        </span>
                                        <span className="line-clamp-2 text-xs text-muted-foreground">{n.message}</span>
                                        <span className="text-[11px] text-muted-foreground/70">{n.at}</span>
                                    </span>
                                </button>
                            );
                        })
                    )}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
