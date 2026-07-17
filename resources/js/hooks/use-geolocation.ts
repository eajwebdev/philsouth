import * as React from 'react';

export interface GeoFix {
    latitude: number;
    longitude: number;
    accuracy: number;
}

/** Why we have no fix. `insecure` is the big one: the browser Geolocation API
 *  only works on https:// or localhost — on a plain http:// LAN address every
 *  browser (desktop and phone) refuses. */
export type GeoReason = 'denied' | 'unsupported' | 'insecure' | 'timeout' | 'unavailable';

export interface GeoState {
    status: 'idle' | 'locating' | 'locked' | 'unavailable';
    fix: GeoFix | null;
    /** 0–100, climbs as the fix sharpens. */
    progress: number;
    reason: GeoReason | null;
    retry: () => void;
}

/** Accuracy (metres) → a progress feel. Tight fix = high %. */
function accuracyToProgress(accuracy: number): number {
    if (accuracy <= 10) return 100;
    if (accuracy >= 2000) return 12;
    // Log-scaled between 10m (100%) and 2000m (12%).
    const p = 100 - ((Math.log10(accuracy) - 1) / (Math.log10(2000) - 1)) * 88;
    return Math.round(Math.max(12, Math.min(99, p)));
}

/**
 * Watches the device position, keeping the sharpest fix seen. Stops once the
 * fix is good enough (or after `maxWaitMs`) so we don't hold the GPS open.
 *
 * @param active   only run while true (e.g. a dialog is open)
 * @param targetAccuracy metres — good enough to call it locked
 */
export function useGeolocation(active = true, targetAccuracy = 20, maxWaitMs = 20000): GeoState {
    const [fix, setFix] = React.useState<GeoFix | null>(null);
    const [status, setStatus] = React.useState<GeoState['status']>('idle');
    const [reason, setReason] = React.useState<GeoReason | null>(null);
    const [nonce, setNonce] = React.useState(0);
    const bestRef = React.useRef<GeoFix | null>(null);

    const retry = React.useCallback(() => {
        bestRef.current = null;
        setFix(null);
        setReason(null);
        setNonce((n) => n + 1);
    }, []);

    React.useEffect(() => {
        if (!active) return;

        // A secure context is mandatory — fail loudly rather than hanging.
        if (typeof window !== 'undefined' && !window.isSecureContext) {
            setStatus('unavailable');
            setReason('insecure');
            return;
        }
        if (typeof navigator === 'undefined' || !navigator.geolocation) {
            setStatus('unavailable');
            setReason('unsupported');
            return;
        }

        setStatus('locating');
        setReason(null);

        let watchId: number | null = null;
        let settled = false;

        const stop = () => {
            if (watchId !== null) {
                navigator.geolocation.clearWatch(watchId);
                watchId = null;
            }
        };

        const finish = () => {
            if (settled) return;
            settled = true;
            stop();
            setStatus(bestRef.current ? 'locked' : 'unavailable');
            if (!bestRef.current) setReason('timeout');
        };

        watchId = navigator.geolocation.watchPosition(
            (pos) => {
                const next: GeoFix = {
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude,
                    accuracy: pos.coords.accuracy,
                };
                // Keep only the sharpest fix.
                if (!bestRef.current || next.accuracy < bestRef.current.accuracy) {
                    bestRef.current = next;
                    setFix(next);
                }
                if (next.accuracy <= targetAccuracy) finish();
            },
            (err) => {
                // A later error shouldn't discard a fix we already have.
                if (bestRef.current) {
                    finish();
                    return;
                }
                settled = true;
                stop();
                setStatus('unavailable');
                setReason(
                    err.code === err.PERMISSION_DENIED ? 'denied'
                        : err.code === err.TIMEOUT ? 'timeout'
                            : 'unavailable',
                );
            },
            { enableHighAccuracy: true, timeout: maxWaitMs, maximumAge: 0 },
        );

        // Give up refining after maxWaitMs, keeping whatever we got.
        const timer = window.setTimeout(finish, maxWaitMs);

        return () => {
            settled = true;
            stop();
            window.clearTimeout(timer);
        };
    }, [active, nonce, targetAccuracy, maxWaitMs]);

    const progress = status === 'locked'
        ? 100
        : fix ? accuracyToProgress(fix.accuracy) : status === 'locating' ? 8 : 0;

    return { status, fix, progress, reason, retry };
}
