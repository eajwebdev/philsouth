import * as React from 'react';
import axios from 'axios';
import { toast } from 'sonner';
import { PackagePlus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { CatalogItem } from '@/components/line-items-editor';

/**
 * Inline "New item" dialog for transaction forms (receiving, withdrawals, transfers).
 * Lets an ICS/engineer add a catalog item on the spot while encoding a paper
 * receipt. The item is GLOBAL — every site sees it immediately with 0 stock —
 * and the created default variant is handed back so it can be added as a line.
 */
export function QuickItemDialog({ onCreated }: { onCreated: (item: CatalogItem) => void }) {
    const [open, setOpen] = React.useState(false);
    const [saving, setSaving] = React.useState(false);
    const [errors, setErrors] = React.useState<Record<string, string>>({});
    const [form, setForm] = React.useState({ code: '', description: '', uom: '', category: '' });

    const set = (key: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) =>
        setForm((f) => ({ ...f, [key]: e.target.value }));

    const submit = async () => {
        setSaving(true);
        setErrors({});
        try {
            const { data } = await axios.post(route('items.quick-store'), {
                code: form.code || undefined,
                description: form.description,
                uom: form.uom,
                category: form.category || undefined,
            });
            onCreated(data.item as CatalogItem);
            toast.success('Item created', { description: `${data.item.description} (${data.item.code}) — added to the line items.` });
            setForm({ code: '', description: '', uom: '', category: '' });
            setOpen(false);
        } catch (err) {
            if (axios.isAxiosError(err) && err.response?.status === 422) {
                const bag = err.response.data.errors as Record<string, string[]>;
                setErrors(Object.fromEntries(Object.entries(bag).map(([k, v]) => [k, v[0]])));
            } else {
                toast.error('Could not create item.');
            }
        } finally {
            setSaving(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" variant="outline" size="sm">
                    <PackagePlus /> New item
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Create catalog item</DialogTitle>
                    <DialogDescription>
                        Added system-wide for all sites (starting at 0 stock everywhere), then attached to this document.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 py-2">
                    <div className="grid gap-2">
                        <Label htmlFor="qi-description">Item description</Label>
                        <Input id="qi-description" value={form.description} onChange={set('description')} placeholder="e.g. Jackaline 12 ft" autoFocus />
                        {errors.description && <p className="text-sm text-destructive">{errors.description}</p>}
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="qi-uom">Unit (U.O.M.)</Label>
                            <Input id="qi-uom" value={form.uom} onChange={set('uom')} placeholder="pcs, m, kg…" />
                            {errors.uom && <p className="text-sm text-destructive">{errors.uom}</p>}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="qi-code">Code <span className="text-muted-foreground">(optional)</span></Label>
                            <Input id="qi-code" value={form.code} onChange={set('code')} placeholder="Auto if blank" />
                            {errors.code && <p className="text-sm text-destructive">{errors.code}</p>}
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="qi-category">Category <span className="text-muted-foreground">(optional)</span></Label>
                        <Input id="qi-category" value={form.category} onChange={set('category')} placeholder="e.g. Formworks" />
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
                    <Button type="button" onClick={submit} disabled={saving || !form.description || !form.uom}>
                        {saving ? 'Creating…' : 'Create & add'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
