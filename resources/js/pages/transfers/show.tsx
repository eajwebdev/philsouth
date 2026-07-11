import * as React from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowLeftRight, ArrowRight, Send, PackageCheck, Ban, Truck, FileDown } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { formatDate, formatQty } from '@/lib/utils';

interface Line {
    id: number;
    qty: string;
    unit: string | null;
    variant: {
        id: number;
        sku: string;
        label: string | null;
        uom: string | null;
        item: { id: number; code: string; description: string; uom: string };
    };
}
interface SiteRef { id: number; code: string; name: string }
interface Transfer {
    id: number;
    ts_no: string;
    date: string;
    time_delivered: string | null;
    delivered_to: string | null;
    delivered_by: string | null;
    vehicle_plate: string | null;
    status: string;
    date_received: string | null;
    time_received: string | null;
    received_by: string | null;
    from_site: SiteRef;
    to_site: SiteRef;
    creator: { id: number; name: string } | null;
    items: Line[];
}
interface Props {
    transfer: Transfer;
    can: { dispatch: boolean; receive: boolean; cancel: boolean };
}

export default function TransferShow({ transfer, can }: Props) {
    const [receiveOpen, setReceiveOpen] = React.useState(false);
    const [confirm, setConfirm] = React.useState<null | 'dispatch' | 'cancel'>(null);

    const act = (action: string) =>
        router.post(route(`transfers.${action}`, transfer.id), {}, { preserveScroll: true, onFinish: () => setConfirm(null) });

    return (
        <>
            <Head title={transfer.ts_no} />
            <div className="flex flex-col gap-6">
                <div>
                    <Button variant="ghost" size="sm" asChild className="mb-2 -ml-2">
                        <Link href={route('transfers.index')}><ArrowLeft /> Back to transfers</Link>
                    </Button>
                    <PageHeader
                        title={transfer.ts_no}
                        description={formatDate(transfer.date)}
                        icon={ArrowLeftRight}
                        actions={
                            <div className="flex flex-wrap items-center gap-2">
                                <StatusBadge status={transfer.status} />
                                <Button variant="outline" onClick={() => window.open(route('transfers.pdf', transfer.id), '_blank')}><FileDown /> View PDF</Button>
                                {can.cancel && <Button variant="outline" onClick={() => setConfirm('cancel')}><Ban /> Cancel</Button>}
                                {can.dispatch && <Button onClick={() => setConfirm('dispatch')}><Send /> Dispatch</Button>}
                                {can.receive && <Button onClick={() => setReceiveOpen(true)}><PackageCheck /> Receive</Button>}
                            </div>
                        }
                    />
                </div>

                {/* Route banner */}
                <div className="flex flex-wrap items-center gap-4 rounded-xl border bg-card p-5">
                    <div className="flex items-center gap-3">
                        <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"><Truck className="size-5" /></div>
                        <div>
                            <p className="text-xs text-muted-foreground">Origin</p>
                            <p className="font-medium">{transfer.from_site.name}</p>
                            <Badge variant="secondary" className="mt-0.5 font-mono">{transfer.from_site.code}</Badge>
                        </div>
                    </div>
                    <ArrowRight className="size-6 text-muted-foreground" />
                    <div className="flex items-center gap-3">
                        <div className="flex size-10 items-center justify-center rounded-lg bg-success/10 text-success"><PackageCheck className="size-5" /></div>
                        <div>
                            <p className="text-xs text-muted-foreground">Destination</p>
                            <p className="font-medium">{transfer.to_site.name}</p>
                            <Badge variant="secondary" className="mt-0.5 font-mono">{transfer.to_site.code}</Badge>
                        </div>
                    </div>
                    <div className="ml-auto text-sm text-muted-foreground">
                        {transfer.vehicle_plate && <p>Vehicle: <span className="font-medium text-foreground">{transfer.vehicle_plate}</span></p>}
                        {transfer.delivered_by && <p>By: {transfer.delivered_by}</p>}
                        {transfer.received_by && <p>Received by: {transfer.received_by} {transfer.date_received ? `(${formatDate(transfer.date_received)})` : ''}</p>}
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
                                        <TableHead>Unit</TableHead>
                                        <TableHead className="text-right">Quantity</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {transfer.items.map((line) => (
                                        <TableRow key={line.id}>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {line.variant.item.description}
                                                    {line.variant.label && <span className="text-muted-foreground"> — {line.variant.label}</span>}
                                                </p>
                                            </TableCell>
                                            <TableCell className="font-mono text-sm text-muted-foreground">{line.variant.sku}</TableCell>
                                            <TableCell className="text-sm">{line.unit ?? '—'}</TableCell>
                                            <TableCell className="text-right font-semibold tabular-nums">
                                                {formatQty(line.qty)}{' '}
                                                <span className="text-xs font-normal text-muted-foreground">{line.variant.uom ?? line.variant.item.uom}</span>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <ReceiveDialog open={receiveOpen} onOpenChange={setReceiveOpen} transfer={transfer} />

            <ConfirmDialog
                open={confirm === 'dispatch'}
                onOpenChange={(o) => !o && setConfirm(null)}
                destructive={false}
                title={`Dispatch ${transfer.ts_no}?`}
                description={`This deducts the items from ${transfer.from_site.name} and marks them in transit.`}
                confirmLabel="Dispatch"
                onConfirm={() => act('dispatch')}
            />
            <ConfirmDialog
                open={confirm === 'cancel'}
                onOpenChange={(o) => !o && setConfirm(null)}
                title={`Cancel ${transfer.ts_no}?`}
                description="This voids the draft transfer."
                confirmLabel="Cancel transfer"
                onConfirm={() => act('cancel')}
            />
        </>
    );
}

function ReceiveDialog({
    open,
    onOpenChange,
    transfer,
}: {
    open: boolean;
    onOpenChange: (o: boolean) => void;
    transfer: Transfer;
}) {
    const { data, setData, post, processing, reset } = useForm({ received_by: '', time_received: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('transfers.receive', transfer.id), {
            preserveScroll: true,
            onSuccess: () => { onOpenChange(false); reset(); },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Receive {transfer.ts_no}</DialogTitle>
                        <DialogDescription>
                            Confirm receipt at {transfer.to_site.name}. This adds the items to its balance.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 py-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="received_by">Received by</Label>
                            <Input id="received_by" value={data.received_by} onChange={(e) => setData('received_by', e.target.value)} placeholder="Name" />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="time_received">Time received</Label>
                            <Input id="time_received" type="time" value={data.time_received} onChange={(e) => setData('time_received', e.target.value)} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
                        <Button type="submit" disabled={processing}>Confirm receipt</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

TransferShow.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
