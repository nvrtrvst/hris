import { useEffect, useState } from 'react';

/**
 * Jam sekarang yang di-refresh otomatis tiap menit (live badge).
 *
 * Tick pertama disinkronkan ke detik ke-0 menit berikutnya, lalu tiap 60s —
 * sehingga badge "Mengajar → Selesai" berganti tepat saat jam_selesai terlewati,
 * tanpa perlu reload halaman. Cleanup lengkap (timeout + interval) saat unmount.
 */
export default function useNowEveryMinute() {
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        let interval = null;
        const tick = () => setNow(new Date());
        const timeout = setTimeout(() => {
            tick();
            interval = setInterval(tick, 60_000);
        }, ((60 - new Date().getSeconds()) % 60) * 1000);

        return () => {
            clearTimeout(timeout);
            if (interval) clearInterval(interval);
        };
    }, []);

    return now;
}
