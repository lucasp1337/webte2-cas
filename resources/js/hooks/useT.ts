import { usePage } from '@inertiajs/react';

import { translations, type Locale, type Translation } from '@/i18n';

const DEFAULT_LOCALE: Locale = 'sk';

export function useT(): Translation {
    const { props } = usePage();
    const locale: Locale = props.locale ?? DEFAULT_LOCALE;

    return translations[locale];
}

export function useLocale(): Locale {
    const { props } = usePage();

    return props.locale ?? DEFAULT_LOCALE;
}
