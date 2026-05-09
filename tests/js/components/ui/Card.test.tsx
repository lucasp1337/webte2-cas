import { render, screen } from '@testing-library/react';

import Card, { CardBody, CardFooter, CardHeader } from '@/Components/ui/Card';

describe('Card', () => {
    it('renders children inside the card surface', () => {
        render(
            <Card>
                <p>Card content</p>
            </Card>,
        );

        expect(screen.getByText('Card content')).toBeInTheDocument();
    });

    it('composes header, body and footer subcomponents', () => {
        render(
            <Card>
                <CardHeader>Header</CardHeader>
                <CardBody>Body</CardBody>
                <CardFooter>Footer</CardFooter>
            </Card>,
        );

        expect(screen.getByText('Header')).toBeInTheDocument();
        expect(screen.getByText('Body')).toBeInTheDocument();
        expect(screen.getByText('Footer')).toBeInTheDocument();
    });
});
