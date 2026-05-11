import { useT } from '@/hooks/useT';

export default function Footer() {
    const t = useT();

    return (
        <footer className="flex items-center justify-between border-t border-border px-7 py-[18px]">
            <span className="font-mono text-[12px] text-on-surface-muted">
                WEBTE2 &middot; {t.footer.academic} &middot; 2026
            </span>
            <a
                href="https://github.com/webte2"
                target="_blank"
                rel="noopener noreferrer"
                className="font-mono text-[12px] text-on-surface-muted transition-colors hover:text-on-surface"
                style={{ borderBottom: '1px solid var(--border)' }}
            >
                github.com/webte2 &#8599;
            </a>
        </footer>
    );
}
