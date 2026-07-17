import * as React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { ClipboardList, Plus, Eye, Search, FileDown } from 'lucide-react';
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

interface SlipRow {
    id: number;
    ws_no: string;
    delivered_to: string | null;
    date: string;
    status: string;
    items_count: number;
    site: { id: number; code: string; name: string };
    prepared_by: { id: number; name: string } | null;
}
interface Props {
    slips: Paginated<SlipRow>;
    filters: { search: string | null; status: string | null };
    can: { create: boolean };
}

const STATUSES = ['draft', 'released', 'received', 'cancelled'];

export default function WithdrawalsIndex({ slips, filters, can }: Props) {
    const [search, setSearch] = React.useState(filters.search ?? '');

    const reload = (params: Record<string, unknown>) => {
        router.get(route('withdrawals.index'), { search, status: filters.status ?? undefined, ...params }, { preserveState: true, replace: true });
    };

    const columns: ColumnDef<SlipRow>[] = [
        {
            accessorKey: 'ws_no',
            header: 'WS No.',
            cell: ({ row }) => (
                <Link href={route('withdrawals.show', row.original.id)} className="font-mono font-medium hover:text-primary hover:underline">
                    {row.original.ws_no}
                </Link>
            ),
        },
        { id: 'site', header: 'Site', cell: ({ row }) => <Badge variant="secondary" className="font-mono">{row.original.site.code}</Badge> },
        {
            id: 'delivered_to',
            header: 'Delivered to',
            cell: ({ row }) => <span className="text-sm">{row.original.delivered_to ?? '—'}</span>,
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
                    <IconButton label="View PDF" onClick={() => window.open(route('withdrawals.pdf', row.original.id), '_blank')}>
                        <FileDown />
                    </IconButton>
                    <IconButton label="View" asChild>
                        <Link href={route('withdrawals.show', row.original.id)}><Eye /></Link>
                    </IconButton>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Withdrawals" />
            <div className="flex flex-col gap-6">
                <PageHeader
                    title="Withdrawal slips"
                    description="Material issuance — release stock directly from a draft slip."
                    icon={ClipboardList}
                    actions={
                        can.create && (
                            <Button asChild>
                                <Link href={route('withdrawals.create')}><Plus /> New withdrawal</Link>
                            </Button>
                        )
                    }
                />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <form onSubmit={(e) => { e.preventDefault(); reload({ search }); }} className="relative max-w-xs flex-1">
                        <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search WS no. or recipient…" className="pl-9" />
                    </form>
                    <Select value={filters.status ?? 'all'} onValueChange={(v) => reload({ status: v === 'all' ? undefined : v })}>
                        <SelectTrigger className="w-full sm:w-48"><SelectValue placeholder="All statuses" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {STATUSES.map((s) => <SelectItem key={s} value={s} className="capitalize">{s.replace('_', ' ')}</SelectItem>)}
                        </SelectContent>
                    </Select>
                </div>

                <DataTable columns={columns} data={slips.data} emptyState="No withdrawal slips yet." />
                <Pagination meta={slips} />
            </div>
        </>
    );
}

WithdrawalsIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
