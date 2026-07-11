import * as React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Truck, PackageCheck, Ban, Pencil, Trash2, FileDown } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, formatQty } from '@/lib/utils';

interface Line {
    id: number;
    quantity: string;
    variant: {
        id: number;
        sku: string;
        label: string | null;
        uom: string | null;
        item: { id: number; code: string; description: string; uom: string };
    };
}
interface Receipt {
    id: number;
    dr_no: string;
    source: 'supplier' | 'other_project' | 'other';
    supplier: string | null;
    received_date: string;
    remarks: string | null;
    status: string;
    received_by: string | null;
    site: { id: number; code: string; name: string };
    creator: { id: number; name: string } | null;
    items: Line[];
}
interface Props {
    receipt: Receipt;
    can: { post: boolean; cancel: boolean; update: boolean; delete: boolean };
}

const SOURCE_LABEL: Record<Receipt['source'], string> = {
    supplier: 'Supplier',
    other_project: 'Other project / site',
    other: 'Other source',
};

export default function ReceivingShow({ receipt, can }: Props) {
    const [confirmPost, setConfirmPost] = React.useState(false);
    const [confirmCancel, setConfirmCancel] = React.useState(false);
    const [confirmDelete, setConfirmDelete] = React.useState(false);

    return (
        <>
            <Head title={receipt.dr_no} />
            <div className="flex flex-col gap-6">
                <div>
                    <Button variant="ghost" size="sm" asChild className="mb-2 -ml-2">
                        <Link href={route('receiving.index')}><ArrowLeft /> Back to receiving</Link>
                    </Button>
                    <PageHeader
                        title={receipt.dr_no}
                        description={`${receipt.site.name} · received ${formatDate(receipt.received_date)}`}
                        icon={Truck}
                        actions={
                            <div className="flex items-center gap-2">
                                <StatusBadge status={receipt.status} />
                                <Button variant="outline" onClick={() => window.open(route('receiving.pdf', receipt.id), '_blank')}><FileDown /> View PDF</Button>
                                {can.update && (
                                    <Button variant="outline" asChild>
                                        <Link href={route('receiving.edit', receipt.id)}><Pencil /> Edit</Link>
                                    </Button>
                                )}
                                {can.delete && (
                                    <Button variant="outline" className="text-destructive hover:text-destructive" onClick={() => setConfirmDelete(true)}>
                                        <Trash2 /> Delete
                                    </Button>
                                )}
                                {can.cancel && (
                                    <Button variant="outline" onClick={() => setConfirmCancel(true)}>
                                        <Ban /> Cancel
                                    </Button>
                                )}
                                {can.post && (
                                    <Button onClick={() => setConfirmPost(true)}>
                                        <PackageCheck /> Post &amp; receive
                                    </Button>
                                )}
                            </div>
                        }
                    />
                </div>

                {/* Compact detail strip — one row instead of three stacked cards. */}
                <div className="grid grid-cols-2 gap-x-6 gap-y-3 rounded-xl border bg-card p-4 text-sm sm:grid-cols-3">
                    <div>
                        <p className="text-xs text-muted-foreground">Source</p>
                        <p className="font-medium">{SOURCE_LABEL[receipt.source]}</p>
                        {receipt.supplier && <p className="text-xs text-muted-foreground">{receipt.supplier}</p>}
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Site</p>
                        <p className="font-medium">{receipt.site.name} <Badge variant="secondary" className="ml-1 font-mono text-[10px]">{receipt.site.code}</Badge></p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Prepared by</p>
                        <p className="font-medium">{receipt.creator?.name ?? '—'}</p>
                        {receipt.received_by && <p className="text-xs text-muted-foreground">Received: {receipt.received_by}</p>}
                    </div>
                </div>

                <Card>
                    <CardHeader><CardTitle className="text-base">Items</CardTitle></CardHeader>
                    <CardContent>
                        <div className="rounded-xl border">
                            <Table>
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead>Item</TableHead>
                                        <TableHead>SKU</TableHead>
                                        <TableHead className="text-right">Quantity</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {receipt.items.map((line) => (
                                        <TableRow key={line.id}>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {line.variant.item.description}
                                                    {line.variant.label && <span className="text-muted-foreground"> — {line.variant.label}</span>}
                                                </p>
                                            </TableCell>
                                            <TableCell className="font-mono text-sm text-muted-foreground">{line.variant.sku}</TableCell>
                                            <TableCell className="text-right font-semibold tabular-nums">
                                                {formatQty(line.quantity)}{' '}
                                                <span className="text-xs font-normal text-muted-foreground">{line.variant.uom ?? line.variant.item.uom}</span>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                        {receipt.remarks && (
                            <p className="mt-4 text-sm text-muted-foreground"><span className="font-medium text-foreground">Remarks:</span> {receipt.remarks}</p>
                        )}
                    </CardContent>
                </Card>
            </div>

            <ConfirmDialog
                open={confirmPost}
                onOpenChange={setConfirmPost}
                destructive={false}
                title={`Post ${receipt.dr_no}?`}
                description="This adds every line to the site's stock balance and can't be undone."
                confirmLabel="Post & receive"
                onConfirm={() => router.post(route('receiving.post', receipt.id), {}, { preserveScroll: true, onFinish: () => setConfirmPost(false) })}
            />
            <ConfirmDialog
                open={confirmCancel}
                onOpenChange={setConfirmCancel}
                title={`Cancel ${receipt.dr_no}?`}
                description="This voids the draft receipt."
                confirmLabel="Cancel receipt"
                onConfirm={() => router.post(route('receiving.cancel', receipt.id), {}, { preserveScroll: true, onFinish: () => setConfirmCancel(false) })}
            />
            <ConfirmDialog
                open={confirmDelete}
                onOpenChange={setConfirmDelete}
                title={`Delete ${receipt.dr_no}?`}
                description="This permanently removes the draft receipt and its lines."
                confirmLabel="Delete draft"
                onConfirm={() => router.delete(route('receiving.destroy', receipt.id), { onFinish: () => setConfirmDelete(false) })}
            />
        </>
    );
}

ReceivingShow.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
