import React from 'react';
import { ChevronDown } from 'lucide-react';
import { cn } from '@/Components/AdminUI/lib/cn';

const Select = React.forwardRef(function Select({ className, children, ...props }, ref) {
    return (
        <div className="relative">
            <select
                ref={ref}
                className={cn(
                    'h-10 w-full appearance-none rounded-lg border border-border bg-white px-3 pr-9 text-sm font-semibold text-text-primary shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/10 disabled:bg-surface disabled:text-text-muted',
                    className,
                )}
                {...props}
            >
                {children}
            </select>
            <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
        </div>
    );
});

export default Select;
