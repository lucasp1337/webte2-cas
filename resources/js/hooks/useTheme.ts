import { useEffect, useState } from 'react';

export type Theme = 'light' | 'dark';

const ATTR = 'data-theme';

function readTheme(): Theme {
    if (typeof document === 'undefined') return 'light';
    return document.documentElement.getAttribute(ATTR) === 'dark' ? 'dark' : 'light';
}

/**
 * Reactive read of the current theme set by `ThemeToggle`. Observes the
 * `data-theme` attribute on `<html>` so consumers (e.g. the CodeMirror
 * editor) re-render when the user toggles theme without prop drilling.
 */
export function useTheme(): Theme {
    const [theme, setTheme] = useState<Theme>(readTheme);

    useEffect(() => {
        if (typeof document === 'undefined') return;

        const target = document.documentElement;
        const observer = new MutationObserver(() => {
            setTheme(readTheme());
        });
        observer.observe(target, { attributes: true, attributeFilter: [ATTR] });

        // Sync once after mount in case ThemeToggle hydrated after this hook.
        setTheme(readTheme());

        return () => {
            observer.disconnect();
        };
    }, []);

    return theme;
}
