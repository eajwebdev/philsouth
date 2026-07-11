import * as React from 'react';
import { Head, router } from '@inertiajs/react';
import { ScrollText, Search } from 'lucide-react';
import type { ColumnDef } from '@tanstack/react-table';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { DataTable } from '@/components/data-table';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import type { Paginated } from '@/types';

interface LogRow {
    id: number;
    action: string;
    description: string | null;
    user: string | null;
    site: string | null;
    properties: Record<string, unknown> | null;
    at: string | null;
}
interface Props {
    logs: Paginated<LogRow>;
    filters: { search: string; action: string };
    actions: string[];
}

// Colour the action chip by its domain prefix.
const tone = (action: string) => {
    if (action.includes('rejected') || action.includes('revoked')) return 'border-destructive/30 bg-destructive/10 text-destructive';
    if (action.includes('approved') || action.includes('granted') || action.includes('received') || action.includes('posted')) return 'border-success/30 bg-success/10 text-success';
    if (action.includes('adjusted') || action.includes('dispatched')) return 'border-warning/40 bg-warning/10 text-warning';
    return 'border-border bg-muted text-muted-foreground';
};

const fmt = (iso: string | null) => (iso ? new Date(iso).toLocaleString() : '—');

export default function LogsIndex({ logs, filters, actions }: Props) {
    const [search, setSearch] = React.useState(filters.search);

    const apply = (next: Partial<{ search: string; action: string }>) =>
        router.get(route('logs.index'), { ...filters, ...next }, { preserveState: true, replace: true });

    const columns: ColumnDef<LogRow>[] = [
        {
            accessorKey: 'at',
            header: 'When',
            cell: ({ row }) => <span className="whitespace-nowrap text-xs text-muted-foreground">{fmt(row.original.at)}</span>,
        },
        {
            accessorKey: 'action',
            header: 'Action',
            cell: ({ row }) => <Badge variant="outline" className={tone(row.original.action)}>{row.original.action}</Badge>,
        },
        {
            accessorKey: 'description',
            header: 'Detail',
            cell: ({ row }) => (
                <div className="min-w-0">
                    <p className="text-sm">{row.original.description ?? '—'}</p>
                    {row.original.properties && (
                        <p className="truncate font-mono text-[11px] text-muted-foreground">
                            {Object.entries(row.original.properties).map(([k, v]) => `${k}: ${v}`).join(' · ')}
                        </p>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'user',
            header: 'By',
            cell: ({ row }) => <span className="text-sm">{row.original.user ?? 'System'}</span>,
        },
        {
            accessorKey: 'site',
            header: 'Site',
            cell: ({ row }) => row.original.site ? <span className="font-mono text-xs text-muted-foreground">{row.original.site}</span> : <span className="text-muted-foreground/50">—</span>,
        },
    ];

    return (
        <>
            <Head title="Audit log" />
            <div className="flex flex-col gap-6">
                <PageHeader title="Audit log" description="Every stock-affecting action and access change, with who and when." icon={ScrollText} />

                <div className="flex flex-col gap-3 sm:flex-row">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => { if (e.key === 'Enter') apply({ search }); }}
                            placeholder="Search description or user…"
                            className="pl-9"
                        />
                    </div>
                    <Select value={filters.action || 'all'} onValueChange={(v) => apply({ action: v === 'all' ? '' : v })}>
                        <SelectTrigger className="w-56"><SelectValue placeholder="All actions" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All actions</SelectItem>
                            {actions.map((a) => <SelectItem key={a} value={a}>{a}</SelectItem>)}
                        </SelectContent>
                    </Select>
                </div>

                <DataTable columns={columns} data={logs.data} emptyState="No activity recorded yet." />
                <Pagination meta={logs} />
            </div>
        </>
    );
}

LogsIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
