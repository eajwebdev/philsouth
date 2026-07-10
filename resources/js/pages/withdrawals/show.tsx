import * as React from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ClipboardList,
    Send,
    Check,
    X,
    PackageCheck,
    PackageOpen,
    Ban,
    CircleCheck,
} from 'lucide-react';
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
import { cn, formatDate, formatQty } from '@/lib/utils';

interface Line {
    id: number;
    qty: string;
    variant: {
        id: number;
        sku: string;
        label: string | null;
        uom: string | null;
        item: { id: number; code: string; description: string; uom: string };
    };
}
interface Person { id: number; name: string }
interface Slip {
    id: number;
    ws_no: string;
    project_code: string | null;
    date: string;
    time: string | null;
    requested_by_type: string;
    requested_by_other: string | null;
    delivered_to: string | null;
    remarks: string | null;
    status: string;
    reject_reason: string | null;
    approved_at: string | null;
    released_at: string | null;
    received_at: string | null;
    received_by: string | null;
    site: { id: number; code: string; name: string };
    prepared_by: Person | null;
    approved_by: Person | null;
    released_by: Person | null;
    items: Line[];
}
interface Props {
    slip: Slip;
    can: {
        submit: boolean;
        approve: boolean;
        reject: boolean;
        release: boolean;
        receive: boolean;
        cancel: boolean;
    };
}

const STEPS = ['draft', 'pending_approval', 'approved', 'released', 'received'];
const STEP_LABEL: Record<string, string> = {
    draft: 'Draft',
    pending_approval: 'Pending approval',
    approved: 'Approved',
    released: 'Released',
    received: 'Received',
};

function WorkflowStepper({ status }: { status: string }) {
    const terminal = status === 'rejected' || status === 'cancelled';
    const currentIndex = STEPS.indexOf(status);

    if (terminal) {
        return (
            <div className="flex items-center gap-2 rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3">
                <Ban className="size-5 text-destructive" />
                <span className="font-medium capitalize text-destructive">{status}</span>
            </div>
        );
    }

    return (
        <div className="flex items-center overflow-x-auto rounded-xl border bg-card p-4">
            {STEPS.map((step, i) => {
                const done = i < currentIndex;
                const active = i === currentIndex;
                return (
                    <React.Fragment key={step}>
                        <div className="flex min-w-max flex-col items-center gap-1.5">
                            <div
                                className={cn(
                                    'flex size-8 items-center justify-center rounded-full border-2 text-xs font-semibold',
                                    done && 'border-success bg-success text-success-foreground',
                                    active && 'border-primary bg-primary text-primary-foreground',
                                    !done && !active && 'border-border text-muted-foreground',
                                )}
                            >
                                {done ? <CircleCheck className="size-4" /> : i + 1}
                            </div>
                            <span className={cn('text-xs', active ? 'font-medium text-foreground' : 'text-muted-foreground')}>
                                {STEP_LABEL[step]}
                            </span>
                        </div>
                        {i < STEPS.length - 1 && (
                            <div className={cn('mx-2 h-0.5 flex-1 min-w-8', done ? 'bg-success' : 'bg-border')} />
                        )}
                    </React.Fragment>
                );
            })}
        </div>
    );
}

export default function WithdrawalShow({ slip, can }: Props) {
    const [rejectOpen, setRejectOpen] = React.useState(false);
    const [confirm, setConfirm] = React.useState<null | 'submit' | 'release' | 'receive' | 'cancel'>(null);

    const act = (action: string) =>
        router.post(route(`withdrawals.${action}`, slip.id), {}, { preserveScroll: true, onFinish: () => setConfirm(null) });

    const requestedBy = slip.requested_by_type === 'others'
        ? slip.requested_by_other ?? 'Others'
        : slip.requested_by_type.replace('_', ' ');

    return (
        <>
            <Head title={slip.ws_no} />
            <div className="flex flex-col gap-6">
                <div>
                    <Button variant="ghost" size="sm" asChild className="mb-2 -ml-2">
                        <Link href={route('withdrawals.index')}><ArrowLeft /> Back to withdrawals</Link>
                    </Button>
                    <PageHeader
                        title={slip.ws_no}
                        description={`${slip.site.name} · ${formatDate(slip.date)}`}
                        icon={ClipboardList}
                        actions={
                            <div className="flex flex-wrap items-center gap-2">
                                <StatusBadge status={slip.status} />
                                {can.cancel && (
                                    <Button variant="outline" onClick={() => setConfirm('cancel')}><Ban /> Cancel</Button>
                                )}
                                {can.submit && (
                                    <Button onClick={() => setConfirm('submit')}><Send /> Submit for approval</Button>
                                )}
                                {can.reject && (
                                    <Button variant="outline" onClick={() => setRejectOpen(true)}><X /> Reject</Button>
                                )}
                                {can.approve && (
                                    <Button onClick={() => act('approve')}><Check /> Approve</Button>
                                )}
                                {can.release && (
                                    <Button onClick={() => setConfirm('release')}><PackageCheck /> Release stock</Button>
                                )}
                                {can.receive && (
                                    <Button onClick={() => setConfirm('receive')}><PackageOpen /> Mark received</Button>
                                )}
                            </div>
                        }
                    />
                </div>

                <WorkflowStepper status={slip.status} />

                {slip.status === 'rejected' && slip.reject_reason && (
                    <div className="rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm">
                        <span className="font-medium text-destructive">Rejected:</span> {slip.reject_reason}
                    </div>
                )}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Site</CardTitle></CardHeader>
                        <CardContent>
                            <p className="font-medium">{slip.site.name}</p>
                            <Badge variant="secondary" className="mt-1 font-mono">{slip.site.code}</Badge>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Requested by</CardTitle></CardHeader>
                        <CardContent>
                            <p className="font-medium capitalize">{requestedBy}</p>
                            {slip.delivered_to && <p className="text-sm text-muted-foreground">To: {slip.delivered_to}</p>}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Prepared / approved</CardTitle></CardHeader>
                        <CardContent className="text-sm">
                            <p>{slip.prepared_by?.name ?? '—'}</p>
                            {slip.approved_by && (
                                <p className="text-muted-foreground">Approved by {slip.approved_by.name}</p>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Released / received</CardTitle></CardHeader>
                        <CardContent className="text-sm">
                            <p>{slip.released_by ? slip.released_by.name : '—'}</p>
                            {slip.received_by && <p className="text-muted-foreground">Received: {slip.received_by}</p>}
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
                                    {slip.items.map((line) => (
                                        <TableRow key={line.id}>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {line.variant.item.description}
                                                    {line.variant.label && <span className="text-muted-foreground"> — {line.variant.label}</span>}
                                                </p>
                                            </TableCell>
                                            <TableCell className="font-mono text-sm text-muted-foreground">{line.variant.sku}</TableCell>
                                            <TableCell className="text-right font-semibold tabular-nums">
                                                {formatQty(line.qty)}{' '}
                                                <span className="text-xs font-normal text-muted-foreground">{line.variant.uom ?? line.variant.item.uom}</span>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                        {slip.remarks && (
                            <p className="mt-4 text-sm text-muted-foreground"><span className="font-medium text-foreground">Remarks:</span> {slip.remarks}</p>
                        )}
                    </CardContent>
                </Card>
            </div>

            <RejectDialog open={rejectOpen} onOpenChange={setRejectOpen} slip={slip} />

            <ConfirmDialog
                open={confirm === 'submit'}
                onOpenChange={(o) => !o && setConfirm(null)}
                destructive={false}
                title={`Submit ${slip.ws_no}?`}
                description="This sends the slip to an engineer for approval."
                confirmLabel="Submit"
                onConfirm={() => act('submit')}
            />
            <ConfirmDialog
                open={confirm === 'release'}
                onOpenChange={(o) => !o && setConfirm(null)}
                destructive={false}
                title={`Release ${slip.ws_no}?`}
                description="This issues the stock OUT and deducts it from the site balance. It can't be undone."
                confirmLabel="Release stock"
                onConfirm={() => act('release')}
            />
            <ConfirmDialog
                open={confirm === 'receive'}
                onOpenChange={(o) => !o && setConfirm(null)}
                destructive={false}
                title={`Mark ${slip.ws_no} received?`}
                confirmLabel="Mark received"
                onConfirm={() => act('receive')}
            />
            <ConfirmDialog
                open={confirm === 'cancel'}
                onOpenChange={(o) => !o && setConfirm(null)}
                title={`Cancel ${slip.ws_no}?`}
                description="This voids the slip."
                confirmLabel="Cancel slip"
                onConfirm={() => act('cancel')}
            />
        </>
    );
}

function RejectDialog({
    open,
    onOpenChange,
    slip,
}: {
    open: boolean;
    onOpenChange: (o: boolean) => void;
    slip: Slip;
}) {
    const { data, setData, post, processing, reset } = useForm({ reject_reason: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('withdrawals.reject', slip.id), {
            preserveScroll: true,
            onSuccess: () => { onOpenChange(false); reset(); },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Reject {slip.ws_no}</DialogTitle>
                        <DialogDescription>Optionally note why this slip is being rejected.</DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2 py-4">
                        <Label htmlFor="reject_reason">Reason</Label>
                        <Input id="reject_reason" value={data.reject_reason} onChange={(e) => setData('reject_reason', e.target.value)} placeholder="Optional" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
                        <Button type="submit" variant="destructive" disabled={processing}>Reject slip</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

WithdrawalShow.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
