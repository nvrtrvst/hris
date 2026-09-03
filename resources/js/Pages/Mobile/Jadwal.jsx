import React, { useState, useEffect, useMemo } from 'react';
import { Head } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card, Empty } from '@/Components/MobileUI';
import { ArrowRight, Calendar, Clock3, WifiOff, X, CheckCircle, XCircle, AlertTriangle, Users, MapPin } from 'lucide-react';

const hariUrut = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

const fmtJam = (t) => (t ? String(t).substring(0, 5) : '—');

const statusConfig = {
    hadir: { label: 'Hadir', color: 'text-emerald-600', bg: 'bg-emerald-50', icon: CheckCircle },
    telat: { label: 'Telat', color: 'text-amber-600', bg: 'bg-amber-50', icon: AlertTriangle },
    izin: { label: 'Izin', color: 'text-blue-600', bg: 'bg-blue-50', icon: CheckCircle },
    sakit: { label: 'Sakit', color: 'text-purple-600', bg: 'bg-purple-50', icon: CheckCircle },
    alpa: { label: 'Alpa', color: 'text-red-600', bg: 'bg-red-50', icon: XCircle },
};

function StatusBadge({ status }) {
    const cfg = statusConfig[status] || statusConfig.alpa;
    const Icon = cfg.icon;
    return (
        <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ${cfg.bg} ${cfg.color}`}>
            <Icon className="h-3 w-3" />
            {cfg.label}
        </span>
    );
}

function PresensiSelfCard({ presensiHariIni, jadwalHariIni, jadwalPerHari, todayName, onOpenDetail }) {
    const regulerPresensi = (presensiHariIni || []).filter(p => !p.is_lembur);
    const lemburPresensi = (presensiHariIni || []).filter(p => p.is_lembur);
    const kantor = regulerPresensi.find(p => p.jam_masuk && !p.jadwal_id);

    const presensiByJadwal = useMemo(() => {
        const map = {};
        regulerPresensi.forEach(p => {
            if (p.jadwal_id) map[p.jadwal_id] = p;
        });
        return map;
    }, [regulerPresensi]);

    const [selectedDay, setSelectedDay] = useState(todayName);
    const isToday = selectedDay === todayName;
    const selectedItems = jadwalPerHari?.[selectedDay] || [];

    return (
        <div className="mb-4">
            {/* Kantor presensi */}
            {kantor && (
                <Card press={false} className="!bg-[#0F3D3E] border-0 px-4 py-3 text-white shadow-[0_8px_20px_-12px_rgba(15,61,62,0.7)] mb-3">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <CheckCircle className="h-3.5 w-3.5 text-emerald-400" />
                            <span className="text-xs font-bold text-white/90">Kantor</span>
                            <span className="font-mono text-xs font-bold text-white/70">{fmtJam(kantor.jam_masuk)}</span>
                            {kantor.jam_keluar && (
                                <>
                                    <span className="text-white/40">→</span>
                                    <span className="font-mono text-xs font-bold text-white/70">{fmtJam(kantor.jam_keluar)}</span>
                                </>
                            )}
                        </div>
                        <StatusBadge status={kantor.status} />
                    </div>
                </Card>
            )}

            {/* Day picker */}
            <div className="mb-3 flex items-center justify-between">
                <h2 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                    {kantor ? 'Mengajar Hari Ini' : 'Jadwal Hari Ini'}
                </h2>
                <div className="rounded-xl bg-primary px-3 py-1.5 text-white">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-emerald-100">Sesi</p>
                    <p className="text-sm font-bold">{jadwalHariIni?.length || 0}</p>
                </div>
            </div>

            <div className="mb-3 -mx-1 overflow-x-auto px-1 pb-1" role="tablist" aria-label="Pilih hari">
                <div className="flex min-w-max gap-2">
                    {hariUrut.map((hari) => {
                        const active = selectedDay === hari;
                        const isTd = todayName === hari;
                        const count = jadwalPerHari?.[hari]?.length || 0;
                        return (
                            <button
                                key={hari}
                                type="button"
                                role="tab"
                                aria-selected={active}
                                onClick={() => setSelectedDay(hari)}
                                className={`min-w-[58px] rounded-xl border px-3 py-2.5 text-center transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/15 ${active ? 'border-primary bg-primary text-white' : 'border-slate-200 bg-white text-slate-600'}`}
                            >
                                <span className={`block text-[10px] font-bold uppercase ${active ? 'text-emerald-100' : 'text-slate-400'}`}>{hari.slice(0, 3)}</span>
                                <span className="mt-1 block text-sm font-bold">{count}</span>
                                {isTd && <span className={`mx-auto mt-1 block h-1 w-1 rounded-full ${active ? 'bg-emerald-200' : 'bg-primary'}`} />}
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* Jadwal list */}
            {selectedItems.length > 0 ? (
                <div className="space-y-2">
                    {selectedItems.map((j) => {
                        const rec = isToday ? presensiByJadwal[j.id] : null;
                        const mapel = j.mata_pelajaran?.nama || (j.jenis_jadwal === 'lembur' ? 'Lembur' : 'Jadwal');
                        const kelas = j.kelas_label || null;
                        const unit = j.unit_sekolah?.singkatan || j.unit_sekolah?.nama || null;
                        const isLembur = j.jenis_jadwal === 'lembur';
                        const now = new Date();
                        const [sh, sm] = (j.jam_selesai || '').split(':').map(Number);
                        const selesai = new Date(now); selesai.setHours(sh || 0, sm || 0, 0, 0);
                        const sudahSelesai = isToday && j.jam_selesai && now > selesai;
                        const badge = rec ? rec.status : sudahSelesai ? 'alpa' : null;

                        return (
                            <Card key={j.id} press onClick={() => onOpenDetail?.(j)} className="px-4 py-3">
                                <div className="flex items-center gap-3">
                                    <div className="w-14 shrink-0 text-center leading-none">
                                        <p className="font-mono text-sm font-bold tabular-nums text-slate-900">{fmtJam(j.jam_mulai)}</p>
                                        <div className="mx-auto my-1 h-3 w-px bg-slate-200" />
                                        <p className="font-mono text-[11px] tabular-nums text-slate-400">{fmtJam(j.jam_selesai)}</p>
                                    </div>
                                    <div className={`h-10 w-1 shrink-0 rounded-full ${isLembur ? 'bg-amber-400' : 'bg-primary'}`} />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-bold text-slate-900">{mapel}</p>
                                        <p className="mt-0.5 truncate text-xs text-slate-500">
                                            {[kelas, unit].filter(Boolean).join(' • ')}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-1.5">
                                        {badge && <StatusBadge status={badge} />}
                                        <ArrowRight className="h-4 w-4 shrink-0 text-slate-300" />
                                    </div>
                                </div>
                            </Card>
                        );
                    })}
                </div>
            ) : (
                <Card press={false} className="px-4 py-8 text-center">
                    <Calendar className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-3 text-sm font-bold text-slate-700">Tidak ada jadwal {selectedDay}</p>
                    <p className="mt-1 text-xs text-slate-500">Pilih hari lain untuk melihat agenda mengajar.</p>
                </Card>
            )}

            {/* Lembur (hanya hari ini) */}
            {isToday && lemburPresensi.length > 0 && (
                <div className="mt-3">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Lembur</p>
                    <div className="space-y-2">
                        {lemburPresensi.map((p, i) => (
                            <Card key={i} press={false} className="!bg-amber-50 border-0 px-4 py-3">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Clock3 className="h-3.5 w-3.5 text-amber-500" />
                                        <span className="font-mono text-xs font-bold text-slate-700">{fmtJam(p.jam_masuk)}</span>
                                        {p.jam_keluar && (
                                            <>
                                                <span className="text-slate-400">→</span>
                                                <span className="font-mono text-xs font-bold text-slate-700">{fmtJam(p.jam_keluar)}</span>
                                            </>
                                        )}
                                    </div>
                                    <StatusBadge status={p.status} />
                                </div>
                            </Card>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

function BawahanPresensiCard({ bawahanPresensi }) {
    if (!bawahanPresensi || bawahanPresensi.length === 0) return null;

    const sorted = [...bawahanPresensi].sort((a, b) => {
        const aMasuk = a.presensi.find(p => p.jam_masuk && !p.is_lembur)?.jam_masuk || 'zzz';
        const bMasuk = b.presensi.find(p => p.jam_masuk && !p.is_lembur)?.jam_masuk || 'zzz';
        return aMasuk.localeCompare(bMasuk);
    });

    return (
        <div className="mb-4">
            <div className="flex items-center gap-2 mb-3">
                <Users className="h-3.5 w-3.5 text-primary" />
                <h2 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                    Bawahan ({bawahanPresensi.length})
                </h2>
            </div>
            <Card press={false} className="overflow-hidden p-0">
                <div className="divide-y divide-slate-100">
                    {sorted.map((b) => {
                        const presensi = b.presensi.filter(p => !p.is_lembur);
                        const masuk = presensi.find(p => p.jam_masuk);
                        const lembur = b.presensi.filter(p => p.is_lembur);

                        return (
                            <div key={b.id} className="flex items-center gap-3 px-4 py-3">
                                <div className="h-9 w-9 shrink-0 rounded-full bg-primary/10 flex items-center justify-center">
                                    <span className="text-xs font-bold text-primary">{b.nama?.charAt(0) || '?'}</span>
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-bold text-slate-900">{b.nama}</p>
                                    <p className="truncate text-[11px] text-slate-400">{b.unit || '—'}</p>
                                </div>
                                <div className="text-right shrink-0">
                                    {masuk ? (
                                        <div className="flex items-center gap-1">
                                            <span className="font-mono text-xs font-bold text-slate-700">{masuk.jam_masuk}</span>
                                            <StatusBadge status={masuk.status} />
                                        </div>
                                    ) : (
                                        <span className="text-[10px] font-bold text-slate-300">Belum absen</span>
                                    )}
                                    {masuk?.jam_keluar && (
                                        <p className="font-mono text-[10px] text-slate-400">→ {masuk.jam_keluar}</p>
                                    )}
                                    {lembur.length > 0 && (
                                        <p className="text-[10px] text-amber-600 font-bold">🔴 Lembur</p>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </Card>
        </div>
    );
}

export default function Jadwal({ auth, pegawai, jadwalPerHari, presensiHariIni = [], jadwalHariIni = [], bawahanPresensi = [] }) {
    const hariMap = { Senin: [], Selasa: [], Rabu: [], Kamis: [], Jumat: [], Sabtu: [], Minggu: [] };
    Object.keys(jadwalPerHari || {}).forEach((hari) => {
        if (hariMap[hari]) hariMap[hari] = jadwalPerHari[hari];
    });

    const todayName = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][new Date().getDay()];

    const [selected, setSelected] = useState(null);
    const [siswa, setSiswa] = useState([]);
    const [loadingSiswa, setLoadingSiswa] = useState(false);
    const [classOptions, setClassOptions] = useState([]);
    const [selectedClass, setSelectedClass] = useState(null);
    const [loadingClasses, setLoadingClasses] = useState(false);
    const [integrationError, setIntegrationError] = useState('');
    const [offline, setOffline] = useState(false);

    const isPimpinan = bawahanPresensi.length > 0;

    // PWA offline jadwal
    useEffect(() => {
        const CACHE_KEY = 'hris_jadwal_' + (pegawai?.id || 0);
        if (!navigator.onLine) {
            setOffline(true);
            try {
                const cached = localStorage.getItem(CACHE_KEY);
                if (cached) {
                    const parsed = JSON.parse(cached);
                    if (parsed && Object.keys(parsed).length > 0 && Object.keys(jadwalPerHari).length === 0) {
                        Object.assign(jadwalPerHari, parsed);
                    }
                }
            } catch { /* ignore */ }
        }
    }, []);

    useEffect(() => {
        if (jadwalPerHari && Object.keys(jadwalPerHari).length > 0) {
            try {
                localStorage.setItem('hris_jadwal_' + (pegawai?.id || 0), JSON.stringify(jadwalPerHari));
            } catch { /* quota exceeded */ }
        }
    }, [jadwalPerHari]);

    const loadStudents = (j, classData) => {
        setLoadingSiswa(true);
        setIntegrationError('');
        const params = new URLSearchParams({
            jadwal_id: String(j.id),
            tingkat: String(classData.tingkat ?? ''),
            kelas: classData.kelas ?? '',
            jurusan: classData.jurusan ?? '',
            class_id: classData.class_id ?? '',
        });
        fetch(`${route('presensi.jadwal.siswa')}?${params.toString()}`)
            .then(async (r) => {
                const data = await r.json();
                if (!r.ok || !data.success) throw new Error(data.message || 'Gagal memuat data murid.');
                return data;
            })
            .then((d) => setSiswa(d.siswa || []))
            .catch((error) => {
                setSiswa([]);
                setIntegrationError(error.message || 'Gagal memuat data murid.');
            })
            .finally(() => setLoadingSiswa(false));
    };

    const openDetail = (j) => {
        setSelected(j);
        setSiswa([]);
        setClassOptions([]);
        setSelectedClass(null);
        setIntegrationError('');
        setLoadingClasses(true);
        fetch(`${route('presensi.jadwal.kelas')}?jadwal_id=${encodeURIComponent(j.id)}`)
            .then(async (r) => {
                const data = await r.json();
                if (!r.ok || !data.success) throw new Error(data.message || 'Gagal memuat daftar kelas.');
                return data;
            })
            .then((d) => setClassOptions(d.kelas || []))
            .catch((error) => {
                setClassOptions([]);
                setIntegrationError(error.message || 'Gagal memuat daftar kelas.');
            })
            .finally(() => setLoadingClasses(false));
    };

    const chooseClass = (classData) => {
        setSelectedClass(classData);
        loadStudents(selected, classData);
    };

    const changeClass = () => {
        setSelectedClass(null);
        setSiswa([]);
        setIntegrationError('');
    };

    return (
        <MobileLayout user={auth.user}>
            <Head title="Jadwal" />

            {/* Status Hari Ini + Jadwal dengan day picker */}
            <PresensiSelfCard
                presensiHariIni={presensiHariIni}
                jadwalHariIni={jadwalHariIni}
                jadwalPerHari={hariMap}
                todayName={todayName}
                onOpenDetail={openDetail}
            />

            {/* Bawahan (untuk pimpinan) */}
            {isPimpinan && <BawahanPresensiCard bawahanPresensi={bawahanPresensi} />}

            {/* Detail modal */}
            {selected && (
                <div
                    className="fixed inset-0 z-50 flex flex-col justify-end bg-black/40"
                    onClick={() => setSelected(null)}
                    role="presentation"
                >
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="schedule-detail-title"
                        className="max-h-[86dvh] overflow-y-auto rounded-t-3xl bg-white p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="mx-auto mb-3 h-1.5 w-12 rounded-full bg-slate-300" />
                        <div className="mb-4 flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                            <div>
                                <p className="text-xs font-bold uppercase tracking-wide text-primary">
                                    {selected.kelas_label || 'Detail jadwal'}
                                </p>
                                <h2 id="schedule-detail-title" className="mt-1 text-xl font-bold text-slate-900">
                                    {selected.mata_pelajaran?.nama || 'Jadwal'}
                                </h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    {selected.unit_sekolah?.nama || selected.unit_sekolah?.singkatan || ''}
                                </p>
                                 <p className="mt-2 font-mono text-sm font-bold tabular-nums text-primary">{fmtJam(selected.jam_mulai)} - {fmtJam(selected.jam_selesai)}</p>
                            </div>
                            <button type="button" onClick={() => setSelected(null)} className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 active:bg-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30" aria-label="Tutup detail jadwal">
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        {integrationError && (
                            <p role="alert" className="mb-3 rounded-xl bg-rose-50 px-3 py-2.5 text-sm font-semibold text-rose-700">
                                {integrationError}
                            </p>
                        )}

                        {selectedClass && (
                            <button type="button" onClick={changeClass} className="mb-3 text-sm font-bold text-primary">
                                Ganti kelas
                            </button>
                        )}

                        {!selectedClass ? (
                            loadingClasses ? (
                                <p className="py-6 text-center text-sm text-slate-400">Memuat daftar kelas…</p>
                            ) : classOptions.length === 0 ? (
                                <p className="py-6 text-center text-sm text-slate-400">Tidak ada kelas aktif di aplikasi keuangan.</p>
                            ) : (
                                <div>
                                    <p className="mb-3 text-sm font-bold text-slate-700">Pilih kelas mengajar</p>
                                    <div className="grid grid-cols-2 gap-2">
                                        {classOptions.map((item) => (
                                            <button
                                                key={`${item.grade}-${item.name}-${item.section || ''}-${item.major_name || ''}`}
                                                type="button"
                                                onClick={() => chooseClass({ class_id: item.id, tingkat: item.grade, kelas: item.name, jurusan: item.major_name || '' })}
                                                className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-left text-sm font-bold text-slate-700 transition active:scale-95"
                                            >
                                                {item.grade} {item.name}{item.section ? ` ${item.section}` : ''}
                                                {item.major_name && <span className="mt-1 block text-xs font-normal text-slate-500">{item.major_name}</span>}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            )
                        ) : loadingSiswa ? (
                            <p className="py-6 text-center text-sm text-slate-400">Memuat…</p>
                        ) : siswa.length === 0 ? (
                            <p className="py-6 text-center text-sm text-slate-400">
                                Tidak ada data siswa yang cocok di app keuangan.
                            </p>
                        ) : (
                            <div>
                                <h4 className="mb-3 text-sm font-bold text-slate-700">
                                    Daftar Murid ({siswa.length})
                                </h4>
                                <ul className="space-y-2">
                                    {siswa.map((s, i) => {
                                        const genderText =
                                            s.gender === 'L' ? 'Laki-laki' : s.gender === 'P' ? 'Perempuan' : s.gender;
                                        return (
                                            <li key={i} className="rounded-xl bg-slate-50 px-3 py-2.5">
                                                <div className="flex items-center justify-between">
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-bold text-slate-800">{s.nama}</p>
                                                        <p className="text-xs text-slate-400">NIS {s.nis} • {genderText}</p>
                                                    </div>
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>
                        )}
                        <button
                            type="button"
                            onClick={() => setSelected(null)}
                            className="mt-3 w-full rounded-2xl bg-slate-100 py-3 font-bold text-slate-600 transition-transform active:scale-[0.98]"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            )}
        </MobileLayout>
    );
}
