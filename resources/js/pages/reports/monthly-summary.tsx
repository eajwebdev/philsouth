import * as React from 'react';
import { Head, router } from '@inertiajs/react';
import { CalendarRange, FileDown, Search, CheckCircle2, AlertTriangle } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { ClientPagination, useClientPagination } from '@/components/client-pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
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
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatQty } from '@/lib/utils';
import type { SiteRef } from '@/types';

interface Row {
    variant: { id: number; sku: string; label: string | null; description: string; code: string; uom: string };
    beginning: number;
    purchases: number;
    warehouse_in: number;
    transfer_in: number;
    usage: number;
    transfer_out: number;
    loss_damage: number;
    return_supplier: number;
    warehouse_out: number;
    sale_other: number;
    adjustment: number;
    total_in: number;
    total_out: number;
    ending: number;
}
interface Summary {
    site: { id: number; code: string; name: string; address: string | null };
    month: string;
    month_label: string;
    is_closed: boolean;
    reconciles: boolean;
    rows: Row[];
}
interface Props {
    sites: SiteRef[];
    filters: { site_id: number | null; month: string };
    summary: Summary | null;
}

const COLS: { key: keyof Row; label: string; tone?: 'in' | 'out' }[] = [
    { key: 'beginning', label: 'Beginning' },
    { key: 'purchases', label: 'Purchases', tone: 'in' },
    { key: 'warehouse_in', label: 'Whse In', tone: 'in' },
    { key: 'transfer_in', label: 'Transfer In', tone: 'in' },
    { key: 'usage', label: 'Usage', tone: 'out' },
    { key: 'transfer_out', label: 'Transfer Out', tone: 'out' },
    { key: 'loss_damage', label: 'Loss & Dmg', tone: 'out' },
    { key: 'return_supplier', label: 'Return', tone: 'out' },
    { key: 'warehouse_out', label: 'Whse Out', tone: 'out' },
    { key: 'sale_other', label: 'Sales/Other', tone: 'out' },
    { key: 'adjustment', label: 'Adjust' },
    { key: 'ending', label: 'Ending' },
];

export default function MonthlySummaryReport({ sites, filters, summary }: Props) {
    const [siteId, setSiteId] = React.useState<string>(filters.site_id ? String(filters.site_id) : '');
    const [month, setMonth] = React.useState<string>(filters.month);
    const pager = useClientPagination(summary?.rows ?? [], 15);

    const generate = () => {
        if (!siteId) return;
        router.get(route('reports.monthly-summary'), { site_id: siteId, month }, { preserveState: true });
    };

    // Open the F-INV-006 style PDF (landscape, with U.O.M. + signature lines) in a new tab.
    const viewPdf = () => {
        if (!siteId) return;
        window.open(`${route('reports.monthly-summary.pdf')}?site_id=${siteId}&month=${month}`, '_blank');
    };

    return (
        <>
            <Head title="Monthly Inventory Summary" />
            <div className="flex flex-col gap-6">
                <PageHeader
                    title="Monthly Inventory Summary"
                    description="Movement aggregation per item for a site and month (F-INV-006)."
                    icon={CalendarRange}
                    actions={summary && (
                        <Button variant="outline" onClick={viewPdf}>
                            <FileDown /> View PDF
                        </Button>
                    )}
                />

                <Card className="no-print">
                    <CardContent className="flex flex-col gap-4 pt-6 sm:flex-row sm:items-end">
                        <div className="grid flex-1 gap-2">
                            <Label>Site</Label>
                            <Select value={siteId} onValueChange={setSiteId}>
                                <SelectTrigger><SelectValue placeholder="Select site" /></SelectTrigger>
                                <SelectContent>
                                    {sites.map((s) => <SelectItem key={s.id} value={String(s.id)}>{s.name}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label>Month</Label>
                            <Input type="month" value={month} onChange={(e) => setMonth(e.target.value)} className="w-48" />
                        </div>
                        <Button onClick={generate} disabled={!siteId}><Search /> Generate</Button>
                    </CardContent>
                </Card>

                {summary && (
                    <div className="print-area rounded-xl border bg-card p-6">
                        <div className="mb-6 flex items-start justify-between border-b pb-4">
                            <div className="flex items-center gap-3">
                                <img src="/logo.jpg" alt="PhilSouth" className="size-14 rounded-lg object-contain" />
                                <div>
                                    <h2 className="text-lg font-bold">PhilSouth Builders Inc.</h2>
                                    <p className="text-sm text-muted-foreground">Monthly Inventory Summary · F-INV-006</p>
                                </div>
                            </div>
                            <div className="text-right text-sm">
                                <p className="font-semibold">{summary.site.name} · {summary.month_label}</p>
                                <p className="text-muted-foreground">{summary.site.code}</p>
                                {summary.is_closed && (
                                    <Badge
                                        variant="outline"
                                        className={summary.reconciles
                                            ? 'mt-1 gap-1 border-success/30 bg-success/10 text-success'
                                            : 'mt-1 gap-1 border-destructive/30 bg-destructive/10 text-destructive'}
                                    >
                                        {summary.reconciles ? <CheckCircle2 className="size-3" /> : <AlertTriangle className="size-3" />}
                                        {summary.reconciles ? 'Reconciled' : 'Does not reconcile'}
                                    </Badge>
                                )}
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead className="sticky left-0 bg-card">Item</TableHead>
                                        {COLS.map((c) => (
                                            <TableHead key={c.key} className="text-right whitespace-nowrap">{c.label}</TableHead>
                                        ))}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {summary.rows.length === 0 ? (
                                        <TableRow><TableCell colSpan={COLS.length + 1} className="h-24 text-center text-muted-foreground">No activity for this month.</TableCell></TableRow>
                                    ) : (
                                        pager.paged.map((r) => (
                                            <TableRow key={r.variant.id}>
                                                <TableCell className="sticky left-0 bg-card">
                                                    <p className="font-medium">
                                                        {r.variant.description}
                                                        {r.variant.label && <span className="text-muted-foreground"> — {r.variant.label}</span>}
                                                    </p>
                                                    <p className="font-mono text-xs text-muted-foreground">{r.variant.sku}</p>
                                                </TableCell>
                                                {COLS.map((c) => {
                                                    const val = r[c.key] as number;
                                                    return (
                                                        <TableCell
                                                            key={c.key}
                                                            className={`text-right tabular-nums ${
                                                                c.key === 'ending' || c.key === 'beginning' ? 'font-semibold' : ''
                                                            } ${c.tone === 'in' && val ? 'text-success' : ''} ${c.tone === 'out' && val ? 'text-destructive' : ''}`}
                                                        >
                                                            {val ? formatQty(val) : <span className="text-muted-foreground/40">–</span>}
                                                        </TableCell>
                                                    );
                                                })}
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                        <ClientPagination {...pager} />
                    </div>
                )}
            </div>
        </>
    );
}

MonthlySummaryReport.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
