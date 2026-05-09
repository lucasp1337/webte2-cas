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
            className={cn('inline-flex overflow-hidden rounded-md border border-border', className)}
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
                            'px-3 py-1.5 text-sm font-medium uppercase transition-colors',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset',
                            isActive ? 'bg-primary text-on-primary' : 'bg-surface text-on-surface hover:bg-secondary',
                        )}
                    >
                        {locale}
                    </button>
                );
            })}
        </div>
    );
}
