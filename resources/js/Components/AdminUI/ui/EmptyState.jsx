import React from 'react';
import { Inbox } from 'lucide-react';
import { cn } from '@/Components/AdminUI/lib/cn';

export default function EmptyState({ icon: Icon = Inbox, title, description, action, className }) {
    return (
        <div className={cn('flex flex-col items-center justify-center px-6 py-12 text-center', className)}>
            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-surface text-text-muted">
                <Icon className="h-6 w-6" />
            </div>
            <p className="mt-3 text-sm font-bold text-primary">{title}</p>
            {description && <p className="mt-1 max-w-sm text-xs leading-relaxed text-text-secondary">{description}</p>}
            {action && <div className="mt-3">{action}</div>}
        </div>
    );
}
