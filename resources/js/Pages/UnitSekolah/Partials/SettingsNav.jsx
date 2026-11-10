import React from 'react';

export default function SettingsNav({ items }) {
    return (
        <nav aria-label="Bagian pengaturan unit" className="sticky top-16 z-20 -mx-1 overflow-x-auto border-b border-border bg-surface py-2">
            <div className="flex min-w-max gap-2 px-1">
                {items.map((item) => (
                    <a
                        key={item.href}
                        href={item.href}
                        className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-border bg-white px-3 text-xs font-bold text-text-secondary transition-colors hover:border-primary/30 hover:text-primary"
                    >
                        {item.label}
                        {item.count !== undefined && (
                            <span className="text-[10px] font-semibold text-text-muted">{item.count}</span>
                        )}
                    </a>
                ))}
            </div>
        </nav>
    );
}
