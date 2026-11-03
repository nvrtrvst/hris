import React from 'react';
import Label from '@/Components/AdminUI/ui/Label';
import { cn } from '@/Components/AdminUI/lib/cn';

export default function FormField({
    label,
    htmlFor,
    error,
    description,
    required = false,
    className,
    children,
}) {
    return (
        <div className={cn('block', className)}>
            {label && (
                <Label htmlFor={htmlFor} required={required}>
                    {label}
                </Label>
            )}
            {children}
            {description && !error && (
                <p className="mt-1 text-[10px] leading-relaxed text-text-muted">{description}</p>
            )}
            {error && (
                <p id={htmlFor ? `${htmlFor}-error` : undefined} className="mt-1 text-[10px] font-semibold text-rose-600">
                    {error}
                </p>
            )}
        </div>
    );
}
