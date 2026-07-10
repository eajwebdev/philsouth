import * as React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowLeftRight } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { LineItemsEditor, type CatalogItem, type LineItem } from '@/components/line-items-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    fromSites: SiteRef[];
    toSites: SiteRef[];
    items: CatalogItem[];
}

export default function TransferCreate({ fromSites, toSites, items }: Props) {
    const [lines, setLines] = React.useState<LineItem[]>([]);
    const { data, setData, post, processing, errors, transform } = useForm({
        from_site_id: fromSites[0]?.id ? String(fromSites[0].id) : '',
        to_site_id: '',
        date: new Date().toISOString().slice(0, 10),
        time_delivered: '',
        delivered_to: '',
        delivered_by: '',
        vehicle_plate: '',
    });

    transform((d) => ({
        ...d,
        items: lines
            .filter((l) => l.item_variant_id && l.quantity)
            .map((l) => ({ item_variant_id: l.item_variant_id, unit: l.unit ?? '', qty: l.quantity })),
    }));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('transfers.store'));
    };

    const destinations = toSites.filter((s) => String(s.id) !== data.from_site_id);

    return (
        <>
            <Head title="New transfer slip" />
            <form onSubmit={submit} className="flex flex-col gap-6">
                <div>
                    <Button variant="ghost" size="sm" asChild className="mb-2 -ml-2">
                        <Link href={route('transfers.index')}><ArrowLeft /> Back to transfers</Link>
                    </Button>
                    <PageHeader title="New transfer slip" description="Draft a transfer. Dispatching deducts from the origin; receiving adds to the destination." icon={ArrowLeftRight} />
                </div>

                <Card>
                    <CardHeader><CardTitle className="text-base">Details</CardTitle></CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="from">From site (origin)</Label>
                            <Select value={data.from_site_id} onValueChange={(v) => setData('from_site_id', v)}>
                                <SelectTrigger id="from"><SelectValue placeholder="Select origin" /></SelectTrigger>
                                <SelectContent>
                                    {fromSites.map((s) => <SelectItem key={s.id} value={String(s.id)}>{s.name}</SelectItem>)}
                                </SelectContent>
                            </Select>
                            {errors.from_site_id && <p className="text-sm text-destructive">{errors.from_site_id}</p>}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="to">To site (destination)</Label>
                            <Select value={data.to_site_id} onValueChange={(v) => setData('to_site_id', v)}>
                                <SelectTrigger id="to"><SelectValue placeholder="Select destination" /></SelectTrigger>
                                <SelectContent>
                                    {destinations.map((s) => <SelectItem key={s.id} value={String(s.id)}>{s.name}</SelectItem>)}
                                </SelectContent>
                            </Select>
                            {errors.to_site_id && <p className="text-sm text-destructive">{errors.to_site_id}</p>}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="date">Date</Label>
                            <Input id="date" type="date" value={data.date} onChange={(e) => setData('date', e.target.value)} />
                            {errors.date && <p className="text-sm text-destructive">{errors.date}</p>}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="vehicle_plate">Vehicle plate</Label>
                            <Input id="vehicle_plate" value={data.vehicle_plate} onChange={(e) => setData('vehicle_plate', e.target.value)} placeholder="Optional" />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="delivered_by">Delivered by</Label>
                            <Input id="delivered_by" value={data.delivered_by} onChange={(e) => setData('delivered_by', e.target.value)} placeholder="Driver / hauler" />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="delivered_to">Delivered to</Label>
                            <Input id="delivered_to" value={data.delivered_to} onChange={(e) => setData('delivered_to', e.target.value)} placeholder="Recipient" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="pt-6">
                        <LineItemsEditor items={items} value={lines} onChange={setLines} withUnit error={(errors as Record<string, string>).items} />
                    </CardContent>
                </Card>

                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" asChild>
                        <Link href={route('transfers.index')}>Cancel</Link>
                    </Button>
                    <Button type="submit" disabled={processing || lines.length === 0}>Save draft</Button>
                </div>
            </form>
        </>
    );
}

TransferCreate.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
