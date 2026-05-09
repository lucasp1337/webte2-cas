import { en } from './en';
import { sk, type Translation } from './sk';

export type Locale = 'sk' | 'en';

export const SUPPORTED_LOCALES: readonly Locale[] = ['sk', 'en'] as const;

export const translations: Record<Locale, Translation> = {
    sk,
    en,
};

export type { Translation };
