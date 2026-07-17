import * as React from 'react';
import { MapPin, LocateFixed, LocateOff, RotateCw, ShieldAlert } from 'lucide-react';
import { useGeolocation, type GeoFix, type GeoReason } from '@/hooks/use-geolocation';
import { cn } from '@/lib/utils';

export interface GeoPayload {
    latitude: number | null;
    longitude: number | null;
    accuracy_m: number | null;
    unavailable_reason: string | null;
}

export const EMPTY_GEO: GeoPayload = {
    latitude: null, longitude: null, accuracy_m: null, unavailable_reason: null,
};

export function toPayload(fix: GeoFix | null, reason: GeoReason | null): GeoPayload {
    if (fix) {
        return {
            latitude: Number(fix.latitude.toFixed(7)),
            longitude: Number(fix.longitude.toFixed(7)),
            accuracy_m: Math.round(fix.accuracy * 100) / 100,
            unavailable_reason: null,
        };
    }
    return { ...EMPTY_GEO, unavailable_reason: reason };
}

const REASON_TEXT: Record<GeoReason, string> = {
    insecure: 'Location needs a secure connection (https). On http it is blocked by the browser.',
    denied: 'Location permission was denied. Allow it in your browser site settings.',
    unsupported: 'This browser does not support location.',
    timeout: 'Could not get a location fix in time.',
    unavailable: 'Location is unavailable right now.',
};

/**
 * Acquires the device location and reports it upward. It NEVER blocks the
 * surrounding action — if location is denied/unavailable we simply report the
 * reason so the server can record "no fix".
 */
export function LocationLock({
    active = true,
    onChange,
    className,
}: {
    active?: boolean;
    onChange: (payload: GeoPayload) => void;
    className?: string;
}) {
    const { status, fix, progress, reason, retry } = useGeolocation(active);

    // Report every change upward (fix sharpening, or a failure reason).
    React.useEffect(() => {
        onChange(toPayload(fix, reason));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [fix?.latitude, fix?.longitude, fix?.accuracy, reason]);

    const failed = status === 'unavailable';
    const locked = status === 'locked';

    return (
        <div className={cn('rounded-lg border bg-muted/40 p-3', className)}>
            <div className="flex items-start gap-3">
                <span
                    className={cn(
                        'flex size-9 shrink-0 items-center justify-center rounded-lg',
                        failed ? 'bg-muted text-muted-foreground'
                            : locked ? 'bg-success/15 text-success'
                                : 'bg-primary/10 text-primary',
                    )}
                >
                    {failed ? <LocateOff className="size-4" />
                        : locked ? <LocateFixed className="size-4" />
                            : <MapPin className="size-4 animate-pulse" />}
                </span>

                <div className="min-w-0 flex-1">
                    {failed ? (
                        <>
                            <p className="text-sm font-medium">Location unavailable</p>
                            <p className="text-xs text-muted-foreground">
                                {reason ? REASON_TEXT[reason] : REASON_TEXT.unavailable}
                            </p>
                            <p className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                                <ShieldAlert className="size-3" /> You can still continue — this will be recorded as “no fix”.
                            </p>
                        </>
                    ) : (
                        <>
                            <p className="text-sm font-medium">
                                {locked ? 'Location locked' : `Locking location… ${progress}%`}
                            </p>
                            {fix ? (
                                <p className="font-mono text-xs text-muted-foreground">
                                    {fix.latitude.toFixed(5)}, {fix.longitude.toFixed(5)}
                                    <span className="ml-1">· ±{Math.round(fix.accuracy)}m</span>
                                </p>
                            ) : (
                                <p className="text-xs text-muted-foreground">Waiting for a GPS fix…</p>
                            )}
                            {/* progress bar */}
                            <div className="mt-1.5 h-1 w-full overflow-hidden rounded-full bg-muted">
                                <div
                                    className={cn('h-full rounded-full transition-all duration-500', locked ? 'bg-success' : 'bg-primary')}
                                    style={{ width: `${progress}%` }}
                                />
                            </div>
                        </>
                    )}
                </div>

                <button
                    type="button"
                    onClick={retry}
                    aria-label="Retry location"
                    className="shrink-0 rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                    <RotateCw className="size-3.5" />
                </button>
            </div>
        </div>
    );
}
