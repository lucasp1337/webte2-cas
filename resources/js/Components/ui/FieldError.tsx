import { type HTMLAttributes, type ReactNode } from 'react';

import { cn } from '@/lib/cn';

export type FieldErrorProps = HTMLAttributes<HTMLParagraphElement> & {
    children?: ReactNode;
};

export default function FieldError({ className, children, ...props }: FieldErrorProps) {
    if (children === undefined || children === null || children === '') {
        return null;
    }

    return (
        <p role="alert" className={cn('mt-1 text-sm text-error', className)} {...props}>
            {children}
        </p>
    );
}
