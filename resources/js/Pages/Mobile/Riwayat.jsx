import React, { useMemo } from 'react';
import { Head, router } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card, SectionTitle, Badge, Empty } from '@/Components/MobileUI';
import { parseISO, format, startOfMonth, endOfMonth, eachDayOfInterval, getDay } from 'date-fns';
import { id } from 'date-fns/locale';
import { History, CalendarDays, Clock, MapPin, CheckCircle2, AlertTriangle, FileText, XCircle, Ban } from 'lucide-react';

export default function Riwayat({ auth, presensi, filters }) {
    const activeMonth = `${filters?.tahun || new Date().getFullYear()}-${String(filters?.bulan || new Date().getMonth() + 1).padStart(2, '0')}`;

    const handleFilter = (e) => {
        const [tahun, bulan] = e.target.value.split('-').map(Number);
        router.get(
            route('presensi.riwayat'),
            { bulan, tahun },
            { preserveState: true, replace: true }
        );
    };

    const capitalize = (s) => (s ? s.charAt(0).toUpperCase() + s.slice(1) : '');
    const getStatusBadge = (status) => {
        const map = {
            hadir: { tone: 'emerald', label: 'Hadir', icon: CheckCircle2 },
            telat: { tone: 'amber', label: 'Telat', icon: AlertTriangle },
            sakit: { tone: 'sky', label: 'Sakit', icon: FileText },
            izin: { tone: 'sky', label: 'Izin', icon: FileText },
            cuti: { tone: 'sky', label: 'Cuti', icon: FileText },
            alpa: { tone: 'rose', label: 'Alpa', icon: XCircle },
        };
        return map[status] || { tone: 'slate', label: capitalize(status), icon: Ban };
    };
    const formatTanggal = (t) => format(parseISO(t), 'd MMMM yyyy', { locale: id });
    const formatJam = (j) => (j ? j.substring(0, 5) : '-');
    const dayName = (t) => capitalize(format(parseISO(t), 'EEEE', { locale: id }));

    const grouped = useMemo(() => {
        const g = {};
        (presensi || []).forEach((p) => {
            if (!g[p.tanggal]) g[p.tanggal] = [];
            g[p.tanggal].push(p);
        });
        return Object.keys(g)
            .sort((a, b) => (a < b ? 1 : -1))
            .map((t) => ({ tanggal: t, items: g[t] }));
    }, [presensi]);

    const dailyStatus = useMemo(() => {
        const priority = { hadir: 1, cuti: 2, izin: 2, sakit: 2, telat: 3, alpa: 4 };
        return (presensi || []).filter((p) => !p.is_lembur).reduce((days, p) => {
            const current = days[p.tanggal];
            if (!current || (priority[p.status] || 0) > (priority[current] || 0)) days[p.tanggal] = p.status;
            return days;
        }, {});
    }, [presensi]);

    const stats = useMemo(() => Object.values(dailyStatus).reduce((result, status) => {
        if (status === 'hadir') result.hadir++;
        else if (status === 'telat') result.telat++;
        else if (['sakit', 'izin', 'cuti'].includes(status)) result.izin++;
        else if (status === 'alpa') result.alpa++;
        return result;
    }, { hadir: 0, telat: 0, izin: 0, alpa: 0 }), [dailyStatus]);

    const statList = [
        { key: 'hadir', label: 'Hadir', tone: 'emerald', icon: CheckCircle2 },
        { key: 'telat', label: 'Telat', tone: 'amber', icon: AlertTriangle },
        { key: 'izin', label: 'Izin', tone: 'sky', icon: FileText },
        { key: 'alpa', label: 'Alpa', tone: 'rose', icon: XCircle },
    ];

    const calendarDays = useMemo(() => {
        const start = parseISO(`${activeMonth}-01`);
        return eachDayOfInterval({ start: startOfMonth(start), end: endOfMonth(start) });
    }, [activeMonth]);
    const leadingDays = getDay(calendarDays[0]);
    const calendarTone = {
        hadir: 'bg-emerald-100 text-emerald-800 ring-emerald-200',
        telat: 'bg-amber-100 text-amber-800 ring-amber-200',
        sakit: 'bg-sky-100 text-sky-800 ring-sky-200',
        izin: 'bg-sky-100 text-sky-800 ring-sky-200',
        cuti: 'bg-sky-100 text-sky-800 ring-sky-200',
        alpa: 'bg-rose-100 text-rose-800 ring-rose-200',
    };

    return (
        <MobileLayout user={auth.user}>
            <Head title="Riwayat Presensi" />

            <div className="mb-5 px-1">
                <h1 className="text-2xl font-extrabold tracking-tight text-slate-800">Riwayat</h1>
                <p className="mt-0.5 text-sm text-slate-500">Rekap kehadiran Anda</p>
            </div>

            <label className="mb-5 block">
                <span className="sr-only">Pilih bulan riwayat</span>
                <input type="month" value={activeMonth} onChange={handleFilter} className="min-h-11 w-full rounded-xl border-slate-200 bg-white text-sm font-bold text-slate-700 shadow-sm focus:border-primary focus:ring-primary" />
            </label>

            {/* Stats */}
            <div className="mb-5 grid grid-cols-4 gap-2">
                {statList.map((s) => (
                    <Card key={s.key} press={false} className="flex flex-col items-center px-1 py-3.5">
                        <s.icon className={`mb-1 h-5 w-5 ${
                            s.tone === 'emerald' ? 'text-emerald-500' : s.tone === 'amber' ? 'text-amber-500' : s.tone === 'sky' ? 'text-emerald-500' : 'text-rose-500'
                        }`} />
                        <p className="text-xl font-extrabold leading-none text-slate-800">{stats[s.key]}</p>
                        <p className="mt-0.5 text-[11px] font-medium text-slate-500">{s.label}</p>
                    </Card>
                ))}
            </div>

            <Card press={false} className="mb-6 p-4">
                <div className="mb-4 flex items-center justify-between">
                    <div>
                        <p className="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Kalender Kehadiran</p>
                        <p className="mt-1 text-base font-extrabold text-slate-900">{format(parseISO(`${activeMonth}-01`), 'MMMM yyyy', { locale: id })}</p>
                    </div>
                    <CalendarDays className="h-5 w-5 text-primary" />
                </div>
                <div className="grid grid-cols-7 text-center text-[10px] font-bold uppercase text-slate-400" aria-hidden="true">
                    {['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'].map((day) => <span key={day} className="py-1">{day}</span>)}
                </div>
                <div className="mt-1 grid grid-cols-7 gap-1" aria-label={`Kalender kehadiran ${activeMonth}`}>
                    {Array.from({ length: leadingDays }).map((_, index) => <span key={`empty-${index}`} />)}
                    {calendarDays.map((day) => {
                        const key = format(day, 'yyyy-MM-dd');
                        const status = dailyStatus[key];
                        return (
                            <div key={key} aria-label={`${format(day, 'd MMMM', { locale: id })}: ${status ? getStatusBadge(status).label : 'Tidak ada catatan'}`} className={`flex aspect-square items-center justify-center rounded-lg text-xs font-bold tabular-nums ring-1 ${status ? calendarTone[status] : 'bg-slate-50 text-slate-400 ring-slate-100'}`}>
                                {format(day, 'd')}
                            </div>
                        );
                    })}
                </div>
                <div className="mt-4 flex flex-wrap gap-x-3 gap-y-2 border-t border-slate-100 pt-3 text-[10px] font-semibold text-slate-600">
                    {[['bg-emerald-400', 'Hadir'], ['bg-amber-400', 'Telat'], ['bg-sky-400', 'Izin/Sakit'], ['bg-rose-400', 'Alpa']].map(([color, label]) => (
                        <span key={label} className="inline-flex items-center gap-1.5"><span className={`h-2 w-2 rounded-full ${color}`} />{label}</span>
                    ))}
                </div>
            </Card>

            {/* List */}
            {grouped.length === 0 ? (
                <Empty icon={History} title="Belum ada riwayat" subtitle="Presensi akan tercatat di sini." />
            ) : (
                <div className="space-y-5">
                    {grouped.map(({ tanggal, items }) => (
                        <div key={tanggal}>
                            <div className="mb-2 flex items-center gap-2 px-1">
                                <CalendarDays className="h-4 w-4 text-emerald-400" />
                                <p className="text-sm font-extrabold text-slate-700">{formatTanggal(tanggal)}</p>
                                <span className="text-xs font-medium text-slate-400">• {dayName(tanggal)}</span>
                            </div>
                            <div className="space-y-2.5">
                                {items.map((p) => {
                                    const b = getStatusBadge(p.status);
                                    return (
                                        <Card key={p.id} className="flex items-center justify-between py-3.5">
                                            <div className="min-w-0">
                                                <p className="truncate font-bold text-slate-800">{p.mata_pelajaran || 'Presensi'}</p>
                                                <p className="mt-0.5 flex items-center gap-3 text-xs text-slate-500">
                                                    <span className="inline-flex items-center gap-1"><Clock className="h-3 w-3 text-emerald-400" />{formatJam(p.jam_masuk)}–{formatJam(p.jam_keluar)}</span>
                                                    {p.jarak_meter != null && (
                                                        <span className="inline-flex items-center gap-1"><MapPin className="h-3 w-3 text-emerald-400" />{p.jarak_meter}m</span>
                                                    )}
                                                </p>
                                            </div>
                                            <Badge tone={b.tone} icon={b.icon}>{b.label}</Badge>
                                        </Card>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </MobileLayout>
    );
}
