import React from 'react';
import { AlertCircle, Check, ClipboardCopy, RefreshCw } from 'lucide-react';

/**
 * Error Boundary Component
 * Catches JavaScript errors in child component trees and displays fallback UI.
 *
 * Error DITAMPILKAN informatif di SEMUA environment (bukan hanya development):
 * pesan error, lokasi file:baris, dan component stack — lengkap dengan tombol
 * "Salin Detail" agar user bisa melaporkan error persis seperti yang terjadi.
 * React error ini murni client-side (render), tidak membocorkan data server.
 */
export class ErrorBoundary extends React.Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false, error: null, errorInfo: null, copied: false };
    }

    static getDerivedStateFromError(error) {
        return { hasError: true };
    }

    componentDidCatch(error, errorInfo) {
        this.setState({ error, errorInfo });

        console.error('Error caught by ErrorBoundary:', error, errorInfo);
    }

    tryRecover = () => {
        this.setState({ hasError: false, error: null, errorInfo: null, copied: false });
    };

    /**
     * Deteksi kelas error API mismatch yang pernah terjadi: memanggil method
     * yang tidak ada (mis. router.off di Inertia v2) → `TypeError: x.off is
     * not a function`. Ditampilkan sebagai hint agar developer langsung tahu
     * perbaikannya, bukan tebak-tebakan.
     */
    getApiMismatchHint() {
        const msg = this.state.error?.message || '';

        // Regex sengaja broad (\S+\.off): error asli muncul sebagai `p.off is not
        // a function` setelah minifikasi (variabel p), jadi regex sempit
        // `router\.off` tidak akan menangkapnya. Hint dibuat kondisional.
        if (/\S+\.off is not a function/.test(msg)) {
            return 'Objek yang dipanggil `.off()` tidak punya method itu. Jika ini subscriber router Inertia (mis. router.off), ganti ke helper subscribeRouter() dari @/Utils/routerEvents — router.on() mengembalikan fungsi unsubscribe.';
        }
        if (/\S+\.on is not a function/.test(msg)) {
            return 'Pemanggilan `.on()` pada objek yang tidak mendukungnya. Cek apakah subscriber dipasang ke objek yang benar (mis. router dari @inertiajs/react).';
        }

        return null;
    }

    /** Lokasi file:baris pertama dari component stack (untuk pesan ringkas). */
    getErrorLocation() {
        const stack = this.state.errorInfo?.componentStack || '';
        const match = stack.match(/\(([^()]*?\.jsx?):(\d+):(\d+)\)/);

        if (!match) return null;

        // Potong path menjadi relatif: .../resources/js/Pages/Jadwal/Index.jsx:42:5
        const file = match[1].split('/resources/js/').pop() || match[1];

        return `${file}:${match[2]}:${match[3]}`;
    }

    /** Detail lengkap untuk disalin user (laporan bug). */
    buildDetails() {
        const { error, errorInfo } = this.state;
        const parts = [
            error?.name ? `${error.name}: ${error.message}` : String(error || 'Error tidak diketahui'),
        ];
        if (error?.stack) parts.push(`\n${error.stack}`);
        if (errorInfo?.componentStack) {
            parts.push(`\nComponent stack:\n${errorInfo.componentStack}`);
        }

        return parts.join('\n');
    }

    copyDetails = async () => {
        const text = this.buildDetails();

        try {
            await navigator.clipboard.writeText(text);
        } catch {
            // Fallback untuk browser tanpa Clipboard API (HTTP non-secure dll.)
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }

        this.setState({ copied: true });
        setTimeout(() => this.setState({ copied: false }), 2000);
    };

    render() {
        if (!this.state.hasError) {
            return this.props.children;
        }

        const { error } = this.state;
        const location = this.getErrorLocation();
        const apiHint = this.getApiMismatchHint();

        return (
            <div className="min-h-screen flex items-center justify-center bg-slate-50 px-4">
                <div className="max-w-md w-full space-y-6 text-center">
                    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-rose-100">
                        <AlertCircle className="h-8 w-8 text-rose-600" />
                    </div>

                    <div>
                        <h2 className="text-2xl font-bold text-slate-900">
                            Terjadi Kesalahan
                        </h2>
                        <p className="mt-1 text-slate-600">
                            Maaf, terjadi kesalahan yang tidak terduga. Coba muat ulang halaman.
                        </p>
                    </div>

                    {error && (
                        <div className="rounded-xl border border-rose-200 bg-white p-4 text-left">
                            <div className="flex items-start justify-between gap-2">
                                <p className="text-sm font-semibold text-rose-700 break-words">
                                    {error.name}: {error.message}
                                </p>
                            </div>
                            {location && (
                                <p className="mt-1.5 text-xs font-mono text-slate-500 break-all">
                                    Terjadi di: {location}
                                </p>
                            )}

                            {apiHint && (
                                <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs leading-relaxed text-amber-800">
                                    <strong className="block font-bold">Kemungkinan penyebab:</strong>
                                    {apiHint}
                                </p>
                            )}

                            <details className="mt-3">
                                <summary className="cursor-pointer text-xs font-semibold text-slate-500 hover:text-slate-700">
                                    Lihat detail teknis (stack trace)
                                </summary>
                                <pre className="mt-2 max-h-48 overflow-auto rounded-lg bg-slate-900 p-3 text-[10px] leading-relaxed text-slate-200 whitespace-pre-wrap">
                                    {this.buildDetails()}
                                </pre>
                            </details>
                        </div>
                    )}

                    <div className="flex flex-col space-y-3 sm:flex-row sm:justify-center sm:space-y-0 sm:space-x-3">
                        <button
                            onClick={this.tryRecover}
                            className="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            <RefreshCw className="mr-2 h-4 w-4" />
                            Coba Ulang
                        </button>

                        <button
                            onClick={() => window.location.reload()}
                            className="inline-flex items-center justify-center px-4 py-2 border border-slate-300 rounded-lg shadow-sm text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            Muat Ulang Halaman
                        </button>

                        {error && (
                            <button
                                onClick={this.copyDetails}
                                className="inline-flex items-center justify-center px-4 py-2 border border-slate-300 rounded-lg shadow-sm text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                {this.state.copied ? (
                                    <>
                                        <Check className="mr-2 h-4 w-4 text-emerald-600" />
                                        Tersalin!
                                    </>
                                ) : (
                                    <>
                                        <ClipboardCopy className="mr-2 h-4 w-4" />
                                        Salin Detail
                                    </>
                                )}
                            </button>
                        )}
                    </div>

                    <p className="text-xs text-slate-400">
                        Jika error terus muncul, salin detail dan hubungi administrator.
                    </p>
                </div>
            </div>
        );
    }
}

export default ErrorBoundary;
