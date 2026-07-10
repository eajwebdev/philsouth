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
    ...props
}: IconButtonProps) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant={variant}
                    size={size}
                    aria-label={label}
                    className={cn(className)}
                    {...props}
                >
                    {children}
                    <span className="sr-only">{label}</span>
                </Button>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}
