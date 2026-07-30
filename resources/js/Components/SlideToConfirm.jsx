import { useRef, useState, useCallback } from 'react';
import { Check, ChevronRight } from 'lucide-react';

export default function SlideToConfirm({ onConfirm, disabled = false, confirmed = false, label = 'Geser untuk konfirmasi' }) {
    const trackRef = useRef(null);
    const [sliding, setSliding] = useState(false);
    const [offset, setOffset] = useState(0);

    const reset = useCallback(() => {
        setSliding(false);
        setOffset(0);
    }, []);

    const move = useCallback((clientX) => {
        if (disabled || confirmed) return;
        const track = trackRef.current;
        if (!track) return;
        const rect = track.getBoundingClientRect();
        const max = rect.width - 48;
        let pos = clientX - rect.left - 24;
        pos = Math.max(0, Math.min(pos, max));
        setOffset(pos);
    }, [disabled, confirmed]);

    const end = useCallback(() => {
        if (disabled || confirmed) return;
        const track = trackRef.current;
        if (!track) return;
        const rect = track.getBoundingClientRect();
        const max = rect.width - 48;
        if (offset / max >= 0.9) {
            setOffset(max);
            onConfirm?.();
            setTimeout(reset, 600);
        } else {
            reset();
        }
    }, [disabled, confirmed, offset, onConfirm, reset]);

    const start = useCallback((e) => {
        if (disabled || confirmed) return;
        e.preventDefault();
        setSliding(true);
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        move(clientX);
    }, [disabled, confirmed, move]);

    if (confirmed) {
        return (
            <div className="flex h-12 items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 text-sm font-bold text-emerald-700 select-none">
                <Check className="h-5 w-5" />
                Sudah di-tap
            </div>
        );
    }

    const pct = (() => {
        if (!trackRef.current) return 0;
        const max = trackRef.current.getBoundingClientRect().width - 48;
        return max > 0 ? Math.round((offset / max) * 100) : 0;
    })();

    return (
        <div className="select-none">
            <div
                ref={trackRef}
                role="slider"
                aria-valuenow={pct}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-label={label}
                tabIndex={0}
                onKeyDown={(e) => { if (e.key === 'Enter' && !disabled) onConfirm?.(); }}
                className={`relative flex h-12 touch-none items-center overflow-hidden rounded-xl border transition-colors ${
                    disabled ? 'border-slate-200 bg-slate-50 opacity-50' : 'border-emerald-200 bg-emerald-50'
                }`}
                onTouchStart={start}
                onTouchMove={(e) => { e.preventDefault(); move(e.touches[0].clientX); }}
                onTouchEnd={end}
                onMouseDown={start}
                onMouseMove={(e) => { if (sliding) move(e.clientX); }}
                onMouseUp={end}
                onMouseLeave={end}
            >
                <div
                    className="absolute inset-y-0 left-0 rounded-xl bg-emerald-400 transition-[width] duration-75"
                    style={{ width: `${pct}%` }}
                />
                <span
                    className="absolute inset-y-0 left-0 flex items-center justify-center"
                    style={{ left: `${offset}px` }}
                >
                    <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-white shadow-sm ring-1 ring-slate-200 transition-transform active:scale-95">
                        <ChevronRight className="h-5 w-5 text-emerald-600" />
                    </span>
                </span>
                <span className="relative mx-auto text-xs font-bold text-emerald-800">
                    {label}
                </span>
            </div>
            {!disabled && (
                <button
                    type="button"
                    onClick={() => { if (!disabled) onConfirm?.(); }}
                    className="mt-1 w-full text-center text-[10px] font-semibold text-slate-400 underline underline-offset-2 active:text-slate-600"
                >
                    Ketuk langsung jika tidak bisa geser
                </button>
            )}
        </div>
    );
}
