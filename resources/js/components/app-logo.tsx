import { cn } from '@/lib/utils';

export function AppLogo({
    className,
    showWordmark = true,
}: {
    className?: string;
    showWordmark?: boolean;
}) {
    return (
        <div className={cn('flex items-center gap-2.5', className)}>
            <img
                src="/logo.png"
                alt="PhilSouth Builders"
                className="size-9 shrink-0 rounded-lg object-contain ring-1 ring-border"
            />
            {showWordmark && (
                <div className="flex flex-col leading-tight">
                    <span className="text-sm font-bold tracking-tight text-foreground">
                        PhilSouth
                    </span>
                    <span className="text-[11px] font-medium text-muted-foreground">
                        Inventory
                    </span>
                </div>
            )}
        </div>
    );
}
