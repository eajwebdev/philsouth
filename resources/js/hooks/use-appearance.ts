import { useCallback, useEffect, useState } from 'react';

export type Appearance = 'light' | 'dark' | 'system';

const prefersDark = () =>
    window.matchMedia('(prefers-color-scheme: dark)').matches;

function applyTheme(appearance: Appearance) {
    const isDark =
        appearance === 'dark' || (appearance === 'system' && prefersDark());
    document.documentElement.classList.toggle('dark', isDark);
}

export function initializeTheme() {
    const saved = (localStorage.getItem('appearance') as Appearance) || 'system';
    applyTheme(saved);

    window
        .matchMedia('(prefers-color-scheme: dark)')
        .addEventListener('change', () => {
            const current =
                (localStorage.getItem('appearance') as Appearance) || 'system';
            if (current === 'system') applyTheme('system');
        });
}

export function useAppearance() {
    const [appearance, setAppearance] = useState<Appearance>('system');

    useEffect(() => {
        const saved =
            (localStorage.getItem('appearance') as Appearance) || 'system';
        setAppearance(saved);
    }, []);

    const updateAppearance = useCallback((value: Appearance) => {
        setAppearance(value);
        localStorage.setItem('appearance', value);
        applyTheme(value);
    }, []);

    return { appearance, updateAppearance } as const;
}
