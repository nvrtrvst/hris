import React from 'react';
import { cn } from '@/Components/AdminUI/lib/cn';

export function Table({ className, tableClassName, ...props }) {
    return (
        <div className={cn('overflow-x-auto', className)}>
            <table className={cn('w-full text-left text-sm', tableClassName)} {...props} />
        </div>
    );
}

export function TableHeader({ className, ...props }) {
    return <thead className={cn('bg-surface/80 text-[10px] font-bold uppercase tracking-wider text-text-secondary', className)} {...props} />;
}

export function TableBody({ className, ...props }) {
    return <tbody className={cn('divide-y divide-border/70', className)} {...props} />;
}

export function TableRow({ className, ...props }) {
    return <tr className={cn('transition-colors hover:bg-surface/60', className)} {...props} />;
}

export function TableHead({ className, ...props }) {
    return <th className={cn('whitespace-nowrap px-4 py-3 text-left', className)} {...props} />;
}

export function TableCell({ className, ...props }) {
    return <td className={cn('px-4 py-3.5', className)} {...props} />;
}
