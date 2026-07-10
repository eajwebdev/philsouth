import * as React from 'react';
import { Head, router } from '@inertiajs/react';
import { FileText, FileDown, Search } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { VariantPicker } from '@/components/variant-picker';
import { DateRangePicker, type DateRange } from '@/components/date-range-picker';
import { ClientPagination, useClientPagination } from '@/components/client-pagination';
import type { CatalogItem } from '@/components/line-items-editor';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
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
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, formatQty } from '@/lib/utils';
import type { SiteRef } from '@/types';

interface Row {
    date: string;
    dr_ws_no: string | null;
    source_label: string;
    issued_to: string | null;
    in: number | null;
    out: number | null;
    balance: number;
    remarks: string | null;
}
interface Card {
    site: { id: number; code: string; name: string; address: string | null };
    variant: { id: number; sku: string; label: string | null; uom: string; item: { code: string; description: string } };
    header: { location: string | null; min_qty: number; max_qty: number | null; balance: number };
    broughtForward: { date: string; balance: number } | null;
    rows: Row[];
    totals: { in: number; out: number };
}
interface Props {
    sites: SiteRef[];
    items: CatalogItem[];
    filters: { site_id: number | null; item_variant_id: number | null; from: string | null; to: string | null };
    card: Card | null;
}

const toYmd = (d: Date) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

export default function StockCardReport({ sites, items, filters, card }: Props) {
    const [siteId, setSiteId] = React.useState<string>(filters.site_id ? String(filters.site_id) : '');
    const [variantId, setVariantId] = React.useState<number | null>(filters.item_variant_id);
    const [range, setRange] = React.useState<DateRange | undefined>(
        filters.from
            ? { from: new Date(`${filters.from}T00:00:00`), to: filters.to ? new Date(`${filters.to}T00:00:00`) : undefined }
            : undefined,
    );
    const pager = useClientPagination(card?.rows ?? [], 15);

    const params = () => ({
        site_id: siteId,
        item_variant_id: variantId,
        ...(range?.from ? { from: toYmd(range.from) } : {}),
        ...(range?.to ? { to: toYmd(range.to) } : {}),
    });

    const generate = () => {
        if (!siteId || !variantId) return;
        router.get(route('reports.stock-card'), params(), { preserveState: true });
    };

    // Open the F-INV-002 style PDF in a new tab.
    const viewPdf = () => {
        if (!siteId || !variantId) return;
        const query = new URLSearchParams(
            Object.entries(params()).map(([k, v]) => [k, String(v)]),
        ).toString();
        window.open(`${route('reports.stock-card.pdf')}?${query}`, '_blank');
    };

    return (
        <>
            <Head title="Stock Card" />
            <div className="flex flex-col gap-6">
                <PageHeader
                    title="Site Warehouse Stock Card"
                    description="Per-item ledger with running balance (F-INV-002)."
                    icon={FileText}
                    actions={card && (
                        <Button variant="outline" onClick={viewPdf}>
                            <FileDown /> View PDF
                        </Button>
                    )}
                />

                <Card>
                    <CardContent className="flex flex-col gap-4 pt-6 lg:flex-row lg:items-end">
                        <div className="grid flex-1 gap-2">
                            <Label>Site</Label>
                            <Select value={siteId} onValueChange={setSiteId}>
                                <SelectTrigger><SelectValue placeholder="Select site" /></SelectTrigger>
                                <SelectContent>
                                    {sites.map((s) => <SelectItem key={s.id} value={String(s.id)}>{s.name}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid flex-1 gap-2">
                            <Label>Item</Label>
                            <VariantPicker items={items} value={variantId} onChange={setVariantId} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Period</Label>
                            <DateRangePicker value={range} onChange={setRange} className="w-64" />
                        </div>
                        <Button onClick={generate} disabled={!siteId || !variantId}>
                            <Search /> Generate
                        </Button>
                    </CardContent>
                </Card>

                {card && (
                    <div className="rounded-xl border bg-card p-6">
                        {/* Report header */}
                        <div className="mb-6 flex items-start justify-between border-b pb-4">
                            <div className="flex items-center gap-3">
                                <img src="/logo.jpg" alt="PhilSouth" className="size-14 rounded-lg object-contain" />
                                <div>
                                    <h2 className="text-lg font-bold">PhilSouth Builders Inc.</h2>
                                    <p className="text-sm text-muted-foreground">Site Warehouse Stock Card · F-INV-002</p>
                                </div>
                            </div>
                            <div className="text-right text-sm">
                                <p className="font-semibold">{card.site.name}</p>
                                <p className="text-muted-foreground">{card.site.code}</p>
                                {filters.from && (
                                    <p className="text-xs text-muted-foreground">
                                        {formatDate(filters.from)} – {filters.to ? formatDate(filters.to) : 'today'}
                                    </p>
                                )}
                            </div>
                        </div>

                        {/* Item + header meta */}
                        <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <Meta label="Item">
                                {card.variant.item.description}
                                {card.variant.label && <span className="text-muted-foreground"> — {card.variant.label}</span>}
                                <span className="block font-mono text-xs text-muted-foreground">{card.variant.sku}</span>
                            </Meta>
                            <Meta label="Location">{card.header.location ?? '—'}</Meta>
                            <Meta label="Min / Max">
                                {formatQty(card.header.min_qty)}{card.header.max_qty != null ? ` / ${formatQty(card.header.max_qty)}` : ''} {card.variant.uom}
                            </Meta>
                            <Meta label="Current balance">
                                <span className="text-lg font-bold">{formatQty(card.header.balance)}</span> {card.variant.uom}
                            </Meta>
                        </div>

                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead>Date</TableHead>
                                        <TableHead>DR/WS No.</TableHead>
                                        <TableHead>Incoming</TableHead>
                                        <TableHead>Issued To</TableHead>
                                        <TableHead className="text-right">In</TableHead>
                                        <TableHead className="text-right">Out</TableHead>
                                        <TableHead className="text-right">Balance</TableHead>
                                        <TableHead>Remarks</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {card.broughtForward && pager.page === 1 && (
                                        <TableRow className="bg-muted/40 italic">
                                            <TableCell className="whitespace-nowrap text-sm">{formatDate(card.broughtForward.date)}</TableCell>
                                            <TableCell className="text-sm text-muted-foreground">—</TableCell>
                                            <TableCell colSpan={4} className="text-sm text-muted-foreground">Balance brought forward</TableCell>
                                            <TableCell className="text-right font-semibold tabular-nums">{formatQty(card.broughtForward.balance)}</TableCell>
                                            <TableCell />
                                        </TableRow>
                                    )}
                                    {card.rows.length === 0 ? (
                                        <TableRow><TableCell colSpan={8} className="h-24 text-center text-muted-foreground">No movements recorded{filters.from ? ' in this period' : ''}.</TableCell></TableRow>
                                    ) : (
                                        pager.paged.map((r, i) => (
                                            <TableRow key={i}>
                                                <TableCell className="whitespace-nowrap text-sm">{formatDate(r.date)}</TableCell>
                                                <TableCell className="font-mono text-sm">{r.dr_ws_no ?? '—'}</TableCell>
                                                <TableCell className="text-sm">{r.in != null ? r.source_label : <span className="text-muted-foreground/50">—</span>}</TableCell>
                                                <TableCell className="text-sm">{r.issued_to ?? (r.out != null ? r.source_label : '—')}</TableCell>
                                                <TableCell className="text-right tabular-nums text-success">{r.in != null ? formatQty(r.in) : ''}</TableCell>
                                                <TableCell className="text-right tabular-nums text-destructive">{r.out != null ? formatQty(r.out) : ''}</TableCell>
                                                <TableCell className="text-right font-semibold tabular-nums">{formatQty(r.balance)}</TableCell>
                                                <TableCell className="text-sm text-muted-foreground">{r.remarks ?? ''}</TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                                <TableFooter>
                                    <TableRow>
                                        <TableCell colSpan={4} className="text-right font-medium">Totals</TableCell>
                                        <TableCell className="text-right font-semibold tabular-nums text-success">{formatQty(card.totals.in)}</TableCell>
                                        <TableCell className="text-right font-semibold tabular-nums text-destructive">{formatQty(card.totals.out)}</TableCell>
                                        <TableCell className="text-right font-bold tabular-nums">{formatQty(card.header.balance)}</TableCell>
                                        <TableCell />
                                    </TableRow>
                                </TableFooter>
                            </Table>
                        </div>
                        <ClientPagination {...pager} />
                    </div>
                )}
            </div>
        </>
    );
}

function Meta({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="rounded-lg border bg-muted/30 p-3">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
            <div className="mt-0.5 text-sm">{children}</div>
        </div>
    );
}

StockCardReport.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
