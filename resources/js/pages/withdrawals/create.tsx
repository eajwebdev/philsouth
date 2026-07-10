import * as React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ClipboardList } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { LineItemsEditor, type CatalogItem, type LineItem } from '@/components/line-items-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { SiteRef } from '@/types';

interface Props {
    sites: SiteRef[];
    items: CatalogItem[];
}

const REQUESTED_BY = [
    { value: 'subcon', label: 'Subcontractor' },
    { value: 'group_a', label: 'Group A' },
    { value: 'group_b', label: 'Group B' },
    { value: 'others', label: 'Others' },
];

export default function WithdrawalCreate({ sites, items }: Props) {
    const [lines, setLines] = React.useState<LineItem[]>([]);
    const { data, setData, post, processing, errors, transform } = useForm({
        site_id: sites[0]?.id ? String(sites[0].id) : '',
        project_code: '',
        date: new Date().toISOString().slice(0, 10),
        time: '',
        requested_by_type: 'subcon',
        requested_by_other: '',
        delivered_to: '',
        remarks: '',
    });

    transform((d) => ({
        ...d,
        items: lines
            .filter((l) => l.item_variant_id && l.quantity)
            .map((l) => ({ item_variant_id: l.item_variant_id, qty: l.quantity })),
    }));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('withdrawals.store'));
    };

    return (
        <>
            <Head title="New withdrawal slip" />
            <form onSubmit={submit} className="flex flex-col gap-6">
                <div>
                    <Button variant="ghost" size="sm" asChild className="mb-2 -ml-2">
                        <Link href={route('withdrawals.index')}><ArrowLeft /> Back to withdrawals</Link>
                    </Button>
                    <PageHeader title="New withdrawal slip" description="Create a draft. It won't move stock until approved and released." icon={ClipboardList} />
                </div>

                <Card>
                    <CardHeader><CardTitle className="text-base">Details</CardTitle></CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="site">Site</Label>
                            <Select value={data.site_id} onValueChange={(v) => setData('site_id', v)}>
                                <SelectTrigger id="site"><SelectValue placeholder="Select site" /></SelectTrigger>
                                <SelectContent>
                                    {sites.map((s) => <SelectItem key={s.id} value={String(s.id)}>{s.name}</SelectItem>)}
                                </SelectContent>
                            </Select>
                            {errors.site_id && <p className="text-sm text-destructive">{errors.site_id}</p>}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="project_code">Project code</Label>
                            <Input id="project_code" value={data.project_code} onChange={(e) => setData('project_code', e.target.value)} placeholder="Optional" />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="date">Date</Label>
                            <Input id="date" type="date" value={data.date} onChange={(e) => setData('date', e.target.value)} />
                            {errors.date && <p className="text-sm text-destructive">{errors.date}</p>}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="time">Time</Label>
                            <Input id="time" type="time" value={data.time} onChange={(e) => setData('time', e.target.value)} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="requested_by_type">Requested by</Label>
                            <Select value={data.requested_by_type} onValueChange={(v) => setData('requested_by_type', v)}>
                                <SelectTrigger id="requested_by_type"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {REQUESTED_BY.map((r) => <SelectItem key={r.value} value={r.value}>{r.label}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </div>
                        {data.requested_by_type === 'others' && (
                            <div className="grid gap-2">
                                <Label htmlFor="requested_by_other">Specify requester</Label>
                                <Input id="requested_by_other" value={data.requested_by_other} onChange={(e) => setData('requested_by_other', e.target.value)} />
                                {errors.requested_by_other && <p className="text-sm text-destructive">{errors.requested_by_other}</p>}
                            </div>
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor="delivered_to">Delivered to</Label>
                            <Input id="delivered_to" value={data.delivered_to} onChange={(e) => setData('delivered_to', e.target.value)} placeholder="Recipient / location" />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="remarks">Remarks</Label>
                            <Textarea id="remarks" value={data.remarks} onChange={(e) => setData('remarks', e.target.value)} placeholder="Optional" rows={2} />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="pt-6">
                        <LineItemsEditor items={items} value={lines} onChange={setLines} allowCreate error={(errors as Record<string, string>).items} />
                    </CardContent>
                </Card>

                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" asChild>
                        <Link href={route('withdrawals.index')}>Cancel</Link>
                    </Button>
                    <Button type="submit" disabled={processing || lines.length === 0}>Save draft</Button>
                </div>
            </form>
        </>
    );
}

WithdrawalCreate.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
