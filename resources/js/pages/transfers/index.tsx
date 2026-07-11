import * as React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { ArrowLeftRight, Plus, Eye, Search, ArrowRight, FileDown } from 'lucide-react';
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

interface TransferRow {
    id: number;
    ts_no: string;
    date: string;
    status: string;
    items_count: number;
    from_site: { id: number; code: string; name: string };
    to_site: { id: number; code: string; name: string };
}
interface Props {
    transfers: Paginated<TransferRow>;
    filters: { search: string | null; status: string | null };
    can: { create: boolean };
}

const STATUSES = ['draft', 'in_transit', 'received', 'cancelled'];

export default function TransfersIndex({ transfers, filters, can }: Props) {
    const [search, setSearch] = React.useState(filters.search ?? '');

    const reload = (params: Record<string, unknown>) => {
        router.get(route('transfers.index'), { search, status: filters.status ?? undefined, ...params }, { preserveState: true, replace: true });
    };

    const columns: ColumnDef<TransferRow>[] = [
        {
            accessorKey: 'ts_no',
            header: 'TS No.',
            cell: ({ row }) => (
                <Link href={route('transfers.show', row.original.id)} className="font-mono font-medium hover:text-primary hover:underline">
                    {row.original.ts_no}
                </Link>
            ),
        },
        {
            id: 'route',
            header: 'From → To',
            cell: ({ row }) => (
                <div className="flex items-center gap-2 text-sm">
                    <Badge variant="secondary" className="font-mono">{row.original.from_site.code}</Badge>
                    <ArrowRight className="size-3.5 text-muted-foreground" />
                    <Badge variant="secondary" className="font-mono">{row.original.to_site.code}</Badge>
                </div>
            ),
        },
        { id: 'items', header: 'Items', cell: ({ row }) => <span className="text-sm text-muted-foreground">{row.original.items_count}</span> },
        { accessorKey: 'date', header: 'Date', cell: ({ row }) => <span className="text-sm">{formatDate(row.original.date)}</span> },
        { accessorKey: 'status', header: 'Status', cell: ({ row }) => <StatusBadge status={row.original.status} /> },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            enableSorting: false,
            cell: ({ row }) => (
                <div className="flex justify-end gap-1">
                    <IconButton label="View PDF" onClick={() => window.open(route('transfers.pdf', row.original.id), '_blank')}>
                        <FileDown />
                    </IconButton>
                    <IconButton label="View" asChild>
                        <Link href={route('transfers.show', row.original.id)}><Eye /></Link>
                    </IconButton>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Transfers" />
            <div className="flex flex-col gap-6">
                <PageHeader
                    title="Transfer slips"
                    description="Move stock between sites — OUT at origin, IN at destination."
                    icon={ArrowLeftRight}
                    actions={
                        can.create && (
                            <Button asChild>
                                <Link href={route('transfers.create')}><Plus /> New transfer</Link>
                            </Button>
                        )
                    }
                />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <form onSubmit={(e) => { e.preventDefault(); reload({ search }); }} className="relative max-w-xs flex-1">
                        <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search TS no.…" className="pl-9" />
                    </form>
                    <Select value={filters.status ?? 'all'} onValueChange={(v) => reload({ status: v === 'all' ? undefined : v })}>
                        <SelectTrigger className="w-full sm:w-44"><SelectValue placeholder="All statuses" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {STATUSES.map((s) => <SelectItem key={s} value={s} className="capitalize">{s.replace('_', ' ')}</SelectItem>)}
                        </SelectContent>
                    </Select>
                </div>

                <DataTable columns={columns} data={transfers.data} emptyState="No transfer slips yet." />
                <Pagination meta={transfers} />
            </div>
        </>
    );
}

TransfersIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
