import { Head, Link } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';
import { ArrowLeft, Printer, QrCode } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';

interface Variant {
    id: number;
    sku: string;
    label: string | null;
    barcode: string | null;
    payload: string;
}
interface Props {
    item: { id: number; code: string; description: string; uom: string };
    variants: Variant[];
}

export default function ItemLabels({ item, variants }: Props) {
    return (
        <>
            <Head title={`${item.code} — QR labels`} />
            <div className="flex flex-col gap-6">
                <div className="no-print">
                    <Button variant="ghost" size="sm" asChild className="mb-2 -ml-2">
                        <Link href={route('items.show', item.id)}><ArrowLeft /> Back to item</Link>
                    </Button>
                    <PageHeader
                        title="QR labels"
                        description={`${item.description} · ${item.code}`}
                        icon={QrCode}
                        actions={
                            <Button variant="outline" onClick={() => window.print()}>
                                <Printer /> Print sheet
                            </Button>
                        }
                    />
                </div>

                <div className="print-area grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    {variants.map((v) => (
                        <div key={v.id} className="flex flex-col items-center gap-2 rounded-lg border bg-white p-4 text-center text-black">
                            <QRCodeSVG value={v.payload} size={120} level="M" />
                            <div>
                                <p className="text-sm font-semibold">{item.description}</p>
                                {v.label && <p className="text-xs text-neutral-600">{v.label}</p>}
                                <p className="font-mono text-xs text-neutral-600">{v.sku}</p>
                                <p className="mt-0.5 font-mono text-[10px] text-neutral-400">{v.payload}</p>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}

ItemLabels.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
