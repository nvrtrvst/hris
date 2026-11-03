import React from 'react';
import { cn } from '@/Components/AdminUI/lib/cn';

const VARIANTS = {
    primary: 'bg-primary text-white hover:bg-primary-600 active:bg-primary-800',
    secondary: 'border border-border bg-white text-text-secondary hover:border-primary/30 hover:text-primary',
    ghost: 'text-text-secondary hover:bg-surface hover:text-primary',
    danger: 'bg-danger text-white hover:bg-red-700 active:bg-red-800',
    success: 'bg-emerald-600 text-white hover:bg-emerald-700 active:bg-emerald-800',
};

const SIZES = {
    sm: 'h-9 px-3 text-xs',
    md: 'h-10 px-4 text-sm',
    lg: 'h-11 px-5 text-sm',
    icon: 'h-9 w-9 p-0',
    'icon-sm': 'h-8 w-8 p-0',
};

export default function Button({
    type = 'button',
    variant = 'primary',
    size = 'md',
    className,
    ...props
}) {
    return (
        <button
            type={type}
            className={cn(
                'inline-flex items-center justify-center gap-1.5 rounded-lg font-bold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 disabled:cursor-not-allowed disabled:opacity-50',
                VARIANTS[variant],
                SIZES[size],
                className,
            )}
            {...props}
        />
    );
}
