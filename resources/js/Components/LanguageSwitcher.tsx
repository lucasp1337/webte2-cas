import { router, usePage } from '@inertiajs/react';

import { useT } from '@/hooks/useT';
import { SUPPORTED_LOCALES, type Locale } from '@/i18n';
import { cn } from '@/lib/cn';

const LOCALE_PREFIX = /^\/(sk|en)(?=\/|$)/;

function buildSwitchedUrl(currentUrl: string, target: Locale): string {
    if (LOCALE_PREFIX.test(currentUrl)) {
        return currentUrl.replace(LOCALE_PREFIX, `/${target}`);
    }
    return `/${target}${currentUrl.startsWith('/') ? currentUrl : `/${currentUrl}`}`;
}

export type LanguageSwitcherProps = {
    className?: string;
};

export default function LanguageSwitcher({ className }: LanguageSwitcherProps) {
    const t = useT();
    const { url, props } = usePage();
    const active: Locale = props.locale;

    const switchTo = (target: Locale): void => {
        if (target === active) {
            return;
        }
        const newUrl = buildSwitchedUrl(url, target);
        router.visit(newUrl, { preserveScroll: true, preserveState: false });
    };

    return (
        <div
            role="group"
            aria-label={t.common.switchLanguage}
            className={cn(
                'inline-flex h-[30px] overflow-hidden rounded border border-border bg-surface-raised',
                'font-mono text-[11px] tracking-[0.04em]',
                className,
            )}
        >
            {SUPPORTED_LOCALES.map((locale) => {
                const isActive = locale === active;
                return (
                    <button
                        key={locale}
                        type="button"
                        onClick={() => switchTo(locale)}
                        aria-pressed={isActive}
                        className={cn(
                            'px-[10px] font-mono text-[11px] uppercase transition-colors',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent',
                            isActive
                                ? 'bg-surface-sunken text-on-surface-strong'
                                : 'bg-transparent text-on-surface-muted hover:text-on-surface',
                        )}
                    >
                        {locale.toUpperCase()}
                    </button>
                );
            })}
        </div>
    );
}
