import React from 'react';
import { cn } from '@/Components/AdminUI/lib/cn';

export default function Card({ className, ...props }) {
    return <div className={cn('rounded-card border border-border bg-white', className)} {...props} />;
}
