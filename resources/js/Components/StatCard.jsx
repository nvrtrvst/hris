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
 */
export default function StatCard({ Icon, label, value, sub, iconBg = 'bg-primary/10', iconCls = 'text-primary', alert, onClick, active }) {
    const className = `stat-card group hover:shadow-card-hover transition-shadow ${onClick ? 'text-left w-full' : ''} ${active ? 'ring-2 ring-primary' : ''}`;

    const content = (
        <>
            <div className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${iconBg} transition-transform group-hover:scale-105`}>
                <Icon className={`h-5 w-5 ${iconCls}`} />
            </div>
            <div className="min-w-0">
                <p className={`text-2xl font-extrabold leading-none tabular-nums ${alert ? 'text-danger' : 'text-primary'}`}>{value}</p>
                <p className="mt-1.5 text-[11px] font-semibold uppercase tracking-wide text-text-secondary">{label}</p>
                {sub && <p className="mt-0.5 text-[10px] text-text-muted truncate">{sub}</p>}
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
