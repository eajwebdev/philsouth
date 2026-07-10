import * as React from 'react';
import { Check, ChevronsUpDown, X } from 'lucide-react';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export interface Option {
    value: number | string;
    label: string;
    description?: string;
}

interface MultiSelectProps {
    options: Option[];
    selected: (number | string)[];
    onChange: (values: (number | string)[]) => void;
    placeholder?: string;
    emptyText?: string;
    disabled?: boolean;
}

export function MultiSelect({
    options,
    selected,
    onChange,
    placeholder = 'Select…',
    emptyText = 'No options.',
    disabled,
}: MultiSelectProps) {
    const [open, setOpen] = React.useState(false);

    const toggle = (value: number | string) => {
        onChange(
            selected.includes(value)
                ? selected.filter((v) => v !== value)
                : [...selected, value],
        );
    };

    const selectedOptions = options.filter((o) => selected.includes(o.value));

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    disabled={disabled}
                    className="h-auto min-h-9 w-full justify-between gap-1 py-1.5"
                >
                    <span className="flex flex-wrap gap-1">
                        {selectedOptions.length === 0 && (
                            <span className="text-muted-foreground">{placeholder}</span>
                        )}
                        {selectedOptions.map((o) => (
                            <Badge
                                key={o.value}
                                variant="secondary"
                                className="gap-1"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    toggle(o.value);
                                }}
                            >
                                {o.label}
                                <X className="size-3" />
                            </Badge>
                        ))}
                    </span>
                    <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-[--radix-popover-trigger-width] p-0" align="start">
                <Command>
                    <CommandInput placeholder="Search…" />
                    <CommandList>
                        <CommandEmpty>{emptyText}</CommandEmpty>
                        <CommandGroup>
                            {options.map((o) => (
                                <CommandItem
                                    key={o.value}
                                    value={o.label}
                                    onSelect={() => toggle(o.value)}
                                >
                                    <Check
                                        className={cn(
                                            'mr-2 size-4',
                                            selected.includes(o.value) ? 'opacity-100' : 'opacity-0',
                                        )}
                                    />
                                    <span className="flex flex-col">
                                        <span>{o.label}</span>
                                        {o.description && (
                                            <span className="text-xs text-muted-foreground">
                                                {o.description}
                                            </span>
                                        )}
                                    </span>
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
