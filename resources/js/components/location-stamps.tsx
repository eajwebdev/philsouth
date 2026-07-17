import { LocateFixed, LocateOff, ExternalLink, MapPin } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

export interface LocationStampRow {
    id: number;
    action: string;
    user: string | null;
    latitude: number | null;
    longitude: number | null;
    accuracy_m: number | null;
    unavailable_reason: string | null;
    at: string | null;
}

const mapsUrl = (lat: number, lng: number) => `https://www.google.com/maps?q=${lat},${lng}`;

/** Where each action on this record physically happened. */
export function LocationStamps({ stamps }: { stamps: LocationStampRow[] }) {
    if (!stamps || stamps.length === 0) return null;

    return (
        <Card className="gap-0 py-0">
            <CardHeader className="border-b py-4">
                <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                    <MapPin className="size-4 text-primary" /> Recorded location
                </CardTitle>
            </CardHeader>
            <CardContent className="p-2">
                <ul className="flex flex-col divide-y">
                    {stamps.map((s) => (
                        <li key={s.id} className="flex flex-wrap items-center justify-between gap-3 px-2 py-2.5">
                            <div className="min-w-0">
                                <p className="flex items-center gap-2 text-sm">
                                    <Badge variant="outline" className="capitalize">{s.action}</Badge>
                                    <span className="text-muted-foreground">{s.user ?? '—'}</span>
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {s.at ? new Date(s.at).toLocaleString() : '—'}
                                </p>
                            </div>
                            {s.latitude !== null && s.longitude !== null ? (
                                <a
                                    href={mapsUrl(s.latitude, s.longitude)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex items-center gap-1.5 text-xs hover:text-primary"
                                >
                                    <LocateFixed className="size-3.5 text-success" />
                                    <span className="font-mono">{s.latitude.toFixed(5)}, {s.longitude.toFixed(5)}</span>
                                    {s.accuracy_m !== null && <span className="text-muted-foreground">· ±{Math.round(s.accuracy_m)}m</span>}
                                    <ExternalLink className="size-3 text-muted-foreground" />
                                </a>
                            ) : (
                                <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <LocateOff className="size-3.5" /> No fix{s.unavailable_reason ? ` (${s.unavailable_reason})` : ''}
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}
