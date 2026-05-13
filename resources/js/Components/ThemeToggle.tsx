import { useEffect, useState } from 'react';

import { MoonIcon, SunIcon } from '@/Components/icons';
import { useT } from '@/hooks/useT';
import { cn } from '@/lib/cn';

export type Theme = 'light' | 'dark';

const STORAGE_KEY = 'webte2-theme';
const DARK_ATTR = 'data-theme';

function readInitialTheme(): Theme {
    if (typeof window === 'undefined') {
        return 'light';
    }
    const stored = window.localStorage.getItem(STORAGE_KEY);
    if (stored === 'light' || stored === 'dark') {
        return stored;
    }
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(theme: Theme): void {
    const root = document.documentElement;
    root.setAttribute(DARK_ATTR, theme);
    root.style.colorScheme = theme;
}

export type ThemeToggleProps = {
    className?: string;
};

export default function ThemeToggle({ className }: ThemeToggleProps) {
    const t = useT();
    const [theme, setTheme] = useState<Theme>('light');
    const [hydrated, setHydrated] = useState(false);

    useEffect(() => {
        const initial = readInitialTheme();
        setTheme(initial);
        applyTheme(initial);
        setHydrated(true);
    }, []);

    const toggle = (): void => {
        const next: Theme = theme === 'dark' ? 'light' : 'dark';
        setTheme(next);
        applyTheme(next);
        window.localStorage.setItem(STORAGE_KEY, next);
    };

    const isDark = theme === 'dark';
    const label = isDark ? t.theme.light : t.theme.dark;

    return (
        <button
            type="button"
            onClick={toggle}
            aria-label={t.common.toggleTheme}
            aria-pressed={isDark}
            title={hydrated ? label : t.common.toggleTheme}
            className={cn(
                'inline-flex h-[30px] w-[30px] items-center justify-center rounded border border-border',
                'bg-surface-raised text-on-surface-muted transition-colors',
                'hover:border-border-strong hover:text-on-surface',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent',
                className,
            )}
        >
            {isDark ? <SunIcon size={14} /> : <MoonIcon size={14} />}
        </button>
    );
}
