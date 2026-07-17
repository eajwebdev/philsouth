import * as React from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Package, Plus, Pencil, Trash2, Search, Boxes, Layers, Upload } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { DataTable } from '@/components/data-table';
import { Pagination } from '@/components/pagination';
import { IconButton } from '@/components/icon-button';
import { ConfirmDialog } from '@/components/confirm-dialog';
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
import { UOM_OPTIONS } from '@/lib/uom';
import type { Paginated } from '@/types';

interface ItemRow {
    id: number;
    code: string;
    description: string;
    uom: string;
    category: string | null;
    has_variants: boolean;
    variants_count: number;
    is_active: boolean;
}
interface Props {
    items: Paginated<ItemRow>;
    filters: { search: string | null };
    can: { manage: boolean };
}

export default function ItemsIndex({ items, filters, can }: Props) {
    const [search, setSearch] = React.useState(filters.search ?? '');
    const [formOpen, setFormOpen] = React.useState(false);
    const [importOpen, setImportOpen] = React.useState(false);
    const [editing, setEditing] = React.useState<ItemRow | null>(null);
    const [deleting, setDeleting] = React.useState<ItemRow | null>(null);

    const onSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('items.index'), { search }, { preserveState: true, replace: true });
    };

    const columns: ColumnDef<ItemRow>[] = [
        {
            accessorKey: 'code',
            header: 'Code',
            cell: ({ row }) => <span className="font-mono text-sm font-medium">{row.original.code}</span>,
        },
        {
            accessorKey: 'description',
            header: 'Description',
            cell: ({ row }) => (
                <Link href={route('items.show', row.original.id)} className="group block">
                    <p className="font-medium group-hover:text-primary group-hover:underline">{row.original.description}</p>
                    {row.original.category && (
                        <p className="text-xs text-muted-foreground">{row.original.category}</p>
                    )}
                </Link>
            ),
        },
        { accessorKey: 'uom', header: 'UoM', cell: ({ row }) => <span className="text-sm">{row.original.uom}</span> },
        {
            id: 'variants',
            header: 'Variants',
            enableSorting: false,
            cell: ({ row }) =>
                row.original.has_variants ? (
                    <Badge variant="secondary" className="gap-1">
                        <Layers className="size-3" /> {row.original.variants_count} variants
                    </Badge>
                ) : (
                    <span className="text-xs text-muted-foreground/60">Single</span>
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
        ...(can.manage
            ? [{
                id: 'actions',
                header: () => <span className="sr-only">Actions</span>,
                enableSorting: false,
                cell: ({ row }: { row: { original: ItemRow } }) => (
                    <div className="flex justify-end gap-0.5">
                        <IconButton label="Manage variants" asChild>
                            <Link href={route('items.show', row.original.id)}>
                                <Boxes />
                            </Link>
                        </IconButton>
                        <IconButton label="Edit" onClick={() => { setEditing(row.original); setFormOpen(true); }}>
                            <Pencil />
                        </IconButton>
                        <IconButton
                            label="Delete"
                            className="text-destructive hover:text-destructive"
                            onClick={() => setDeleting(row.original)}
                        >
                            <Trash2 />
                        </IconButton>
                    </div>
                ),
            } as ColumnDef<ItemRow>]
            : []),
    ];

    return (
        <>
            <Head title="Items" />
            <div className="flex flex-col gap-6">
                <PageHeader
                    title="Items master"
                    description="The shared catalogue of materials used across all sites."
                    icon={Package}
                    actions={
                        can.manage && (
                            <div className="flex items-center gap-2">
                                <Button variant="outline" onClick={() => setImportOpen(true)}>
                                    <Upload /> Import CSV
                                </Button>
                                <Button onClick={() => { setEditing(null); setFormOpen(true); }}>
                                    <Plus /> New item
                                </Button>
                            </div>
                        )
                    }
                />

                <form onSubmit={onSearch} className="relative max-w-sm">
                    <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search code, description, barcode…" className="pl-9" />
                </form>

                <DataTable columns={columns} data={items.data} emptyState="No items yet." />
                <Pagination meta={items} />
            </div>

            <ItemFormDialog open={formOpen} onOpenChange={setFormOpen} item={editing} />
            <ImportDialog open={importOpen} onOpenChange={setImportOpen} />

            <ConfirmDialog
                open={!!deleting}
                onOpenChange={(o) => !o && setDeleting(null)}
                title={`Delete ${deleting?.code}?`}
                description="Items with recorded stock movements can't be deleted — deactivate them instead."
                confirmLabel="Delete item"
                onConfirm={() => {
                    if (!deleting) return;
                    router.delete(route('items.destroy', deleting.id), {
                        preserveScroll: true,
                        onFinish: () => setDeleting(null),
                    });
                }}
            />
        </>
    );
}

function ImportDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (o: boolean) => void }) {
    const { setData, post, processing, errors, reset } = useForm<{ file: File | null }>({ file: null });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('items.import'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { reset(); onOpenChange(false); },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Import items from CSV</DialogTitle>
                        <DialogDescription>
                            Header row required with columns: <span className="font-mono">code, description, uom, category</span>.
                            Existing codes are skipped.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2 py-4">
                        <Label htmlFor="csv">CSV file</Label>
                        <Input id="csv" type="file" accept=".csv,text/csv" onChange={(e) => setData('file', e.target.files?.[0] ?? null)} />
                        {errors.file && <p className="text-sm text-destructive">{errors.file}</p>}
                        <p className="text-xs text-muted-foreground">Example: <span className="font-mono">CEM-001,Portland Cement 40kg,bag,Cement</span></p>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
                        <Button type="submit" disabled={processing}>Import</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ItemFormDialog({
    open,
    onOpenChange,
    item,
}: {
    open: boolean;
    onOpenChange: (o: boolean) => void;
    item: ItemRow | null;
}) {
    const { data, setData, post, put, processing, errors, reset } = useForm({
        code: '',
        description: '',
        uom: '',
        category: '',
        has_variants: false,
        is_active: true,
    });

    React.useEffect(() => {
        if (open) {
            setData({
                code: item?.code ?? '',
                description: item?.description ?? '',
                uom: item?.uom ?? '',
                category: item?.category ?? '',
                has_variants: item?.has_variants ?? false,
                is_active: item?.is_active ?? true,
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, item]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => { onOpenChange(false); reset(); } };
        if (item) put(route('items.update', item.id), opts);
        else post(route('items.store'), opts);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>{item ? 'Edit item' : 'New item'}</DialogTitle>
                        <DialogDescription>
                            {item ? 'Update the material details.' : 'Add a material to the catalogue.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="grid grid-cols-3 gap-3">
                            <div className="col-span-2 grid gap-2">
                                <Label htmlFor="code">Item code</Label>
                                <Input id="code" value={data.code} onChange={(e) => setData('code', e.target.value)} placeholder="CEM-001" aria-invalid={!!errors.code} />
                                {errors.code && <p className="text-sm text-destructive">{errors.code}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="uom">UoM</Label>
                                <Input id="uom" list="uom-options" value={data.uom} onChange={(e) => setData('uom', e.target.value)} placeholder="bag" aria-invalid={!!errors.uom} />
                                <datalist id="uom-options">
                                    {UOM_OPTIONS.map((u) => <option key={u} value={u} />)}
                                </datalist>
                                {errors.uom && <p className="text-sm text-destructive">{errors.uom}</p>}
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="description">Description</Label>
                            <Input id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} placeholder="Portland Cement 40kg" aria-invalid={!!errors.description} />
                            {errors.description && <p className="text-sm text-destructive">{errors.description}</p>}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="category">Category</Label>
                            <Input id="category" value={data.category} onChange={(e) => setData('category', e.target.value)} placeholder="Optional" />
                        </div>
                        <label className="flex items-start gap-2 rounded-lg border bg-muted/30 p-3 text-sm">
                            <Checkbox className="mt-0.5" checked={data.has_variants} onCheckedChange={(v) => setData('has_variants', v === true)} />
                            <span>
                                <span className="font-medium">Stocked by variants</span>
                                <span className="block text-xs text-muted-foreground">
                                    Enable when this material comes in several specs (sizes, grades). Manage them from the item's page.
                                </span>
                            </span>
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={data.is_active} onCheckedChange={(v) => setData('is_active', v === true)} />
                            Active
                        </label>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
                        <Button type="submit" disabled={processing}>{item ? 'Save changes' : 'Create item'}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

ItemsIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
