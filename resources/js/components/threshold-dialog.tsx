import * as React from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';

interface Target {
    id: number;
    item: string;
    min_qty: number | string;
    max_qty: number | string | null;
    location?: string | null;
}

/** Edit reorder thresholds + location for a stock row (balance stays ledger-driven). */
export function ThresholdDialog({ target, onClose }: { target: Target | null; onClose: () => void }) {
    const { data, setData, put, processing, errors, reset } = useForm({
        min_qty: '',
        max_qty: '',
        location: '',
    });

    React.useEffect(() => {
        if (target) {
            setData({
                min_qty: String(target.min_qty ?? ''),
                max_qty: target.max_qty != null ? String(target.max_qty) : '',
                location: target.location ?? '',
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [target?.id]);

    if (!target) return null;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('inventory.thresholds', target.id), {
            preserveScroll: true,
            onSuccess: () => { reset(); onClose(); },
        });
    };

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Reorder levels</DialogTitle>
                        <DialogDescription className="truncate">{target.item}</DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 py-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="min_qty">Minimum</Label>
                            <Input id="min_qty" type="number" step="0.01" min="0" value={data.min_qty} onChange={(e) => setData('min_qty', e.target.value)} />
                            {errors.min_qty && <p className="text-sm text-destructive">{errors.min_qty}</p>}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="max_qty">Maximum</Label>
                            <Input id="max_qty" type="number" step="0.01" min="0" value={data.max_qty} onChange={(e) => setData('max_qty', e.target.value)} placeholder="Optional" />
                            {errors.max_qty && <p className="text-sm text-destructive">{errors.max_qty}</p>}
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="location">Location</Label>
                            <Input id="location" value={data.location} onChange={(e) => setData('location', e.target.value)} placeholder="e.g. Bay A / Rack 3" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={processing}>Save</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
