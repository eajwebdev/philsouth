import * as React from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Package,
    Plus,
    Pencil,
    Trash2,
    Star,
    Barcode,
    Layers,
    X,
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { IconButton } from '@/components/icon-button';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface Variant {
    id: number;
    sku: string;
    label: string | null;
    attributes: Record<string, string> | null;
    barcode: string | null;
    uom: string | null;
    is_default: boolean;
    is_active: boolean;
}
interface ItemModel {
    id: number;
    code: string;
    description: string;
    uom: string;
    category: string | null;
    has_variants: boolean;
    variants_count: number;
    variants: Variant[];
}
interface Props {
    item: ItemModel;
    can: { manage: boolean };
}

export default function ItemShow({ item, can }: Props) {
    const [formOpen, setFormOpen] = React.useState(false);
    const [editing, setEditing] = React.useState<Variant | null>(null);
    const [deleting, setDeleting] = React.useState<Variant | null>(null);

    return (
        <>
            <Head title={item.code} />
            <div className="flex flex-col gap-6">
                <div>
                    <Button variant="ghost" size="sm" asChild className="mb-2 -ml-2">
                        <Link href={route('items.index')}>
                            <ArrowLeft /> Back to items
                        </Link>
                    </Button>
                    <PageHeader
                        title={item.description}
                        description={`${item.code} · ${item.uom}${item.category ? ' · ' + item.category : ''}`}
                        icon={Package}
                        actions={
                            item.has_variants ? (
                                <Badge variant="secondary" className="gap-1">
                                    <Layers className="size-3" /> Variant item
                                </Badge>
                            ) : (
                                <Badge variant="outline">Single stockable unit</Badge>
                            )
                        }
                    />
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle className="text-base">
                            {item.has_variants ? 'Variants' : 'Stockable unit'}
                        </CardTitle>
                        {can.manage && item.has_variants && (
                            <Button size="sm" onClick={() => { setEditing(null); setFormOpen(true); }}>
                                <Plus /> Add variant
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent>
                        {!item.has_variants && (
                            <p className="mb-4 text-sm text-muted-foreground">
                                This is a simple item — it holds stock as a single unit. You can still
                                give it a barcode for scanning. Turn on “stocked by variants” to manage
                                multiple specs.
                            </p>
                        )}
                        <div className="rounded-xl border">
                            <Table>
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead>SKU</TableHead>
                                        <TableHead>Label</TableHead>
                                        <TableHead>Attributes</TableHead>
                                        <TableHead>Barcode</TableHead>
                                        <TableHead>UoM</TableHead>
                                        <TableHead>Status</TableHead>
                                        {can.manage && <TableHead className="text-right sr-only">Actions</TableHead>}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {item.variants.map((v) => (
                                        <TableRow key={v.id}>
                                            <TableCell className="font-mono text-sm font-medium">
                                                <span className="flex items-center gap-2">
                                                    {v.sku}
                                                    {v.is_default && (
                                                        <Badge variant="outline" className="gap-1 border-primary/30 bg-primary/10 text-primary">
                                                            <Star className="size-3" /> Default
                                                        </Badge>
                                                    )}
                                                </span>
                                            </TableCell>
                                            <TableCell>{v.label ?? <span className="text-muted-foreground/50">—</span>}</TableCell>
                                            <TableCell>
                                                {v.attributes && Object.keys(v.attributes).length ? (
                                                    <div className="flex flex-wrap gap-1">
                                                        {Object.entries(v.attributes).map(([k, val]) => (
                                                            <Badge key={k} variant="secondary" className="text-xs">
                                                                {k}: {val}
                                                            </Badge>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground/50">—</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {v.barcode ? (
                                                    <span className="inline-flex items-center gap-1 font-mono text-xs text-muted-foreground">
                                                        <Barcode className="size-3.5" /> {v.barcode}
                                                    </span>
                                                ) : (
                                                    <span className="text-muted-foreground/50">—</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-sm">{v.uom ?? item.uom}</TableCell>
                                            <TableCell>
                                                {v.is_active ? (
                                                    <Badge variant="outline" className="border-success/30 bg-success/10 text-success">Active</Badge>
                                                ) : (
                                                    <Badge variant="outline" className="text-muted-foreground">Inactive</Badge>
                                                )}
                                            </TableCell>
                                            {can.manage && (
                                                <TableCell>
                                                    <div className="flex justify-end gap-0.5">
                                                        {!v.is_default && item.has_variants && (
                                                            <IconButton
                                                                label="Set as default"
                                                                onClick={() => router.put(route('variants.default', [item.id, v.id]), {}, { preserveScroll: true })}
                                                            >
                                                                <Star />
                                                            </IconButton>
                                                        )}
                                                        <IconButton label="Edit" onClick={() => { setEditing(v); setFormOpen(true); }}>
                                                            <Pencil />
                                                        </IconButton>
                                                        {item.has_variants && (
                                                            <IconButton
                                                                label="Delete"
                                                                className="text-destructive hover:text-destructive"
                                                                onClick={() => setDeleting(v)}
                                                            >
                                                                <Trash2 />
                                                            </IconButton>
                                                        )}
                                                    </div>
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {can.manage && (
                <VariantFormDialog open={formOpen} onOpenChange={setFormOpen} item={item} variant={editing} />
            )}

            <ConfirmDialog
                open={!!deleting}
                onOpenChange={(o) => !o && setDeleting(null)}
                title={`Delete ${deleting?.sku}?`}
                description="Variants with stock movements can't be deleted — deactivate them instead."
                confirmLabel="Delete variant"
                onConfirm={() => {
                    if (!deleting) return;
                    router.delete(route('variants.destroy', [item.id, deleting.id]), {
                        preserveScroll: true,
                        onFinish: () => setDeleting(null),
                    });
                }}
            />
        </>
    );
}

interface AttrRow {
    key: string;
    value: string;
}

function VariantFormDialog({
    open,
    onOpenChange,
    item,
    variant,
}: {
    open: boolean;
    onOpenChange: (o: boolean) => void;
    item: ItemModel;
    variant: Variant | null;
}) {
    const [attrs, setAttrs] = React.useState<AttrRow[]>([]);
    const { data, setData, post, put, processing, errors, reset, transform } = useForm({
        sku: '',
        label: '',
        barcode: '',
        uom: '',
        is_active: true,
    });

    React.useEffect(() => {
        if (open) {
            setData({
                sku: variant?.sku ?? '',
                label: variant?.label ?? '',
                barcode: variant?.barcode ?? '',
                uom: variant?.uom ?? '',
                is_active: variant?.is_active ?? true,
            });
            setAttrs(
                variant?.attributes
                    ? Object.entries(variant.attributes).map(([key, value]) => ({ key, value }))
                    : [],
            );
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, variant]);

    transform((d) => ({
        ...d,
        attributes: attrs
            .filter((a) => a.key.trim())
            .reduce<Record<string, string>>((acc, a) => ({ ...acc, [a.key.trim()]: a.value }), {}),
    }));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => { onOpenChange(false); reset(); setAttrs([]); } };
        if (variant) put(route('variants.update', [item.id, variant.id]), opts);
        else post(route('variants.store', item.id), opts);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>{variant ? 'Edit variant' : 'Add variant'}</DialogTitle>
                        <DialogDescription>{item.description}</DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="sku">SKU</Label>
                                <Input id="sku" value={data.sku} onChange={(e) => setData('sku', e.target.value)} placeholder="STL-DB-12" aria-invalid={!!errors.sku} />
                                {errors.sku && <p className="text-sm text-destructive">{errors.sku}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="label">Label</Label>
                                <Input id="label" value={data.label} onChange={(e) => setData('label', e.target.value)} placeholder="12mm x 6m" />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="barcode">Barcode / QR</Label>
                                <Input id="barcode" value={data.barcode} onChange={(e) => setData('barcode', e.target.value)} placeholder="Optional" aria-invalid={!!errors.barcode} />
                                {errors.barcode && <p className="text-sm text-destructive">{errors.barcode}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="uom">UoM override</Label>
                                <Input id="uom" value={data.uom} onChange={(e) => setData('uom', e.target.value)} placeholder={item.uom} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label>Attributes</Label>
                            <div className="flex flex-col gap-2">
                                {attrs.map((a, i) => (
                                    <div key={i} className="flex items-center gap-2">
                                        <Input
                                            value={a.key}
                                            onChange={(e) => setAttrs(attrs.map((x, j) => (j === i ? { ...x, key: e.target.value } : x)))}
                                            placeholder="size"
                                            className="flex-1"
                                        />
                                        <Input
                                            value={a.value}
                                            onChange={(e) => setAttrs(attrs.map((x, j) => (j === i ? { ...x, value: e.target.value } : x)))}
                                            placeholder="12mm"
                                            className="flex-1"
                                        />
                                        <IconButton label="Remove attribute" onClick={() => setAttrs(attrs.filter((_, j) => j !== i))}>
                                            <X />
                                        </IconButton>
                                    </div>
                                ))}
                                <Button type="button" variant="outline" size="sm" className="w-fit" onClick={() => setAttrs([...attrs, { key: '', value: '' }])}>
                                    <Plus /> Add attribute
                                </Button>
                            </div>
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={data.is_active} onCheckedChange={(v) => setData('is_active', v === true)} />
                            Active
                        </label>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
                        <Button type="submit" disabled={processing}>{variant ? 'Save changes' : 'Add variant'}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

ItemShow.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
