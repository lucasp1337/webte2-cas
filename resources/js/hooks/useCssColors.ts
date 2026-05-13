import { useEffect, useState } from 'react';

import { useTheme } from '@/hooks/useTheme';

/**
 * Resolves a set of CSS custom properties to concrete colour strings.
 *
 * Konva renders to `<canvas>`, which doesn't understand `var(--foo)`. Passing
 * an unknown string makes the Canvas API fall back to opaque black, which
 * silently breaks dark-mode animations. This hook reads the computed values
 * off `<html>` and re-reads when `data-theme` flips.
 */
export function useCssColors<K extends string>(tokens: Record<K, string>): Record<K, string> {
    const theme = useTheme();
    const [resolved, setResolved] = useState<Record<K, string>>(() => fallback(tokens));

    useEffect(() => {
        if (typeof document === 'undefined') return;
        const style = getComputedStyle(document.documentElement);
        const next: Record<K, string> = fallback(tokens);
        // `Object.keys` widens to `string[]` even on Record<K, V>; the cast back
        // to K[] is safe because tokens is the exact source of those keys.
        for (const key of Object.keys(tokens) as K[]) {
            const tokenName = tokens[key];
            const value = style.getPropertyValue(tokenName).trim();
            if (value !== '') {
                next[key] = value;
            }
        }
        setResolved(next);
    }, [theme, tokens]);

    return resolved;
}

function fallback<K extends string>(tokens: Record<K, string>): Record<K, string> {
    // Partial<Record<K, V>> starts empty and gets populated below; we widen at
    // return after every key has been assigned.
    const out: Partial<Record<K, string>> = {};
    for (const key of Object.keys(tokens) as K[]) out[key] = '#000000';
    return out as Record<K, string>;
}
