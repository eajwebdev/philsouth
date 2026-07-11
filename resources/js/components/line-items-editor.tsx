import * as React from 'react';
import { toast } from 'sonner';
import { Plus, Trash2, PackageSearch } from 'lucide-react';
import { ScanField } from '@/components/scan-field';
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
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { IconButton } from '@/components/icon-button';
import { QuickItemDialog } from '@/components/quick-item-dialog';
import { useAuth } from '@/hooks/use-auth';

export interface CatalogVariant {
    id: number;
    sku: string;
    label: string | null;
    uom: string;
    barcode: string | null;
    is_default: boolean;
}
export interface CatalogItem {
    id: number;
    code: string;
    description: string;
    uom: string;
    has_variants: boolean;
    variants: CatalogVariant[];
}

export interface LineItem {
    item_variant_id: number | null;
    quantity: string;
    unit?: string;
    unit_cost?: string;
}

interface FlatOption {
    id: number;
    itemCode: string;
    itemDescription: string;
    variantLabel: string | null;
    sku: string;
    uom: string;
    barcode: string | null;
    hasVariants: boolean;
}

function flatten(items: CatalogItem[]): FlatOption[] {
    return items.flatMap((item) =>
        item.variants.map((v) => ({
            id: v.id,
            itemCode: item.code,
            itemDescription: item.description,
            variantLabel: item.has_variants ? v.label ?? v.sku : null,
            sku: v.sku,
            uom: v.uom || item.uom,
            barcode: v.barcode,
            hasVariants: item.has_variants,
        })),
    );
}

export function LineItemsEditor({
    items,
    value,
    onChange,
    withUnit = false,
    withCost = false,
    allowCreate = false,
    error,
}: {
    items: CatalogItem[];
    value: LineItem[];
    onChange: (lines: LineItem[]) => void;
    withUnit?: boolean;
    /** Show a per-line unit-cost field (receiving) so inventory can be valued. */
    withCost?: boolean;
    /** Show a "New item" button (requires items.manage) so paper-receipt items can be created inline. */
    allowCreate?: boolean;
    error?: string;
}) {
    const { can } = useAuth();
    const [pickerOpen, setPickerOpen] = React.useState(false);
    // Items created inline this session — merged with the server-provided catalog.
    const [created, setCreated] = React.useState<CatalogItem[]>([]);
    const catalog = React.useMemo(() => [...items, ...created], [items, created]);
    const options = React.useMemo(() => flatten(catalog), [catalog]);
    const byId = React.useMemo(() => new Map(options.map((o) => [o.id, o])), [options]);

    const chosenIds = new Set(value.map((l) => l.item_variant_id));

    const addVariant = (id: number) => {
        if (chosenIds.has(id)) return;
        onChange([...value, { item_variant_id: id, quantity: '', ...(withUnit ? { unit: '' } : {}) }]);
        setPickerOpen(false);
    };

    const onScan = (code: string) => {
        const match = options.find((o) => o.barcode && o.barcode === code);
        if (!match) {
            toast.error('Item not found', { description: `No active variant has barcode ${code}.` });
            return;
        }
        if (chosenIds.has(match.id)) {
            toast.info('Already added', { description: match.itemDescription });
            return;
        }
        addVariant(match.id);
        toast.success('Item added', { description: `${match.itemDescription} (${match.sku})` });
    };

    const updateLine = (index: number, patch: Partial<LineItem>) => {
        onChange(value.map((l, i) => (i === index ? { ...l, ...patch } : l)));
    };

    const removeLine = (index: number) => {
        onChange(value.filter((_, i) => i !== index));
    };

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="text-sm font-medium">Line items</span>
                <div className="flex items-center gap-2">
                {allowCreate && can('items.manage') && (
                    <QuickItemDialog
                        onCreated={(item) => {
                            setCreated((prev) => [...prev, item]);
                            const def = item.variants.find((v) => v.is_default) ?? item.variants[0];
                            if (def) {
                                onChange([...value, { item_variant_id: def.id, quantity: '', ...(withUnit ? { unit: def.uom } : {}) }]);
                            }
                        }}
                    />
                )}
                <Popover open={pickerOpen} onOpenChange={setPickerOpen}>
                    <PopoverTrigger asChild>
                        <Button type="button" variant="outline" size="sm">
                            <Plus /> Add item
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-80 p-0" align="end">
                        <Command>
                            <CommandInput placeholder="Search item, variant, SKU…" />
                            <CommandList>
                                <CommandEmpty>No matching items.</CommandEmpty>
                                <CommandGroup>
                                    {options
                                        .filter((o) => !chosenIds.has(o.id))
                                        .map((o) => (
                                            <CommandItem
                                                key={o.id}
                                                value={`${o.itemCode} ${o.itemDescription} ${o.variantLabel ?? ''} ${o.sku}`}
                                                onSelect={() => addVariant(o.id)}
                                            >
                                                <div className="flex flex-col">
                                                    <span className="font-medium">
                                                        {o.itemDescription}
                                                        {o.variantLabel && <span className="text-muted-foreground"> — {o.variantLabel}</span>}
                                                    </span>
                                                    <span className="font-mono text-xs text-muted-foreground">{o.sku}</span>
                                                </div>
                                            </CommandItem>
                                        ))}
                                </CommandGroup>
                            </CommandList>
                        </Command>
                    </PopoverContent>
                </Popover>
                </div>
            </div>

            <ScanField onScan={onScan} placeholder="Scan a barcode to add a line… (optional — you can add items manually)" />

            {value.length === 0 ? (
                <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed py-10 text-center">
                    <PackageSearch className="size-8 text-muted-foreground/50" />
                    <p className="text-sm text-muted-foreground">No items added yet. Scan or use “Add item”.</p>
                </div>
            ) : (
                <div className="rounded-xl border">
                    {value.map((line, i) => {
                        const opt = line.item_variant_id ? byId.get(line.item_variant_id) : undefined;
                        return (
                            <div
                                key={i}
                                className="flex flex-wrap items-center gap-3 border-b p-3 last:border-b-0"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">
                                        {opt?.itemDescription}
                                        {opt?.variantLabel && <span className="text-muted-foreground"> — {opt.variantLabel}</span>}
                                    </p>
                                    <p className="font-mono text-xs text-muted-foreground">{opt?.sku}</p>
                                </div>
                                {withUnit && (
                                    <Input
                                        value={line.unit ?? ''}
                                        onChange={(e) => updateLine(i, { unit: e.target.value })}
                                        placeholder="unit"
                                        className="w-24"
                                    />
                                )}
                                <div className="flex items-center gap-2">
                                    {withCost && (
                                        <div className="relative">
                                            <span className="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">₱</span>
                                            <Input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={line.unit_cost ?? ''}
                                                onChange={(e) => updateLine(i, { unit_cost: e.target.value })}
                                                placeholder="Unit cost"
                                                className="w-28 pl-5"
                                            />
                                        </div>
                                    )}
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={line.quantity}
                                        onChange={(e) => updateLine(i, { quantity: e.target.value })}
                                        placeholder="Qty"
                                        className="w-24"
                                    />
                                    <Badge variant="secondary" className="min-w-12 justify-center">{opt?.uom}</Badge>
                                    <IconButton
                                        label="Remove line"
                                        className="text-destructive hover:text-destructive"
                                        onClick={() => removeLine(i)}
                                    >
                                        <Trash2 />
                                    </IconButton>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}
