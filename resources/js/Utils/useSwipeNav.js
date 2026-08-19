import { useRef } from 'react';
import { router } from '@inertiajs/react';

/**
 * Swipe horizontal untuk pindah antar menu utama mobile (Beranda→Jadwal→...).
 * Geser kiri = menu berikutnya, geser kanan = menu sebelumnya.
 * Hanya aktif saat ada event sentuh (perangkat touch) — mouse tak terpengaruh.
 */
export default function useSwipeNav({ items, enabled = true, threshold = 70 }) {
    const touchStart = useRef(null);
    const navigating = useRef(false);

    const handleTouchStart = (e) => {
        if (!enabled || navigating.current) return;
        const t = e.touches?.[0];
        if (!t) return;
        touchStart.current = { x: t.clientX, y: t.clientY };
    };

    const handleTouchEnd = (e) => {
        if (!enabled || navigating.current || !touchStart.current) return;
        const t = e.changedTouches?.[0];
        if (!t) return;

        const dx = t.clientX - touchStart.current.x;
        const dy = t.clientY - touchStart.current.y;
        touchStart.current = null;

        if (Math.abs(dx) < threshold || Math.abs(dx) <= Math.abs(dy)) return;

        const activeIndex = items.findIndex((item) => route().current(item.route));
        if (activeIndex === -1) return;

        const nextIndex = dx < 0 ? activeIndex + 1 : activeIndex - 1;
        if (nextIndex < 0 || nextIndex >= items.length) return;

        navigating.current = true;
        router.visit(route(items[nextIndex].route));
        setTimeout(() => { navigating.current = false; }, 700);
    };

    return { onTouchStart: handleTouchStart, onTouchEnd: handleTouchEnd };
}