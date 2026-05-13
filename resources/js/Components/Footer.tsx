import { useT } from '@/hooks/useT';

export default function Footer() {
    const t = useT();

    return (
        <footer className="flex items-center justify-between border-t border-border px-7 py-[18px]">
            <span className="font-mono text-[12px] text-on-surface-muted">
                WEBTE2 &middot; {t.footer.academic} &middot; 2026
            </span>
            <a
                href="https://github.com/lucasp1337/webte2-cas"
                target="_blank"
                rel="noopener noreferrer"
                className="font-mono text-[12px] text-on-surface-muted transition-colors hover:text-on-surface"
                style={{ borderBottom: '1px solid var(--border)' }}
            >
                github.com/lucasp1337/webte2-cas &#8599;
            </a>
        </footer>
    );
}
