import * as React from 'react';
import { format } from 'date-fns';
import { CalendarIcon, X } from 'lucide-react';
import { DayPicker, type DateRange } from 'react-day-picker';
import 'react-day-picker/style.css';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export type { DateRange };

/**
 * Single-control date range picker: one button showing the literal range
 * ("Jun 1, 2026 – Jun 30, 2026") opening a two-month range calendar.
 */
export function DateRangePicker({
    value,
    onChange,
    placeholder = 'All dates',
    className,
}: {
    value: DateRange | undefined;
    onChange: (range: DateRange | undefined) => void;
    placeholder?: string;
    className?: string;
}) {
    const [open, setOpen] = React.useState(false);

    const label = value?.from
        ? value.to
            ? `${format(value.from, 'MMM d, yyyy')} – ${format(value.to, 'MMM d, yyyy')}`
            : format(value.from, 'MMM d, yyyy')
        : null;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    className={cn('justify-start gap-2 font-normal', !label && 'text-muted-foreground', className)}
                >
                    <CalendarIcon className="size-4 shrink-0" />
                    <span className="truncate">{label ?? placeholder}</span>
                    {label && (
                        <span
                            role="button"
                            aria-label="Clear date range"
                            className="ml-auto rounded-sm p-0.5 hover:bg-muted"
                            onClick={(e) => {
                                e.stopPropagation();
                                onChange(undefined);
                            }}
                        >
                            <X className="size-3.5" />
                        </span>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-2" align="start">
                <DayPicker
                    mode="range"
                    numberOfMonths={2}
                    selected={value}
                    defaultMonth={value?.from}
                    onSelect={(range) => {
                        onChange(range);
                        if (range?.from && range?.to) setOpen(false);
                    }}
                    classNames={{
                        today: 'font-bold text-primary',
                    }}
                    styles={{
                        root: { '--rdp-accent-color': 'hsl(var(--primary))', margin: 0 } as React.CSSProperties,
                    }}
                />
            </PopoverContent>
        </Popover>
    );
}
