import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card, Badge, Empty } from '@/Components/MobileUI';
import { format, addMonths, subMonths } from 'date-fns';
import { id } from 'date-fns/locale';
import { Calendar, ChevronLeft, ChevronRight } from 'lucide-react';

const hariUrut = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

export default function Jadwal({ auth, pegawai, jadwalPerHari }) {
    const [currentMonth, setCurrentMonth] = useState(new Date());

    const hariMap = { Senin: [], Selasa: [], Rabu: [], Kamis: [], Jumat: [], Sabtu: [], Minggu: [] };
    Object.keys(jadwalPerHari || {}).forEach((hari) => {
        if (hariMap[hari]) hariMap[hari] = jadwalPerHari[hari];
    });

    const onPrevMonth = () => setCurrentMonth((prev) => subMonths(prev, 1));
    const onNextMonth = () => setCurrentMonth((prev) => addMonths(prev, 1));

    const totalJadwal = hariUrut.reduce((sum, h) => sum + (hariMap[h]?.length || 0), 0);
    const activeHari = hariUrut.filter((h) => (hariMap[h]?.length || 0) > 0);

    const [selected, setSelected] = useState(null);
    const [siswa, setSiswa] = useState([]);
    const [loadingSiswa, setLoadingSiswa] = useState(false);
    const [absenState, setAbsenState] = useState({});
    const [search, setSearch] = useState('');
    const [savingBatch, setSavingBatch] = useState(false);
    const [savedBatch, setSavedBatch] = useState(false);

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
        if (!selected?.kelas || savingBatch) return;
        const unit = selected.unit_sekolah?.nama || selected.unit_sekolah?.singkatan || '';
        const jurusan = selected.kelas?.jurusan?.nama || '';
        const tanggal = new Date().toISOString().slice(0, 10);
        const absens = siswa
            .filter((s) => absenState[s.nis])
            .map((s) => ({ nis: s.nis, status: absenState[s.nis] }));
        if (absens.length === 0) return;
        setSavingBatch(true);
        fetch(route('presensi.jadwal.siswa.absen-batch'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                unit,
                tingkat: String(selected.kelas.tingkat ?? ''),
                kelas: selected.kelas.nama ?? '',
                jurusan,
                tanggal,
                absens,
            }),
        })
            .then((r) => r.json())
            .then((d) => {
                if (d.success) setSavedBatch(true);
            })
            .catch(() => {})
            .finally(() => setSavingBatch(false));
    };

    const openDetail = (j) => {
        setSelected(j);
        setSiswa([]);
        setAbsenState({});
        setSearch('');
        setSavedBatch(false);
        const kelas = j.kelas;
        if (!kelas) {
            setLoadingSiswa(false);
            return;
        }
        setLoadingSiswa(true);
        const unit = j.unit_sekolah?.nama || j.unit_sekolah?.singkatan || '';
        const jurusan = kelas.jurusan?.nama || '';
        const params = new URLSearchParams({
            unit,
            tingkat: String(kelas.tingkat ?? ''),
            kelas: kelas.nama ?? '',
            jurusan,
        });
        fetch(`${route('presensi.jadwal.siswa')}?${params.toString()}`)
            .then((r) => r.json())
            .then((d) => setSiswa(d.siswa || []))
            .catch(() => setSiswa([]))
            .finally(() => setLoadingSiswa(false));
    };

    return (
        <MobileLayout user={auth.user}>
            <Head title="Jadwal" />

            <div className="mb-4 px-1">
                <h1 className="text-2xl font-extrabold tracking-tight text-slate-800">Jadwal</h1>
                <p className="mt-0.5 text-sm text-slate-500">
                    {totalJadwal} jadwal • {format(currentMonth, 'MMMM yyyy', { locale: id })}
                </p>
            </div>

            {/* Navigator bulan */}
            <div className="mb-3 flex items-center justify-between rounded-2xl bg-white px-2 py-1.5 shadow-sm ring-1 ring-black/5">
                <button
                    type="button"
                    onClick={onPrevMonth}
                    className="flex h-9 w-9 items-center justify-center rounded-xl text-slate-500 transition-transform active:scale-90 hover:bg-slate-100"
                >
                    <ChevronLeft className="h-5 w-5" />
                </button>
                <p className="text-sm font-extrabold capitalize text-slate-800">
                    {format(currentMonth, 'MMMM yyyy', { locale: id })}
                </p>
                <button
                    type="button"
                    onClick={onNextMonth}
                    className="flex h-9 w-9 items-center justify-center rounded-xl text-slate-500 transition-transform active:scale-90 hover:bg-slate-100"
                >
                    <ChevronRight className="h-5 w-5" />
                </button>
            </div>

            {totalJadwal === 0 ? (
                <Empty icon={Calendar} title="Belum ada jadwal" subtitle="Jadwal mengajar akan muncul di sini." />
            ) : (
                <Card className="overflow-hidden p-0">
                    {activeHari.map((hari, i) => {
                        const list = hariMap[hari];
                        return (
                            <div key={hari}>
                                {i > 0 && <div className="h-px bg-slate-100" />}
                                <div className="flex items-center justify-between bg-primary/5 px-4 py-2">
                                    <span className="text-xs font-extrabold uppercase tracking-wide text-primary">{hari}</span>
                                    <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary">
                                        {list.length}
                                    </span>
                                </div>
                                {list.map((j) => {
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
                                            className="flex w-full items-center gap-3 px-4 py-2.5 text-left transition-colors active:bg-slate-50"
                                        >
                                            <div className="w-14 shrink-0 leading-none">
                                                <p className="text-sm font-extrabold text-slate-800">{j.jam_mulai}</p>
                                                <p className="mt-0.5 text-[11px] text-slate-400">{j.jam_selesai}</p>
                                            </div>
                                            <div className="h-8 w-px shrink-0 bg-slate-200" />
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-bold text-slate-800">{mapel}</p>
                                                <p className="truncate text-xs text-slate-500">
                                                    {[kelas, unit].filter(Boolean).join(' • ')}
                                                </p>
                                            </div>
                                            {isLembur && (
                                                <Badge tone="amber" className="shrink-0">
                                                    Lembur
                                                </Badge>
                                            )}
                                        </button>
                                    );
                                })}
                </div>
            );
        })}
    </Card>
    )}

    {selected && (
                <div
                    className="fixed inset-0 z-50 flex flex-col justify-end bg-black/40"
                    onClick={() => setSelected(null)}
                >
                    <div
                        className="max-h-[82vh] overflow-y-auto rounded-t-3xl bg-white p-5"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="mx-auto mb-3 h-1.5 w-12 rounded-full bg-slate-300" />
                        <div className="mb-4">
                            <p className="text-xs font-bold uppercase tracking-wide text-primary">
                                {selected.kelas
                                    ? `Kelas ${selected.kelas.tingkat} ${selected.kelas.nama}`
                                    : 'Jadwal'}
                            </p>
                            <p className="text-lg font-extrabold text-slate-800">
                                {selected.mata_pelajaran?.nama || 'Jadwal'}
                            </p>
                            <p className="text-sm text-slate-500">
                                {[
                                    selected.kelas
                                        ? `Kls ${selected.kelas.tingkat} ${selected.kelas.nama}`
                                        : null,
                                    selected.unit_sekolah?.nama || selected.unit_sekolah?.singkatan,
                                    selected.kelas?.jurusan?.nama,
                                ]
                                    .filter(Boolean)
                                    .join(' • ')}
                            </p>
                            <p className="text-sm text-slate-500">
                                {selected.jam_mulai} – {selected.jam_selesai}
                            </p>
                        </div>

                        {!selected.kelas ? (
                            <p className="py-6 text-center text-sm text-slate-400">
                                Jadwal ini tidak terikat kelas.
                            </p>
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
