import * as React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Truck, Plus, Eye, Search, FileDown } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { DataTable } from '@/components/data-table';
import { Pagination } from '@/components/pagination';
import { IconButton } from '@/components/icon-button';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/utils';
import type { Paginated } from '@/types';

interface ReceiptRow {
    id: number;
    dr_no: string;
    source: 'supplier' | 'other_project';
    supplier: string | null;
    received_date: string;
    status: string;
    items_count: number;
    site: { id: number; code: string; name: string };
    creator: { id: number; name: string } | null;
}
interface Props {
    receipts: Paginated<ReceiptRow>;
    filters: { search: string | null; status: string | null };
    can: { create: boolean };
}

const STATUSES = ['draft', 'posted', 'cancelled'];

export default function ReceivingIndex({ receipts, filters, can }: Props) {
    const [search, setSearch] = React.useState(filters.search ?? '');

    const reload = (params: Record<string, unknown>) => {
        router.get(route('receiving.index'), { search, status: filters.status ?? undefined, ...params }, { preserveState: true, replace: true });
    };

    const columns: ColumnDef<ReceiptRow>[] = [
        {
            accessorKey: 'dr_no',
            header: 'DR No.',
            cell: ({ row }) => (
                <Link href={route('receiving.show', row.original.id)} className="font-mono font-medium hover:text-primary hover:underline">
                    {row.original.dr_no}
                </Link>
            ),
        },
        {
            id: 'site',
            header: 'Site',
            cell: ({ row }) => <Badge variant="secondary" className="font-mono">{row.original.site.code}</Badge>,
        },
        {
            id: 'source',
            header: 'Source',
            cell: ({ row }) => (
                <div className="text-sm">
                    <span className="capitalize">{row.original.source.replace('_', ' ')}</span>
                    {row.original.supplier && <p className="text-xs text-muted-foreground">{row.original.supplier}</p>}
                </div>
            ),
        },
        { id: 'items', header: 'Items', cell: ({ row }) => <span className="text-sm text-muted-foreground">{row.original.items_count}</span> },
        { accessorKey: 'received_date', header: 'Received', cell: ({ row }) => <span className="text-sm">{formatDate(row.original.received_date)}</span> },
        { accessorKey: 'status', header: 'Status', cell: ({ row }) => <StatusBadge status={row.original.status} /> },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            enableSorting: false,
            cell: ({ row }) => (
                <div className="flex justify-end gap-1">
                    <IconButton label="View PDF" onClick={() => window.open(route('receiving.pdf', row.original.id), '_blank')}>
                        <FileDown />
                    </IconButton>
                    <IconButton label="View" asChild>
                        <Link href={route('receiving.show', row.original.id)}><Eye /></Link>
                    </IconButton>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Receiving" />
            <div className="flex flex-col gap-6">
                <PageHeader
                    title="Receiving"
                    description="Delivery receipts — incoming stock from suppliers and other projects."
                    icon={Truck}
                    actions={
                        can.create && (
                            <Button asChild>
                                <Link href={route('receiving.create')}><Plus /> New receipt</Link>
                            </Button>
                        )
                    }
                />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <form onSubmit={(e) => { e.preventDefault(); reload({ search }); }} className="relative max-w-xs flex-1">
                        <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search DR no. or supplier…" className="pl-9" />
                    </form>
                    <Select
                        value={filters.status ?? 'all'}
                        onValueChange={(v) => reload({ status: v === 'all' ? undefined : v })}
                    >
                        <SelectTrigger className="w-full sm:w-44"><SelectValue placeholder="All statuses" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {STATUSES.map((s) => <SelectItem key={s} value={s} className="capitalize">{s}</SelectItem>)}
                        </SelectContent>
                    </Select>
                </div>

                <DataTable columns={columns} data={receipts.data} emptyState="No delivery receipts yet." />
                <Pagination meta={receipts} />
            </div>
        </>
    );
}

ReceivingIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
