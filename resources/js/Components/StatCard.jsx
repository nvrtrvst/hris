import React from 'react';

/**
 * Kartu statistik ringkas untuk halaman admin.
 * @param {React.Component} Icon - Ikon lucide-react
 * @param {string} label - Label kecil uppercase
 * @param {number|string} value - Nilai utama
 * @param {string} [sub] - Teks tambahan kecil di bawah label
 * @param {string} [iconBg] - Background icon (default: bg-primary/10)
 * @param {string} [iconCls] - Warna icon (default: text-primary)
 * @param {boolean} [alert] - Warna value merah jika true
 * @param {function} [onClick] - Jika ada, render sebagai <button>
 * @param {boolean} [active] - Highlight border jika true (butuh onClick)
 * @param {boolean} [compact] - Padding dan ikon lebih kecil untuk grid padat
 */
export default function StatCard({ Icon, label, value, sub, iconBg = 'bg-primary/10', iconCls = 'text-primary', alert, onClick, active, compact = false }) {
    const className = `stat-card group hover:shadow-card-hover transition-shadow ${compact ? '!p-3.5 !gap-3' : ''} ${onClick ? 'text-left w-full' : ''} ${active ? 'ring-2 ring-primary' : ''}`;

    const content = (
        <>
            <div className={`flex shrink-0 items-center justify-center ${compact ? 'h-9 w-9 rounded-lg' : 'h-11 w-11 rounded-xl'} ${iconBg} transition-transform group-hover:scale-105`}>
                <Icon className={`${compact ? 'h-4 w-4' : 'h-5 w-5'} ${iconCls}`} />
            </div>
            <div className="min-w-0">
                <p title={String(value)} className={`truncate font-extrabold leading-none tabular-nums ${compact ? 'text-xl' : 'text-2xl'} ${alert ? 'text-danger' : 'text-primary'} ${typeof value === 'string' && value.length > 14 ? '!text-[10px] sm:!text-xs lg:!text-sm' : typeof value === 'string' && value.length > 10 ? '!text-xs sm:!text-sm lg:!text-base' : ''}`}>{value}</p>
                <p title={label} className={`${compact ? 'mt-1 text-[10px]' : 'mt-1.5 text-[11px]'} font-semibold uppercase leading-tight tracking-wide text-text-secondary`}>{label}</p>
                {sub && <p className={`${compact ? 'mt-0.5 text-[9px]' : 'mt-0.5 text-[10px]'} text-text-muted truncate`}>{sub}</p>}
            </div>
        </>
    );

    if (onClick) {
        return (
            <button type="button" onClick={onClick} className={className}>
                {content}
            </button>
        );
    }

    return (
        <div className={className}>
            {content}
        </div>
    );
}
