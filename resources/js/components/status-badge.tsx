import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type Tone = 'muted' | 'warning' | 'info' | 'success' | 'destructive';

const STATUS_TONE: Record<string, Tone> = {
    draft: 'muted',
    pending_approval: 'warning',
    in_transit: 'info',
    approved: 'success',
    released: 'success',
    received: 'success',
    posted: 'success',
    rejected: 'destructive',
    cancelled: 'destructive',
};

const TONE_CLASS: Record<Tone, string> = {
    muted: 'bg-muted text-muted-foreground',
    warning: 'bg-warning/15 text-warning-foreground border-warning/30 dark:text-warning',
    info: 'bg-info/15 text-info border-info/30',
    success: 'bg-success/15 text-success border-success/30',
    destructive: 'bg-destructive/15 text-destructive border-destructive/30',
};

function humanize(status: string): string {
    return status
        .split('_')
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}

export function StatusBadge({
    status,
    className,
}: {
    status: string;
    className?: string;
}) {
    const tone = STATUS_TONE[status] ?? 'muted';
    return (
        <Badge
            variant="outline"
            className={cn('border font-medium capitalize', TONE_CLASS[tone], className)}
        >
            {humanize(status)}
        </Badge>
    );
}
