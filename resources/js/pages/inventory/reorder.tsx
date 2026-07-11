import * as React from 'react';
import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { PackageMinus, SlidersHorizontal } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { DataTable } from '@/components/data-table';
import { Pagination } from '@/components/pagination';
import { IconButton } from '@/components/icon-button';
import { ThresholdDialog } from '@/components/threshold-dialog';
import { Badge } from '@/components/ui/badge';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { formatQty } from '@/lib/utils';
import type { Paginated, SiteRef } from '@/types';

interface ReorderRow {
    id: number;
    location: string | null;
    min_qty: number;
    max_qty: number | null;
    balance: number;
    suggested: number;
    uom: string;
    sku: string;
    item: string;
    site: string;
}
interface Props {
    rows: Paginated<ReorderRow>;
    sites: SiteRef[];
    canManage: boolean;
    filters: { site_id: number | null };
}

export default function Reorder({ rows, sites, canManage, filters }: Props) {
    const [editing, setEditing] = React.useState<ReorderRow | null>(null);

    const columns: ColumnDef<ReorderRow>[] = [
        {
            id: 'item',
            header: 'Item',
            cell: ({ row }) => (
                <div>
                    <p className="font-medium">{row.original.item}</p>
                    <p className="font-mono text-xs text-muted-foreground">{row.original.sku}</p>
                </div>
            ),
        },
        { id: 'site', header: 'Site', cell: ({ row }) => <Badge variant="secondary" className="font-mono">{row.original.site}</Badge> },
        {
            id: 'balance',
            header: () => <span className="block text-right">On hand</span>,
            cell: ({ row }) => (
                <span className="block text-right tabular-nums">
                    <span className="font-semibold text-warning">{formatQty(row.original.balance)}</span>
                    <span className="ml-1 text-xs text-muted-foreground">/ min {formatQty(row.original.min_qty)}</span>
                </span>
            ),
        },
        {
            id: 'suggested',
            header: () => <span className="block text-right">Suggested order</span>,
            cell: ({ row }) => (
                <span className="block text-right font-semibold tabular-nums">
                    {formatQty(row.original.suggested)} <span className="text-xs font-normal text-muted-foreground">{row.original.uom}</span>
                </span>
            ),
        },
        ...(canManage ? [{
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            enableSorting: false,
            cell: ({ row }: { row: { original: ReorderRow } }) => (
                <div className="flex justify-end">
                    <IconButton label="Edit reorder levels" onClick={() => setEditing(row.original)}>
                        <SlidersHorizontal />
                    </IconButton>
                </div>
            ),
        }] : []),
    ];

    return (
        <>
            <Head title="Reorder report" />
            <div className="flex flex-col gap-6">
                <PageHeader
                    title="Reorder report"
                    description="Items at or below their minimum, with a suggested order quantity to reach the maximum."
                    icon={PackageMinus}
                />

                <div className="sm:w-52">
                    <Select
                        value={filters.site_id ? String(filters.site_id) : 'all'}
                        onValueChange={(v) => router.get(route('inventory.reorder'), v === 'all' ? {} : { site_id: v }, { preserveState: true, replace: true })}
                    >
                        <SelectTrigger><SelectValue placeholder="All sites" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All sites</SelectItem>
                            {sites.map((s) => <SelectItem key={s.id} value={String(s.id)}>{s.name}</SelectItem>)}
                        </SelectContent>
                    </Select>
                </div>

                <DataTable columns={columns} data={rows.data} emptyState="Nothing to reorder — every item is above its minimum." />
                <Pagination meta={rows} />
            </div>

            <ThresholdDialog target={editing} onClose={() => setEditing(null)} />
        </>
    );
}

Reorder.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
