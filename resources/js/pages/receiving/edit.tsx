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
    receipt: {
        id: number;
        dr_no: string;
        site_id: number;
        source: 'supplier' | 'other_project';
        supplier: string | null;
        received_date: string;
        remarks: string | null;
        items: LineItem[];
    };
    sites: SiteRef[];
    items: CatalogItem[];
}

export default function ReceivingEdit({ receipt, sites, items }: Props) {
    const [lines, setLines] = React.useState<LineItem[]>(receipt.items);
    const { data, setData, put, processing, errors, transform } = useForm({
        site_id: String(receipt.site_id),
        source: receipt.source,
        supplier: receipt.supplier ?? '',
        received_date: receipt.received_date,
        remarks: receipt.remarks ?? '',
    });

    transform((d) => ({
        ...d,
        items: lines
            .filter((l) => l.item_variant_id && l.quantity)
            .map((l) => ({ item_variant_id: l.item_variant_id, quantity: l.quantity })),
    }));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('receiving.update', receipt.id));
    };

    return (
        <>
            <Head title={`Edit ${receipt.dr_no}`} />
            <form onSubmit={submit} className="flex flex-col gap-6">
                <div>
                    <Button variant="ghost" size="sm" asChild className="mb-2 -ml-2">
                        <Link href={route('receiving.show', receipt.id)}><ArrowLeft /> Back to {receipt.dr_no}</Link>
                    </Button>
                    <PageHeader title={`Edit ${receipt.dr_no}`} description="Update this draft before posting." icon={Truck} />
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
                            <Label htmlFor="received_date">Received date</Label>
                            <Input id="received_date" type="date" value={data.received_date} onChange={(e) => setData('received_date', e.target.value)} />
                            {errors.received_date && <p className="text-sm text-destructive">{errors.received_date}</p>}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="source">Source</Label>
                            <Select value={data.source} onValueChange={(v) => setData('source', v as 'supplier' | 'other_project')}>
                                <SelectTrigger id="source"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="supplier">Supplier</SelectItem>
                                    <SelectItem value="other_project">Other project</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        {data.source === 'supplier' && (
                            <div className="grid gap-2">
                                <Label htmlFor="supplier">Supplier</Label>
                                <Input id="supplier" value={data.supplier} onChange={(e) => setData('supplier', e.target.value)} placeholder="Supplier name" />
                                {errors.supplier && <p className="text-sm text-destructive">{errors.supplier}</p>}
                            </div>
                        )}
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
                        <Link href={route('receiving.show', receipt.id)}>Cancel</Link>
                    </Button>
                    <Button type="submit" disabled={processing || lines.length === 0}>Save changes</Button>
                </div>
            </form>
        </>
    );
}

ReceivingEdit.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
