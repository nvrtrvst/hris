import { useCallback, useRef, useState } from 'react';

/**
 * Hook kamera bersama utk halaman presensi mobile.
 *
 * Mengelola: getUserMedia (kamera belakang, facingMode environment), pratinjau
 * live, ambil foto bukti (crop 3:4 + resize max 640px, JPEG q0.75 — sama dengan
 * target resize server di ImageUploadService (640px), sehingga resize server jadi
 * no-op dan beban CPU/RAM di jam sibuk (250 absen serentak) turun drastis).
 * TIDAK ada fallback galeri: foto HARUS realtime (anti-spoof replay).
 *
 * @param {object} opts
 * @param {boolean} opts.canCapture — true saat GPS siap & dalam radius (foto diblokir di luar itu)
 * @param {object|null} opts.currentPosition — posisi GPS saat ini (utk posisi-A anti-spoof)
 * @param {function} [opts.onWillCapture] — callback saat foto diambil, menerima posisi-A snapshot
 * @param {string} [opts.facingMode='user'] — 'user' (depan, selfie presensi) atau 'environment' (belakang).
 *        Dinas luar pakai 'environment' + switchCamera() utk ganti depan/belakang.
 * @returns {object} refs + state + handlers
 */
export function useCamera({ canCapture = true, currentPosition = null, onWillCapture = null, facingMode = 'user' }) {
    const [showLive, setShowLive] = useState(false);
    const [capturedPhoto, setCapturedPhoto] = useState(null);
    const [cameraError, setCameraError] = useState(null);
    const [isCapturing, setIsCapturing] = useState(false);
    const [facing, setFacing] = useState(facingMode);

    const videoRef = useRef(null);
    const canvasRef = useRef(null);
    const streamRef = useRef(null);

    const startCamera = useCallback(async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: facing, width: { ideal: 720 }, height: { ideal: 960 } },
                audio: false,
            });
            streamRef.current = stream;
            setShowLive(true);
        } catch (err) {
            setShowLive(false);
            setCameraError((prev) => prev || 'Kamera tidak dapat diakses. Gunakan tombol di bawah untuk unggah foto.');
        }
    }, [facing]);

    const switchCamera = useCallback(() => {
        setFacing((prev) => {
            const next = prev === 'user' ? 'environment' : 'user';
            if (streamRef.current) {
                streamRef.current.getTracks().forEach((t) => t.stop());
                streamRef.current = null;
            }
            setShowLive(false);
            navigator.mediaDevices.getUserMedia({
                video: { facingMode: next, width: { ideal: 720 }, height: { ideal: 960 } },
                audio: false,
            }).then((stream) => {
                streamRef.current = stream;
                setShowLive(true);
            }).catch(() => {
                setCameraError((p) => p || 'Gagal mengganti kamera. Pastikan izin kamera aktif.');
            });
            return next;
        });
    }, []);

    const stopCamera = useCallback(() => {
        if (streamRef.current) {
            streamRef.current.getTracks().forEach((t) => t.stop());
            streamRef.current = null;
        }
    }, []);

    const createEvidencePhoto = useCallback((source, sourceWidth, sourceHeight) => {
        return new Promise((resolve) => {
            const MAX = 640;
            const targetRatio = 3 / 4;
            let cropWidth = sourceWidth;
            let cropHeight = sourceHeight;
            let cropX = 0;
            let cropY = 0;

            if (sourceWidth / sourceHeight > targetRatio) {
                cropWidth = sourceHeight * targetRatio;
                cropX = (sourceWidth - cropWidth) / 2;
            } else {
                cropHeight = sourceWidth / targetRatio;
                cropY = (sourceHeight - cropHeight) / 2;
            }

            const scale = Math.min(1, MAX / Math.max(cropWidth, cropHeight));
            const width = Math.round(cropWidth * scale);
            const height = Math.round(cropHeight * scale);

            const canvas = canvasRef.current;
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(source, cropX, cropY, cropWidth, cropHeight, 0, 0, width, height);

            resolve(canvas.toDataURL('image/jpeg', 0.75));
        });
    }, []);

    const capturePhoto = useCallback(async () => {
        if (!canCapture) return;
        if (!videoRef.current || !canvasRef.current) return;
        const video = videoRef.current;
        if (!video.videoWidth || !video.videoHeight) return;

        onWillCapture?.(currentPosition ? { ...currentPosition, captured_at: new Date().toISOString() } : null);

        setIsCapturing(true);
        try {
            const dataUrl = await createEvidencePhoto(video, video.videoWidth, video.videoHeight);
            setCapturedPhoto(dataUrl);
            stopCamera();
            setShowLive(false);
        } finally {
            setIsCapturing(false);
        }
    }, [canCapture, currentPosition, createEvidencePhoto, onWillCapture, stopCamera]);

    const retakePhoto = useCallback(() => {
        setCapturedPhoto(null);
        startCamera();
    }, [startCamera]);

    const clearCamera = useCallback(() => {
        stopCamera();
        setShowLive(false);
        setCapturedPhoto(null);
        setCameraError(null);
    }, [stopCamera]);

    return {
        videoRef,
        canvasRef,
        streamRef,
        showLive,
        capturedPhoto,
        cameraError,
        isCapturing,
        facing,
        startCamera,
        stopCamera,
        capturePhoto,
        retakePhoto,
        clearCamera,
        switchCamera,
    };
}
