import * as React from 'react';
import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Boxes, Search, AlertTriangle } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { DataTable } from '@/components/data-table';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatQty } from '@/lib/utils';
import type { Paginated, SiteRef } from '@/types';

interface StockRow {
    id: number;
    location: string | null;
    min_qty: string;
    max_qty: string | null;
    balance: string;
    item: { id: number; code: string; description: string; uom: string; category: string | null };
    site: { id: number; code: string; name: string };
}
interface Props {
    stock: Paginated<StockRow>;
    sites: SiteRef[];
    filters: { search: string | null; site_id: number | null; low_only: boolean };
}

export default function InventoryIndex({ stock, sites, filters }: Props) {
    const [search, setSearch] = React.useState(filters.search ?? '');

    const reload = (params: Record<string, unknown>) => {
        router.get(
            route('inventory.index'),
            { search, site_id: filters.site_id ?? undefined, low_only: filters.low_only || undefined, ...params },
            { preserveState: true, replace: true },
        );
    };

    const onSearch = (e: React.FormEvent) => {
        e.preventDefault();
        reload({ search });
    };

    const isLow = (row: StockRow) => parseFloat(row.balance) <= parseFloat(row.min_qty);

    const columns: ColumnDef<StockRow>[] = [
        {
            id: 'item',
            header: 'Item',
            cell: ({ row }) => (
                <div>
                    <p className="font-medium">{row.original.item.description}</p>
                    <p className="font-mono text-xs text-muted-foreground">{row.original.item.code}</p>
                </div>
            ),
        },
        {
            id: 'site',
            header: 'Site',
            cell: ({ row }) => (
                <Badge variant="secondary" className="font-mono">{row.original.site.code}</Badge>
            ),
        },
        {
            id: 'location',
            header: 'Location',
            cell: ({ row }) => <span className="text-sm text-muted-foreground">{row.original.location ?? '—'}</span>,
        },
        {
            id: 'minmax',
            header: 'Min / Max',
            cell: ({ row }) => (
                <span className="text-sm text-muted-foreground">
                    {formatQty(row.original.min_qty)}
                    {row.original.max_qty ? ` / ${formatQty(row.original.max_qty)}` : ''}
                </span>
            ),
        },
        {
            id: 'balance',
            header: 'Balance',
            cell: ({ row }) => (
                <div className="flex items-center gap-2">
                    <span className="font-semibold tabular-nums">{formatQty(row.original.balance)}</span>
                    <span className="text-xs text-muted-foreground">{row.original.item.uom}</span>
                    {isLow(row.original) && (
                        <Badge variant="outline" className="gap-1 border-warning/40 bg-warning/10 text-warning">
                            <AlertTriangle className="size-3" /> Low
                        </Badge>
                    )}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Stock" />
            <div className="flex flex-col gap-6">
                <PageHeader
                    title="Stock on hand"
                    description="Live balances per site and item, from the movement ledger."
                    icon={Boxes}
                />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <form onSubmit={onSearch} className="relative max-w-xs flex-1">
                        <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search items…" className="pl-9" />
                    </form>

                    <Select
                        value={filters.site_id ? String(filters.site_id) : 'all'}
                        onValueChange={(v) => reload({ site_id: v === 'all' ? undefined : v })}
                    >
                        <SelectTrigger className="w-full sm:w-52">
                            <SelectValue placeholder="All sites" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All sites</SelectItem>
                            {sites.map((s) => (
                                <SelectItem key={s.id} value={String(s.id)}>{s.name}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <label className="flex items-center gap-2 whitespace-nowrap text-sm">
                        <Checkbox
                            checked={filters.low_only}
                            onCheckedChange={(v) => reload({ low_only: v === true ? true : undefined })}
                        />
                        Low stock only
                    </label>
                </div>

                <DataTable columns={columns} data={stock.data} emptyState="No stock records for this filter." />
                <Pagination meta={stock} />
            </div>
        </>
    );
}

InventoryIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
