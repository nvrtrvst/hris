import { useState, useRef, useEffect, useMemo } from 'react';
import { checkGeofence } from '@/Utils/geo';
import { MAP_TILE_URL, MAP_ATTRIBUTION } from '@/Constants/AppConstants';
import { Head, usePage } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card, Toggle } from '@/Components/MobileUI';
import TetapPresensi from '@/Pages/Mobile/Partials/TetapPresensi';
import BuktiKegiatan from '@/Pages/Mobile/Partials/BuktiKegiatan';
import { useCamera } from '@/Hooks/useCamera';
import { useGeolocation } from '@/Hooks/useGeolocation';
import { useMotionSamples } from '@/Hooks/useMotionSamples';
import { Camera, RefreshCw, MapPin, CheckCircle, AlertCircle, Loader2, LocateFixed, ShieldCheck } from 'lucide-react';

export default function Absen({ auth, pegawai, jadwals, presensiHariIni, officeAttendance = false, izinHariIni = null }) {
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [successMessage, setSuccessMessage] = useState(null);
    const [isLembur, setIsLembur] = useState(false);
    const [isTugasLuar, setIsTugasLuar] = useState(false);
    const [tujuan, setTujuan] = useState('');
    const [jadwalId, setJadwalId] = useState(null);
    const [currentTime, setCurrentTime] = useState('');
    const [posA, setPosA] = useState(null);
    const [posAwal, setPosAwal] = useState(null);

    const sedangIzin = Boolean(izinHariIni);
    const izinLabel = izinHariIni ? ({ izin: 'Izin', cuti: 'Cuti', sakit: 'Sakit' }[izinHariIni.jenis_izin] || 'Izin') : null;

    const renderIzinBlock = () => (
        <MobileLayout user={auth.user}>
            <Head title="Presensi" />
            <div className="mb-5">
                <p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Presensi harian</p>
                <h1 className="mt-1 text-2xl font-bold tracking-tight text-slate-950">Verifikasi kehadiran</h1>
            </div>
            <div role="status" className="flex flex-col items-center gap-3 rounded-2xl border border-sky-200 bg-sky-50 px-5 py-8 text-center">
                <ShieldCheck className="h-10 w-10 text-sky-500" />
                <p className="text-base font-bold text-sky-900">Presensi dinonaktifkan</p>
                <p className="max-w-xs text-sm leading-relaxed text-sky-800">
                    Anda sedang {izinLabel} hari ini ({format(new Date(izinHariIni.tanggal_mulai), 'd MMM', { locale: id })} – {format(new Date(izinHariIni.tanggal_selesai), 'd MMM', { locale: id })}).
                    Kehadiran otomatis tercatat sebagai {izinLabel.toLowerCase()}.
                </p>
            </div>
        </MobileLayout>
    );

    const errorRef = useRef(null);

    const {
        loadingLocation,
        geoStatus,
        currentPosition,
        geoInfo,
        geoInfoLoading,
        locationError: geoLocationError,
        getCurrentPosition,
        clearGeolocation,
    } = useGeolocation();

    // Unit geofence target: lembur -> unit primer; reguler -> unit jadwal terpilih (atau primer).
    const lemburUnit =
        pegawai?.units?.find((u) => u.pivot?.is_primary) ?? pegawai?.units?.[0] ?? null;
    const selectedJadwal = jadwals.find((j) => j.id === jadwalId);
    const targetUnit = isTugasLuar || isLembur
        ? lemburUnit
        : selectedJadwal?.unit_sekolah ?? lemburUnit;

    const isTeaching = !isLembur && !officeAttendance && Boolean(jadwalId);
    const kantorRecord = officeAttendance
        ? (presensiHariIni || []).find((p) => !p.jadwal_id && !p.is_lembur)
        : null;
    const teachingRecord = isTeaching
        ? (presensiHariIni || []).find((p) => p.jadwal_id === jadwalId)
        : null;
    const teachingDone = Boolean(teachingRecord?.jam_masuk);
    const teachingOpen = Boolean(teachingRecord?.jam_masuk && !teachingRecord?.jam_keluar);
    const kantorOpen = Boolean(kantorRecord?.jam_masuk && !kantorRecord?.jam_keluar);
    const lemburOpen = Boolean(
        (presensiHariIni || []).find((p) => p.is_lembur && p.jam_masuk && !p.jam_keluar)
    );
    const tugasLuarOpen = Boolean(
        (presensiHariIni || []).find((p) => p.is_tugas_luar && p.jam_masuk && !p.jam_keluar)
    );
    const tugasLuarRecord = (presensiHariIni || []).find((p) => p.is_tugas_luar && p.jam_masuk);

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

    const geoBlocked = geofence && !geofence.inside && !isTugasLuar;
    const geoReady = geofence !== null;

    const camera = useCamera({
        canCapture: Boolean(currentPosition && (geofence?.inside || isTugasLuar)),
        currentPosition,
        onWillCapture: (pos) => setPosA(pos),
    });

    const {
        videoRef,
        canvasRef,
        streamRef,
        showLive,
        capturedPhoto,
        cameraError,
        isCapturing,
        startCamera,
        capturePhoto,
        retakePhoto,
        clearCamera,
    } = camera;

    // Tampilkan error gabungan (kamera / GPS) di fallback upload.
    const locationError = cameraError || geoLocationError;

    useEffect(() => {
        if (error) {
            errorRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, [error]);

    const { flash = {}, attestation_token } = usePage().props;

    const { motionSamples, samplesReady, collectMotionSamples } = useMotionSamples();

    // Capture posisi awal (first GPS fix) untuk trajectory 3-titik
    useEffect(() => {
        if (currentPosition && !posAwal) {
            setPosAwal({
                latitude: currentPosition.latitude,
                longitude: currentPosition.longitude,
                accuracy: currentPosition.accuracy,
                captured_at: new Date().toISOString(),
            });
        }
    }, [currentPosition, posAwal]);

    useEffect(() => {
        if (sedangIzin) return;
        startCamera();
        setCurrentTime(new Date().toLocaleTimeString('id-ID', { hour12: false }));
        const clock = setInterval(() => {
            const now = new Date();
            setCurrentTime(now.toLocaleTimeString('id-ID', { hour12: false }));
        }, 1000);
        getCurrentPosition();
        return () => {
            clearInterval(clock);
            clearCamera();
            clearGeolocation();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (showLive && videoRef.current && streamRef.current) {
            videoRef.current.srcObject = streamRef.current;
            videoRef.current.play().catch(() => { });
        }
    }, [showLive]);

    const pad = (n) => String(n).padStart(2, '0');

    const toggleLembur = () => {
        setIsLembur((prev) => !prev);
        setIsTugasLuar(false);
    };

    const toggleTugasLuar = () => {
        setIsTugasLuar((prev) => !prev);
        if (!isTugasLuar) {
            setIsLembur(false);
            setJadwalId(null);
        }
    };

    const selectJadwal = (id) => setJadwalId(id);

    // Trigger motion sampling saat foto diambil
    const handleCaptureWithMotion = () => {
        collectMotionSamples();
        capturePhoto();
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError(null);
        setSuccessMessage(null);

        if (!capturedPhoto) {
            setError('Silakan ambil foto presensi terlebih dahulu.');
            return;
        }
        if (!isLembur && !isTugasLuar && !officeAttendance && jadwals.length > 0 && !jadwalId) {
            setError('Silakan pilih jadwal presensi Anda.');
            return;
        }
        if (isTeaching && teachingRecord?.jam_masuk && teachingRecord?.jam_keluar) {
            setError('Anda sudah melakukan presensi lengkap untuk jadwal ini.');
            return;
        }
        if (!isLembur && officeAttendance && kantorRecord?.jam_keluar) {
            setError('Anda sudah melakukan presensi keluar.');
            return;
        }
        if (isTugasLuar && !tugasLuarOpen && !tujuan.trim()) {
            setError('Tujuan tugas luar wajib diisi.');
            return;
        }
        if (!currentPosition && !isLembur && !isTugasLuar) {
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
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const now = new Date().toISOString();
        const payload = {
            _token: token,
            foto: capturedPhoto,
            is_lembur: isLembur,
            is_tugas_luar: isTugasLuar,
            tujuan: isTugasLuar ? tujuan : null,
            keterangan: null,
            jadwal_id: isLembur || isTugasLuar ? null : jadwalId,
            tipe: isTeaching ? (teachingOpen ? 'keluar' : 'masuk') : isLembur ? (lemburOpen ? 'keluar' : 'masuk') : isTugasLuar ? (tugasLuarOpen ? 'keluar' : 'masuk') : (kantorOpen ? 'keluar' : 'masuk'),
            latitude: currentPosition?.latitude ?? null,
            longitude: currentPosition?.longitude ?? null,
            accuracy: currentPosition?.accuracy ?? null,
            mock_suspect: currentPosition?.accuracy === 0,
            captured_at: now,
            pos_a_lat: posA?.latitude ?? null,
            pos_a_lng: posA?.longitude ?? null,
            pos_a_accuracy: posA?.accuracy ?? null,
            pos_a_captured_at: posA?.captured_at ?? null,
            pos_awal_lat: posAwal?.latitude ?? null,
            pos_awal_lng: posAwal?.longitude ?? null,
            pos_awal_accuracy: posAwal?.accuracy ?? null,
            pos_awal_captured_at: posAwal?.captured_at ?? null,
            motion_samples: motionSamples ? JSON.stringify(motionSamples) : null,
            attestation_token: attestation_token || null,
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
            if (!res.ok) {
                if (res.status === 419) {
                    throw { type: 'session_expired' };
                }
                const data = await res.json().catch(() => ({}));
                const errMap = {
                    413: 'Foto terlalu besar. Perkecil ukuran foto atau gunakan koneksi WiFi.',
                    429: 'Terlalu banyak permintaan. Tunggu beberapa saat, lalu coba lagi.',
                    500: 'Server error. Coba lagi nanti.',
                };
                let message = errMap[res.status] || data.message || 'Gagal mengirim presensi.';
                if (res.status === 422 && data.errors) {
                    const first = Object.values(data.errors)[0];
                    if (Array.isArray(first) && first.length) message = first[0];
                }
                setError(message);
                return;
            }
            const contentType = res.headers.get('content-type') || '';
            if (!contentType.includes('application/json') && !contentType.includes('text/json')) {
                const body = await res.text().catch(() => '');
                console.error('[Presensi] Response bukan JSON (200 OK), isi:', body.substring(0, 300));
                setError('Server merespon dengan halaman HTML. Coba refresh halaman. Jika masih error, hubungi admin.');
                return;
            }
            const data = await res.json();
            if (data.success) {
                setSuccessMessage(data.message || 'Presensi berhasil dikirim.');
                clearCamera();
                setJadwalId(null);
                setTimeout(() => {
                    if (typeof window !== 'undefined') window.location.assign(route('presensi.dashboard'));
                }, 1500);
            } else {
                setError(data.message || 'Gagal mengirim presensi.');
            }
        } catch (err) {
            if (err.type === 'session_expired') {
                setError('Sesi habis. Silakan refresh halaman dan login ulang.');
            } else if (err.name === 'AbortError') {
                setError('Permintaan dibatalkan. Coba lagi.');
            } else if (err instanceof TypeError && err.message === 'Failed to fetch') {
                setError('Koneksi terputus. Periksa jaringan Anda dan coba lagi.');
            } else {
                console.error('[Presensi] Error tak terduga:', err);
                setError('Tidak dapat terhubung ke server. Periksa jaringan atau coba lagi nanti.');
            }
        } finally {
            setIsSubmitting(false);
        }
    };

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

    const mapTileUrl = currentPosition
        ? (() => {
            const zoom = 16;
            const tiles = 2 ** zoom;
            const x = Math.floor(((currentPosition.longitude + 180) / 360) * tiles);
            const latitude = (currentPosition.latitude * Math.PI) / 180;
            const y = Math.floor(((1 - Math.log(Math.tan(latitude) + 1 / Math.cos(latitude)) / Math.PI) / 2) * tiles);
            return MAP_TILE_URL.replace('{z}', String(zoom)).replace('{x}', String(x)).replace('{y}', String(y));
        })()
        : null;

    if (sedangIzin) {
        return renderIzinBlock();
    }

    if (pegawai.status_kepegawaian === 'tetap') {
        return (
            <MobileLayout user={auth.user}>
                <Head title="Presensi" />
                <div className="mb-5">
                    <p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Presensi harian</p>
                    <h1 className="mt-1 text-2xl font-bold tracking-tight text-slate-950">Verifikasi kehadiran</h1>
                    <p className="mt-1 text-sm leading-relaxed text-slate-600">Presensi pagi & sore, slide kehadiran per jadwal.</p>
                </div>
                <TetapPresensi
                    pegawai={pegawai}
                    jadwals={jadwals}
                    presensiHariIni={presensiHariIni}
                    attestationToken={attestation_token}
                />
            </MobileLayout>
        );
    }

    return (
        <MobileLayout user={auth.user}>
            <Head title="Presensi" />

            <div className="mb-5">
                <p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Presensi harian</p>
                <h1 className="mt-1 text-2xl font-bold tracking-tight text-slate-950">Verifikasi kehadiran</h1>
                <p className="mt-1 text-sm leading-relaxed text-slate-600">Pilih jadwal, pastikan GPS valid, lalu ambil foto.</p>
            </div>

            {flash.message && (
                <div role="status" className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.message}</div>
            )}
            {flash.error && (
                <div role="alert" className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{flash.error}</div>
            )}

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

            <div className="mb-4 grid grid-cols-2 gap-2" aria-label="Status verifikasi">
                <div className={`flex min-h-14 items-center gap-2.5 rounded-xl border px-3 py-2.5 ${geoReady && geofence.inside ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : geoBlocked ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-slate-200 bg-white text-slate-600'}`}>
                    {loadingLocation ? <Loader2 className="h-5 w-5 shrink-0 animate-spin" /> : <LocateFixed className="h-5 w-5 shrink-0" />}
                    <div className="min-w-0">
                        <p className="text-[10px] font-bold uppercase tracking-wider">GPS</p>
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

            {!isLembur && !isTugasLuar && jadwals.length > 0 && (
                <section className="mb-4" aria-labelledby="jadwal-heading">
                    <div className="mb-2 flex items-center justify-between">
                        <h2 id="jadwal-heading" className="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Jadwal hari ini</h2>
                        <span className="text-xs text-slate-500">{jadwals.length} jadwal</span>
                    </div>
                    <div className="flex gap-2 overflow-x-auto pb-1">
                        {jadwals.map((j) => (
                            <button
                                key={j.id}
                                type="button"
                                onClick={() => selectJadwal(j.id)}
                                aria-pressed={jadwalId === j.id}
                                className={`min-h-[76px] min-w-[172px] shrink-0 rounded-xl border p-3 text-left transition-transform active:scale-[0.98] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/15 ${jadwalId === j.id ? 'border-primary bg-emerald-50 ring-1 ring-primary' : 'border-slate-200 bg-white'}`}
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <p className="line-clamp-1 text-sm font-bold text-slate-900">{j.mata_pelajaran?.nama || 'Jadwal'}</p>
                                    {jadwalId === j.id && <CheckCircle className="h-4 w-4 shrink-0 text-primary" />}
                                </div>
                                <p className="mt-1 text-xs text-slate-500">{j.kelas_label || (j.hari || '')}</p>
                                <p className="mt-1 font-mono text-xs font-bold tabular-nums text-primary">{j.jam_mulai} - {j.jam_selesai}</p>
                            </button>
                        ))}
                    </div>
                </section>
            )}

            {!isLembur && !isTugasLuar && officeAttendance && (
                <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                    <p className="text-sm font-bold text-emerald-900">Kehadiran kantor</p>
                    <p className="mt-0.5 text-xs leading-relaxed text-emerald-800">Tidak ada jadwal mengajar hari ini. Presensi memakai unit primer dan jam masuk kantor.</p>
                </div>
            )}

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
                            <img src={capturedPhoto} alt="Pratinjau foto presensi" className="block w-full" />
                            {currentPosition && (
                                <span className="absolute right-2 top-2 rounded-lg bg-black/65 px-2.5 py-1.5 font-mono text-[10px] font-bold tabular-nums text-white">
                                    {currentPosition.latitude.toFixed(5)}, {currentPosition.longitude.toFixed(5)}
                                </span>
                            )}
                        </>
                    ) : (
                        <div className="flex h-full w-full flex-col items-center justify-center gap-2 bg-slate-950 px-6 text-center text-slate-300">
                            <div className="flex h-14 w-14 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-slate-200">
                                <Camera className="h-6 w-6" />
                            </div>
                            <p className="text-sm font-medium">{locationError || 'Kamera tidak dapat diakses'}</p>
                            <p className="text-xs text-slate-400">Aktifkan kamera untuk presensi realtime.</p>
                        </div>
                    )}

                    {!capturedPhoto && (
                        <span className="absolute left-3 top-3 rounded-lg bg-black/65 px-2.5 py-1.5 font-mono text-xs font-bold tabular-nums text-white">{currentTime}</span>
                    )}

                    {isLembur && !capturedPhoto && (
                        <span className="absolute right-3 top-3 rounded-lg bg-amber-500 px-2.5 py-1.5 text-xs font-bold text-amber-950">LEMBUR</span>
                    )}

                    {isTugasLuar && !capturedPhoto && (
                        <span className="absolute right-3 top-3 rounded-lg bg-sky-500 px-2.5 py-1.5 text-xs font-bold text-sky-950">TUGAS LUAR</span>
                    )}

                    {currentPosition && !capturedPhoto && (
                        <span className={`absolute right-3 ${isLembur || isTugasLuar ? 'top-12' : 'top-3'} rounded-lg bg-black/65 px-2.5 py-1.5 text-[10px] font-mono font-semibold text-white`}>
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
                                    onClick={handleCaptureWithMotion}
                                    disabled={geoBlocked || !currentPosition}
                                    aria-label="Ambil foto presensi"
                                    className="flex h-[72px] w-[72px] items-center justify-center rounded-full border-4 border-white bg-transparent transition-transform active:scale-95 disabled:opacity-40 disabled:active:scale-100"
                                >
                                    <span className={`h-14 w-14 rounded-full ${isLembur ? 'bg-amber-500' : isTugasLuar ? 'bg-sky-500' : 'bg-emerald-500'}`} />
                                </button>
                            </div>
                            {showLive && !currentPosition && (
                                <p className="mb-3 text-center text-[10px] font-semibold text-white/80">Menunggu koordinat GPS sebelum foto dapat diambil...</p>
                            )}
                        </div>
                    )}
                </div>

                {showLive && currentPosition && (
                    <div className="relative h-48 overflow-hidden border-t border-slate-200 bg-slate-100">
                        {mapTileUrl && (
                            <img
                                src={mapTileUrl}
                                alt="Peta lokasi presensi"
                                width="512"
                                height="256"
                                loading="eager"
                                onError={(event) => { event.currentTarget.style.display = 'none'; }}
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
                                <p className="mt-1 text-xs font-semibold">{targetUnit?.nama || targetUnit?.nama_unit || 'Unit sekolah'}</p>
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
                                src={mapTileUrl}
                                alt="Peta lokasi presensi"
                                width="512"
                                height="256"
                                loading="lazy"
                                onError={(event) => { event.currentTarget.style.display = 'none'; }}
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
                                <p className="mt-1 text-xs font-semibold">{targetUnit?.nama || targetUnit?.nama_unit || 'Unit sekolah'}</p>
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

            <Card press={false} className="mt-3 flex items-center justify-between py-3.5">
                <div>
                    <p className="text-sm font-bold text-slate-900">Mode lembur</p>
                    <p className="mt-0.5 text-xs text-slate-500">Tanpa jadwal, perlu persetujuan admin</p>
                </div>
                <Toggle checked={isLembur} onChange={toggleLembur} tone="amber" />
            </Card>

            <Card press={false} className="mt-3 flex items-center justify-between py-3.5">
                <div>
                    <p className="text-sm font-bold text-slate-900">Mode tugas luar</p>
                    <p className="mt-0.5 text-xs text-slate-500">Dikecualikan dari cek radius, perlu tujuan</p>
                </div>
                <Toggle checked={isTugasLuar} onChange={toggleTugasLuar} tone="sky" />
            </Card>

            {isTugasLuar && (
                <div className="mt-3">
                    <label htmlFor="tujuan" className="mb-1.5 block text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Tujuan tugas luar</label>
                    <input
                        id="tujuan"
                        type="text"
                        value={tujuan}
                        onChange={(e) => setTujuan(e.target.value)}
                        placeholder="Contoh: Rapat dinas di Dinas Pendidikan"
                        className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-sky-200"
                    />
                </div>
            )}

            {tugasLuarRecord && (
                <BuktiKegiatan
                    presensiId={tugasLuarRecord.id}
                    initialUrls={tugasLuarRecord.foto_kegiatan_urls || []}
                    currentPosition={currentPosition}
                />
            )}

            <button
                type="button"
                onClick={handleSubmit}
                disabled={isSubmitting || !capturedPhoto || !currentPosition || geoBlocked || (isTugasLuar && !tugasLuarOpen && !tujuan.trim()) || (!isLembur && !isTugasLuar && !officeAttendance && !jadwals.length) || (isTeaching && teachingRecord?.jam_masuk && teachingRecord?.jam_keluar) || (!isLembur && officeAttendance && kantorRecord?.jam_keluar)}
                className={`mt-4 flex min-h-14 w-full items-center justify-center rounded-xl px-5 py-4 text-sm font-bold transition-transform active:scale-[0.99] disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500 ${isTugasLuar ? 'bg-sky-500 text-sky-950' : isLembur ? 'bg-amber-500 text-amber-950' : 'bg-primary text-white'}`}
            >
                {isSubmitting ? <><Loader2 className="mr-2 h-5 w-5 animate-spin" />Memproses...</> : isLembur ? 'Kirim bukti lembur' : isTugasLuar ? (tugasLuarOpen ? 'Kirim presensi keluar tugas luar' : 'Kirim presensi masuk tugas luar') : isTeaching ? (teachingRecord?.jam_keluar ? 'Sudah presensi lengkap' : teachingOpen ? 'Konfirmasi presensi keluar' : 'Konfirmasi presensi masuk') : (kantorOpen ? 'Konfirmasi presensi keluar' : 'Konfirmasi presensi masuk')}
            </button>

            <canvas ref={canvasRef} className="hidden" />
        </MobileLayout>
    );
}
