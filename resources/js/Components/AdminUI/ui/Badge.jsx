import React from 'react';
import { cn } from '@/Components/AdminUI/lib/cn';

const VARIANTS = {
    neutral: 'border-border bg-surface text-text-secondary',
    success: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    warning: 'border-amber-200 bg-amber-50 text-amber-700',
    danger: 'border-rose-200 bg-rose-50 text-rose-700',
    info: 'border-sky-200 bg-sky-50 text-sky-700',
    purple: 'border-purple-200 bg-purple-50 text-purple-700',
};

export default function Badge({ variant = 'neutral', className, children, ...props }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide',
                VARIANTS[variant],
                className,
            )}
            {...props}
        >
            {children}
        </span>
    );
}
