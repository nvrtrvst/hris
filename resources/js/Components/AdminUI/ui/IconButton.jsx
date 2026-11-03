import React from 'react';
import Button from '@/Components/AdminUI/ui/Button';
import { cn } from '@/Components/AdminUI/lib/cn';

export default function IconButton({ label, className, size = 'icon', ...props }) {
    return (
        <Button
            aria-label={label}
            title={label}
            size={size}
            className={cn('shrink-0', className)}
            {...props}
        />
    );
}
