import * as React from 'react';
import { Head, useForm } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { MapPin, LocateFixed, LocateOff, ExternalLink } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { DataTable } from '@/components/data-table';
import { Pagination } from '@/components/pagination';
import { LocationLock, EMPTY_GEO, type GeoPayload } from '@/components/location-lock';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import type { Paginated, SiteRef } from '@/types';

interface CheckInRow {
    id: number;
    user: string | null;
    site: string | null;
    site_name: string | null;
    latitude: number | null;
    longitude: number | null;
    accuracy_m: number | null;
    unavailable_reason: string | null;
    note: string | null;
    at: string | null;
}
interface Props {
    checkIns: Paginated<CheckInRow>;
    sites: SiteRef[];
}

const mapsUrl = (lat: number, lng: number) => `https://www.google.com/maps?q=${lat},${lng}`;

export default function CheckInIndex({ checkIns, sites }: Props) {
    const [geo, setGeo] = React.useState<GeoPayload>(EMPTY_GEO);
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        site_id: sites[0]?.id ? String(sites[0].id) : '',
        note: '',
    });

    // Attach whatever location we have; the check-in goes through either way.
    transform((d) => ({ ...d, ...geo }));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('check-in.store'), {
            preserveScroll: true,
            onSuccess: () => reset('note'),
        });
    };

    const columns: ColumnDef<CheckInRow>[] = [
        {
            accessorKey: 'at',
            header: 'When',
            cell: ({ row }) => (
                <span className="whitespace-nowrap text-sm">
                    {row.original.at ? new Date(row.original.at).toLocaleString() : '—'}
                </span>
            ),
        },
        { accessorKey: 'user', header: 'Who', cell: ({ row }) => <span className="text-sm font-medium">{row.original.user ?? '—'}</span> },
        {
            accessorKey: 'site',
            header: 'Site',
            cell: ({ row }) => <Badge variant="secondary" className="font-mono">{row.original.site}</Badge>,
        },
        {
            id: 'location',
            header: 'Location',
            cell: ({ row }) => {
                const r = row.original;
                if (r.latitude === null || r.longitude === null) {
                    return (
                        <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <LocateOff className="size-3.5" /> No fix{r.unavailable_reason ? ` (${r.unavailable_reason})` : ''}
                        </span>
                    );
                }
                return (
                    <a
                        href={mapsUrl(r.latitude, r.longitude)}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex items-center gap-1.5 text-xs hover:text-primary"
                    >
                        <LocateFixed className="size-3.5 text-success" />
                        <span className="font-mono">{r.latitude.toFixed(5)}, {r.longitude.toFixed(5)}</span>
                        {r.accuracy_m !== null && <span className="text-muted-foreground">· ±{Math.round(r.accuracy_m)}m</span>}
                        <ExternalLink className="size-3 text-muted-foreground" />
                    </a>
                );
            },
        },
        { accessorKey: 'note', header: 'Note', cell: ({ row }) => <span className="text-sm text-muted-foreground">{row.original.note ?? '—'}</span> },
    ];

    return (
        <>
            <Head title="Site check-in" />
            <div className="flex flex-col gap-6">
                <PageHeader
                    title="Site check-in"
                    description="Log your arrival at a site. Your location is attached when available."
                    icon={MapPin}
                />

                <Card>
                    <CardHeader className="pb-3"><CardTitle className="text-base">Check in now</CardTitle></CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end">
                            <div className="grid gap-1.5">
                                <Label>Site</Label>
                                <Select value={data.site_id} onValueChange={(v) => setData('site_id', v)}>
                                    <SelectTrigger><SelectValue placeholder="Select site" /></SelectTrigger>
                                    <SelectContent>
                                        {sites.map((s) => <SelectItem key={s.id} value={String(s.id)}>{s.name}</SelectItem>)}
                                    </SelectContent>
                                </Select>
                                {errors.site_id && <p className="text-sm text-destructive">{errors.site_id}</p>}
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="note">Note</Label>
                                <Input id="note" value={data.note} onChange={(e) => setData('note', e.target.value)} placeholder="Optional" />
                            </div>
                            <Button type="submit" disabled={processing || !data.site_id}>
                                <MapPin /> Check in
                            </Button>

                            {/* Live GPS acquisition — never blocks the button. */}
                            <div className="lg:col-span-3">
                                <LocationLock onChange={setGeo} />
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <DataTable columns={columns} data={checkIns.data} emptyState="No check-ins yet." />
                <Pagination meta={checkIns} />
            </div>
        </>
    );
}

CheckInIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
