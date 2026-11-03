import React from 'react';
import { cn } from '@/Components/AdminUI/lib/cn';

export default function Label({ className, required = false, children, ...props }) {
    return (
        <label className={cn('mb-1.5 block text-[11px] font-bold text-text-secondary', className)} {...props}>
            {children}
            {required && <span className="ml-0.5 text-danger">*</span>}
        </label>
    );
}
