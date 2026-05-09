import type { Locale } from '@/i18n';

export type AppSharedProps = {
    locale: Locale;
};

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: AppSharedProps;
    }
}
