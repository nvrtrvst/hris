import React, { useState, useRef, useEffect } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card, Toggle, Badge } from '@/Components/MobileUI';
import { Camera, RefreshCw, MapPin, Clock, CheckCircle, AlertCircle, Loader2 } from 'lucide-react';

export default function Absen({ auth, pegawai, jadwals, presensiHariIni }) {
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [loadingLocation, setLoadingLocation] = useState(false);
    const [locationError, setLocationError] = useState(null);
    const [showLive, setShowLive] = useState(false);
    const [error, setError] = useState(null);
    const [successMessage, setSuccessMessage] = useState(null);
    const [capturedPhoto, setCapturedPhoto] = useState(null);
    const [isLembur, setIsLembur] = useState(false);
    const [jadwalId, setJadwalId] = useState(null);
    const [currentTime, setCurrentTime] = useState('');
    const [watchId, setWatchId] = useState(null);
    const [geoStatus, setGeoStatus] = useState('idle');
    const [currentPosition, setCurrentPosition] = useState(null);
    const [geoInfo, setGeoInfo] = useState(null);
    const [geoInfoLoading, setGeoInfoLoading] = useState(false);

    const videoRef = useRef(null);
    const canvasRef = useRef(null);
    const streamRef = useRef(null);
    const fileInputRef = useRef(null);
    const photoInputRef = useRef(null);

    const { flash } = usePage().props;

    useEffect(() => {
        startCamera();
        const clock = setInterval(() => {
            const now = new Date();
            setCurrentTime(now.toLocaleTimeString('id-ID', { hour12: false }));
        }, 1000);
        getCurrentPosition();
        return () => {
            clearInterval(clock);
            if (streamRef.current) {
                streamRef.current.getTracks().forEach((t) => t.stop());
            }
            if (watchId) navigator.geolocation.clearWatch(watchId);
        };
    }, []);

    useEffect(() => {
        if (showLive && videoRef.current && streamRef.current) {
            videoRef.current.srcObject = streamRef.current;
            videoRef.current.play().catch(() => {});
        }
    }, [showLive]);

    const startCamera = async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user' },
                audio: false,
            });
            streamRef.current = stream;
            setShowLive(true);
        } catch (err) {
            setShowLive(false);
            if (!locationError) setLocationError('Kamera tidak dapat diakses. Gunakan tombol di bawah untuk unggah foto.');
        }
    };

    const stopCamera = () => {
        if (streamRef.current) {
            streamRef.current.getTracks().forEach((t) => t.stop());
            streamRef.current = null;
        }
    };

    const capturePhoto = () => {
        if (!videoRef.current || !canvasRef.current) return;
        const video = videoRef.current;
        const canvas = canvasRef.current;
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const context = canvas.getContext('2d');
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        const dataUrl = canvas.toDataURL('image/jpeg');
        setCapturedPhoto(dataUrl);
        stopCamera();
        setShowLive(false);
    };

    const retakePhoto = () => {
        setCapturedPhoto(null);
        startCamera();
    };

    const toggleLembur = () => setIsLembur((prev) => !prev);

    const getCurrentPosition = () => {
        setLoadingLocation(true);
        setGeoStatus('loading');
        if (!navigator.geolocation) {
            setGeoStatus('error');
            setLocationError('Geolocation tidak didukung di perangkat ini.');
            setLoadingLocation(false);
            return;
        }
        const id = navigator.geolocation.watchPosition(
            (pos) => {
                setCurrentPosition({
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude,
                    accuracy: pos.coords.accuracy,
                });
                setGeoStatus('ready');
                setLoadingLocation(false);
                setGeoInfoLoading(true);
                fetch(
                    `https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${pos.coords.latitude}&longitude=${pos.coords.longitude}&localityLanguage=id`
                )
                    .then((r) => r.json())
                    .then((d) =>
                        setGeoInfo({
                            locality: d.locality,
                            city: d.city,
                            principalSubdivision: d.principalSubdivision,
                            postcode: d.postcode,
                            countryName: d.countryName,
                        })
                    )
                    .catch(() => setGeoInfo(null))
                    .finally(() => setGeoInfoLoading(false));
            },
            (err) => {
                setGeoStatus('error');
                setLocationError(err.message || 'Tidak dapat mengambil lokasi.');
                setLoadingLocation(false);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
        setWatchId(id);
    };

    const handleFileFallback = (e) => {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onloadend = () => setCapturedPhoto(reader.result);
        reader.readAsDataURL(file);
    };

    const selectJadwal = (id) => setJadwalId(id);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError(null);
        setSuccessMessage(null);

        if (!capturedPhoto) {
            setError('Silakan ambil foto presensi terlebih dahulu.');
            return;
        }
        if (!isLembur && jadwals.length > 0 && !jadwalId) {
            setError('Silakan pilih jadwal presensi Anda.');
            return;
        }
        if (!currentPosition && !isLembur) {
            setError('Lokasi belum tersedia. Pastikan GPS aktif.');
            return;
        }

        setIsSubmitting(true);
        const payload = {
            photo: capturedPhoto,
            is_lembur: isLembur,
            jadwal_id: isLembur ? null : jadwalId,
            latitude: currentPosition?.latitude ?? null,
            longitude: currentPosition?.longitude ?? null,
            accuracy: currentPosition?.accuracy ?? null,
        };
        try {
            const res = await fetch(route('mobile.absen.store'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (res.ok && data.success) {
                setSuccessMessage(data.message || 'Presensi berhasil dikirim.');
                setCapturedPhoto(null);
                setJadwalId(null);
                setTimeout(() => {
                    if (typeof window !== 'undefined') window.location.reload();
                }, 1500);
            } else {
                setError(data.message || 'Gagal mengirim presensi.');
            }
        } catch (err) {
            setError('Terjadi kesalahan jaringan. Coba lagi.');
        } finally {
            setIsSubmitting(false);
        }
    };

    const accent = isLembur ? 'amber' : 'indigo';

    const fullDate = new Date().toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    return (
        <MobileLayout user={auth.user}>
            <Head title="Presensi" />

            <div className="mb-5 px-1">
                <h1 className="text-2xl font-extrabold tracking-tight text-slate-800">Presensi</h1>
                <p className="mt-0.5 text-sm text-slate-500">Ambil foto & pastikan lokasi terdeteksi</p>
            </div>

            {flash.message && (
                <div className="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{flash.message}</div>
            )}
            {flash.error && (
                <div className="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{flash.error}</div>
            )}

            {successMessage && (
                <div className="mb-4 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    <CheckCircle className="h-5 w-5" /> {successMessage}
                </div>
            )}
            {error && (
                <div className="mb-4 flex items-center gap-2 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                    <AlertCircle className="h-5 w-5" /> {error}
                </div>
            )}

            {/* Camera */}
            <Card className="overflow-hidden p-0">
                <div className="relative aspect-[3/4] w-full overflow-hidden bg-slate-900">
                    {showLive ? (
                        <>
                            <video ref={videoRef} autoPlay playsInline muted className="absolute inset-0 h-full w-full object-cover" />
                            {currentPosition && (
                                <iframe
                                    title="Geo tag"
                                    loading="lazy"
                                    src={`https://www.openstreetmap.org/export/embed.html?bbox=${(currentPosition.longitude - 0.004).toFixed(6)}%2C${(currentPosition.latitude - 0.004).toFixed(6)}%2C${(currentPosition.longitude + 0.004).toFixed(6)}%2C${(currentPosition.latitude + 0.004).toFixed(6)}&layer=mapnik&marker=${currentPosition.latitude}%2C${currentPosition.longitude}`}
                                    className="pointer-events-none absolute inset-0 h-full w-full opacity-20"
                                />
                            )}
                            <div className="absolute inset-x-0 top-0 flex items-start justify-between gap-2 bg-gradient-to-b from-black/60 to-transparent p-3">
                                <span className="rounded-lg bg-black/50 px-2 py-1 text-[11px] font-semibold text-white backdrop-blur">{fullDate}</span>
                                <span className="rounded-lg bg-black/50 px-2 py-1 text-[11px] font-semibold text-white backdrop-blur">
                                    <Clock className="mr-1 inline h-3 w-3" />
                                    {currentTime}
                                </span>
                            </div>
                            <div className="absolute inset-x-0 bottom-24 bg-gradient-to-t from-black/70 to-transparent p-3">
                                <div className="flex items-start gap-1.5 rounded-xl bg-black/45 px-3 py-2 text-white backdrop-blur-sm">
                                    <MapPin className="mt-0.5 h-3.5 w-3.5 flex-shrink-0 text-emerald-300" />
                                    <div className="text-[11px] leading-tight">
                                        {geoInfoLoading ? (
                                            <p className="text-white/80">Mendeteksi alamat…</p>
                                        ) : geoInfo ? (
                                            <>
                                                <p className="font-semibold">{geoInfo.locality || geoInfo.city}</p>
                                                <p className="text-white/80">
                                                    {geoInfo.city && geoInfo.locality ? `${geoInfo.city}, ` : ''}
                                                    {geoInfo.principalSubdivision}
                                                    {geoInfo.postcode ? ` ${geoInfo.postcode}` : ''}
                                                </p>
                                                <p className="text-white/60">{geoInfo.countryName}</p>
                                            </>
                                        ) : (
                                            <p className="text-white/80">Lokasi belum tersedia</p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </>
                    ) : capturedPhoto ? (
                        <img src={capturedPhoto} alt="captured" className="h-full w-full object-cover" />
                    ) : (
                        <button
                            type="button"
                            onClick={() => photoInputRef.current?.click()}
                            className="flex h-full w-full flex-col items-center justify-center gap-2 bg-slate-900 px-6 text-center text-slate-300 transition-colors active:bg-slate-800"
                        >
                            <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/10 text-slate-200">
                                <Camera className="h-6 w-6" />
                            </div>
                            <p className="text-sm font-medium">{locationError || 'Kamera tidak dapat diakses'}</p>
                            <p className="text-xs text-slate-400">Ketuk untuk mengunggah foto dari galeri</p>
                        </button>
                    )}

                    {!showLive && (
                        <span className="absolute left-3 top-3 rounded-full bg-black/50 px-2.5 py-1 text-xs font-semibold text-white backdrop-blur">
                            <Clock className="mr-1 inline h-3 w-3" />
                            {currentTime}
                        </span>
                    )}

                    {isLembur && (
                        <span className="absolute right-3 top-3 rounded-full bg-amber-500/90 px-2.5 py-1 text-xs font-bold text-white backdrop-blur">LEMBUR</span>
                    )}

                    {/* bottom gradient + action */}
                    <div className="absolute inset-x-0 bottom-0 flex items-center justify-center bg-gradient-to-t from-black/50 to-transparent p-5">
                        {capturedPhoto ? (
                            <button
                                type="button"
                                onClick={retakePhoto}
                                className="flex items-center gap-2 rounded-2xl bg-white/90 px-5 py-3 text-sm font-bold text-slate-800 shadow-lg backdrop-blur transition-transform active:scale-95"
                            >
                                <RefreshCw className="h-4 w-4" /> Ambil Ulang
                            </button>
                        ) : (
                            <button
                                type="button"
                                onClick={capturePhoto}
                                className="flex h-16 w-16 items-center justify-center rounded-full bg-white shadow-[0_8px_24px_-4px_rgba(0,0,0,0.5)] transition-transform active:scale-90"
                            >
                                <span className={`h-12 w-12 rounded-full ${isLembur ? 'bg-amber-500' : 'bg-emerald-500'}`} />
                            </button>
                        )}
                    </div>
                </div>
            </Card>

            <input type="file" accept="image/*" capture="environment" ref={photoInputRef} className="hidden" onChange={handleFileFallback} />

            {/* Lembur toggle */}
            <Card className="mt-3 flex items-center justify-between py-4">
                <div>
                    <p className="font-bold text-slate-800">Mode Lembur</p>
                    <p className="text-xs text-slate-500">Presensi tanpa jadwal & cek telat</p>
                </div>
                <Toggle checked={isLembur} onChange={toggleLembur} tone="amber" />
            </Card>

            {/* Jadwal */}
            {!isLembur && jadwals.length > 0 && (
                <div className="mt-5">
                    <h3 className="mb-3 px-1 text-sm font-extrabold uppercase tracking-wider text-slate-500">Pilih Jadwal</h3>
                    <div className="flex gap-2 overflow-x-auto pb-2">
                        {jadwals.map((j) => (
                            <button
                                key={j.id}
                                type="button"
                                onClick={() => selectJadwal(j.id)}
                                className={`flex-shrink-0 rounded-2xl border-2 p-3 text-left transition-all active:scale-95 ${
                                    jadwalId === j.id
                                        ? 'border-emerald-500 bg-emerald-50 shadow-md'
                                        : 'border-slate-200 bg-white hover:border-emerald-200'
                                }`}
                            >
                                <p className="text-sm font-bold text-slate-800">{j.mata_pelajaran?.nama || 'Jadwal'}</p>
                                <p className="text-xs text-slate-500">{j.kelas ? `Kls ${j.kelas.tingkat} ${j.kelas.nama}` : (j.hari || '')}</p>
                                <p className="mt-1 text-xs font-semibold text-emerald-500">{j.jam_mulai} – {j.jam_selesai}</p>
                            </button>
                        ))}
                    </div>
                </div>
            )}

            {/* Lokasi */}
            <Card className="mt-3 py-4">
                <div className="flex items-center gap-3">
                    <div className={`flex h-11 w-11 items-center justify-center rounded-2xl ${
                        geoStatus === 'ready' ? 'bg-emerald-100 text-emerald-600' : geoStatus === 'error' ? 'bg-rose-100 text-rose-600' : 'bg-slate-100 text-slate-400'
                    }`}>
                        {loadingLocation ? <Loader2 className="h-5 w-5 animate-spin" /> : <MapPin className="h-5 w-5" />}
                    </div>
                    <div className="flex-1">
                        <p className="font-bold text-slate-800">
                            {geoStatus === 'ready' ? 'Lokasi Terdeteksi' : geoStatus === 'error' ? 'Lokasi Gagal' : 'Mendeteksi Lokasi…'}
                        </p>
                        <p className="text-xs text-slate-500">
                            {currentPosition ? `${currentPosition.latitude.toFixed(5)}, ${currentPosition.longitude.toFixed(5)}` : (locationError || 'Aktifkan GPS')}
                        </p>
                    </div>
                </div>
            </Card>

            {/* Submit */}
            <button
                type="button"
                onClick={handleSubmit}
                disabled={isSubmitting}
                className={`mt-5 w-full rounded-2xl bg-gradient-to-br ${isLembur ? 'from-amber-500 to-orange-500 shadow-[0_10px_24px_-6px_rgba(245,158,11,0.6)]' : 'from-emerald-500 to-primary shadow-[0_10px_24px_-6px_rgba(79,70,229,0.6)]'} py-4 font-extrabold text-white transition-transform active:scale-[0.98] disabled:opacity-60`}
            >
                {isSubmitting ? <><Loader2 className="mr-2 inline h-5 w-5 animate-spin" />Memproses…</> : isLembur ? 'Kirim Lembur' : 'Kirim Presensi'}
            </button>

            <canvas ref={canvasRef} className="hidden" />
        </MobileLayout>
    );
}
