import React from 'react';

/**
 * Kartu statistik ringkas untuk halaman admin.
 * @param {React.Component} Icon - Ikon lucide-react
 * @param {string} label - Label kecil uppercase
 * @param {number|string} value - Nilai utama
 * @param {string} [sub] - Teks tambahan kecil di bawah label
 */
export default function StatCard({ Icon, label, value, sub }) {
    return (
        <div className="card flex items-center gap-4 p-5">
            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                <Icon className="h-5 w-5 text-primary" />
            </span>
            <div className="min-w-0">
                <p className="text-2xl font-extrabold leading-tight text-text-primary tabular-nums">{value}</p>
                <p className="truncate text-xs font-semibold uppercase tracking-wide text-text-muted">{label}</p>
                {sub && <p className="truncate text-[11px] text-text-muted">{sub}</p>}
            </div>
        </div>
    );
}
