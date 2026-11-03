import React from 'react';
import { cn } from '@/Components/AdminUI/lib/cn';

export default function PageHeader({ eyebrow, title, description, actions, className }) {
    return (
        <div className={cn('mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between', className)}>
            <div>
                {eyebrow && (
                    <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-accent">{eyebrow}</p>
                )}
                <h1 className="mt-1 text-2xl font-extrabold tracking-tight text-primary">{title}</h1>
                {description && <p className="mt-1 text-sm text-text-secondary">{description}</p>}
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}
