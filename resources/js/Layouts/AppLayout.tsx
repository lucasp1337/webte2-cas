import { Head } from '@inertiajs/react';
import { type ReactNode } from 'react';

import Footer from '@/Components/Footer';
import Header from '@/Components/Header';
import { useT } from '@/hooks/useT';

export type AppLayoutProps = {
    title?: string;
    children: ReactNode;
};

const MAIN_ID = 'main-content';

export default function AppLayout({ title, children }: AppLayoutProps) {
    const t = useT();

    return (
        <div className="flex min-h-screen flex-col bg-surface text-on-surface">
            {title !== undefined && <Head title={title} />}
            <a href={`#${MAIN_ID}`} className="skip-link">
                {t.common.skipToContent}
            </a>
            <Header />
            <main
                id={MAIN_ID}
                tabIndex={-1}
                className="mx-auto w-full max-w-[1280px] flex-1 px-7 pb-14 pt-9 focus:outline-none"
            >
                {children}
            </main>
            <Footer />
        </div>
    );
}
