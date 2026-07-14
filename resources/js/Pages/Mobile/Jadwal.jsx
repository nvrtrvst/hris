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
                                        <div key={j.id} className="flex items-center gap-3 px-4 py-2.5">
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
                                        </div>
                                    );
                                })}
                            </div>
                        );
                    })}
                </Card>
            )}
        </MobileLayout>
    );
}
