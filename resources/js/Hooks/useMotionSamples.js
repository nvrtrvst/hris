import { useCallback, useRef, useState } from 'react';

/**
 * Hook untuk mengumpulkan sampel accelerometer/gyroscope sebagai
 * bukti bahwa perangkat benar-benar digenggam (bukan emulator).
 *
 * Menggunakan DeviceMotionEvent API — hanya jalan di HTTPS + perlu
 * izin eksplisit di iOS. Jika API tidak tersedia, graceful degradation
 * dengan mengirim `null` (server tidak akan flag motion).
 *
 * @returns {object} { motionSamples, samplesReady, isSupported, collectMotionSamples }
 *
 * motionSamples: Array<{x,y,z,timestamp}> — 5 sample terakhir
 * samplesReady: boolean — true jika minimal 2 sample terkumpul
 * isSupported: boolean — apakah DeviceMotionEvent tersedia
 * collectMotionSamples: () => void — panggil untuk mulai koleksi
 */
export function useMotionSamples() {
    const [motionSamples, setMotionSamples] = useState(null);
    const [samplesReady, setSamplesReady] = useState(false);
    const [isSupported, setIsSupported] = useState(false);
    const samplesRef = useRef([]);
    const timerRef = useRef(null);

    const isMotionSupported = typeof window !== 'undefined' && 'DeviceMotionEvent' in window;

    const collectMotionSamples = useCallback(async () => {
        if (!isMotionSupported) {
            setIsSupported(false);

            return;
        }

        // iOS requires explicit permission request
        if (typeof DeviceMotionEvent.requestPermission === 'function') {
            try {
                const permission = await DeviceMotionEvent.requestPermission();
                if (permission !== 'granted') {
                    setIsSupported(false);

                    return;
                }
            } catch {
                // Fallback: some browsers don't have requestPermission
            }
        }

        setIsSupported(true);
        samplesRef.current = [];

        const handler = (event) => {
            const accel = event.accelerationIncludingGravity;
            if (!accel) return;

            samplesRef.current.push({
                x: accel.x ?? 0,
                y: accel.y ?? 0,
                z: accel.z ?? 0,
                timestamp: Date.now(),
            });

            // Keep last 5 samples
            if (samplesRef.current.length > 5) {
                samplesRef.current = samplesRef.current.slice(-5);
            }

            if (samplesRef.current.length >= 2) {
                setSamplesReady(true);
            }

            // Stop after 2 seconds (roughly 5-10 samples at 60Hz)
            if (samplesRef.current.length >= 5) {
                window.removeEventListener('devicemotion', handler);
                setMotionSamples([...samplesRef.current]);
            }
        };

        window.addEventListener('devicemotion', handler);

        // Safety timeout: stop after 3 seconds
        timerRef.current = setTimeout(() => {
            window.removeEventListener('devicemotion', handler);
            if (samplesRef.current.length >= 2) {
                setMotionSamples([...samplesRef.current]);
                setSamplesReady(true);
            }
        }, 3000);
    }, [isMotionSupported]);

    return {
        motionSamples,
        samplesReady,
        isSupported,
        collectMotionSamples,
    };
}