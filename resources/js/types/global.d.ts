import { route as routeFn } from 'ziggy-js';

declare global {
    // eslint-disable-next-line no-var
    var route: typeof routeFn;
}

declare module '@inertiajs/react' {
    export function usePage<T = Record<string, unknown>>(): {
        props: PageProps & T;
        url: string;
        component: string;
        version: string | null;
    };
}
