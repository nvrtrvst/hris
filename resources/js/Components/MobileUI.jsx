import { useState } from 'react';

// Ambient background with floating 3D orbs — gives depth to the whole app
export function AmbientBg() {
    return (
        <div className="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
            <div className="absolute inset-0 bg-gradient-to-b from-indigo-50/80 via-slate-50 to-white" />
            <div className="absolute -left-16 -top-16 h-64 w-64 rounded-full bg-indigo-300/30 blur-3xl animate-float" />
            <div className="absolute -right-10 top-1/3 h-72 w-72 rounded-full bg-violet-300/25 blur-3xl animate-float-slow" />
            <div className="absolute bottom-10 left-1/4 h-56 w-56 rounded-full bg-sky-200/20 blur-3xl animate-float" />
        </div>
    );
}

// Glassy elevated card
export function Card({ children, className = '', press = true }) {
    return (
        <div
            className={`rounded-3xl bg-white/90 p-5 shadow-[0_8px_30px_-12px_rgba(15,61,62,0.25)] ring-1 ring-black/5 backdrop-blur-xl transition-all ${
                press ? 'active:scale-[0.985] active:shadow-[0_4px_16px_-10px_rgba(15,61,62,0.3)]' : ''
            } ${className}`}
        >
            {children}
        </div>
    );
}

// Section title with small leading dot / icon
export function SectionTitle({ children, icon: Icon, action, className = '' }) {
    return (
        <div className={`mb-3 mt-1 flex items-center justify-between px-1 ${className}`}>
            <h3 className="flex items-center gap-2 text-sm font-extrabold uppercase tracking-wider text-slate-500">
                {Icon && (
                    <span className="flex h-6 w-6 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600">
                        <Icon className="h-3.5 w-3.5" />
                    </span>
                )}
                {children}
            </h3>
            {action}
        </div>
    );
}

// Status / category pill
export function Badge({ children, tone = 'slate', icon: Icon, className = '' }) {
    const tones = {
        slate: 'bg-slate-100 text-slate-600',
        indigo: 'bg-emerald-100 text-emerald-700',
        emerald: 'bg-emerald-100 text-emerald-700',
        amber: 'bg-amber-100 text-amber-700',
        rose: 'bg-rose-100 text-rose-700',
        sky: 'bg-sky-100 text-sky-700',
        red: 'bg-red-100 text-red-700',
    };
    return (
        <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold ${tones[tone] || tones.slate} ${className}`}>
            {Icon && <Icon className="h-3.5 w-3.5" />}
            {children}
        </span>
    );
}

// Floating action button (round, lifted)
export function FAB({ onClick, href, icon: Icon, label }) {
    const inner = (
        <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-primary text-white shadow-[0_10px_24px_-6px_rgba(15,61,62,0.6)] transition-transform active:scale-90">
            <Icon className="h-6 w-6" />
        </span>
    );
    if (href) {
        return (
            <a href={href} className="fixed bottom-24 right-5 z-30 block" aria-label={label}>
                {inner}
            </a>
        );
    }
    return (
        <button type="button" onClick={onClick} className="fixed bottom-24 right-5 z-30 block" aria-label={label}>
            {inner}
        </button>
    );
}

// 3D toggle switch
export function Toggle({ checked, onChange, tone = 'indigo' }) {
    const on = tone === 'amber' ? 'bg-amber-500' : 'bg-emerald-500';
    return (
        <button
            type="button"
            onClick={onChange}
            className={`relative h-7 rounded-full transition-colors duration-300 ${checked ? on : 'bg-slate-300'}`}
            style={{ width: '52px' }}
        >
            <span
                className={`absolute top-1 left-1 h-5 w-5 rounded-full bg-white shadow-md transition-transform duration-300 ${checked ? 'translate-x-6' : ''}`}
            />
        </button>
    );
}

// Segmented control (single selection display)
export function Seg({ children, active }) {
    return (
        <div className={`flex-1 rounded-2xl px-3 py-2.5 text-center text-sm font-bold shadow-sm transition-all ${active ? 'bg-white text-primary shadow-md' : 'text-slate-400'}`}>
            {children}
        </div>
    );
}

// Empty state
export function Empty({ icon: Icon, title, subtitle }) {
    return (
        <Card className="flex flex-col items-center py-10 text-center">
            <div className="mb-3 flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-300">
                <Icon className="h-8 w-8" />
            </div>
            <h3 className="font-bold text-slate-700">{title}</h3>
            {subtitle && <p className="mt-1 text-sm text-slate-400">{subtitle}</p>}
        </Card>
    );
}
