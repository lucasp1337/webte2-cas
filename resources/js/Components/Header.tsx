import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

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
        <header className="border-b border-border bg-surface-raised">
            <div className="container mx-auto flex items-center justify-between px-4 py-3">
                <Link
                    href={localePrefix}
                    className="text-lg font-semibold text-on-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
                >
                    WEBTE2
                </Link>

                <nav aria-label={t.nav.home} className="hidden md:block">
                    <ul className="flex items-center gap-1">
                        {NAV_ITEMS.map((item) => {
                            const active = isActive(url, localePrefix, item.path);
                            return (
                                <li key={item.key}>
                                    <Link
                                        href={`${localePrefix}${item.path}`}
                                        aria-current={active ? 'page' : undefined}
                                        className={cn(
                                            'rounded-md px-3 py-2 text-sm font-medium transition-colors',
                                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-surface',
                                            active
                                                ? 'bg-secondary text-on-surface'
                                                : 'text-on-surface-muted hover:bg-secondary hover:text-on-surface',
                                        )}
                                    >
                                        {t.nav[item.key]}
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </nav>

                <div className="flex items-center gap-2">
                    <LanguageSwitcher />
                    <ThemeToggle />
                    <button
                        type="button"
                        onClick={() => setMobileOpen((open) => !open)}
                        aria-label={mobileOpen ? t.common.closeMenu : t.common.openMenu}
                        aria-expanded={mobileOpen}
                        aria-controls="mobile-nav"
                        className="inline-flex h-9 w-9 items-center justify-center rounded-md text-on-surface hover:bg-secondary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-surface md:hidden"
                    >
                        <svg
                            aria-hidden="true"
                            viewBox="0 0 24 24"
                            className="h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                        >
                            {mobileOpen ? (
                                <path strokeLinecap="round" strokeLinejoin="round" d="M6 6l12 12M18 6L6 18" />
                            ) : (
                                <path strokeLinecap="round" strokeLinejoin="round" d="M4 7h16M4 12h16M4 17h16" />
                            )}
                        </svg>
                    </button>
                </div>
            </div>

            {mobileOpen && (
                <nav id="mobile-nav" aria-label={t.nav.home} className="border-t border-border md:hidden">
                    <ul className="flex flex-col gap-1 px-4 py-3">
                        {NAV_ITEMS.map((item) => {
                            const active = isActive(url, localePrefix, item.path);
                            return (
                                <li key={item.key}>
                                    <Link
                                        href={`${localePrefix}${item.path}`}
                                        onClick={() => setMobileOpen(false)}
                                        aria-current={active ? 'page' : undefined}
                                        className={cn(
                                            'block rounded-md px-3 py-2 text-base font-medium',
                                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-surface',
                                            active
                                                ? 'bg-secondary text-on-surface'
                                                : 'text-on-surface-muted hover:bg-secondary hover:text-on-surface',
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
