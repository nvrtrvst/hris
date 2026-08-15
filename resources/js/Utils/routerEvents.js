import { router } from '@inertiajs/react';

/**
 * Subscribe ke event Inertia secara AMAN (API Inertia v2).
 *
 * Di @inertiajs/core v2, `router.on()` mengembalikan fungsi unsubscribe dan
 * method `router.off()` TIDAK ada — memanggil `router.off()` akan melempar
 * `TypeError: x.off is not a function` saat cleanup (unmount) halaman. Bug ini
 * pernah terjadi di Jadwal/Payroll/Pegawai/Presensi Index.
 *
 * Helper ini menjadi SATU-SATUNYA cara subscribe event router yang diizinkan:
 * - Cleanup selalu dikembalikan sebagai fungsi (guard `typeof`) → pola lama
 *   `router.off(...)` tidak mungkin crash aplikasi lagi.
 * - Cleanup dibungkus try/catch → kegagalan satu unsubscribe tidak merusak
 *   yang lain maupun proses unmount halaman.
 *
 * Pemakaian (cleanup langsung jadi return useEffect):
 *   useEffect(() => subscribeRouter({
 *       start: () => setProcessing(true),
 *       finish: () => setProcessing(false),
 *   }), []);
 */
export function subscribeRouter(handlers = {}) {
    const unsubscribes = [];

    Object.entries(handlers).forEach(([event, callback]) => {
        if (typeof callback !== 'function' || typeof router.on !== 'function') {
            return;
        }

        const unsubscribe = router.on(event, callback);
        if (typeof unsubscribe === 'function') {
            unsubscribes.push(unsubscribe);
        }
    });

    return () => {
        unsubscribes.forEach((unsubscribe) => {
            try {
                unsubscribe();
            } catch (err) {
                // Cleanup TIDAK boleh pernah merusak unmount halaman.
                console.warn('[subscribeRouter] cleanup gagal untuk event router:', err);
            }
        });
    };
}
