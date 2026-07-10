import * as React from 'react';
import { ScanLine, Camera, X } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Scan input with two modes:
 *  1. Hardware/USB/Bluetooth scanner — emulates a keyboard; type into the
 *     field, the scanner ends the burst with Enter. Zero libraries, most
 *     reliable on-site.
 *  2. Camera — html5-qrcode in a dialog for phones without a hardware scanner.
 */
export function ScanField({
    onScan,
    placeholder = 'Scan or type a barcode, then Enter',
    autoFocus = false,
}: {
    onScan: (code: string) => void;
    placeholder?: string;
    autoFocus?: boolean;
}) {
    const [value, setValue] = React.useState('');
    const [cameraOpen, setCameraOpen] = React.useState(false);

    const submit = () => {
        const code = value.trim();
        if (code) {
            onScan(code);
            setValue('');
        }
    };

    return (
        <div className="flex items-center gap-2">
            <div className="relative flex-1">
                <ScanLine className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-primary" />
                <Input
                    value={value}
                    autoFocus={autoFocus}
                    onChange={(e) => setValue(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            submit();
                        }
                    }}
                    placeholder={placeholder}
                    className="pl-9"
                    inputMode="text"
                    autoComplete="off"
                />
            </div>
            <Button type="button" variant="outline" size="icon" onClick={() => setCameraOpen(true)} aria-label="Scan with camera">
                <Camera className="size-4" />
            </Button>

            <Dialog open={cameraOpen} onOpenChange={setCameraOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Scan with camera</DialogTitle>
                    </DialogHeader>
                    {cameraOpen && (
                        <CameraScanner
                            onDecode={(code) => {
                                setCameraOpen(false);
                                onScan(code);
                            }}
                            onClose={() => setCameraOpen(false)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}

function CameraScanner({ onDecode, onClose }: { onDecode: (code: string) => void; onClose: () => void }) {
    const regionId = React.useId().replace(/:/g, '');
    const [error, setError] = React.useState<string | null>(null);

    React.useEffect(() => {
        let scanner: { stop: () => Promise<void>; clear: () => void } | null = null;
        let cancelled = false;

        (async () => {
            try {
                const { Html5Qrcode } = await import('html5-qrcode');
                if (cancelled) return;
                const instance = new Html5Qrcode(regionId);
                scanner = instance;
                await instance.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 240, height: 240 } },
                    (decodedText: string) => {
                        onDecode(decodedText);
                    },
                    () => {
                        /* per-frame decode failures are normal; ignore */
                    },
                );
            } catch {
                if (!cancelled) setError('Unable to access the camera. Use a hardware scanner or type the code.');
            }
        })();

        return () => {
            cancelled = true;
            if (scanner) {
                scanner.stop().then(() => scanner?.clear()).catch(() => undefined);
            }
        };
    }, [regionId, onDecode]);

    return (
        <div className="flex flex-col gap-3">
            <div id={regionId} className="overflow-hidden rounded-lg bg-black/90" />
            {error && <p className="text-sm text-destructive">{error}</p>}
            <Button type="button" variant="outline" onClick={onClose}>
                <X /> Close
            </Button>
        </div>
    );
}
