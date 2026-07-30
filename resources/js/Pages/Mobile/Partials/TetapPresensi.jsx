import { useState, useRef, useEffect, useMemo, useCallback } from 'react';
import { checkGeofence } from '@/Utils/geo';
import { MAP_TILE_URL, MAP_ATTRIBUTION } from '@/Constants/AppConstants';
import { Card, Toggle, Empty } from '@/Components/MobileUI';
import SlideToConfirm from '@/Components/SlideToConfirm';
import { Camera, RefreshCw, MapPin, CheckCircle, AlertCircle, Loader2, LocateFixed, ShieldCheck, Clock } from 'lucide-react';

const FOTO_PAGI = 'foto_pagi';
const TAP_JADWAL = 'tap_jadwal';
const FOTO_SORE = 'foto_sore';
const SELESAI = 'selesai';

const pad = (n) => String(n).padStart(2, '0');

export default function TetapPresensi({ pegawai, jadwals, presensiHariIni }) {
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [loadingLocation, setLoadingLocation] = useState(false);
    const [locationError, setLocationError] = useState(null);
    const [showLive, setShowLive] = useState(false);
    const [error, setError] = useState(null);
    const [successMessage, setSuccessMessage] = useState(null);
    const [capturedPhoto, setCapturedPhoto] = useState(null);
    const [currentTime, setCurrentTime] = useState('');
    const [geoStatus, setGeoStatus] = useState('idle');
    const [currentPosition, setCurrentPosition] = useState(null);
    const [geoInfo, setGeoInfo] = useState(null);
    const [geoInfoLoading, setGeoInfoLoading] = useState(false);
    const [posA, setPosA] = useState(null);
    const [tappedIds, setTappedIds] = useState(() => new Set(
        presensiHariIni.filter((p) => p.jadwal_id).map((p) => p.jadwal_id)
    ));
    const [tapLoading, setTapLoading] = useState(null);

    const videoRef = useRef(null);
    const canvasRef = useRef(null);
    const streamRef = useRef(null);
    const photoInputRef = useRef(null);
    const geoControllerRef = useRef(null);
    const watchIdRef = useRef(null);
    const geocodedRef = useRef(false);
    const errorRef = useRef(null);

    const pagiRecord = useMemo(() => presensiHariIni.find((p) => p.jadwal_id === null && !p.is_lembur), [presensiHariIni]);
    const now = new Date();
    const jamSekarang = now.getHours() * 60 + now.getMinutes();
    const jamSore = 960;
    const toMinutes = (hms) => { if (!hms) return 0; const p = String(hms).split(':'); return parseInt(p[0], 10) * 60 + parseInt(p[1] || 0, 10); };

    const activeJadwals = useMemo(() => jadwals.filter((j) => tappedIds.has(j.id) || toMinutes(j.jam_mulai) <= jamSekarang), [jadwals, tappedIds, jamSekarang]);
    const allActiveTapped = activeJadwals.length > 0 && activeJadwals.every((j) => tappedIds.has(j.id));
    const untappedActive = activeJadwals.filter((j) => !tappedIds.has(j.id));

    const phase = useMemo(() => {
        if (!pagiRecord) return FOTO_PAGI;
        if (pagiRecord.jam_keluar) return SELESAI;
        if (allActiveTapped || jamSekarang >= jamSore) return FOTO_SORE;
        return TAP_JADWAL;
    }, [pagiRecord, allActiveTapped, jamSekarang]);

    useEffect(() => {
        if (error) errorRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, [error]);

    useEffect(() => {
        if (phase === FOTO_PAGI || phase === FOTO_SORE) {
            startCamera();
            setCurrentTime(new Date().toLocaleTimeString('id-ID', { hour12: false }));
            const clock = setInterval(() => {
                setCurrentTime(new Date().toLocaleTimeString('id-ID', { hour12: false }));
            }, 1000);
            getCurrentPosition();
            return () => {
                clearInterval(clock);
                if (streamRef.current) {
                    streamRef.current.getTracks().forEach((t) => t.stop());
                    streamRef.current = null;
                }
                if (watchIdRef.current !== null) navigator.geolocation.clearWatch(watchIdRef.current);
                if (geoControllerRef.current) geoControllerRef.current.abort();
            };
        }
        return () => {};
    }, [phase]);

    useEffect(() => {
        if (showLive && videoRef.current && streamRef.current) {
            videoRef.current.srcObject = streamRef.current;
            videoRef.current.play().catch(() => {});
        }
    }, [showLive]);

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
            if (!locationError) setLocationError('Kamera tidak dapat diakses. Gunakan tombol di bawah untuk unggah foto.');
        }
    }, [locationError]);

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
            let cropWidth = sourceWidth, cropHeight = sourceHeight, cropX = 0, cropY = 0;
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
            const cvs = canvasRef.current;
            cvs.width = width;
            cvs.height = height;
            cvs.getContext('2d').drawImage(source, cropX, cropY, cropWidth, cropHeight, 0, 0, width, height);
            resolve(cvs.toDataURL('image/jpeg', 0.84));
        });
    }, []);

    const capturePhoto = useCallback(async () => {
        if (!currentPosition) return;
        if (!videoRef.current || !canvasRef.current) return;
        const video = videoRef.current;
        if (!video.videoWidth || !video.videoHeight) return;

        setPosA({ ...currentPosition, captured_at: new Date().toISOString() });
        setIsSubmitting(true);
        const dataUrl = await createEvidencePhoto(video, video.videoWidth, video.videoHeight);
        setCapturedPhoto(dataUrl);
        stopCamera();
        setShowLive(false);
        setIsSubmitting(false);
    }, [currentPosition, createEvidencePhoto, stopCamera]);

    const retakePhoto = useCallback(() => {
        setCapturedPhoto(null);
        startCamera();
    }, [startCamera]);

    const getCurrentPosition = useCallback(() => {
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
                if (geocodedRef.current) return;
                geocodedRef.current = true;
                setGeoInfoLoading(true);
                geoControllerRef.current?.abort();
                geoControllerRef.current = new AbortController();
                fetch(
                    `https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${pos.coords.latitude}&longitude=${pos.coords.longitude}&localityLanguage=id`,
                    { signal: geoControllerRef.current.signal }
                )
                    .then((r) => { if (!r.ok) throw new Error('Geocoding failed'); return r.json(); })
                    .then((d) => setGeoInfo({
                        locality: d.locality, city: d.city, principalSubdivision: d.principalSubdivision,
                        postcode: d.postcode, countryName: d.countryName, streetName: d.streetName, streetNumber: d.streetNumber,
                    }))
                    .catch((err) => { if (err.name !== 'AbortError') setGeoInfo(null); })
                    .finally(() => setGeoInfoLoading(false));
            },
            (err) => {
                setGeoStatus('error');
                setLocationError(err.message || 'Tidak dapat mengambil lokasi.');
                setLoadingLocation(false);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
        watchIdRef.current = id;
    }, []);

    const handleFileFallback = useCallback((e) => {
        if (!currentPosition) return;
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onloadend = () => {
            const image = new Image();
            image.onload = async () => {
                setIsSubmitting(true);
                const dataUrl = await createEvidencePhoto(image, image.naturalWidth, image.naturalHeight);
                setCapturedPhoto(dataUrl);
                setIsSubmitting(false);
            };
            image.onerror = () => setError('Foto tidak dapat diproses. Pilih file gambar lain.');
            image.src = reader.result;
        };
        reader.readAsDataURL(file);
    }, [currentPosition, createEvidencePhoto]);

    const lemburUnit = pegawai?.units?.find((u) => u.pivot?.is_primary) ?? pegawai?.units?.[0] ?? null;

    const geofence = useMemo(() => {
        if (!currentPosition || !lemburUnit) return null;
        const lat = parseFloat(lemburUnit.latitude);
        const lon = parseFloat(lemburUnit.longitude);
        if (isNaN(lat) || isNaN(lon)) return null;
        const radius = lemburUnit.radius_meter ?? 50;
        const { inside, distance } = checkGeofence(currentPosition.latitude, currentPosition.longitude, lat, lon, radius);
        return { name: lemburUnit.nama_unit || lemburUnit.nama || 'Unit Sekolah', distance, radius, inside };
    }, [currentPosition, lemburUnit]);

    const geoBlocked = geofence && !geofence.inside;
    const geoReady = geofence !== null;

    const handleSubmitFoto = useCallback(async (tipe) => {
        setError(null);
        setSuccessMessage(null);

        if (!capturedPhoto) { setError('Silakan ambil foto terlebih dahulu.'); return; }
        if (!currentPosition) { setError('Lokasi belum tersedia. Pastikan GPS aktif.'); return; }
        if (geoBlocked) { setError(`Anda di luar radius ${geofence.name} (${Math.round(geofence.distance)}m / batas ${geofence.radius}m).`); return; }
        if (tipe === 'keluar' && untappedActive.length > 0) {
            if (!confirm(`Ada ${untappedActive.length} jadwal aktif belum di-tap. Lanjutkan foto sore?`)) return;
        }

        setIsSubmitting(true);
        const now = new Date().toISOString();
        const payload = {
            _token: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            foto: capturedPhoto,
            tipe,
            latitude: currentPosition?.latitude ?? null,
            longitude: currentPosition?.longitude ?? null,
            accuracy: currentPosition?.accuracy ?? null,
            mock_suspect: currentPosition?.accuracy === 0,
            captured_at: now,
            pos_a_lat: posA?.latitude ?? null,
            pos_a_lng: posA?.longitude ?? null,
            pos_a_accuracy: posA?.accuracy ?? null,
            pos_a_captured_at: posA?.captured_at ?? null,
        };

        try {
            const res = await fetch(route('presensi.absen.tetap'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });

            if (!res.ok) {
                if (res.status === 419) throw { type: 'session_expired' };
                const data = await res.json().catch(() => ({}));
                const errMap = { 413: 'Foto terlalu besar.', 422: data.message || 'Data tidak valid.', 429: 'Terlalu banyak permintaan. Tunggu.', 500: 'Server error.' };
                setError(errMap[res.status] || data.message || 'Gagal.');
                return;
            }

            const contentType = res.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                setError('Server merespon halaman HTML. Refresh halaman.');
                return;
            }

            const data = await res.json();
            if (data.success) {
                setSuccessMessage(data.message || `${tipe === 'masuk' ? 'Foto pagi' : 'Foto sore'} berhasil.`);
                setCapturedPhoto(null);
                if (tipe === 'keluar') {
                    setTimeout(() => { if (typeof window !== 'undefined') window.location.assign(route('presensi.dashboard')); }, 1500);
                } else {
                    setTimeout(() => { if (typeof window !== 'undefined') window.location.reload(); }, 1500);
                }
            } else {
                setError(data.message || 'Gagal.');
            }
        } catch (err) {
            if (err.type === 'session_expired') setError('Sesi habis. Refresh dan login ulang.');
            else if (err.name === 'AbortError') setError('Permintaan dibatalkan.');
            else if (err instanceof TypeError && err.message === 'Failed to fetch') setError('Koneksi terputus.');
            else { console.error('[TetapPresensi]', err); setError('Tidak dapat terhubung ke server.'); }
        } finally {
            setIsSubmitting(false);
        }
    }, [capturedPhoto, currentPosition, geoBlocked, geofence, posA]);

    const handleTap = useCallback(async (jadwalId) => {
        setTapLoading(jadwalId);
        setError(null);
        setSuccessMessage(null);

        setTappedIds((prev) => new Set(prev).add(jadwalId));
        setSuccessMessage(null);

        try {
            const res = await fetch(route('presensi.absen.tap'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    _token: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    jadwal_id: jadwalId,
                }),
            });

            if (!res.ok) {
                if (res.status === 419) throw { type: 'session_expired' };
                const data = await res.json().catch(() => ({}));
                setTappedIds((prev) => { const next = new Set(prev); next.delete(jadwalId); return next; });
                setError(data.message || 'Gagal tap jadwal.');
                return;
            }

            const data = await res.json();
            if (data.success) {
                setSuccessMessage(data.message || 'Kehadiran tercatat.');
                setTimeout(() => setSuccessMessage(null), 2000);
            } else {
                setTappedIds((prev) => { const next = new Set(prev); next.delete(jadwalId); return next; });
                setError(data.message || 'Gagal.');
            }
        } catch (err) {
            setTappedIds((prev) => { const next = new Set(prev); next.delete(jadwalId); return next; });
            if (err.type === 'session_expired') setError('Sesi habis.');
            else setError('Gagal terhubung ke server.');
        } finally {
            setTapLoading(null);
        }
    }, []);

    const timeString = `${pad(now.getHours())}.${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
    const dateString = now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

    const placeParts = [];
    if (geoInfo?.streetName) placeParts.push(`Jl. ${geoInfo.streetName}${geoInfo.streetNumber ? ' No. ' + geoInfo.streetNumber : ''}`);
    if (geoInfo?.locality) placeParts.push(geoInfo.locality);
    if (geoInfo?.city && geoInfo.city !== geoInfo.locality) placeParts.push(geoInfo.city);
    if (geoInfo?.principalSubdivision) placeParts.push(geoInfo.principalSubdivision);
    if (geoInfo?.postcode) placeParts.push(geoInfo.postcode);
    const placeString = geoInfoLoading ? 'Mendeteksi alamat\u2026' : placeParts.length ? placeParts.join(', ') : 'Lokasi belum tersedia';

    const mapTileUrl = currentPosition ? (() => {
        const zoom = 16, tiles = 2 ** zoom;
        const x = Math.floor(((currentPosition.longitude + 180) / 360) * tiles);
        const latitude = (currentPosition.latitude * Math.PI) / 180;
        const y = Math.floor(((1 - Math.log(Math.tan(latitude) + 1 / Math.cos(latitude)) / Math.PI) / 2) * tiles);
        return MAP_TILE_URL.replace('{z}', String(zoom)).replace('{x}', String(x)).replace('{y}', String(y));
    })() : null;

    const faseLabel = phase === FOTO_PAGI ? 'Foto Pagi' : phase === TAP_JADWAL ? 'Tap Jadwal' : phase === FOTO_SORE ? 'Foto Sore' : 'Selesai';

    return (
        <>
            {successMessage && (
                <div role="status" className="mb-4 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                    <CheckCircle className="h-5 w-5" /> {successMessage}
                </div>
            )}
            {error && (
                <div ref={errorRef} role="alert" className="mb-4 flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">
                    <AlertCircle className="h-5 w-5" /> {error}
                </div>
            )}

            <div className="mb-4">
                <div className="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                    <Clock className="h-5 w-5 shrink-0 text-emerald-600" />
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Fase</p>
                        <p className="text-sm font-bold text-emerald-900">{faseLabel}</p>
                    </div>
                </div>
            </div>

            {/* Jadwal section - always visible (hanya yg sudah waktunya) */}
            {jadwals.length > 0 && (
                <section aria-labelledby="jadwal-heading" className="mb-4">
                    <div className="mb-2 flex items-center justify-between">
                        <h2 id="jadwal-heading" className="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Agenda Mengajar</h2>
                        <span className="text-xs text-slate-500">{tappedIds.size} dari {activeJadwals.length} sudah di-tap</span>
                    </div>
                    {phase === FOTO_SORE && untappedActive.length > 0 && (
                        <div className="mb-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">
                            ⚠ {untappedActive.length} jadwal aktif belum di-tap
                        </div>
                    )}
                    <div className="space-y-2">
                        {activeJadwals.map((j) => {
                            const done = tappedIds.has(j.id);
                            return (
                                <div key={j.id} className={`rounded-xl border p-3 ${done ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white'}`}>
                                    <div className="mb-2 flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="text-sm font-bold text-slate-900">{j.mata_pelajaran?.nama || 'Jadwal'}</p>
                                            <p className="mt-0.5 text-xs text-slate-500">{j.kelas_label || ''}</p>
                                        </div>
                                        <span className="shrink-0 font-mono text-xs font-bold tabular-nums text-primary">{j.jam_mulai?.slice(0, 5)}</span>
                                    </div>
                                    {phase === TAP_JADWAL ? (
                                        <SlideToConfirm
                                            onConfirm={() => handleTap(j.id)}
                                            disabled={done || tapLoading !== null}
                                            confirmed={done}
                                            label={done ? 'Sudah di-tap' : `Tap ${j.mata_pelajaran?.nama || 'jadwal'}`}
                                        />
                                    ) : done && (
                                        <div className="flex items-center gap-2 text-xs font-semibold text-emerald-700">
                                            <CheckCircle className="h-4 w-4" /> Sudah di-tap
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </section>
            )}

            {(phase === FOTO_PAGI || phase === FOTO_SORE) && (
                <>
                    <div className="mb-4 grid grid-cols-2 gap-2" aria-label="Status verifikasi">
                        <div className={`flex min-h-14 items-center gap-2.5 rounded-xl border px-3 py-2.5 ${geoReady && geofence.inside ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : geoBlocked ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-slate-200 bg-white text-slate-600'}`}>
                            {loadingLocation ? <Loader2 className="h-5 w-5 shrink-0 animate-spin" /> : <LocateFixed className="h-5 w-5 shrink-0" />}
                            <div className="min-w-0">
                                <p className="text-[10px] font-bold uppercase tracking-wider">{phase === FOTO_PAGI ? 'GPS PAGI' : 'GPS SORE'}</p>
                                <p className="truncate text-xs font-semibold">{geoReady ? `${Math.round(geofence.distance)} m dari unit` : geoStatus === 'error' ? 'Tidak tersedia' : 'Mendeteksi...'}</p>
                            </div>
                        </div>
                        <div className={`flex min-h-14 items-center gap-2.5 rounded-xl border px-3 py-2.5 ${capturedPhoto ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-white text-slate-600'}`}>
                            <ShieldCheck className="h-5 w-5 shrink-0" />
                            <div>
                                <p className="text-[10px] font-bold uppercase tracking-wider">Bukti foto</p>
                                <p className="text-xs font-semibold">{capturedPhoto ? 'Siap dikirim' : 'Belum diambil'}</p>
                            </div>
                        </div>
                    </div>

                    {/* Camera */}
                    <Card press={false} className="overflow-hidden border-slate-300 p-0">
                        <div className={`relative w-full overflow-hidden ${capturedPhoto && !showLive ? 'bg-transparent' : 'bg-slate-950'} ${showLive ? 'aspect-[3/4]' : capturedPhoto ? '' : 'aspect-[4/5]'}`}>
                            {showLive ? (
                                <>
                                    <video ref={videoRef} autoPlay playsInline muted className="absolute inset-0 h-full w-full object-cover" />
                                    <div className="pointer-events-none absolute inset-8 rounded-[42%] border border-white/35" />
                                </>
                            ) : capturedPhoto ? (
                                <>
                                    <img src={capturedPhoto} alt="Pratinjau foto" className="block w-full" />
                                    {currentPosition && (
                                        <span className="absolute right-2 top-2 rounded-lg bg-black/65 px-2.5 py-1.5 font-mono text-[10px] font-bold tabular-nums text-white">
                                            {currentPosition.latitude.toFixed(5)}, {currentPosition.longitude.toFixed(5)}
                                        </span>
                                    )}
                                </>
                            ) : (
                                <button
                                    type="button"
                                    onClick={() => photoInputRef.current?.click()}
                                    disabled={!currentPosition}
                                    className="flex h-full w-full flex-col items-center justify-center gap-2 bg-slate-950 px-6 text-center text-slate-300 transition-colors active:bg-slate-900 disabled:opacity-40"
                                >
                                    <div className="flex h-14 w-14 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-slate-200">
                                        <Camera className="h-6 w-6" />
                                    </div>
                                    <p className="text-sm font-medium">{locationError || 'Kamera tidak dapat diakses'}</p>
                                    <p className="text-xs text-slate-400">Ketuk untuk mengunggah foto dari galeri</p>
                                </button>
                            )}

                            {!capturedPhoto && (
                                <span className="absolute left-3 top-3 rounded-lg bg-black/65 px-2.5 py-1.5 font-mono text-xs font-bold tabular-nums text-white">{currentTime}</span>
                            )}

                            {phase === FOTO_SORE && !capturedPhoto && (
                                <span className="absolute right-3 top-3 rounded-lg bg-blue-500 px-2.5 py-1.5 text-xs font-bold text-white">FOTO SORE</span>
                            )}

                            {currentPosition && !capturedPhoto && (
                                <span className={`absolute right-3 ${phase === FOTO_SORE ? 'top-12' : 'top-3'} rounded-lg bg-black/65 px-2.5 py-1.5 text-[10px] font-mono font-semibold text-white`}>
                                    {currentPosition.latitude.toFixed(5)}, {currentPosition.longitude.toFixed(5)}
                                </span>
                            )}

                            {!capturedPhoto && (
                                <div className="absolute inset-x-0 bottom-0 flex h-[50%] flex-col justify-end bg-gradient-to-t from-black/90 via-black/40 to-transparent">
                                    {showLive && (
                                        <div className="absolute bottom-4 left-4 right-1/2 pr-3">
                                            <p className="font-mono text-lg font-bold leading-tight tabular-nums text-white">{timeString}</p>
                                            <p className="text-[11px] font-semibold text-white/90">{dateString}</p>
                                            <p className="mt-1 flex items-start gap-1 text-[10px] leading-tight text-white/80">
                                                <MapPin className="mt-0.5 h-3 w-3 flex-shrink-0 text-emerald-300" />
                                                <span className="line-clamp-2">{placeString}</span>
                                            </p>
                                        </div>
                                    )}
                                    <div className="mb-4 flex justify-center">
                                        <button
                                            type="button"
                                            onClick={capturePhoto}
                                            disabled={!currentPosition}
                                            aria-label={`Ambil foto ${phase === FOTO_PAGI ? 'pagi' : 'sore'}`}
                                            className={`flex h-[72px] w-[72px] items-center justify-center rounded-full border-4 border-white bg-transparent transition-transform active:scale-95 disabled:opacity-40 disabled:active:scale-100`}
                                        >
                                            <span className="h-14 w-14 rounded-full bg-emerald-500" />
                                        </button>
                                    </div>
                                    {showLive && !currentPosition && (
                                        <p className="mb-3 text-center text-[10px] font-semibold text-white/80">Menunggu koordinat GPS...</p>
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Map */}
                        {showLive && currentPosition && (
                            <div className="relative h-48 overflow-hidden border-t border-slate-200 bg-slate-100">
                                {mapTileUrl && (
                                    <img
                                        src={mapTileUrl} alt="Peta lokasi" width="512" height="256" loading="eager"
                                        onError={(e) => { e.currentTarget.style.display = 'none'; }}
                                        className="absolute inset-0 h-full w-full object-cover opacity-50"
                                    />
                                )}
                                <div className="absolute inset-0 bg-white/30" />
                                <div className="absolute left-1/2 top-1/2 flex -translate-x-1/2 -translate-y-1/2 items-center justify-center">
                                    <span className="absolute h-12 w-12 rounded-full bg-emerald-400/25" />
                                    <MapPin className="relative h-8 w-8 fill-primary text-white drop-shadow-lg" />
                                </div>
                                <div className="absolute inset-x-3 bottom-3 flex items-end justify-between gap-3">
                                    <div className="rounded-lg bg-white/90 px-3 py-2 text-slate-900 shadow-sm backdrop-blur-sm">
                                        <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-primary">Lokasi presensi</p>
                                        <p className="mt-1 text-xs font-semibold">{lemburUnit?.nama || lemburUnit?.nama_unit || 'Unit'}</p>
                                        <p className="mt-1 font-mono text-[10px] tabular-nums text-slate-500">{currentPosition.latitude.toFixed(6)}, {currentPosition.longitude.toFixed(6)}</p>
                                    </div>
                                    <div className={`rounded-lg px-2.5 py-2 text-[10px] font-bold ${geoReady && geofence.inside ? 'bg-emerald-400 text-emerald-950' : 'bg-rose-400 text-rose-950'}`}>
                                        {geoReady && geofence.inside ? 'Dalam radius' : 'Di luar radius'}
                                    </div>
                                </div>
                                <span className="absolute right-2 top-2 rounded bg-white/70 px-1.5 py-1 text-[8px] font-semibold text-slate-500" dangerouslySetInnerHTML={{ __html: MAP_ATTRIBUTION }}></span>
                            </div>
                        )}
                    </Card>

                    {capturedPhoto && currentPosition && (
                        <div className="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
                            <div className="relative h-48">
                                {mapTileUrl && (
                                    <img
                                        src={mapTileUrl} alt="Peta lokasi" width="512" height="256" loading="lazy"
                                        onError={(e) => { e.currentTarget.style.display = 'none'; }}
                                        className="absolute inset-0 h-full w-full object-cover opacity-60"
                                    />
                                )}
                                <div className="absolute inset-0 bg-white/20" />
                                <div className="absolute left-1/2 top-1/2 flex -translate-x-1/2 -translate-y-1/2 items-center justify-center">
                                    <span className="absolute h-14 w-14 rounded-full bg-emerald-400/25" />
                                    <MapPin className="relative h-8 w-8 fill-primary text-white drop-shadow-lg" />
                                </div>
                                <div className="absolute inset-x-3 bottom-3 flex items-end justify-between gap-3">
                                    <div className="rounded-lg bg-white/90 px-3 py-2 text-slate-900 shadow-sm backdrop-blur-sm">
                                        <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-primary">Lokasi presensi</p>
                                        <p className="mt-1 text-xs font-semibold">{lemburUnit?.nama || lemburUnit?.nama_unit || 'Unit'}</p>
                                        <p className="mt-1 font-mono text-[10px] tabular-nums text-slate-500">{currentPosition.latitude.toFixed(6)}, {currentPosition.longitude.toFixed(6)}</p>
                                    </div>
                                    <div className={`rounded-lg px-2.5 py-2 text-[10px] font-bold ${geoReady && geofence.inside ? 'bg-emerald-400 text-emerald-950' : 'bg-rose-400 text-rose-950'}`}>
                                        {geoReady && geofence.inside ? 'Dalam radius' : 'Di luar radius'}
                                    </div>
                                </div>
                                <span className="absolute right-2 top-2 rounded bg-white/70 px-1.5 py-1 text-[8px] font-semibold text-slate-500" dangerouslySetInnerHTML={{ __html: MAP_ATTRIBUTION }}></span>
                            </div>

                            {posA && (
                                <div className="border-t border-slate-200 bg-slate-50 px-4 py-3">
                                    <div className="flex items-center justify-between gap-4">
                                        <div className="min-w-0">
                                            <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">Posisi A (saat foto)</p>
                                            <p className="mt-0.5 font-mono text-xs tabular-nums text-slate-700">{posA.latitude.toFixed(6)}, {posA.longitude.toFixed(6)}</p>
                                            <p className="text-[10px] text-slate-400">Akurasi {posA.accuracy?.toFixed(0)}m</p>
                                        </div>
                                        <div className="shrink-0 text-right">
                                            <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">Posisi B (submit)</p>
                                            <p className="mt-0.5 font-mono text-xs tabular-nums text-slate-700">{currentPosition.latitude.toFixed(6)}, {currentPosition.longitude.toFixed(6)}</p>
                                            <p className="text-[10px] text-slate-400">Akurasi {currentPosition.accuracy?.toFixed(0)}m</p>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {capturedPhoto && (
                        <div className="mt-3 flex justify-center">
                            <button
                                type="button"
                                onClick={retakePhoto}
                                className="flex min-h-12 items-center gap-2 rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-900 shadow-sm transition-transform active:scale-95"
                            >
                                <RefreshCw className="h-4 w-4" /> Ambil Ulang
                            </button>
                        </div>
                    )}

                    <input type="file" accept="image/*" capture="environment" ref={photoInputRef} className="hidden" onChange={handleFileFallback} />

                    <button
                        type="button"
                        onClick={() => handleSubmitFoto(phase === FOTO_PAGI ? 'masuk' : 'keluar')}
                        disabled={isSubmitting || !capturedPhoto || !currentPosition || !geofence?.inside}
                        className="mt-4 flex min-h-14 w-full items-center justify-center rounded-xl bg-primary px-5 py-4 text-sm font-bold text-white transition-transform active:scale-[0.99] disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500"
                    >
                        {isSubmitting ? <><Loader2 className="mr-2 h-5 w-5 animate-spin" />Memproses...</> : `Kirim ${phase === FOTO_PAGI ? 'foto pagi' : 'foto sore'}`}
                    </button>
                    <p className="mt-2 text-center text-[11px] leading-relaxed text-slate-500">Foto, waktu, dan koordinat dikirim sebagai bukti presensi.</p>
                </>
            )}

            {activeJadwals.length === 0 && jadwals.length > 0 && (
                <Empty icon={Clock} title="Jadwal hari ini belum mulai" subtitle="Jadwal aktif akan muncul setelah jam mengajar tiba." />
            )}
            {jadwals.length === 0 && (
                <Empty icon={Clock} title="Tidak ada jadwal hari ini" subtitle="Jika tidak ada jadwal, lanjut ke foto sore." />
            )}

            {phase === SELESAI && (
                <div className="flex flex-col items-center py-12 text-center">
                    <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600">
                        <CheckCircle className="h-8 w-8" />
                    </div>
                    <h2 className="text-lg font-bold text-slate-900">Presensi hari ini selesai</h2>
                    <p className="mt-1 text-sm text-slate-500">Foto pagi, tap jadwal, dan foto sore sudah lengkap.</p>
                </div>
            )}

            <canvas ref={canvasRef} className="hidden" />
        </>
    );
}
