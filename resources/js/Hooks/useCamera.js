import { useCallback, useRef, useState } from 'react';

/**
 * Hook kamera bersama utk halaman presensi mobile.
 *
 * Mengelola: getUserMedia (selfie), pratinjau live, ambil foto bukti
 * (crop 3:4 + resize max 1024px), fallback upload dari galeri.
 *
 * @param {object} opts
 * @param {boolean} opts.canCapture — true saat GPS siap & dalam radius (foto diblokir di luar itu)
 * @param {object|null} opts.currentPosition — posisi GPS saat ini (utk posisi-A anti-spoof)
 * @param {function} [opts.onWillCapture] — callback saat foto diambil, menerima posisi-A snapshot
 * @returns {object} refs + state + handlers
 */
export function useCamera({ canCapture = true, currentPosition = null, onWillCapture = null }) {
    const [showLive, setShowLive] = useState(false);
    const [capturedPhoto, setCapturedPhoto] = useState(null);
    const [cameraError, setCameraError] = useState(null);
    const [isCapturing, setIsCapturing] = useState(false);

    const videoRef = useRef(null);
    const canvasRef = useRef(null);
    const streamRef = useRef(null);
    const photoInputRef = useRef(null);

    const startCamera = useCallback(async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 720 }, height: { ideal: 960 } },
                audio: false,
            });
            streamRef.current = stream;
            setShowLive(true);
        } catch (err) {
            setShowLive(false);
            setCameraError((prev) => prev || 'Kamera tidak dapat diakses. Gunakan tombol di bawah untuk unggah foto.');
        }
    }, []);

    const stopCamera = useCallback(() => {
        if (streamRef.current) {
            streamRef.current.getTracks().forEach((t) => t.stop());
            streamRef.current = null;
        }
    }, []);

    const createEvidencePhoto = useCallback((source, sourceWidth, sourceHeight) => {
        return new Promise((resolve) => {
            const MAX = 1024;
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

            resolve(canvas.toDataURL('image/jpeg', 0.84));
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

    const handleFileFallback = useCallback((e) => {
        if (!canCapture) return;
        const file = e.target.files?.[0];
        if (!file) return;
        onWillCapture?.(currentPosition ? { ...currentPosition, captured_at: new Date().toISOString() } : null);

        const reader = new FileReader();
        reader.onloadend = () => {
            const image = new Image();
            image.onload = async () => {
                setIsCapturing(true);
                try {
                    const dataUrl = await createEvidencePhoto(image, image.naturalWidth, image.naturalHeight);
                    setCapturedPhoto(dataUrl);
                } finally {
                    setIsCapturing(false);
                }
            };
            image.onerror = () => setCameraError('Foto tidak dapat diproses. Pilih file gambar lain.');
            image.src = reader.result;
        };
        reader.readAsDataURL(file);
    }, [canCapture, currentPosition, createEvidencePhoto, onWillCapture]);

    const clearCamera = useCallback(() => {
        stopCamera();
        setShowLive(false);
        setCapturedPhoto(null);
        setCameraError(null);
    }, [stopCamera]);

    return {
        videoRef,
        canvasRef,
        photoInputRef,
        streamRef,
        showLive,
        capturedPhoto,
        cameraError,
        isCapturing,
        startCamera,
        stopCamera,
        capturePhoto,
        retakePhoto,
        handleFileFallback,
        clearCamera,
    };
}
