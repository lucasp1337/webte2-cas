import { type HTMLAttributes, type ReactNode } from 'react';

import { cn } from '@/lib/cn';

export type CardProps = HTMLAttributes<HTMLDivElement> & {
    children: ReactNode;
};

export type CardHeaderProps = HTMLAttributes<HTMLDivElement> & {
    children: ReactNode;
};

export type CardBodyProps = HTMLAttributes<HTMLDivElement> & {
    children: ReactNode;
};

export type CardFooterProps = HTMLAttributes<HTMLDivElement> & {
    children: ReactNode;
};

export function CardHeader({ className, children, ...props }: CardHeaderProps) {
    return (
        <div className={cn('border-b border-border px-6 py-4', className)} {...props}>
            {children}
        </div>
    );
}

export function CardBody({ className, children, ...props }: CardBodyProps) {
    return (
        <div className={cn('px-6 py-4', className)} {...props}>
            {children}
        </div>
    );
}

export function CardFooter({ className, children, ...props }: CardFooterProps) {
    return (
        <div className={cn('border-t border-border px-6 py-4', className)} {...props}>
            {children}
        </div>
    );
}

export default function Card({ className, children, ...props }: CardProps) {
    return (
        <div
            className={cn('rounded-lg border border-border bg-surface-raised text-on-surface shadow-sm', className)}
            {...props}
        >
            {children}
        </div>
    );
}
