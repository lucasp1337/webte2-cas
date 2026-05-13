import { render, screen, within } from '@testing-library/react';
import type { AnchorHTMLAttributes, ReactNode } from 'react';

// Inertia stubs — not running inside the real app router.
vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { locale: 'en' } }),
    Head: ({ title }: { title?: string }) => (title === undefined ? null : <title>{title}</title>),
    Link: ({
        href,
        children,
        ...rest
    }: { href: string; children: ReactNode } & AnchorHTMLAttributes<HTMLAnchorElement>) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
}));

// AppLayout pulls in Header/Footer which have their own Inertia deps and DOM
// complexity.  Stub it to a simple wrapper so this test focuses on NotFound.
vi.mock('@/Layouts/AppLayout', () => ({
    default: ({ children }: { children: ReactNode }) => <div>{children}</div>,
}));

import NotFound from '@/Pages/NotFound';

describe('NotFound page', () => {
    it('renders the 404 hero number', () => {
        render(<NotFound />);

        expect(screen.getByText('404')).toBeInTheDocument();
    });

    it('renders the page-not-found title', () => {
        render(<NotFound />);

        expect(screen.getByRole('heading', { name: 'Page not found' })).toBeInTheDocument();
    });

    it('renders the subtitle', () => {
        render(<NotFound />);

        expect(screen.getByText("This page doesn't exist — try one of the routes below.")).toBeInTheDocument();
    });

    it('renders a "Back to home" link pointing at the locale prefix root', () => {
        render(<NotFound />);

        // The CTA wraps a Button inside a Link — query by link text.
        const homeLink = screen.getByRole('link', { name: 'Back to home' });
        expect(homeLink).toHaveAttribute('href', '/en');
    });

    it('renders the suggested routes list', () => {
        render(<NotFound />);

        const nav = screen.getByText('Available pages').closest('div') as HTMLElement;
        const list = within(nav).getByRole('list');
        // There are 7 routes defined in SUGGESTED_ROUTES.
        expect(within(list).getAllByRole('listitem')).toHaveLength(7);
    });

    it('each suggested route link includes the locale prefix in its href', () => {
        render(<NotFound />);

        // Console route should be /en/console.
        const consoleLink = screen.getByRole('link', { name: /console/i });
        expect(consoleLink).toHaveAttribute('href', '/en/console');
    });

    it('renders the /en path for the home route', () => {
        render(<NotFound />);

        // The "Home" list item link href should be exactly /en (empty path segment).
        const homeRouteLink = screen.getAllByRole('link').find((el) => el.getAttribute('href') === '/en');
        expect(homeRouteLink).toBeDefined();
    });
});
