import { useState, useRef, useEffect, useMemo, useCallback } from 'react';
import { checkGeofence } from '@/Utils/geo';
import { MAP_TILE_URL, MAP_ATTRIBUTION } from '@/Constants/AppConstants';
import { Card, Toggle, Empty } from '@/Components/MobileUI';
import SlideToConfirm from '@/Components/SlideToConfirm';
import { useCamera } from '@/Hooks/useCamera';
import BuktiKegiatan from '@/Pages/Mobile/Partials/BuktiKegiatan';
import { useGeolocation } from '@/Hooks/useGeolocation';
import { useMotionSamples } from '@/Hooks/useMotionSamples';
import { Camera, RefreshCw, MapPin, CheckCircle, AlertCircle, Loader2, LocateFixed, ShieldCheck, Clock } from 'lucide-react';

const FOTO_PAGI = 'foto_pagi';
const TAP_JADWAL = 'tap_jadwal';
const FOTO_SORE = 'foto_sore';
const SELESAI = 'selesai';

// Tap jadwal hanya dalam rentang [jam_mulai, jam_selesai + grace] —
// harus sinkron dengan PresensiMessages::TAP_GRACE_MINUTES di backend.
const TAP_GRACE_MINUTES = 15;

/**
 * Kelompokkan jadwal berurutan dengan mata pelajaran sama (back-to-back).
 * Syarat satu grup: pegawai_mapel_id SAMA + jam_selesai[j] === jam_mulai[j+1].
 * Jadwal beda mapel atau ada gap → grup baru.
 */
function groupConsecutiveJadwals(jadwals) {
    if (!jadwals?.length) return [];
    const sorted = [...jadwals].sort((a, b) => (a.jam_mulai ?? '').localeCompare(b.jam_mulai ?? ''));
    const groups = [];
    let cur = null;
    for (const j of sorted) {
        const sameClass = j.kelas_label === cur?.kelas_label;
        if (cur && sameClass && j.pegawai_mapel_id === cur.pegMapelId && j.jam_mulai === cur.jamSelesaiLast) {
            cur.allIds.push(j.id);
            cur.jadwals.push(j);
            cur.jamSelesaiLast = j.jam_selesai;
        } else {
            if (cur) groups.push(cur);
            cur = {
                id: j.id,
                pegMapelId: j.pegawai_mapel_id,
                allIds: [j.id],
                jadwals: [j],
                mata_pelajaran: j.mata_pelajaran,
                kelas_label: j.kelas_label,
                unit_sekolah: j.unit_sekolah,
                jam_mulai: j.jam_mulai,
                jamSelesaiLast: j.jam_selesai,
            };
        }
    }
    if (cur) groups.push(cur);
    return groups;
}

const pad = (n) => String(n).padStart(2, '0');

export default function TetapPresensi({ pegawai, jadwals, presensiHariIni, attestationToken = null }) {
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [successMessage, setSuccessMessage] = useState(null);
    const [currentTime, setCurrentTime] = useState('');
    const [posA, setPosA] = useState(null);
    const [posAwal, setPosAwal] = useState(null);
    const [tappedIds, setTappedIds] = useState(() => new Set(
        presensiHariIni.filter((p) => p.jadwal_id && p.jam_masuk).map((p) => p.jadwal_id)
    ));
    const [closedIds, setClosedIds] = useState(() => new Set(
        presensiHariIni.filter((p) => p.jadwal_id && p.jam_keluar).map((p) => p.jadwal_id)
    ));
    const [tapLoading, setTapLoading] = useState(null);
    const [isTugasLuar, setIsTugasLuar] = useState(false);
    const [tujuan, setTujuan] = useState('');

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

    const pagiRecord = useMemo(() => presensiHariIni.find((p) => p.jadwal_id === null && !p.is_lembur), [presensiHariIni]);
    const now = new Date();
    const jamSekarang = now.getHours() * 60 + now.getMinutes();
    const jamSore = 960;
    const toMinutes = (hms) => { if (!hms) return 0; const p = String(hms).split(':'); return parseInt(p[0], 10) * 60 + parseInt(p[1] || 0, 10); };

    // Kelompokkan jadwal berurutan dengan mapel sama (back-to-back).
    const groupedJadwals = useMemo(() => groupConsecutiveJadwals(jadwals), [jadwals]);

    // Helper: semua jadwal dalam grup sudah di-tap (jam_masuk tercatat).
    const isGroupTapped = useCallback((g) => g.allIds.every((id) => tappedIds.has(id)), [tappedIds]);
    // Helper: semua jadwal dalam grup sudah closed (jam_keluar tercatat).
    const isGroupClosed = useCallback((g) => g.allIds.every((id) => closedIds.has(id)), [closedIds]);

    // Grup "aktif" = SUDAH di-tap ATAU masih dalam jendela waktu tap.
    const activeGroups = useMemo(() => groupedJadwals.filter((g) => {
        if (isGroupTapped(g)) return true;
        const mulai = toMinutes(g.jam_mulai);
        const selesai = toMinutes(g.jamSelesaiLast) || (mulai + 60);
        const grace = g.unit_sekolah?.toleransi_tap_menit ?? TAP_GRACE_MINUTES;
        return mulai <= jamSekarang && jamSekarang <= selesai + grace;
    }), [groupedJadwals, isGroupTapped, jamSekarang]);
    const untappedActiveGroups = activeGroups.filter((g) => !isGroupTapped(g));
    const masihBisaTap = untappedActiveGroups.length > 0;

    // Ada grup yang SEDANG berlangsung (mulai <= sekarang < selesai) —
    // di-tap bukan berarti selesai; foto sore menunggu jam mengajar habis.
    const adaBerlangsung = useMemo(() => groupedJadwals.some((g) => {
        const mulai = toMinutes(g.jam_mulai);
        const selesai = toMinutes(g.jamSelesaiLast) || (mulai + 60);
        return mulai <= jamSekarang && jamSekarang < selesai;
    }), [groupedJadwals, jamSekarang]);

    // Semua grup hari ini "beres": di-tap & jam selesainya sudah lewat, ATAU
    // sudah lewat batas tap (terlambat — tidak bisa di-tap lagi).
    const semuaBeres = useMemo(() => groupedJadwals.length > 0 && groupedJadwals.every((g) => {
        const selesai = toMinutes(g.jamSelesaiLast) || (toMinutes(g.jam_mulai) + 60);
        if (isGroupTapped(g)) return jamSekarang >= selesai;
        const grace = g.unit_sekolah?.toleransi_tap_menit ?? TAP_GRACE_MINUTES;
        return jamSekarang > selesai + grace;
    }), [groupedJadwals, isGroupTapped, jamSekarang]);

    // Generic check: semua presensi hari ini sudah lengkap
    const allRecordsComplete = presensiHariIni.length > 0
        && presensiHariIni.every((p) => p.jam_masuk && p.jam_keluar);

    const phase = useMemo(() => {
        if (allRecordsComplete) return SELESAI;
        if (!pagiRecord) return FOTO_PAGI;
        if (pagiRecord.jam_keluar) return SELESAI;
        // Foto sore hanya ketika tidak ada lagi jadwal yang berlangsung/menunggu
        // di-tap — cegah presensi keluar sebelum jam mengajar selesai.
        if (semuaBeres) return FOTO_SORE;
        // Tanpa jadwal sama sekali: foto sore tetap tersedia setelah jam sore tiba.
        if (jadwals.length === 0 && jamSekarang >= jamSore) return FOTO_SORE;

        return TAP_JADWAL;
    }, [allRecordsComplete, pagiRecord, semuaBeres, jadwals.length, jamSekarang]);

    const lemburUnit = pegawai?.units?.find((u) => u.pivot?.is_primary) ?? pegawai?.units?.[0] ?? null;

    const tugasLuarOpen = useMemo(() => presensiHariIni.some((p) => p.is_tugas_luar && p.jam_masuk && !p.jam_keluar), [presensiHariIni]);
    const tugasLuarRecord = useMemo(() => presensiHariIni.find((p) => p.is_tugas_luar && p.jam_masuk), [presensiHariIni]);
    const tugasLuarDone = useMemo(() => presensiHariIni.some((p) => p.is_tugas_luar && p.jam_masuk && p.jam_keluar), [presensiHariIni]);
    const isDinasLuarFlow = tugasLuarOpen || (isTugasLuar && !tugasLuarRecord);

    const geofence = useMemo(() => {
        if (!currentPosition || !lemburUnit) return null;
        const lat = parseFloat(lemburUnit.latitude);
        const lon = parseFloat(lemburUnit.longitude);
        if (isNaN(lat) || isNaN(lon)) return null;
        const radius = lemburUnit.radius_meter ?? 50;
        const { inside, distance } = checkGeofence(currentPosition.latitude, currentPosition.longitude, lat, lon, radius);
        return { name: lemburUnit.nama_unit || lemburUnit.nama || 'Unit Sekolah', distance, radius, inside };
    }, [currentPosition, lemburUnit]);

    const geoBlocked = isTugasLuar ? false : (geofence && !geofence.inside);
    const geoReady = geofence !== null;

    const isInsideJadwal = useCallback((jadwal) => {
        if (!currentPosition) return false;
        const unit = jadwal?.unit_sekolah || lemburUnit;
        if (!unit) return false;
        const lat = parseFloat(unit.latitude);
        const lon = parseFloat(unit.longitude);
        if (isNaN(lat) || isNaN(lon)) return false;
        const radius = unit.radius_meter ?? 50;
        const { inside } = checkGeofence(currentPosition.latitude, currentPosition.longitude, lat, lon, radius);
        return inside;
    }, [currentPosition, lemburUnit]);

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

    const { motionSamples, collectMotionSamples } = useMotionSamples();

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
        setCurrentTime(new Date().toLocaleTimeString('id-ID', { hour12: false }));
        const clock = setInterval(() => {
            setCurrentTime(new Date().toLocaleTimeString('id-ID', { hour12: false }));
        }, 1000);
        getCurrentPosition();

        if (phase === FOTO_PAGI || phase === FOTO_SORE || isTugasLuar) {
            startCamera();
        }

        return () => {
            clearInterval(clock);
            clearCamera();
            clearGeolocation();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [phase]);

    useEffect(() => {
        if (showLive && videoRef.current && streamRef.current) {
            videoRef.current.srcObject = streamRef.current;
            videoRef.current.play().catch(() => { });
        }
    }, [showLive]);

    const handleSubmitFoto = useCallback(async (tipe) => {
        setError(null);
        setSuccessMessage(null);

        if (!capturedPhoto) { setError('Silakan ambil foto terlebih dahulu.'); return; }
        if (!currentPosition) { setError('Lokasi belum tersedia. Pastikan GPS aktif.'); return; }
        if (isDinasLuarFlow && !tugasLuarRecord && !tujuan.trim()) { setError('Tujuan tugas luar wajib diisi.'); return; }
        if (!isTugasLuar && geoBlocked) { setError(`Anda di luar radius ${geofence.name} (${Math.round(geofence.distance)}m / batas ${geofence.radius}m).`); return; }

        setIsSubmitting(true);
        const now = new Date().toISOString();
        const payload = {
            _token: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            foto: capturedPhoto,
            tipe,
            is_tugas_luar: isDinasLuarFlow,
            tujuan: isDinasLuarFlow ? (tugasLuarRecord?.tujuan || tujuan) : null,
            keterangan: null,
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
            attestation_token: attestationToken,
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
                const errMap = { 413: 'Foto terlalu besar.', 429: 'Terlalu banyak permintaan. Tunggu.', 500: 'Server error.' };
                let message = errMap[res.status] || data.message || 'Gagal.';
                if (res.status === 422 && data.errors) {
                    const first = Object.values(data.errors)[0];
                    if (Array.isArray(first) && first.length) message = first[0];
                }
                setError(message);
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
                clearCamera();
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
    }, [capturedPhoto, currentPosition, geoBlocked, geofence, posA, posAwal, motionSamples]);

    // Tap seluruh grup secara atomik: optimis UI → kirim request untuk semua
    // jadwal_id dalam grup → rollback semua bila ADA satupun gagal (cegah partial state).
    const handleTapGroup = useCallback(async (group, tipe = 'masuk') => {
        setTapLoading(group.id);
        setError(null);
        setSuccessMessage(null);

        if (!currentPosition) {
            setTapLoading(null);
            setError('Lokasi belum tersedia. Pastikan GPS aktif.');
            return;
        }

        // Optimis: tandai SEMUA jadwal dalam grup sekaligus.
        if (tipe === 'keluar') {
            setClosedIds((prev) => { const next = new Set(prev); group.allIds.forEach((id) => next.add(id)); return next; });
        } else {
            setTappedIds((prev) => { const next = new Set(prev); group.allIds.forEach((id) => next.add(id)); return next; });
        }

        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        let allOk = true;

        for (const jadwalId of group.allIds) {
            try {
                const res = await fetch(route('presensi.absen.tap'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        _token: token,
                        jadwal_id: jadwalId,
                        tipe,
                        latitude: currentPosition.latitude,
                        longitude: currentPosition.longitude,
                        accuracy: currentPosition.accuracy,
                        mock_suspect: currentPosition.accuracy === 0,
                    }),
                });

                if (!res.ok) {
                    allOk = false;
                    if (res.status === 419) throw { type: 'session_expired' };
                    const data = await res.json().catch(() => ({}));
                    const msg = (data.errors && Object.values(data.errors)[0]?.[0]) || data.message || 'Gagal tap jadwal.';
                    setError(msg);
                    break;
                }

                const data = await res.json();
                if (!data.success) {
                    allOk = false;
                    setError(data.message || 'Gagal.');
                    break;
                }
            } catch (err) {
                allOk = false;
                if (err.type === 'session_expired') setError('Sesi habis.');
                else setError('Gagal terhubung ke server.');
                break;
            }
        }

        if (!allOk) {
            // Rollback SEMUA jadwal dalam grup — partial state tidak diperbolehkan.
            if (tipe === 'keluar') {
                setClosedIds((prev) => { const next = new Set(prev); group.allIds.forEach((id) => next.delete(id)); return next; });
            } else {
                setTappedIds((prev) => { const next = new Set(prev); group.allIds.forEach((id) => next.delete(id)); return next; });
            }
        } else {
            setSuccessMessage(tipe === 'keluar' ? 'Presensi pulang tercatat.' : 'Kehadiran tercatat.');
            setTimeout(() => setSuccessMessage(null), 2000);
        }

        setTapLoading(null);
    }, [currentPosition]);

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

            {tugasLuarRecord ? (
                <Card press={false} className="mb-4 flex items-center justify-between py-3.5">
                    <div>
                        <p className="text-sm font-bold text-slate-900">{tugasLuarDone ? 'Tugas luar selesai' : 'Tugas luar berlangsung'}</p>
                        <p className="mt-0.5 text-xs text-slate-500">{tugasLuarDone ? 'Presensi dinas luar sudah lengkap.' : 'Dikecualikan dari cek radius, perlu tujuan'}</p>
                    </div>
                    <span className={`rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ${tugasLuarDone ? 'bg-emerald-100 text-emerald-700' : 'bg-sky-100 text-sky-700'}`}>
                        {tugasLuarDone ? 'Selesai' : 'Aktif'}
                    </span>
                </Card>
            ) : (
                <Card press={false} className="mb-4 flex items-center justify-between py-3.5">
                    <div>
                        <p className="text-sm font-bold text-slate-900">Mode tugas luar</p>
                        <p className="mt-0.5 text-xs text-slate-500">Dikecualikan dari cek radius, perlu tujuan</p>
                    </div>
                    <Toggle checked={isTugasLuar} onChange={() => setIsTugasLuar((prev) => !prev)} tone="sky" />
                </Card>
            )}

            {tugasLuarRecord ? (
                <div className="mb-4">
                    <label className="mb-1.5 block text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Tujuan tugas luar</label>
                    <input
                        type="text"
                        value={tugasLuarRecord.tujuan || tujuan}
                        readOnly
                        className="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700"
                    />
                </div>
            ) : (
                isTugasLuar && (
                    <div className="mb-4">
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
                )
            )}

            {tugasLuarOpen && tugasLuarRecord && (
                <BuktiKegiatan
                    presensiId={tugasLuarRecord.id}
                    initialUrls={tugasLuarRecord.foto_kegiatan_urls || []}
                    currentPosition={currentPosition}
                />
            )}

            {/* Jadwal section - always visible (hanya yg sudah waktunya) */}
            {jadwals.length > 0 && (
                <section aria-labelledby="jadwal-heading" className="mb-4">
                    <div className="mb-2 flex items-center justify-between">
                        <h2 id="jadwal-heading" className="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Agenda Mengajar</h2>
                        <span className="text-xs text-slate-500">{activeGroups.filter(isGroupTapped).length} dari {activeGroups.length} sudah di-tap</span>
                    </div>
                    {phase === TAP_JADWAL && adaBerlangsung && !masihBisaTap && (
                        <div className="mb-2 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-800">
                            Semua jadwal mengajar sudah terpenuhi. Presensi Foto pulang tersedia setelah jam mengajar selesai.
                        </div>
                    )}
                    <div className="space-y-2">
                        {activeGroups.map((g) => {
                            const done = isGroupTapped(g);
                            const closed = isGroupClosed(g);
                            const timeLabel = g.jam_mulai?.slice(0, 5) + (g.jamSelesaiLast ? '–' + g.jamSelesaiLast.slice(0, 5) : '');
                            const count = g.allIds.length;
                            return (
                                <div key={g.id} className={`rounded-xl border p-3 ${done ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white'}`}>
                                    <div className="mb-2 flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="text-sm font-bold text-slate-900">{g.mata_pelajaran?.nama || 'Jadwal'}</p>
                                            <p className="mt-0.5 text-xs text-slate-500">
                                                {g.kelas_label || ''}
                                                {count > 1 && <span className="ml-1 font-semibold text-primary">· {count} jam berturut</span>}
                                            </p>
                                        </div>
                                        <span className="shrink-0 font-mono text-xs font-bold tabular-nums text-primary">{timeLabel}</span>
                                    </div>
                                    {!done && phase === TAP_JADWAL ? (
                                        <>
                                            <SlideToConfirm
                                                onConfirm={() => handleTapGroup(g)}
                                                disabled={tapLoading !== null || !currentPosition || !isInsideJadwal(g)}
                                                confirmed={false}
                                                label={`Tap ${g.mata_pelajaran?.nama || 'jadwal'}${count > 1 ? ` (${count} jam)` : ''}`}
                                            />
                                            {(!currentPosition || !isInsideJadwal(g)) && (
                                                <p className="mt-1.5 flex items-center gap-1 text-[11px] font-semibold text-rose-600">
                                                    <MapPin className="h-3 w-3 shrink-0" />
                                                    {!currentPosition ? 'Tunggu GPS aktif…' : 'Di luar radius unit — geser tidak aktif'}
                                                </p>
                                            )}
                                        </>
                                    ) : done && !closed ? (
                                        <div className="flex items-center gap-2 text-xs font-semibold text-emerald-700">
                                            <CheckCircle className="h-4 w-4" /> Presensi mengajar tercatat
                                            {count > 1 && <span className="text-emerald-500">({count} jam)</span>}
                                        </div>
                                    ) : done && closed ? (
                                        <div className="flex items-center gap-2 text-xs font-semibold text-emerald-700">
                                            <CheckCircle className="h-4 w-4" /> Presensi mengajar lengkap
                                        </div>
                                    ) : (
                                        <p className="text-xs text-slate-400">Tap masuk tersedia saat jam mengajar.</p>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </section>
            )}

            {(phase === FOTO_PAGI || phase === FOTO_SORE || isTugasLuar) && (
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
                                            onClick={() => { collectMotionSamples(); capturePhoto(); }}
                                            disabled={isCapturing || geoBlocked || !currentPosition}
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

            <button
                type="button"
                onClick={() => handleSubmitFoto(isDinasLuarFlow ? (tugasLuarOpen ? 'keluar' : 'masuk') : (phase === FOTO_PAGI ? 'masuk' : 'keluar'))}
                disabled={isSubmitting || !capturedPhoto || !currentPosition || (!geofence?.inside && !isDinasLuarFlow) || (isDinasLuarFlow && !tugasLuarRecord && !tujuan.trim()) || allRecordsComplete}
                className={`mt-4 flex min-h-14 w-full items-center justify-center rounded-xl px-5 py-4 text-sm font-bold text-white transition-transform active:scale-[0.99] disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500 ${isDinasLuarFlow ? 'bg-sky-500' : 'bg-primary'}`}
            >
                {isSubmitting ? <><Loader2 className="mr-2 h-5 w-5 animate-spin" />Memproses...</> : isDinasLuarFlow ? (tugasLuarOpen ? 'Kirim presensi keluar tugas luar' : 'Kirim presensi masuk tugas luar') : `Kirim ${phase === FOTO_PAGI ? 'foto pagi' : 'foto sore'}`}
                    </button>
                </>
            )}

            {activeGroups.length === 0 && jadwals.length > 0 && phase !== SELESAI && (
                <Empty icon={Clock} title="Tidak ada jadwal yang bisa di-tap" subtitle={semuaBeres ? 'Semua jadwal sudah berakhir — lanjut ke foto sore.' : 'Jadwal aktif akan muncul setelah jam mengajar tiba.'} />
            )}
            {jadwals.length === 0 && phase !== SELESAI && (
                <Empty icon={Clock} title="Tidak ada jadwal hari ini" subtitle="Jika tidak ada jadwal, lanjut ke foto sore." />
            )}

            {phase === SELESAI && (
                <div className="flex flex-col items-center py-12 text-center">
                    <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600">
                        <CheckCircle className="h-8 w-8" />
                    </div>
                    <h2 className="text-lg font-bold text-slate-900">Presensi hari ini selesai</h2>
                    <p className="mt-1 text-sm text-slate-500">{tugasLuarDone ? 'Presensi dinas luar tercatat lengkap.' : 'Foto pagi, tap jadwal, dan foto sore sudah lengkap.'}</p>
                </div>
            )}

            <canvas ref={canvasRef} className="hidden" />
        </>
    );
}
