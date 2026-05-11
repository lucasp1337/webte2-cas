import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { BrandWedgeIcon, MenuIcon } from '@/Components/icons';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import ThemeToggle from '@/Components/ThemeToggle';
import { useT } from '@/hooks/useT';
import { useLocale } from '@/hooks/useT';
import { cn } from '@/lib/cn';

type NavKey = 'home' | 'console' | 'pendulum' | 'ballBeam' | 'logs' | 'apiDocs' | 'stats';

type NavItem = {
    key: NavKey;
    path: string;
};

const NAV_ITEMS: readonly NavItem[] = [
    { key: 'home', path: '' },
    { key: 'console', path: '/console' },
    { key: 'pendulum', path: '/pendulum' },
    { key: 'ballBeam', path: '/ball-beam' },
    { key: 'logs', path: '/logs' },
    { key: 'apiDocs', path: '/api-docs' },
    { key: 'stats', path: '/stats' },
];

function isActive(currentUrl: string, prefix: string, path: string): boolean {
    const target = `${prefix}${path}`;
    if (path === '') {
        return currentUrl === prefix || currentUrl === `${prefix}/`;
    }
    return currentUrl === target || currentUrl.startsWith(`${target}/`);
}

export default function Header() {
    const t = useT();
    const locale = useLocale();
    const { url } = usePage();
    const [mobileOpen, setMobileOpen] = useState(false);

    const localePrefix = `/${locale}`;

    return (
        <header className="sticky top-0 z-10 flex h-14 items-center gap-7 border-b border-border bg-surface px-7">
            {/* Wordmark */}
            <Link
                href={localePrefix}
                className="inline-flex shrink-0 items-center gap-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
            >
                <BrandWedgeIcon size={14} />
                <span
                    className="font-sans text-base font-bold tracking-[-0.02em]"
                    style={{
                        background: 'var(--brand-gradient)',
                        WebkitBackgroundClip: 'text',
                        backgroundClip: 'text',
                        color: 'transparent',
                    }}
                >
                    WEBTE2
                </span>
            </Link>

            {/* Desktop nav */}
            <nav aria-label={t.nav.home} className="hidden flex-1 items-center gap-1 md:flex">
                {NAV_ITEMS.map((item) => {
                    const active = isActive(url, localePrefix, item.path);
                    return (
                        <Link
                            key={item.key}
                            href={`${localePrefix}${item.path}`}
                            aria-current={active ? 'page' : undefined}
                            className={cn(
                                'rounded px-[10px] py-[6px] text-[13px] tracking-[-0.005em] transition-colors',
                                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent',
                                active
                                    ? 'bg-surface-sunken text-on-surface-strong'
                                    : 'text-on-surface-muted hover:bg-surface-sunken hover:text-on-surface',
                            )}
                        >
                            {t.nav[item.key]}
                        </Link>
                    );
                })}
            </nav>

            {/* Spacer on mobile so right controls push to the right */}
            <div className="flex-1 md:hidden" />

            {/* Right controls */}
            <div className="flex items-center gap-1.5">
                <LanguageSwitcher />
                <ThemeToggle />
                {/* Mobile menu button */}
                <button
                    type="button"
                    onClick={() => setMobileOpen((open) => !open)}
                    aria-label={mobileOpen ? t.common.closeMenu : t.common.openMenu}
                    aria-expanded={mobileOpen}
                    aria-controls="mobile-nav"
                    className={cn(
                        'inline-flex h-[30px] w-[30px] items-center justify-center rounded border border-border',
                        'bg-surface-raised text-on-surface-muted transition-colors',
                        'hover:border-border-strong hover:text-on-surface',
                        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent',
                        'md:hidden',
                    )}
                >
                    <MenuIcon size={14} />
                </button>
            </div>

            {/* Mobile nav — rendered below as a sibling via a portal-like trick;
                actually rendered inline and the parent is position:sticky */}
            {mobileOpen && (
                <nav
                    id="mobile-nav"
                    aria-label={t.nav.home}
                    className="absolute inset-x-0 top-14 border-b border-border bg-surface px-4 py-3 md:hidden"
                >
                    <ul className="flex flex-col gap-1">
                        {NAV_ITEMS.map((item) => {
                            const active = isActive(url, localePrefix, item.path);
                            return (
                                <li key={item.key}>
                                    <Link
                                        href={`${localePrefix}${item.path}`}
                                        onClick={() => setMobileOpen(false)}
                                        aria-current={active ? 'page' : undefined}
                                        className={cn(
                                            'block rounded px-3 py-2 text-sm font-medium tracking-[-0.005em]',
                                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent',
                                            active
                                                ? 'bg-surface-sunken text-on-surface-strong'
                                                : 'text-on-surface-muted hover:bg-surface-sunken hover:text-on-surface',
                                        )}
                                    >
                                        {t.nav[item.key]}
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </nav>
            )}
        </header>
    );
}
