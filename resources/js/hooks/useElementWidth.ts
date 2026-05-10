import { useEffect, useRef, useState } from 'react';

/**
 * Tracks the offset width of the returned ref's element via ResizeObserver.
 *
 * Returns the live width in CSS pixels. SSR / first render before the element
 * mounts returns `fallback`.
 *
 * Useful for fitting fixed-aspect canvases (Konva, Chart.js) into responsive
 * containers without manually wiring window resize handlers.
 */
export function useElementWidth<T extends HTMLElement = HTMLDivElement>(
    fallback: number,
): { ref: React.RefObject<T | null>; width: number } {
    const ref = useRef<T | null>(null);
    const [width, setWidth] = useState<number>(fallback);

    useEffect(() => {
        const element = ref.current;
        if (element === null) return;

        // Initial measurement runs synchronously after mount so the first paint
        // already reflects the real width when possible.
        setWidth(element.offsetWidth || fallback);

        const observer = new ResizeObserver((entries) => {
            const entry = entries[0];
            if (entry === undefined) return;
            const next = Math.round(entry.contentRect.width);
            if (next > 0) setWidth(next);
        });
        observer.observe(element);
        return () => {
            observer.disconnect();
        };
    }, [fallback]);

    return { ref, width };
}
