import React from 'react';
import { cn } from '@/Components/AdminUI/lib/cn';

const Input = React.forwardRef(function Input({ className, type = 'text', ...props }, ref) {
    return (
        <input
            ref={ref}
            type={type}
            className={cn(
                'h-10 w-full rounded-lg border border-border bg-white px-3 text-sm text-text-primary shadow-sm transition-colors placeholder:text-text-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/10 disabled:bg-surface disabled:text-text-muted aria-[invalid=true]:border-rose-300 aria-[invalid=true]:bg-rose-50/40 aria-[invalid=true]:focus:ring-rose-200',
                className,
            )}
            {...props}
        />
    );
});

export default Input;
