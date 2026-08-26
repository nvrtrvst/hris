import { useState, useEffect } from 'react';
import { useCamera } from '@/Hooks/useCamera';
import { Camera, RefreshCw, Send, Loader2, SwitchCamera } from 'lucide-react';

export default function BuktiKegiatan({ presensiId, initialUrls = [], currentPosition = null, onCameraOpen, onCameraClose }) {
    const [urls, setUrls] = useState(initialUrls);
    const [keterangan, setKeterangan] = useState('');
    const [open, setOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);

    const camera = useCamera({ canCapture: true, currentPosition, facingMode: 'environment' });

    // Ikat stream ke <video> saat live — wajib, kalau tidak preview hitam.
    useEffect(() => {
        if (camera.showLive && camera.videoRef.current && camera.streamRef.current) {
            camera.videoRef.current.srcObject = camera.streamRef.current;
            camera.videoRef.current.play().catch(() => {});
        }
    }, [camera.showLive, camera.facing]);

    const token = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const posLabel = currentPosition
        ? `${currentPosition.latitude.toFixed(6)}, ${currentPosition.longitude.toFixed(6)} · Akurasi ${currentPosition.accuracy?.toFixed(0)}m`
        : null;

    const start = () => {
        setError(null);
        setSuccess(null);
        setOpen(true);
        onCameraOpen?.();
        camera.startCamera();
    };

    const upload = async () => {
        if (!camera.capturedPhoto) return;
        setSaving(true);
        setError(null);
        try {
            const res = await fetch(route('presensi.tugas-luar.bukti', { presensi: presensiId }), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    foto: camera.capturedPhoto,
                    keterangan,
                    latitude: currentPosition?.latitude ?? null,
                    longitude: currentPosition?.longitude ?? null,
                    accuracy: currentPosition?.accuracy ?? null,
                }),
            });
            if (!res.ok) {
                const d = await res.json().catch(() => ({}));
                throw new Error(d.message || 'Gagal mengunggah foto kegiatan.');
            }
            const d = await res.json();
            if (d.foto_kegiatan_urls) setUrls(d.foto_kegiatan_urls);
            setSuccess('Foto kegiatan tersimpan.');
            camera.clearCamera();
            setOpen(false);
            onCameraClose?.();
            setKeterangan('');
        } catch (e) {
            setError(e.message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="mb-4 rounded-2xl border border-sky-200 bg-sky-50 p-4">
            <p className="text-sm font-bold text-sky-900">Foto Kegiatan Tugas Luar</p>
            <p className="mt-0.5 text-xs text-sky-700">Ambil bukti kegiatan (mis. saat rapat). Opsional, bisa beberapa foto.</p>

            {urls.length > 0 && (
                <div className="mt-3 grid grid-cols-3 gap-2">
                    {urls.map((u, i) => (
                        <img key={i} src={u} alt={`Bukti kegiatan ${i + 1}`} className="h-24 w-full rounded-lg object-cover" />
                    ))}
                </div>
            )}

            {error && <p className="mt-3 text-xs font-medium text-red-600">{error}</p>}
            {success && <p className="mt-3 text-xs font-medium text-emerald-600">{success}</p>}

            {!open ? (
                <button
                    type="button"
                    onClick={start}
                    className="mt-3 flex w-full items-center justify-center gap-2 rounded-xl bg-sky-500 px-4 py-3 text-sm font-bold text-sky-950"
                >
                    <Camera className="h-5 w-5" /> Ambil Foto Kegiatan
                </button>
            ) : (
                <div className="mt-3">
                    <canvas ref={camera.canvasRef} className="hidden" />

                    {camera.showLive && !camera.capturedPhoto && (
                        <div className="relative overflow-hidden rounded-xl bg-slate-950">
                            <video ref={camera.videoRef} autoPlay playsInline className="block aspect-[3/4] w-full bg-slate-950 object-cover" />
                            <button
                                type="button"
                                onClick={camera.switchCamera}
                                title={camera.facing === 'user' ? 'Kamera depan — tap utk belakang' : 'Kamera belakang — tap utk depan'}
                                className="absolute right-2 top-2 flex items-center gap-1 rounded-full bg-black/55 px-2.5 py-1.5 text-xs font-semibold text-white"
                            >
                                <SwitchCamera className="h-4 w-4" />
                                {camera.facing === 'user' ? 'Depan' : 'Belakang'}
                            </button>
                            <button
                                type="button"
                                onClick={camera.capturePhoto}
                                disabled={camera.isCapturing}
                                className="absolute inset-x-0 bottom-0 flex items-center justify-center gap-2 bg-sky-500 py-3 text-sm font-bold text-sky-950"
                            >
                                <Camera className="h-5 w-5" /> {camera.isCapturing ? 'Memproses...' : 'Ambil Foto'}
                            </button>
                        </div>
                    )}

                    {posLabel && (
                        <p className="px-1 text-center font-mono text-[10px] tabular-nums text-sky-700">{posLabel}</p>
                    )}
                    {!currentPosition && (
                        <p className="px-1 text-center text-[10px] font-medium text-amber-600">GPS belum aktif — koordinat bukti tidak tercatat.</p>
                    )}

                    {camera.capturedPhoto && (
                        <div>
                            <img src={camera.capturedPhoto} alt="Pratinjau bukti" className="block w-full rounded-xl" />
                            {posLabel && (
                                <p className="mt-2 font-mono text-[10px] tabular-nums text-sky-700">{posLabel}</p>
                            )}
                            {!currentPosition && (
                                <p className="mt-2 text-[10px] font-medium text-amber-600">GPS belum aktif — koordinat bukti tidak tercatat.</p>
                            )}
                            <input
                                type="text"
                                value={keterangan}
                                onChange={(e) => setKeterangan(e.target.value)}
                                placeholder="Keterangan (opsional)"
                                className="mt-2 w-full rounded-xl border border-sky-300 bg-white px-3 py-2 text-sm"
                            />
                            <div className="mt-2 flex gap-2">
                                <button
                                    type="button"
                                    onClick={camera.retakePhoto}
                                    className="flex-1 rounded-xl border border-sky-300 px-3 py-2 text-sm font-semibold text-sky-900"
                                >
                                    <RefreshCw className="mr-1 inline h-4 w-4" /> Ulangi
                                </button>
                                <button
                                    type="button"
                                    onClick={upload}
                                    disabled={saving}
                                    className="flex-1 rounded-xl bg-sky-500 px-3 py-2 text-sm font-bold text-sky-950 disabled:opacity-60"
                                >
                                    {saving ? (
                                        <><Loader2 className="mr-1 inline h-4 w-4 animate-spin" /> Mengirim...</>
                                    ) : (
                                        <><Send className="mr-1 inline h-4 w-4" /> Kirim</>
                                    )}
                                </button>
                            </div>
                        </div>
                    )}

                    {camera.cameraError && !camera.showLive && !camera.capturedPhoto && (
                        <p className="text-xs text-red-600">{camera.cameraError}</p>
                    )}

                    <button
                        type="button"
                        onClick={() => {
                            camera.clearCamera();
                            setOpen(false);
                            onCameraClose?.();
                        }}
                        className="mt-2 w-full text-center text-xs text-slate-500"
                    >
                        Batal
                    </button>
                </div>
            )}
        </div>
    );
}
