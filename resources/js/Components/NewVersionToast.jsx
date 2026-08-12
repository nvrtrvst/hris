import { useEffect, useRef, useState } from 'react';
import { RefreshCw } from 'lucide-react';

/**
 * Toast "Versi baru tersedia" — muncul saat Inertia mendeteksi asset version
 * mismatch (409) sebelum reload otomatis dijalankan, agar user tahu aplikasi
 * sedang dimuat ulang ke versi terbaru.
 *
 * Dipicu lewat event global `hris:new-version` yang di-dispatch oleh
 * interceptor `window.fetch` di app.jsx. `delay` harus sinkron dengan
 * penundaan reload di interceptor.
 */
export default function NewVersionToast({ delay = 2000 }) {
    const [visible, setVisible] = useState(false);
    const [reloading, setReloading] = useState(false);
    // URL tujuan saat reload — halaman yang diminta user saat version mismatch
    // terdeteksi (bisa berbeda dari halaman sekarang, mis. navigasi A→B).
    const targetRef = useRef(window.location.href);

    useEffect(() => {
        const handler = (event) => {
            targetRef.current = event.detail || window.location.href;
            setReloading(false);
            setVisible(true);
        };
        window.addEventListener('hris:new-version', handler);

        return () => window.removeEventListener('hris:new-version', handler);
    }, []);

    const reloadNow = () => {
        setReloading(true);
        window.location.href = targetRef.current;
    };

    // Live region SELALU di DOM (tidak mount-on-demand) agar screen reader
    // mengumumkan teks saat toast muncul. Konten visual conditional di dalamnya.
    return (
        <div role="status" aria-live="polite" aria-atomic="true">
            {visible && (
            <div className="toast-version-top fixed left-4 right-4 z-[10000] overflow-hidden rounded-xl print:hidden shadow-toast sm:left-auto sm:right-6 sm:w-full sm:max-w-sm">
            <div className="bg-primary p-4">
                <div className="flex items-start gap-3">
                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/15">
                        <RefreshCw className="h-5 w-5 animate-spin text-white" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="text-sm font-bold text-white">Versi baru tersedia</p>
                        <p className="mt-0.5 text-xs text-white/80">
                            {reloading ? 'Memuat ulang…' : 'Membuka versi terbaru aplikasi…'}
                        </p>
                        <button
                            onClick={reloadNow}
                            className="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-white/20 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-white/30"
                        >
                            <RefreshCw className="h-3.5 w-3.5" />
                            Muat ulang sekarang
                        </button>
                    </div>
                </div>
                <div className="mt-3 h-0.5 overflow-hidden rounded-full bg-white/20">
                    <div
                        className="h-full rounded-full bg-white/50"
                        style={{ animation: `shrinkWidthNew ${delay}ms linear forwards` }}
                    />
                </div>
            </div>
            <style>{`
                @keyframes shrinkWidthNew {
                    from { width: 100%; }
                    to { width: 0%; }
                }
            `}</style>
            </div>
            )}
        </div>
    );
}
