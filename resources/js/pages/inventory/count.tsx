import * as React from 'react';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { ClipboardCheck, Trash2, Check, TriangleAlert } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { ScanField } from '@/components/scan-field';
import { VariantPicker } from '@/components/variant-picker';
import { IconButton } from '@/components/icon-button';
import type { CatalogItem } from '@/components/line-items-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { toast } from 'sonner';
import { formatQty } from '@/lib/utils';
import type { SiteRef } from '@/types';

interface Props {
    sites: SiteRef[];
    items: CatalogItem[];
    canAdjust: boolean;
}

interface CountRow {
    variantId: number;
    label: string;
    sku: string;
    uom: string;
    system: number;
    counted: string;
    posting: boolean;
}

export default function PhysicalCount({ sites, items, canAdjust }: Props) {
    const [siteId, setSiteId] = React.useState<string>(sites[0]?.id ? String(sites[0].id) : '');
    const [rows, setRows] = React.useState<CountRow[]>([]);
    const [manual, setManual] = React.useState<number | null>(null);

    const addVariant = async (variantId: number, barcode?: string) => {
        if (!siteId) {
            toast.error('Select a site first.');
            return;
        }
        if (rows.some((r) => r.variantId === variantId)) {
            toast.info('Already in the count list.');
            return;
        }
        try {
            const { data } = await axios.get(route('scan.lookup'), {
                params: { site_id: siteId, ...(barcode ? { barcode } : { variant_id: variantId }) },
            });
            if (!data.found) {
                toast.error('Item not found', { description: barcode ? `Barcode ${barcode}` : undefined });
                return;
            }
            const v = data.variant;
            setRows((prev) => [
                ...prev,
                {
                    variantId: v.id,
                    label: v.item.description + (v.label ? ` — ${v.label}` : ''),
                    sku: v.sku,
                    uom: v.uom,
                    system: data.balance ?? 0,
                    counted: '',
                    posting: false,
                },
            ]);
        } catch {
            toast.error('Lookup failed.');
        }
    };

    const onScan = (code: string) => addVariant(-1, code);

    const post = (index: number) => {
        const row = rows[index];
        if (row.counted === '') return;
        setRows((prev) => prev.map((r, i) => (i === index ? { ...r, posting: true } : r)));
        router.post(
            route('inventory.count.store'),
            { site_id: siteId, item_variant_id: row.variantId, counted_qty: row.counted },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setRows((prev) => prev.filter((_, i) => i !== index)),
                onError: () => setRows((prev) => prev.map((r, i) => (i === index ? { ...r, posting: false } : r))),
            },
        );
    };

    return (
        <>
            <Head title="Physical count" />
            <div className="flex flex-col gap-6">
                <PageHeader
                    title="Physical count"
                    description="Scan or pick items, enter the counted quantity, and post the variance as an adjustment."
                    icon={ClipboardCheck}
                />

                <Card>
                    <CardHeader><CardTitle className="text-base">Count session</CardTitle></CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label>Site</Label>
                                <Select value={siteId} onValueChange={setSiteId}>
                                    <SelectTrigger><SelectValue placeholder="Select site" /></SelectTrigger>
                                    <SelectContent>
                                        {sites.map((s) => <SelectItem key={s.id} value={String(s.id)}>{s.name}</SelectItem>)}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label>Add manually</Label>
                                <VariantPicker
                                    items={items}
                                    value={manual}
                                    onChange={(id) => { setManual(id); addVariant(id); setManual(null); }}
                                    placeholder="Pick an item…"
                                />
                            </div>
                        </div>
                        <ScanField onScan={onScan} placeholder="Scan an item barcode to add it to the count…" autoFocus />
                    </CardContent>
                </Card>

                {rows.length > 0 && (
                    <div className="flex flex-col gap-3">
                        {rows.map((row, i) => {
                            const counted = parseFloat(row.counted);
                            const variance = row.counted === '' || Number.isNaN(counted) ? null : counted - row.system;
                            return (
                                <Card key={row.variantId}>
                                    <CardContent className="flex flex-wrap items-center gap-4 py-4">
                                        <div className="min-w-0 flex-1">
                                            <p className="font-medium">{row.label}</p>
                                            <p className="font-mono text-xs text-muted-foreground">{row.sku}</p>
                                        </div>
                                        <div className="text-center">
                                            <p className="text-xs text-muted-foreground">System</p>
                                            <p className="font-semibold tabular-nums">{formatQty(row.system)} {row.uom}</p>
                                        </div>
                                        <div className="grid gap-1">
                                            <Label className="text-xs text-muted-foreground">Counted</Label>
                                            <Input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={row.counted}
                                                autoFocus
                                                onChange={(e) => setRows((prev) => prev.map((r, j) => (j === i ? { ...r, counted: e.target.value } : r)))}
                                                className="w-28"
                                            />
                                        </div>
                                        <div className="min-w-24 text-center">
                                            <p className="text-xs text-muted-foreground">Variance</p>
                                            {variance === null ? (
                                                <p className="text-muted-foreground/50">—</p>
                                            ) : variance === 0 ? (
                                                <Badge variant="outline" className="gap-1 border-success/30 bg-success/10 text-success"><Check className="size-3" /> Match</Badge>
                                            ) : (
                                                <Badge variant="outline" className="gap-1 border-warning/40 bg-warning/10 text-warning">
                                                    <TriangleAlert className="size-3" /> {variance > 0 ? '+' : ''}{formatQty(variance)}
                                                </Badge>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-1">
                                            {canAdjust && (
                                                <Button size="sm" onClick={() => post(i)} disabled={row.counted === '' || row.posting}>
                                                    Post
                                                </Button>
                                            )}
                                            <IconButton label="Remove" className="text-destructive hover:text-destructive" onClick={() => setRows((prev) => prev.filter((_, j) => j !== i))}>
                                                <Trash2 />
                                            </IconButton>
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>
        </>
    );
}

PhysicalCount.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
