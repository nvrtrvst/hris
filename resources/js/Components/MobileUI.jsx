export function AmbientBg() {
    return (
        <div className="pointer-events-none fixed inset-0 -z-10 bg-[#f4f7f5]" />
    );
}

export function Card({ children, className = '', press = true }) {
    return (
        <div
            className={`rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_1px_2px_rgba(15,23,42,0.04)] ${
                press ? 'transition-transform active:scale-[0.99]' : ''
            } ${className}`}
        >
            {children}
        </div>
    );
}

export function SectionTitle({ children, icon: Icon, action, className = '' }) {
    return (
        <div className={`mb-3 mt-1 flex items-center justify-between px-1 ${className}`}>
            <h2 className="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.14em] text-slate-500">
                {Icon && (
                    <span className="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-primary">
                        <Icon className="h-3.5 w-3.5" />
                    </span>
                )}
                {children}
            </h2>
            {action}
        </div>
    );
}

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
        <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold leading-none ${tones[tone] || tones.slate} ${className}`}>
            {Icon && <Icon className="h-3.5 w-3.5" />}
            {children}
        </span>
    );
}

export function FAB({ onClick, href, icon: Icon, label }) {
    const inner = (
        <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary text-white shadow-[0_8px_18px_-8px_rgba(15,61,62,0.65)] transition-transform active:scale-95">
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

export function Toggle({ checked, onChange, tone = 'indigo' }) {
    const on = tone === 'amber' ? 'bg-amber-500' : 'bg-emerald-500';
    return (
        <button
            type="button"
            onClick={onChange}
            role="switch"
            aria-checked={checked}
            className={`relative h-8 min-w-[52px] rounded-full transition-colors duration-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/20 ${checked ? on : 'bg-slate-300'}`}
            style={{ width: '52px' }}
        >
            <span
                className={`absolute left-1 top-1 h-6 w-6 rounded-full bg-white shadow-sm transition-transform duration-200 ${checked ? 'translate-x-5' : ''}`}
            />
        </button>
    );
}

export function Seg({ children, active }) {
    return (
        <div className={`flex-1 rounded-xl px-3 py-2.5 text-center text-sm font-bold transition-colors ${active ? 'bg-white text-primary shadow-sm' : 'text-slate-500'}`}>
            {children}
        </div>
    );
}

export function Empty({ icon: Icon, title, subtitle }) {
    return (
        <div className="flex flex-col items-center px-4 py-8 text-center">
            <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
                <Icon className="h-6 w-6" />
            </div>
            <h3 className="font-bold text-slate-700">{title}</h3>
            {subtitle && <p className="mt-1 text-sm text-slate-400">{subtitle}</p>}
        </div>
    );
}
