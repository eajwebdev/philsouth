import * as React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Truck } from 'lucide-react';
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

export default function ReceivingCreate({ sites, items }: Props) {
    const [lines, setLines] = React.useState<LineItem[]>([]);
    const { data, setData, post, processing, errors, transform } = useForm({
        site_id: sites[0]?.id ? String(sites[0].id) : '',
        source: 'supplier' as 'supplier' | 'other_project' | 'other',
        supplier: '',
        received_date: new Date().toISOString().slice(0, 10),
        remarks: '',
    });

    transform((d) => ({
        ...d,
        items: lines
            .filter((l) => l.item_variant_id && l.quantity)
            .map((l) => ({ item_variant_id: l.item_variant_id, quantity: l.quantity, unit_cost: l.unit_cost || null })),
    }));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('receiving.store'));
    };

    return (
        <>
            <Head title="New delivery receipt" />
            <form onSubmit={submit} className="flex flex-col gap-4">
                <div>
                    <Button variant="ghost" size="sm" asChild className="mb-2 -ml-2">
                        <Link href={route('receiving.index')}><ArrowLeft /> Back to receiving</Link>
                    </Button>
                    <PageHeader
                        title="New delivery receipt"
                        description="Record incoming stock. Posting adds it to the site balance."
                        icon={Truck}
                        actions={
                            <div className="flex items-center gap-2">
                                <Button type="button" variant="outline" asChild>
                                    <Link href={route('receiving.index')}>Cancel</Link>
                                </Button>
                                <Button type="submit" disabled={processing || lines.length === 0}>Save draft</Button>
                            </div>
                        }
                    />
                </div>

                {/* Details beside items: everything fits one screen on desktop. */}
                <div className="grid items-start gap-4 lg:grid-cols-[minmax(280px,340px)_1fr]">
                    <Card>
                        <CardHeader className="pb-3"><CardTitle className="text-base">Details</CardTitle></CardHeader>
                        <CardContent className="grid gap-3">
                            <div className="grid gap-1.5">
                                <Label htmlFor="site">Site</Label>
                                <Select value={data.site_id} onValueChange={(v) => setData('site_id', v)}>
                                    <SelectTrigger id="site"><SelectValue placeholder="Select site" /></SelectTrigger>
                                    <SelectContent>
                                        {sites.map((s) => <SelectItem key={s.id} value={String(s.id)}>{s.name}</SelectItem>)}
                                    </SelectContent>
                                </Select>
                                {errors.site_id && <p className="text-sm text-destructive">{errors.site_id}</p>}
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="received_date">Received date</Label>
                                <Input id="received_date" type="date" value={data.received_date} onChange={(e) => setData('received_date', e.target.value)} />
                                {errors.received_date && <p className="text-sm text-destructive">{errors.received_date}</p>}
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="source">Source</Label>
                                <Select value={data.source} onValueChange={(v) => setData('source', v as 'supplier' | 'other_project' | 'other')}>
                                    <SelectTrigger id="source"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="supplier">Supplier</SelectItem>
                                        <SelectItem value="other_project">Other project / site</SelectItem>
                                        <SelectItem value="other">Other source</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            {data.source !== 'other_project' && (
                                <div className="grid gap-1.5">
                                    <Label htmlFor="supplier">{data.source === 'supplier' ? 'Supplier' : 'Source (where from)'}</Label>
                                    <Input id="supplier" value={data.supplier} onChange={(e) => setData('supplier', e.target.value)} placeholder={data.source === 'supplier' ? 'Supplier name' : 'e.g. Donation, client-supplied, head office'} />
                                    {errors.supplier && <p className="text-sm text-destructive">{errors.supplier}</p>}
                                </div>
                            )}
                            <div className="grid gap-1.5">
                                <Label htmlFor="remarks">Remarks</Label>
                                <Textarea id="remarks" value={data.remarks} onChange={(e) => setData('remarks', e.target.value)} placeholder="Optional" rows={2} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <LineItemsEditor items={items} value={lines} onChange={setLines} withCost allowCreate error={(errors as Record<string, string>).items} />
                        </CardContent>
                    </Card>
                </div>
            </form>
        </>
    );
}

ReceivingCreate.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
