import React, { useState, useRef, useEffect, useMemo } from 'react';
import { calculateDistance, checkGeofence } from '@/Utils/geo';
import { OSM_TILE_URL } from '@/Constants/AppConstants';
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
    const [mapError, setMapError] = useState(false);

    const videoRef = useRef(null);
    const canvasRef = useRef(null);
    const streamRef = useRef(null);
    const fileInputRef = useRef(null);
    const photoInputRef = useRef(null);
    const geoControllerRef = useRef(null);

    const { flash = {} } = usePage().props;

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
            if (geoControllerRef.current) {
                geoControllerRef.current.abort();
            }
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

    const pad = (n) => String(n).padStart(2, '0');

    const capturePhoto = () => {
        if (geoBlocked) return;
        if (!videoRef.current || !canvasRef.current) return;
        const video = videoRef.current;
        const canvas = canvasRef.current;
        const MAX = 1024;
        let w = video.videoWidth;
        let h = video.videoHeight;
        if (w > MAX || h > MAX) {
            if (w > h) { h = Math.round(h * MAX / w); w = MAX; }
            else { w = Math.round(w * MAX / h); h = MAX; }
        }
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, w, h);

        // Burn-in overlay: jam, tgl, lokasi
        const now = new Date();
        const ts = `${pad(now.getHours())}.${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
        const ds = now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        const pp = [];
        if (geoInfo?.streetName) pp.push(`Jl. ${geoInfo.streetName}${geoInfo.streetNumber ? ' No. ' + geoInfo.streetNumber : ''}`);
        if (geoInfo?.locality) pp.push(geoInfo.locality);
        if (geoInfo?.city && geoInfo.city !== geoInfo.locality) pp.push(geoInfo.city);
        if (geoInfo?.principalSubdivision) pp.push(geoInfo.principalSubdivision);
        const loc = pp.length ? pp.join(', ') : 'Lokasi belum tersedia';

        ctx.textBaseline = 'bottom';
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 4;
        ctx.font = `bold ${Math.max(18, Math.round(w * 0.05))}px sans-serif`;
        ctx.fillStyle = '#fff';
        ctx.strokeText(ts, 12, h - 50);
        ctx.fillText(ts, 12, h - 50);
        ctx.font = `bold ${Math.max(13, Math.round(w * 0.034))}px sans-serif`;
        ctx.strokeText(ds, 12, h - 28);
        ctx.fillText(ds, 12, h - 28);
        ctx.font = `bold ${Math.max(11, Math.round(w * 0.03))}px sans-serif`;
        ctx.strokeText(loc.length > 50 ? loc.substring(0, 47) + '...' : loc, 12, h - 10);
        ctx.fillText(loc.length > 50 ? loc.substring(0, 47) + '...' : loc, 12, h - 10);
        ctx.lineWidth = 1;

        const dataUrl = canvas.toDataURL('image/jpeg', 0.95);
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

                geoControllerRef.current = new AbortController();

                fetch(
                    `https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${pos.coords.latitude}&longitude=${pos.coords.longitude}&localityLanguage=id`,
                    { signal: geoControllerRef.current.signal }
                )
                    .then((r) => {
                        if (!r.ok) throw new Error('Geocoding failed');
                        return r.json();
                    })
                    .then((d) =>
                        setGeoInfo({
                            locality: d.locality,
                            city: d.city,
                            principalSubdivision: d.principalSubdivision,
                            postcode: d.postcode,
                            countryName: d.countryName,
                            streetName: d.streetName,
                            streetNumber: d.streetNumber,
                        })
                    )
                    .catch((err) => {
                        if (err.name !== 'AbortError') {
                            setGeoInfo(null);
                        }
                    })
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
        if (geoBlocked) return;
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onloadend = () => setCapturedPhoto(reader.result);
        reader.readAsDataURL(file);
    };

    const selectJadwal = (id) => setJadwalId(id);

    // Unit geofence target: lembur -> unit primer; reguler -> unit jadwal terpilih (atau primer).
    const lemburUnit =
        pegawai?.units?.find((u) => u.pivot?.is_primary) ?? pegawai?.units?.[0] ?? null;
    const selectedJadwal = jadwals.find((j) => j.id === jadwalId);
    const targetUnit = isLembur
        ? lemburUnit
        : selectedJadwal?.unit_sekolah ?? lemburUnit;

    const geofence = useMemo(() => {
        if (!currentPosition || !targetUnit) return null;
        const lat = parseFloat(targetUnit.latitude);
        const lon = parseFloat(targetUnit.longitude);
        if (isNaN(lat) || isNaN(lon)) return null;
        const radius = targetUnit.radius_meter ?? 50;
        const { inside, distance } = checkGeofence(
            currentPosition.latitude,
            currentPosition.longitude,
            lat,
            lon,
            radius
        );
        return {
            name: targetUnit.nama_unit || targetUnit.nama || 'Unit Sekolah',
            distance,
            radius,
            inside,
        };
    }, [currentPosition, targetUnit]);

    const geoBlocked = geofence && !geofence.inside;
    const geoReady = geofence !== null;

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
        if (geoBlocked) {
            setError(
                `Anda di luar radius ${geofence.name} (${Math.round(geofence.distance)}m / batas ${geofence.radius}m). Tidak dapat presensi.`
            );
            return;
        }

        setIsSubmitting(true);
        const openPresensi = presensiHariIni?.find((p) => p.jam_masuk && !p.jam_keluar);
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const payload = {
            _token: token,
            foto: capturedPhoto,
            is_lembur: isLembur,
            jadwal_id: isLembur ? null : jadwalId,
            tipe: openPresensi ? 'keluar' : 'masuk',
            latitude: currentPosition?.latitude ?? null,
            longitude: currentPosition?.longitude ?? null,
            accuracy: currentPosition?.accuracy ?? null,
        };
        try {
            const res = await fetch(route('presensi.absen.store'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
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

    let tileUrl = null;
    if (currentPosition && !mapError) {
        const z = 15;
        const n = 2 ** z;
        const xt = Math.floor(((currentPosition.longitude + 180) / 360) * n);
        const latRad = (currentPosition.latitude * Math.PI) / 180;
        const yt = Math.floor(((1 - Math.log(Math.tan(latRad) + 1 / Math.cos(latRad)) / Math.PI) / 2) * n);
        tileUrl = OSM_TILE_URL.replace('{z}', z).replace('{x}', xt).replace('{y}', yt);
    }

    const now = new Date();
    const timeString = `${pad(now.getHours())}.${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
    const dateString = now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

    const placeParts = [];
    if (geoInfo?.streetName) {
        placeParts.push(`Jl. ${geoInfo.streetName}${geoInfo.streetNumber ? ' No. ' + geoInfo.streetNumber : ''}`);
    }
    if (geoInfo?.locality) placeParts.push(geoInfo.locality);
    if (geoInfo?.city && geoInfo.city !== geoInfo.locality) placeParts.push(geoInfo.city);
    if (geoInfo?.principalSubdivision) placeParts.push(geoInfo.principalSubdivision);
    if (geoInfo?.postcode) placeParts.push(geoInfo.postcode);
    const placeString = geoInfoLoading
        ? 'Mendeteksi alamat\u2026'
        : placeParts.length
          ? placeParts.join(', ')
          : 'Lokasi belum tersedia';

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

            {geoReady && (
                <div className={`mb-4 flex items-center gap-2 rounded-2xl border px-4 py-3 text-sm font-semibold ${
                    geofence.inside
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                        : 'border-rose-200 bg-rose-50 text-rose-700'
                }`}>
                    <MapPin className="h-4 w-4 shrink-0" />
                    {geofence.inside
                        ? `Dalam radius ${geofence.name} (${Math.round(geofence.distance)}m)`
                        : `Di luar radius ${geofence.name} (${Math.round(geofence.distance)}m / batas ${geofence.radius}m)`}
                </div>
            )}

            {/* Camera */}
            <Card className="overflow-hidden p-0">
                <div className="relative aspect-[3/4] w-full overflow-hidden bg-slate-900">
                    {showLive ? (
                        <>
                            <video ref={videoRef} autoPlay playsInline muted className="absolute inset-0 h-full w-full object-cover" />
                            {tileUrl && (
                                <>
                                    <img
                                        src={tileUrl}
                                        alt=""
                                        onError={() => setMapError(true)}
                                        className="pointer-events-none absolute inset-0 h-full w-full object-cover opacity-20"
                                    />
                                    <MapPin className="pointer-events-none absolute left-1/2 top-1/2 h-7 w-7 -translate-x-1/2 -translate-y-1/2 text-emerald-300 drop-shadow" />
                                </>
                            )}
                        </>
                    ) : capturedPhoto ? (
                        <img src={capturedPhoto} alt="captured" className="h-full w-full object-cover" />
                    ) : (
                                <button
                                    type="button"
                                    onClick={() => photoInputRef.current?.click()}
                                    disabled={geoBlocked}
                                    className="flex h-full w-full flex-col items-center justify-center gap-2 bg-slate-900 px-6 text-center text-slate-300 transition-colors active:bg-slate-800 disabled:opacity-40"
                                >
                            <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/10 text-slate-200">
                                <Camera className="h-6 w-6" />
                            </div>
                            <p className="text-sm font-medium">{locationError || 'Kamera tidak dapat diakses'}</p>
                            <p className="text-xs text-slate-400">Ketuk untuk mengunggah foto dari galeri</p>
                        </button>
                    )}

                    {!showLive && !capturedPhoto && (
                        <span className="absolute left-3 top-3 rounded-full bg-black/50 px-2.5 py-1 text-xs font-semibold text-white backdrop-blur">
                            <Clock className="mr-1 inline h-3 w-3" />
                            {currentTime}
                        </span>
                    )}

                    {isLembur && (
                        <span className="absolute right-3 top-3 rounded-full bg-amber-500/90 px-2.5 py-1 text-xs font-bold text-white backdrop-blur">LEMBUR</span>
                    )}

                    {currentPosition && !capturedPhoto && (
                        <span className={`absolute right-3 ${isLembur ? 'top-12' : 'top-3'} rounded-full bg-black/50 px-2.5 py-1 text-[10px] font-mono font-semibold text-white backdrop-blur`}>
                            {currentPosition.latitude.toFixed(5)}, {currentPosition.longitude.toFixed(5)}
                        </span>
                    )}

                    {/* overlay bawah 40%: info di ujung bawah-kiri, tombol di tengah */}
                    <div className="absolute inset-x-0 bottom-0 flex h-[50%] flex-col justify-end bg-gradient-to-t from-black/90 via-black/40 to-transparent">
                        {showLive && (
                            <div className="absolute bottom-3 left-3 right-1/2 pr-2">
                                <p className="text-[16px] font-extrabold leading-tight text-white">{timeString}</p>
                                <p className="text-[11px] font-semibold text-white/90">{dateString}</p>
                                <p className="mt-1 flex items-start gap-1 text-[10px] leading-tight text-white/80">
                                    <MapPin className="mt-0.5 h-3 w-3 flex-shrink-0 text-emerald-300" />
                                    <span className="line-clamp-2">{placeString}</span>
                                </p>
                            </div>
                        )}
                        <div className="mb-3 flex justify-center">
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
                                    disabled={geoBlocked}
                                    className="flex h-16 w-16 items-center justify-center rounded-full bg-white shadow-[0_8px_24px_-4px_rgba(0,0,0,0.5)] transition-transform active:scale-90 disabled:opacity-40 disabled:active:scale-100"
                                >
                                    <span className={`h-12 w-12 rounded-full ${isLembur ? 'bg-amber-500' : 'bg-emerald-500'}`} />
                                </button>
                            )}
                        </div>
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
                                <p className="mt-1 text-xs font-semibold text-emerald-500">{j.jam_mulai} \u2013 {j.jam_selesai}</p>
                            </button>
                        ))}
                    </div>
                </div>
            )}

            {/* Submit */}
            <button
                type="button"
                onClick={handleSubmit}
                disabled={isSubmitting}
                className={`mt-5 w-full rounded-2xl bg-gradient-to-br ${isLembur ? 'from-amber-500 to-orange-500 shadow-[0_10px_24px_-6px_rgba(245,158,11,0.6)]' : 'from-emerald-500 to-primary shadow-[0_10px_24px_-6px_rgba(79,70,229,0.6)]'} py-4 font-extrabold text-white transition-transform active:scale-[0.98] disabled:opacity-60`}
            >
                {isSubmitting ? <><Loader2 className="mr-2 inline h-5 w-5 animate-spin" />Memproses\u2026</> : isLembur ? 'Kirim Lembur' : 'Kirim Presensi'}
            </button>

            <canvas ref={canvasRef} className="hidden" />
        </MobileLayout>
    );
}
