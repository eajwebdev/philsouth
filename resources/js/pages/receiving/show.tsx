import * as React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Truck, PackageCheck, Ban } from 'lucide-react';
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
    source: 'supplier' | 'other_project';
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
    can: { post: boolean; cancel: boolean };
}

export default function ReceivingShow({ receipt, can }: Props) {
    const [confirmPost, setConfirmPost] = React.useState(false);
    const [confirmCancel, setConfirmCancel] = React.useState(false);

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

                <div className="grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Source</CardTitle></CardHeader>
                        <CardContent>
                            <p className="font-medium capitalize">{receipt.source.replace('_', ' ')}</p>
                            {receipt.supplier && <p className="text-sm text-muted-foreground">{receipt.supplier}</p>}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Site</CardTitle></CardHeader>
                        <CardContent>
                            <p className="font-medium">{receipt.site.name}</p>
                            <Badge variant="secondary" className="mt-1 font-mono">{receipt.site.code}</Badge>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Prepared by</CardTitle></CardHeader>
                        <CardContent>
                            <p className="font-medium">{receipt.creator?.name ?? '—'}</p>
                            {receipt.received_by && <p className="text-sm text-muted-foreground">Received: {receipt.received_by}</p>}
                        </CardContent>
                    </Card>
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
        </>
    );
}

ReceivingShow.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
