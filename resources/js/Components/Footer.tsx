import { useT } from '@/hooks/useT';

export default function Footer() {
    const t = useT();
    const year = new Date().getFullYear();

    return (
        <footer className="border-t border-border bg-surface-raised">
            <div className="container mx-auto px-4 py-6 text-sm text-on-surface-muted">
                <p>
                    &copy; {year} {t.footer.copyright}
                </p>
            </div>
        </footer>
    );
}
