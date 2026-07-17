import * as React from 'react';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { ClipboardCheck, Trash2, Check, TriangleAlert, Upload, X } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { ScanField } from '@/components/scan-field';
import { VariantPicker } from '@/components/variant-picker';
import { LocationLock, EMPTY_GEO, type GeoPayload } from '@/components/location-lock';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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
    const [geo, setGeo] = React.useState<GeoPayload>(EMPTY_GEO);

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

    const setCounted = (variantId: number, value: string) =>
        setRows((prev) => prev.map((r) => (r.variantId === variantId ? { ...r, counted: value } : r)));

    const remove = (variantId: number) =>
        setRows((prev) => prev.filter((r) => r.variantId !== variantId));

    const post = (variantId: number) => {
        const row = rows.find((r) => r.variantId === variantId);
        if (!row || row.counted === '') return;
        setRows((prev) => prev.map((r) => (r.variantId === variantId ? { ...r, posting: true } : r)));
        router.post(
            route('inventory.count.store'),
            // Geotag where the count was actually taken (never blocks the post).
            { site_id: siteId, item_variant_id: variantId, counted_qty: row.counted, ...geo },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => remove(variantId),
                onError: () => setRows((prev) => prev.map((r) => (r.variantId === variantId ? { ...r, posting: false } : r))),
            },
        );
    };

    // Post every row that has a counted value, in one pass.
    const postAll = () => {
        rows.filter((r) => r.counted !== '' && !r.posting).forEach((r) => post(r.variantId));
    };

    const readyCount = rows.filter((r) => r.counted !== '').length;

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
                        <LocationLock onChange={setGeo} />
                    </CardContent>
                </Card>

                {rows.length > 0 && (
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between gap-3 space-y-0">
                            <CardTitle className="text-base">
                                Count list <span className="ml-1 text-sm font-normal text-muted-foreground">{rows.length} item(s) · {readyCount} ready</span>
                            </CardTitle>
                            <div className="flex items-center gap-2">
                                <Button variant="outline" size="sm" onClick={() => setRows([])}><X /> Clear</Button>
                                {canAdjust && (
                                    <Button size="sm" onClick={postAll} disabled={readyCount === 0}>
                                        <Upload /> Post all ({readyCount})
                                    </Button>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto rounded-lg border">
                                <Table>
                                    <TableHeader>
                                        <TableRow className="hover:bg-transparent">
                                            <TableHead>Item</TableHead>
                                            <TableHead className="text-right">System</TableHead>
                                            <TableHead className="w-32">Counted</TableHead>
                                            <TableHead className="text-center">Variance</TableHead>
                                            <TableHead className="w-24 text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {rows.map((row) => {
                                            const counted = parseFloat(row.counted);
                                            const variance = row.counted === '' || Number.isNaN(counted) ? null : counted - row.system;
                                            return (
                                                <TableRow key={row.variantId}>
                                                    <TableCell>
                                                        <p className="font-medium leading-tight">{row.label}</p>
                                                        <p className="font-mono text-xs text-muted-foreground">{row.sku}</p>
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums whitespace-nowrap">
                                                        {formatQty(row.system)} <span className="text-xs text-muted-foreground">{row.uom}</span>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Input
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            value={row.counted}
                                                            onChange={(e) => setCounted(row.variantId, e.target.value)}
                                                            onKeyDown={(e) => { if (e.key === 'Enter') post(row.variantId); }}
                                                            className="h-8 w-28"
                                                        />
                                                    </TableCell>
                                                    <TableCell className="text-center">
                                                        {variance === null ? (
                                                            <span className="text-muted-foreground/50">—</span>
                                                        ) : variance === 0 ? (
                                                            <Badge variant="outline" className="gap-1 border-success/30 bg-success/10 text-success"><Check className="size-3" /> Match</Badge>
                                                        ) : (
                                                            <Badge variant="outline" className="gap-1 border-warning/40 bg-warning/10 text-warning">
                                                                <TriangleAlert className="size-3" /> {variance > 0 ? '+' : ''}{formatQty(variance)}
                                                            </Badge>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center justify-end gap-0.5">
                                                            {canAdjust && (
                                                                <IconButton label="Post" onClick={() => post(row.variantId)} disabled={row.counted === '' || row.posting}>
                                                                    <Check />
                                                                </IconButton>
                                                            )}
                                                            <IconButton label="Remove" className="text-destructive hover:text-destructive" onClick={() => remove(row.variantId)}>
                                                                <Trash2 />
                                                            </IconButton>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

PhysicalCount.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
