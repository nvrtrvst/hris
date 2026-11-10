import React from 'react';
import { ChevronDown } from 'lucide-react';
import { Card } from '@/Components/AdminUI';

export default function CollapsibleSection({
    id,
    Icon,
    title,
    description,
    hasError = false,
    defaultOpen = false,
    children,
}) {
    return (
        <Card id={id} className="scroll-mt-32">
            <details open={defaultOpen || hasError} className="group">
                <summary className="flex cursor-pointer list-none items-center justify-between gap-4 p-5 sm:p-6 [&::-webkit-details-marker]:hidden">
                    <div className="flex min-w-0 items-start gap-3">
                        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                            <Icon className="h-4 w-4 text-primary" />
                        </span>
                        <div>
                            <h3 className="text-sm font-bold text-primary">{title}</h3>
                            {description && <p className="mt-1 text-xs text-text-secondary">{description}</p>}
                            {hasError && <p className="mt-1 text-[10px] font-semibold text-rose-600">Ada input yang perlu diperiksa.</p>}
                        </div>
                    </div>
                    <ChevronDown className="h-4 w-4 shrink-0 text-text-muted transition-transform group-open:rotate-180" />
                </summary>
                <div className="border-t border-border p-5 sm:p-6">{children}</div>
            </details>
        </Card>
    );
}
