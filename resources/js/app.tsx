import '../css/app.css';

import type { ComponentType } from 'react';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { Toaster } from '@/components/ui/sonner';
import { initializeTheme } from '@/hooks/use-appearance';

const appName = import.meta.env.VITE_APP_NAME || 'PhilSouth';

const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx');

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) => {
        const importPage = pages[`./pages/${name}.tsx`];
        if (!importPage) {
            throw new Error(`Page not found: ./pages/${name}.tsx`);
        }
        return importPage().then((module) => module.default);
    },
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(
            <>
                <App {...props} />
                <Toaster position="bottom-right" richColors closeButton />
            </>,
        );
    },
    progress: {
        color: '#B8792B',
    },
});

initializeTheme();
