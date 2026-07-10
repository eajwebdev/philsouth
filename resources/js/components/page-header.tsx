import * as React from 'react';
import { cn } from '@/lib/utils';

export function PageHeader({
    title,
    description,
    icon: Icon,
    actions,
    className,
}: {
    title: string;
    description?: string;
    icon?: React.ComponentType<{ className?: string }>;
    actions?: React.ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between', className)}>
            <div className="flex items-center gap-3">
                {Icon && (
                    <div className="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <Icon className="size-5" />
                    </div>
                )}
                <div>
                    <h1 className="text-xl font-semibold tracking-tight text-foreground">{title}</h1>
                    {description && (
                        <p className="text-sm text-muted-foreground">{description}</p>
                    )}
                </div>
            </div>
            {actions && <div className="flex items-center gap-2">{actions}</div>}
        </div>
    );
}
