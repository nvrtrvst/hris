import { useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';

/**
 * Live polling Inertia (partial reload) yang aman & hemat:
 *
 * - Memanggil `poll()` tiap `intervalMs` via router.reload({ only: [...], ... }).
 * - `busyRef` mencegah request bertumpuk jika reload sebelumnya belum selesai.
 * - Berhenti otomatis saat tab tidak aktif (visibilitychange) — hemat server &
 *   baterai — lalu lanjut lagi saat tab kembali aktif.
 * - Cleanup lengkap (interval + listener) saat unmount.
 * - `only`/`options` dibaca via ref sehingga interval TIDAK restart tiap render
 *   (nilai literal baru tidak memicu ulang effect).
 *
 * Pemakaian:
 *   usePolling({
 *       intervalMs: 60_000,
 *       enabled: isAdmin,
 *       only: ['presensiHariIni'],
 *       options: { preserveState: true, preserveScroll: true },
 *   });
 */
export default function usePolling({ intervalMs = 60_000, enabled = true, only = [], options = {} } = {}) {
    const busyRef = useRef(false);
    // Deps stabil: only/options boleh berubah nilai antar render tanpa restart interval.
    const onlyRef = useRef(only);
    const optionsRef = useRef(options);
    onlyRef.current = only;
    optionsRef.current = options;

    useEffect(() => {
        if (!enabled) return undefined;

        let interval = null;
        let stopped = false;

        const poll = () => {
            if (busyRef.current) return;
            busyRef.current = true;
            const release = () => { busyRef.current = false; };
            router.reload({
                only: onlyRef.current,
                preserveState: true,
                preserveScroll: true,
                ...optionsRef.current,
                onFinish: release,
                onError: release,
                onCancel: release,
            });
        };

        const start = () => {
            if (stopped || interval) return;
            interval = setInterval(poll, intervalMs);
        };
        const stop = () => {
            if (interval) {
                clearInterval(interval);
                interval = null;
            }
        };
        const onVisibility = () => (document.hidden ? stop() : start());

        start();
        document.addEventListener('visibilitychange', onVisibility);

        return () => {
            stopped = true;
            stop();
            document.removeEventListener('visibilitychange', onVisibility);
        };
    }, [enabled, intervalMs]);
}
