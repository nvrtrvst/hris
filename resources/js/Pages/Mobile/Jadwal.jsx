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

    return (
        <MobileLayout user={auth.user}>
            <Head title="Jadwal" />

            <div className="mb-4 px-1">
                <h1 className="text-2xl font-extrabold tracking-tight text-slate-800">Jadwal</h1>
                <p className="mt-0.5 text-sm text-slate-500">{totalJadwal} jadwal • {format(currentMonth, 'MMMM yyyy', { locale: id })}</p>
            </div>

            {/* Compact month navigator */}
            <div className="mb-4 flex items-center justify-between rounded-2xl bg-white/80 px-2 py-2 shadow-[0_8px_30px_-12px_rgba(15,61,62,0.2)] ring-1 ring-black/5 backdrop-blur">
                <button type="button" onClick={onPrevMonth} className="flex h-9 w-9 items-center justify-center rounded-xl text-slate-500 transition-transform active:scale-90 hover:bg-slate-100">
                    <ChevronLeft className="h-5 w-5" />
                </button>
                <p className="text-sm font-extrabold capitalize text-slate-800">{format(currentMonth, 'MMMM yyyy', { locale: id })}</p>
                <button type="button" onClick={onNextMonth} className="flex h-9 w-9 items-center justify-center rounded-xl text-slate-500 transition-transform active:scale-90 hover:bg-slate-100">
                    <ChevronRight className="h-5 w-5" />
                </button>
            </div>

            {totalJadwal === 0 ? (
                <Empty icon={Calendar} title="Belum ada jadwal" subtitle="Jadwal mengajar akan muncul di sini." />
            ) : (
                <div className="space-y-4">
                    {hariUrut.map((hari) => {
                        const list = hariMap[hari] || [];
                        if (list.length === 0) return null;
                        return (
                            <div key={hari}>
                                <div className="mb-2 flex items-center gap-2 px-1">
                                    <span className="h-2 w-2 rounded-full bg-emerald-500" />
                                    <h3 className="text-sm font-extrabold text-slate-700">{hari}</h3>
                                    <span className="text-xs font-medium text-slate-400">{list.length}</span>
                                </div>
                                <Card className="divide-y divide-slate-50 p-0">
                                    {list.map((j) => {
                                        const mapel = j.mata_pelajaran?.nama
                                            || (j.jenis_jadwal ? j.jenis_jadwal.charAt(0).toUpperCase() + j.jenis_jadwal.slice(1) : 'Jadwal');
                                        const kelas = j.kelas ? `Kls ${j.kelas.tingkat} ${j.kelas.nama}` : null;
                                        const unit = j.unit_sekolah?.singkatan || j.unit_sekolah?.nama || pegawai?.units?.[0]?.nama_unit || null;
                                        const isLembur = j.jenis_jadwal === 'lembur';
                                        return (
                                            <div key={j.id} className="flex items-center gap-3 px-4 py-3">
                                                <div className="w-14 shrink-0 text-center">
                                                    <p className="text-sm font-extrabold leading-none text-primary">{j.jam_mulai}</p>
                                                    <p className="mt-0.5 text-[11px] text-slate-400">{j.jam_selesai}</p>
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate font-bold text-slate-800">{mapel}</p>
                                                    <p className="truncate text-xs text-slate-500">{[kelas, unit].filter(Boolean).join(' • ')}</p>
                                                </div>
                                                {isLembur && (
                                                    <Badge tone="amber" className="shrink-0">Lembur</Badge>
                                                )}
                                            </div>
                                        );
                                    })}
                                </Card>
                            </div>
                        );
                    })}
                </div>
            )}
        </MobileLayout>
    );
}
