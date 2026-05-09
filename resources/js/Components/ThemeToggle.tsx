import { useEffect, useState } from 'react';

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
                'inline-flex h-9 w-9 items-center justify-center rounded-md text-on-surface',
                'hover:bg-secondary',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-surface',
                className,
            )}
        >
            <svg
                aria-hidden="true"
                viewBox="0 0 24 24"
                className="h-5 w-5"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
            >
                {isDark ? (
                    <circle cx="12" cy="12" r="4" />
                ) : (
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M21 12.79A9 9 0 1111.21 3a7 7 0 009.79 9.79z"
                    />
                )}
            </svg>
        </button>
    );
}
