import { type LabelHTMLAttributes } from 'react';

import { cn } from '@/lib/cn';

export type LabelProps = LabelHTMLAttributes<HTMLLabelElement> & {
    required?: boolean;
};

export default function Label({ className, required = false, children, ...props }: LabelProps) {
    return (
        <label className={cn('block text-sm font-medium text-on-surface', className)} {...props}>
            {children}
            {required && (
                <span aria-hidden="true" className="ml-1 text-error">
                    *
                </span>
            )}
        </label>
    );
}
