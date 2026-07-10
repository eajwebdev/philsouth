import * as React from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    Building2,
    Plus,
    Pencil,
    Trash2,
    HardHat,
    Users as UsersIcon,
    Search,
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { DataTable } from '@/components/data-table';
import { Pagination } from '@/components/pagination';
import { IconButton } from '@/components/icon-button';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { MultiSelect } from '@/components/multi-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Paginated } from '@/types';

interface SiteUser {
    id: number;
    name: string;
    roles: { name: string }[];
}
interface SiteRow {
    id: number;
    code: string;
    name: string;
    address: string | null;
    is_active: boolean;
    engineers_count: number;
    ics_count: number;
    users: SiteUser[];
}
interface EngineerOption {
    id: number;
    name: string;
    email: string;
}
interface Props {
    sites: Paginated<SiteRow>;
    filters: { search: string | null };
    engineers: EngineerOption[];
    can: { manage: boolean; assignEngineer: boolean };
}

export default function SitesIndex({ sites, filters, engineers, can }: Props) {
    const [search, setSearch] = React.useState(filters.search ?? '');
    const [formOpen, setFormOpen] = React.useState(false);
    const [editing, setEditing] = React.useState<SiteRow | null>(null);
    const [deleting, setDeleting] = React.useState<SiteRow | null>(null);
    const [assigning, setAssigning] = React.useState<SiteRow | null>(null);

    const onSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('sites.index'), { search }, { preserveState: true, replace: true });
    };

    const openCreate = () => {
        setEditing(null);
        setFormOpen(true);
    };
    const openEdit = (site: SiteRow) => {
        setEditing(site);
        setFormOpen(true);
    };

    const columns: ColumnDef<SiteRow>[] = [
        {
            accessorKey: 'code',
            header: 'Code',
            cell: ({ row }) => <span className="font-mono text-sm font-medium">{row.original.code}</span>,
        },
        {
            accessorKey: 'name',
            header: 'Site',
            cell: ({ row }) => (
                <div>
                    <p className="font-medium">{row.original.name}</p>
                    {row.original.address && (
                        <p className="text-xs text-muted-foreground">{row.original.address}</p>
                    )}
                </div>
            ),
        },
        {
            id: 'team',
            header: 'Team',
            enableSorting: false,
            cell: ({ row }) => (
                <div className="flex gap-1.5">
                    <Badge variant="secondary" className="gap-1">
                        <HardHat className="size-3" /> {row.original.engineers_count}
                    </Badge>
                    <Badge variant="secondary" className="gap-1">
                        <UsersIcon className="size-3" /> {row.original.ics_count}
                    </Badge>
                </div>
            ),
        },
        {
            accessorKey: 'is_active',
            header: 'Status',
            cell: ({ row }) =>
                row.original.is_active ? (
                    <Badge variant="outline" className="border-success/30 bg-success/10 text-success">Active</Badge>
                ) : (
                    <Badge variant="outline" className="text-muted-foreground">Inactive</Badge>
                ),
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            enableSorting: false,
            cell: ({ row }) => (
                <div className="flex justify-end gap-0.5">
                    <IconButton label="Manage team" asChild>
                        <Link href={route('sites.team', row.original.id)}>
                            <UsersIcon />
                        </Link>
                    </IconButton>
                    {can.assignEngineer && (
                        <IconButton label="Assign engineers" onClick={() => setAssigning(row.original)}>
                            <HardHat />
                        </IconButton>
                    )}
                    {can.manage && (
                        <>
                            <IconButton label="Edit" onClick={() => openEdit(row.original)}>
                                <Pencil />
                            </IconButton>
                            <IconButton
                                label="Delete"
                                className="text-destructive hover:text-destructive"
                                onClick={() => setDeleting(row.original)}
                            >
                                <Trash2 />
                            </IconButton>
                        </>
                    )}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Sites" />
            <div className="flex flex-col gap-6">
                <PageHeader
                    title="Sites"
                    description="Project sites and their assigned teams."
                    icon={Building2}
                    actions={
                        can.manage && (
                            <Button onClick={openCreate}>
                                <Plus /> New site
                            </Button>
                        )
                    }
                />

                <form onSubmit={onSearch} className="relative max-w-sm">
                    <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search sites…"
                        className="pl-9"
                    />
                </form>

                <DataTable
                    columns={columns}
                    data={sites.data}
                    emptyState="No sites yet."
                />
                <Pagination meta={sites} />
            </div>

            <SiteFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                site={editing}
            />

            {assigning && (
                <AssignEngineersDialog
                    site={assigning}
                    engineers={engineers}
                    onClose={() => setAssigning(null)}
                />
            )}

            <ConfirmDialog
                open={!!deleting}
                onOpenChange={(o) => !o && setDeleting(null)}
                title={`Delete ${deleting?.name}?`}
                description="This permanently removes the site and its assignments. This cannot be undone."
                confirmLabel="Delete site"
                onConfirm={() => {
                    if (!deleting) return;
                    router.delete(route('sites.destroy', deleting.id), {
                        preserveScroll: true,
                        onFinish: () => setDeleting(null),
                    });
                }}
            />
        </>
    );
}

function SiteFormDialog({
    open,
    onOpenChange,
    site,
}: {
    open: boolean;
    onOpenChange: (o: boolean) => void;
    site: SiteRow | null;
}) {
    const { data, setData, post, put, processing, errors, reset } = useForm({
        code: '',
        name: '',
        address: '',
        is_active: true,
    });

    React.useEffect(() => {
        if (open) {
            setData({
                code: site?.code ?? '',
                name: site?.name ?? '',
                address: site?.address ?? '',
                is_active: site?.is_active ?? true,
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, site]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                reset();
            },
        };
        if (site) put(route('sites.update', site.id), opts);
        else post(route('sites.store'), opts);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>{site ? 'Edit site' : 'New site'}</DialogTitle>
                        <DialogDescription>
                            {site ? 'Update the site details.' : 'Create a new project site.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="code">Site code</Label>
                            <Input
                                id="code"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value)}
                                placeholder="MKT-01"
                                aria-invalid={!!errors.code}
                            />
                            {errors.code && <p className="text-sm text-destructive">{errors.code}</p>}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Makati Tower"
                                aria-invalid={!!errors.name}
                            />
                            {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="address">Address</Label>
                            <Input
                                id="address"
                                value={data.address}
                                onChange={(e) => setData('address', e.target.value)}
                                placeholder="Optional"
                            />
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={data.is_active}
                                onCheckedChange={(v) => setData('is_active', v === true)}
                            />
                            Active
                        </label>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {site ? 'Save changes' : 'Create site'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AssignEngineersDialog({
    site,
    engineers,
    onClose,
}: {
    site: SiteRow;
    engineers: EngineerOption[];
    onClose: () => void;
}) {
    const current = site.users
        .filter((u) => u.roles.some((r) => r.name === 'engineer'))
        .map((u) => u.id);
    const { data, setData, put, processing } = useForm<{ engineer_ids: (number | string)[] }>({
        engineer_ids: current,
    });

    const submit = () => {
        put(route('sites.engineers', site.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Assign engineers</DialogTitle>
                    <DialogDescription>
                        Choose the engineers responsible for {site.name}.
                    </DialogDescription>
                </DialogHeader>
                <div className="py-4">
                    <MultiSelect
                        options={engineers.map((e) => ({ value: e.id, label: e.name, description: e.email }))}
                        selected={data.engineer_ids}
                        onChange={(v) => setData('engineer_ids', v)}
                        placeholder="Select engineers…"
                        emptyText="No engineers available."
                    />
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit} disabled={processing}>Save assignments</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

SitesIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
