import * as React from 'react';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { VariantProps } from 'class-variance-authority';

type IconButtonProps = React.ComponentProps<'button'> &
    VariantProps<typeof buttonVariants> & {
        label: string;
        asChild?: boolean;
    };

/**
 * Icon-only action button with an accessible tooltip + aria-label.
 * The tooltip provides the visible label on hover and screen-reader text.
 */
export function IconButton({
    label,
    variant = 'ghost',
    size = 'icon-sm',
    className,
    children,
    asChild,
    ...props
}: IconButtonProps) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type={asChild ? undefined : 'button'}
                    variant={variant}
                    size={size}
                    aria-label={label}
                    className={cn(className)}
                    asChild={asChild}
                    {...props}
                >
                    {/* With asChild the Button becomes a Radix Slot, which requires
                        a single child — so skip the extra sr-only span (aria-label
                        already supplies the accessible name). */}
                    {asChild ? children : (
                        <>
                            {children}
                            <span className="sr-only">{label}</span>
                        </>
                    )}
                </Button>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}
