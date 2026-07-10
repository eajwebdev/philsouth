import * as React from 'react';
import { Check, ChevronsUpDown } from 'lucide-react';
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
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { CatalogItem } from '@/components/line-items-editor';

interface FlatOption {
    id: number;
    label: string;
    sub: string;
}

function flatten(items: CatalogItem[]): FlatOption[] {
    return items.flatMap((item) =>
        item.variants.map((v) => ({
            id: v.id,
            label: item.has_variants && v.label ? `${item.description} — ${v.label}` : item.description,
            sub: v.sku,
        })),
    );
}

export function VariantPicker({
    items,
    value,
    onChange,
    placeholder = 'Select item…',
}: {
    items: CatalogItem[];
    value: number | null;
    onChange: (id: number) => void;
    placeholder?: string;
}) {
    const [open, setOpen] = React.useState(false);
    const options = React.useMemo(() => flatten(items), [items]);
    const selected = options.find((o) => o.id === value);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button variant="outline" role="combobox" aria-expanded={open} className="w-full justify-between">
                    {selected ? (
                        <span className="flex min-w-0 flex-col items-start">
                            <span className="truncate">{selected.label}</span>
                            <span className="font-mono text-xs text-muted-foreground">{selected.sub}</span>
                        </span>
                    ) : (
                        <span className="text-muted-foreground">{placeholder}</span>
                    )}
                    <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-[--radix-popover-trigger-width] p-0" align="start">
                <Command>
                    <CommandInput placeholder="Search item or SKU…" />
                    <CommandList>
                        <CommandEmpty>No items found.</CommandEmpty>
                        <CommandGroup>
                            {options.map((o) => (
                                <CommandItem
                                    key={o.id}
                                    value={`${o.label} ${o.sub}`}
                                    onSelect={() => { onChange(o.id); setOpen(false); }}
                                >
                                    <Check className={cn('mr-2 size-4', o.id === value ? 'opacity-100' : 'opacity-0')} />
                                    <span className="flex flex-col">
                                        <span>{o.label}</span>
                                        <span className="font-mono text-xs text-muted-foreground">{o.sub}</span>
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
