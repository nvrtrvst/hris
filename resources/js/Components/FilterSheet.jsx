import { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { Filter, RotateCcw, X } from 'lucide-react';

/**
 * Kartu filter yang menjadi bottom sheet di mobile.
 * Satu instance JSX: tampil sebagai kartu di desktop, tersembunyi di mobile
 * sampai tombol "Filter" ditekan. Footer menyediakan Reset + Terapkan/Tutup.
 */
export default function FilterSheet({
    open,
    onOpenChange,
    activeCount = 0,
    onReset,
    onApply,
    applyLabel = 'Terapkan',
    title = 'Filter',
    children,
}) {
    useEffect(() => {
        if (!open) return undefined;
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = previous;
        };
    }, [open]);

    const close = () => onOpenChange(false);
    const reset = () => {
        onReset?.();
    };

    return (
        <>
            <div className="flex items-center gap-2 md:hidden">
                <button
                    type="button"
                    onClick={() => onOpenChange(true)}
                    aria-expanded={open}
                    className="flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-lg border border-border bg-white px-3 text-xs font-bold text-text-secondary"
                >
                    <Filter className="h-4 w-4" />
                    {title}
                    {activeCount > 0 && (
                        <span className="rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-white">{activeCount}</span>
                    )}
                </button>
                {activeCount > 0 && onReset && (
                    <button type="button" onClick={reset} className="flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-border bg-white text-text-secondary" aria-label="Reset filter">
                        <RotateCcw className="h-4 w-4" />
                    </button>
                )}
            </div>

            {open && typeof document !== 'undefined' && createPortal(
                <div className="fixed inset-0 z-40 bg-black/40 md:hidden" onClick={close} role="presentation" />,
                document.body,
            )}

            <div
                className={`card p-5 ${open ? 'fixed inset-x-0 bottom-0 z-50 max-h-[88dvh] overflow-y-auto overscroll-contain rounded-t-2xl rounded-b-none !border-b-0 pb-[calc(1.25rem+env(safe-area-inset-bottom))]' : 'hidden'} md:static md:block md:max-h-none md:overflow-visible md:rounded-card md:border-b md:pb-5`}
            >
                <div className="mb-3 flex items-center justify-between md:hidden">
                    <p className="text-sm font-bold text-text-primary">{title}</p>
                    <button type="button" onClick={close} className="flex min-h-11 min-w-11 items-center justify-center rounded-lg text-text-secondary" aria-label="Tutup filter">
                        <X className="h-5 w-5" />
                    </button>
                </div>

                {children}

                {(onReset || onApply) && (
                    <div className="sticky bottom-0 -mx-5 -mb-5 mt-4 flex items-center justify-end gap-2 border-t border-border/60 bg-white px-5 pb-[calc(1rem+env(safe-area-inset-bottom))] pt-3 md:static md:mx-0 md:mb-0 md:border-0 md:bg-transparent md:px-0 md:pb-0 md:pt-3">
                        {onReset && <button type="button" onClick={reset} className="btn-secondary">Reset</button>}
                        {onApply ? (
                            <button type="button" onClick={() => { onApply(); close(); }} className="btn-primary">{applyLabel}</button>
                        ) : (
                            <button type="button" onClick={close} className="btn-primary md:hidden">Tutup</button>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
