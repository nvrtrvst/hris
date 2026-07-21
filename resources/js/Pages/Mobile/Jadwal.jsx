import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card, Empty } from '@/Components/MobileUI';
import { ArrowRight, Calendar, Clock3, X } from 'lucide-react';

const hariUrut = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

export default function Jadwal({ auth, pegawai, jadwalPerHari }) {
    const hariMap = { Senin: [], Selasa: [], Rabu: [], Kamis: [], Jumat: [], Sabtu: [], Minggu: [] };
    Object.keys(jadwalPerHari || {}).forEach((hari) => {
        if (hariMap[hari]) hariMap[hari] = jadwalPerHari[hari];
    });

    const totalJadwal = hariUrut.reduce((sum, h) => sum + (hariMap[h]?.length || 0), 0);
    const activeHari = hariUrut.filter((h) => (hariMap[h]?.length || 0) > 0);
    const todayName = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][new Date().getDay()];
    const [selectedDay, setSelectedDay] = useState(hariMap[todayName]?.length ? todayName : activeHari[0] || 'Senin');
    const selectedDayItems = hariMap[selectedDay] || [];
    const featuredDay = hariMap[todayName]?.length ? todayName : activeHari[0];
    const featuredSchedule = featuredDay ? hariMap[featuredDay][0] : null;

    const [selected, setSelected] = useState(null);
    const [siswa, setSiswa] = useState([]);
    const [loadingSiswa, setLoadingSiswa] = useState(false);
    const [absenState, setAbsenState] = useState({});
    const [search, setSearch] = useState('');
    const [savingBatch, setSavingBatch] = useState(false);
    const [savedBatch, setSavedBatch] = useState(false);
    const [classOptions, setClassOptions] = useState([]);
    const [selectedClass, setSelectedClass] = useState(null);
    const [loadingClasses, setLoadingClasses] = useState(false);
    const [integrationError, setIntegrationError] = useState('');

    const setStatus = (s, status) => {
        setSavedBatch(false);
        setAbsenState((prev) => ({ ...prev, [s.nis]: status }));
    };

    const markAll = (status = 'hadir') => {
        setSavedBatch(false);
        setAbsenState((prev) => {
            const next = { ...prev };
            siswa.forEach((s) => {
                next[s.nis] = status;
            });
            return next;
        });
    };

    const markRest = (status = 'hadir') => {
        setSavedBatch(false);
        setAbsenState((prev) => {
            const next = { ...prev };
            siswa.forEach((s) => {
                if (!next[s.nis]) next[s.nis] = status;
            });
            return next;
        });
    };

    const submitBatch = () => {
        if ((!selected?.kelas && !selectedClass) || savingBatch) return;
        const classData = selected.kelas
            ? { tingkat: String(selected.kelas.tingkat ?? ''), kelas: selected.kelas.nama ?? '', jurusan: selected.kelas.jurusan?.nama || '' }
            : selectedClass;
        const jurusan = classData.jurusan || '';
        const tanggal = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(new Date());
        const absens = siswa
            .filter((s) => absenState[s.nis])
            .map((s) => ({ nis: s.nis, status: absenState[s.nis] }));
        if (absens.length === 0) return;
        setSavingBatch(true);
        setIntegrationError('');
        fetch(route('presensi.jadwal.siswa.absen-batch'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                jadwal_id: selected.id,
                tingkat: classData.tingkat,
                kelas: classData.kelas,
                jurusan,
                class_id: classData.class_id || '',
                tanggal,
                absens,
            }),
        })
            .then(async (r) => {
                const data = await r.json();
                if (!r.ok || !data.success) throw new Error(data.message || 'Gagal menyimpan presensi murid.');
                return data;
            })
            .then((d) => {
                if (d.success) setSavedBatch(true);
            })
            .catch((error) => setIntegrationError(error.message || 'Gagal menyimpan presensi murid.'))
            .finally(() => setSavingBatch(false));
    };

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
        setAbsenState({});
        setSearch('');
        setSavedBatch(false);
        setIntegrationError('');
        const kelas = j.kelas;
        if (!kelas) {
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
            return;
        }
        loadStudents(j, { tingkat: kelas.tingkat, kelas: kelas.nama, jurusan: kelas.jurusan?.nama || '' });
    };

    const chooseClass = (classData) => {
        setSelectedClass(classData);
        setAbsenState({});
        loadStudents(selected, classData);
    };

    const changeClass = () => {
        setSelectedClass(null);
        setSiswa([]);
        setAbsenState({});
        setIntegrationError('');
    };

    return (
        <MobileLayout user={auth.user}>
            <Head title="Jadwal" />

            <div className="mb-5">
                <p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Agenda mengajar</p>
                <div className="mt-1 flex items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-slate-950">Jadwal saya</h1>
                        <p className="mt-1 text-sm text-slate-600">{totalJadwal} sesi dalam satu minggu</p>
                    </div>
                    <div className="rounded-xl bg-primary px-3 py-2 text-right text-white">
                        <p className="text-[10px] font-bold uppercase tracking-wider text-emerald-100">Hari ini</p>
                        <p className="mt-0.5 text-sm font-bold">{hariMap[todayName]?.length || 0} sesi</p>
                    </div>
                </div>
            </div>

            {featuredSchedule && (
                <Card press={false} className="mb-4 border-0 bg-[#0F3D3E] p-4 text-white shadow-[0_8px_20px_-12px_rgba(15,61,62,0.7)]">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-100">{featuredDay === todayName ? 'Sesi pertama hari ini' : `Sesi berikutnya, ${featuredDay}`}</p>
                            <p className="mt-1 text-base font-bold text-white">{featuredSchedule.mata_pelajaran?.nama || 'Jadwal mengajar'}</p>
                            <p className="mt-1 text-xs text-emerald-100">{featuredSchedule.kelas ? `Kelas ${featuredSchedule.kelas.tingkat} ${featuredSchedule.kelas.nama}` : 'Tanpa kelas'}</p>
                        </div>
                        <div className="rounded-xl bg-white/10 px-3 py-2 text-right">
                            <Clock3 className="ml-auto h-4 w-4 text-emerald-100" />
                            <p className="mt-1 font-mono text-sm font-bold tabular-nums">{featuredSchedule.jam_mulai}</p>
                        </div>
                    </div>
                </Card>
            )}

            <div className="mb-4 -mx-1 overflow-x-auto px-1 pb-1" role="tablist" aria-label="Pilih hari">
                <div className="flex min-w-max gap-2">
                    {hariUrut.map((hari) => {
                        const active = selectedDay === hari;
                        const isToday = todayName === hari;
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
                                <span className="mt-1 block text-sm font-bold">{hariMap[hari]?.length || 0}</span>
                                {isToday && <span className={`mx-auto mt-1 block h-1 w-1 rounded-full ${active ? 'bg-emerald-200' : 'bg-primary'}`} />}
                            </button>
                        );
                    })}
                </div>
            </div>

            {totalJadwal === 0 ? (
                <Empty icon={Calendar} title="Belum ada jadwal" subtitle="Jadwal mengajar akan muncul di sini." />
            ) : selectedDayItems.length === 0 ? (
                <Card press={false} className="px-4 py-8 text-center">
                    <Calendar className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-3 text-sm font-bold text-slate-700">Tidak ada jadwal {selectedDay}</p>
                    <p className="mt-1 text-xs text-slate-500">Pilih hari lain untuk melihat agenda mengajar.</p>
                </Card>
            ) : (
                <Card press={false} className="overflow-hidden p-0">
                    <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <div>
                            <p className="text-base font-bold text-slate-900">{selectedDay}</p>
                            <p className="text-xs text-slate-500">{selectedDayItems.length} sesi terjadwal</p>
                        </div>
                        <Calendar className="h-5 w-5 text-primary" />
                    </div>
                    <div className="divide-y divide-slate-100">
                        {selectedDayItems.map((j) => {
                                    const mapel =
                                        j.mata_pelajaran?.nama ||
                                        (j.jenis_jadwal === 'lembur' ? 'Lembur' : 'Jadwal');
                                    const kelas = j.kelas ? `Kls ${j.kelas.tingkat} ${j.kelas.nama}` : null;
                                    const unit =
                                        j.unit_sekolah?.singkatan ||
                                        j.unit_sekolah?.nama ||
                                        pegawai?.units?.[0]?.nama_unit ||
                                        null;
                                    const isLembur = j.jenis_jadwal === 'lembur';
                                    return (
                                        <button
                                            key={j.id}
                                            type="button"
                                            onClick={() => openDetail(j)}
                                            className="flex min-h-[82px] w-full items-center gap-3 px-4 py-3.5 text-left transition-colors active:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary/30"
                                        >
                                            <div className="w-16 shrink-0 text-center leading-none">
                                                <p className="font-mono text-sm font-bold tabular-nums text-slate-900">{j.jam_mulai}</p>
                                                <div className="mx-auto my-1 h-3 w-px bg-slate-200" />
                                                <p className="font-mono text-[11px] tabular-nums text-slate-400">{j.jam_selesai}</p>
                                            </div>
                                            <div className={`h-10 w-1 shrink-0 rounded-full ${isLembur ? 'bg-amber-400' : 'bg-primary'}`} />
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-bold text-slate-900">{mapel}</p>
                                                <p className="mt-1 truncate text-xs text-slate-500">
                                                    {[kelas, unit].filter(Boolean).join(' • ')}
                                                </p>
                                            </div>
                                            <ArrowRight className="h-4 w-4 shrink-0 text-slate-300" />
                                        </button>
                                    );
                                })}
                    </div>
                </Card>
            )}

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
                                    {selected.kelas ? `Kelas ${selected.kelas.tingkat} ${selected.kelas.nama}` : 'Detail jadwal'}
                                </p>
                                <h2 id="schedule-detail-title" className="mt-1 text-xl font-bold text-slate-900">
                                    {selected.mata_pelajaran?.nama || 'Jadwal'}
                                </h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    {[selected.unit_sekolah?.nama || selected.unit_sekolah?.singkatan, selected.kelas?.jurusan?.nama].filter(Boolean).join(' • ')}
                                </p>
                                <p className="mt-2 font-mono text-sm font-bold tabular-nums text-primary">{selected.jam_mulai} - {selected.jam_selesai}</p>
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

                        {!selected.kelas && selectedClass && (
                            <button type="button" onClick={changeClass} className="mb-3 text-sm font-bold text-primary">
                                Ganti kelas
                            </button>
                        )}

                        {!selected.kelas && !selectedClass ? (
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
                            (() => {
                                const q = search.trim().toLowerCase();
                                const filtered = siswa.filter(
                                    (s) => !q || s.nama.toLowerCase().includes(q) || (s.nis || '').toLowerCase().includes(q)
                                );
                                const counts = { hadir: 0, izin: 0, sakit: 0, alpa: 0, belum: 0 };
                                siswa.forEach((s) => {
                                    const v = absenState[s.nis];
                                    if (v && counts[v] !== undefined) counts[v] += 1;
                                    else counts.belum += 1;
                                });
                                const marked = siswa.length - counts.belum;
                                const opts = [
                                    ['hadir', 'Hadir', 'bg-emerald-500'],
                                    ['izin', 'Izin', 'bg-amber-500'],
                                    ['sakit', 'Sakit', 'bg-orange-500'],
                                    ['alpa', 'Alpa', 'bg-rose-500'],
                                ];
                                const summary = [
                                    ['hadir', `${counts.hadir} Hadir`, 'bg-emerald-50 text-emerald-700'],
                                    ['izin', `${counts.izin} Izin`, 'bg-amber-50 text-amber-700'],
                                    ['sakit', `${counts.sakit} Sakit`, 'bg-orange-50 text-orange-700'],
                                    ['alpa', `${counts.alpa} Alpa`, 'bg-rose-50 text-rose-700'],
                                    ['belum', `${counts.belum} Belum`, 'bg-slate-100 text-slate-500'],
                                ];
                                return (
                                    <>
                                        <input
                                            value={search}
                                            onChange={(e) => setSearch(e.target.value)}
                                            placeholder="Cari nama / NIS murid…"
                                            className="mb-2 w-full rounded-xl bg-slate-100 px-3 py-2.5 text-sm outline-none ring-1 ring-black/5 placeholder:text-slate-400 focus:ring-2 focus:ring-primary"
                                        />
                                        <div className="mb-2 grid grid-cols-2 gap-2">
                                            <button
                                                type="button"
                                                onClick={() => markAll('hadir')}
                                                className="rounded-xl bg-emerald-500 py-2.5 text-xs font-extrabold text-white transition active:scale-95"
                                            >
                                                ✓ Semua Hadir
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => markRest('hadir')}
                                                className="rounded-xl bg-white py-2.5 text-xs font-extrabold text-slate-600 ring-1 ring-slate-200 transition active:scale-95"
                                            >
                                                Sisanya Hadir
                                            </button>
                                        </div>
                                        <p className="mb-3 text-[11px] leading-snug text-slate-400">
                                            <b className="text-slate-500">Kecuali:</b> tandai dulu murid yang berhalangan
                                            (Izin/Sakit/Alpa), lalu tekan <b className="text-slate-500">Sisanya Hadir</b>.
                                        </p>
                                        <div className="mb-3 flex flex-wrap gap-1.5">
                                            {summary.map(([key, label, cls]) => (
                                                <span key={key} className={`rounded-full px-2.5 py-1 text-[11px] font-bold ${cls}`}>
                                                    {label}
                                                </span>
                                            ))}
                                        </div>
                                        <h4 className="mb-2 text-sm font-extrabold text-slate-700">
                                            Daftar Murid ({filtered.length}/{siswa.length})
                                        </h4>
                                        <ul className="space-y-2">
                                            {filtered.map((s, i) => {
                                                const genderText =
                                                    s.gender === 'L' ? 'Laki-laki' : s.gender === 'P' ? 'Perempuan' : s.gender;
                                                return (
                                                    <li key={i} className="rounded-xl bg-slate-50 px-3 py-2">
                                                        <div className="flex items-center justify-between">
                                                            <div className="min-w-0">
                                                                <p className="truncate text-sm font-bold text-slate-800">{s.nama}</p>
                                                                <p className="text-xs text-slate-400">NIS {s.nis} • {genderText}</p>
                                                            </div>
                                                            {absenState[s.nis] && (
                                                                <span className="ml-2 shrink-0 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase text-primary">
                                                                    ✓ {absenState[s.nis]}
                                                                </span>
                                                            )}
                                                        </div>
                                                        <div className="mt-2 grid grid-cols-4 gap-1.5">
                                                            {opts.map(([val, label, active]) => {
                                                                const on = absenState[s.nis] === val;
                                                                return (
                                                                    <button
                                                                        key={val}
                                                                        type="button"
                                                                        disabled={savingBatch}
                                                                        onClick={() => setStatus(s, val)}
                                                                        className={`rounded-lg py-1.5 text-[11px] font-bold transition active:scale-95 disabled:opacity-50 ${
                                                                            on ? `${active} text-white` : 'bg-white text-slate-500 ring-1 ring-slate-200'
                                                                        }`}
                                                                    >
                                                                        {label}
                                                                    </button>
                                                                );
                                                            })}
                                                        </div>
                                                    </li>
                                                );
                                            })}
                                            {filtered.length === 0 && (
                                                <li className="py-6 text-center text-sm text-slate-400">Tidak ada nama cocok.</li>
                                            )}
                                        </ul>
                                        <button
                                            type="button"
                                            onClick={submitBatch}
                                            disabled={savingBatch || marked === 0}
                                            className="mt-4 w-full rounded-2xl bg-primary py-3 font-extrabold text-white transition-transform active:scale-[0.98] disabled:opacity-50"
                                        >
                                            {savingBatch
                                                ? 'Menyimpan…'
                                                : savedBatch
                                                ? `✓ Tersimpan (${marked})`
                                                : `Simpan ${marked > 0 ? `(${marked})` : ''}`}
                                        </button>
                                    </>
                                );
                            })()
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
