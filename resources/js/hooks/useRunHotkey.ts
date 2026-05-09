import { useEffect, useRef } from 'react';

/**
 * Binds Ctrl+Enter and Cmd+Enter to the supplied callback at the document
 * level. The latest callback is stored in a ref so consumers can pass an
 * inline arrow without triggering a listener re-binding on every render.
 *
 * Defined in-house rather than reaching for `react-hotkeys-hook` because the
 * project keeps its dependency list lean and we only need one shortcut on
 * this page.
 */
export function useRunHotkey(callback: () => void, enabled: boolean = true): void {
    const callbackRef = useRef(callback);

    useEffect(() => {
        callbackRef.current = callback;
    }, [callback]);

    useEffect(() => {
        if (!enabled) return;

        function onKeyDown(event: KeyboardEvent): void {
            if (event.key !== 'Enter') return;
            if (!(event.ctrlKey || event.metaKey)) return;
            event.preventDefault();
            callbackRef.current();
        }

        document.addEventListener('keydown', onKeyDown);
        return () => {
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [enabled]);
}
