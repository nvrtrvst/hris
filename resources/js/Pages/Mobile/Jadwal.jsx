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
    const [classOptions, setClassOptions] = useState([]);
    const [selectedClass, setSelectedClass] = useState(null);
    const [loadingClasses, setLoadingClasses] = useState(false);
    const [integrationError, setIntegrationError] = useState('');

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
                <Card press={false} className="!bg-[#0F3D3E] mb-4 border-0 p-4 text-white shadow-[0_8px_20px_-12px_rgba(15,61,62,0.7)]">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-white/70">{featuredDay === todayName ? 'Sesi pertama hari ini' : `Sesi berikutnya, ${featuredDay}`}</p>
                            <p className="mt-1 text-base font-bold text-white">{featuredSchedule.mata_pelajaran?.nama || 'Jadwal mengajar'}</p>
                            <p className="mt-1 text-xs text-white/80">{featuredSchedule.kelas ? `Kelas ${featuredSchedule.kelas.tingkat} ${featuredSchedule.kelas.nama}` : 'Tanpa kelas'}</p>
                        </div>
                        <div className="rounded-xl bg-white/10 px-3 py-2 text-right">
                            <Clock3 className="ml-auto h-4 w-4 text-white/80" />
                            <p className="mt-1 font-mono text-sm font-bold tabular-nums text-white">{featuredSchedule.jam_mulai}</p>
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
