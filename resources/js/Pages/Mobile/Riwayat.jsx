import React, { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card, SectionTitle, Badge, Empty } from '@/Components/MobileUI';
import { parseISO, format, startOfMonth, endOfMonth, eachDayOfInterval, getDay } from 'date-fns';
import { id } from 'date-fns/locale';
import { History, CalendarDays, Clock, MapPin, CheckCircle2, AlertTriangle, FileText, XCircle, Ban, ChevronDown } from 'lucide-react';

export default function Riwayat({ auth, presensi, summary, filters }) {
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

    const [selectedDate, setSelectedDate] = useState(null);

    const selectedItems = useMemo(() => {
        if (!selectedDate) return [];
        return (presensi || []).filter((p) => p.tanggal === selectedDate)
            .sort((a, b) => (a.jam_masuk || '').localeCompare(b.jam_masuk || ''));
    }, [presensi, selectedDate]);

    const [expandedMapel, setExpandedMapel] = useState(new Set());
    const toggleMapel = (nama) => setExpandedMapel((prev) => {
        const next = new Set(prev);
        next.has(nama) ? next.delete(nama) : next.add(nama);
        return next;
    });

    const groupedByMapel = useMemo(() => {
        const groups = [];
        const kantorItems = [];
        const mapelMap = {};

        selectedItems.forEach((p) => {
            if (!p.jadwal_id) { kantorItems.push(p); return; }
            const nama = p.jadwal?.mata_pelajaran?.nama || 'Lainnya';
            if (!mapelMap[nama]) mapelMap[nama] = [];
            mapelMap[nama].push(p);
        });

        kantorItems.forEach((p) => groups.push({ type: 'single', item: p }));

        Object.entries(mapelMap)
            .sort((a, b) => (a[1][0].jadwal?.jam_mulai || '').localeCompare(b[1][0].jadwal?.jam_mulai || ''))
            .forEach(([nama, items]) => {
                items.sort((a, b) => (a.jadwal?.jam_mulai || '').localeCompare(b.jadwal?.jam_mulai || ''));
                groups.push({
                    type: 'group', nama, items,
                    timeRange: `${formatJam(items[0].jadwal?.jam_mulai)}–${formatJam(items[items.length - 1].jadwal?.jam_selesai)}`,
                    count: items.length,
                });
            });

        return groups;
    }, [selectedItems]);

    const dailyStatus = useMemo(() => {
        const priority = { hadir: 1, cuti: 2, izin: 2, sakit: 2, telat: 3, alpa: 4 };
        return (presensi || []).filter((p) => !p.is_lembur && !p.jadwal_id).reduce((days, p) => {
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

            {/* Kehadiran bulan ini (F5) */}
            {summary && (
                <Card press={false} className="mb-6 p-4">
                    <div className="flex items-center justify-between">
                        <p className="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Kehadiran Bulan Ini</p>
                        <p className="text-sm font-extrabold text-primary">{summary.present}/{summary.working_days} hari ({summary.percent}%)</p>
                    </div>
                    <div className="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-100">
                        <div className="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-600 transition-all" style={{ width: `${Math.min(100, summary.percent)}%` }} />
                    </div>
                    <p className="mt-2 text-[11px] text-slate-400">Alpa {summary.alpa} • Sakit {summary.sakit} • Izin {summary.izin} • Cuti {summary.cuti}</p>
                </Card>
            )}

            <Card press={false} className="mb-6 p-4">
                <div className="mb-4 flex items-center justify-between">
                    <div>
                        <p className="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Kalender Kehadiran Harian</p>
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
                        const isSelected = selectedDate === key;
                        return (
                            <button key={key} type="button" onClick={() => setSelectedDate(isSelected ? null : key)} aria-label={`${format(day, 'd MMMM', { locale: id })}: ${status ? getStatusBadge(status).label : 'Tidak ada catatan'}`} className={`flex aspect-square items-center justify-center rounded-lg text-xs font-bold tabular-nums ring-1 transition-all ${isSelected ? 'ring-2 ring-primary ring-offset-1' : ''} ${status ? calendarTone[status] : 'bg-slate-50 text-slate-400 ring-slate-100'}`}>
                                {format(day, 'd')}
                            </button>
                        );
                    })}
                </div>
                <div className="mt-4 flex flex-wrap gap-x-3 gap-y-2 border-t border-slate-100 pt-3 text-[10px] font-semibold text-slate-600">
                    {[['bg-emerald-400', 'Hadir'], ['bg-amber-400', 'Telat'], ['bg-sky-400', 'Izin/Sakit'], ['bg-rose-400', 'Alpa']].map(([color, label]) => (
                        <span key={label} className="inline-flex items-center gap-1.5"><span className={`h-2 w-2 rounded-full ${color}`} />{label}</span>
                    ))}
                </div>
            </Card>

            {/* Detail per tanggal */}
            {selectedDate && (
                <div className="mt-2 space-y-2.5">
                    <div className="flex items-center gap-2 px-1">
                        <CalendarDays className="h-4 w-4 text-emerald-400" />
                        <p className="text-sm font-extrabold text-slate-700">{formatTanggal(selectedDate)}</p>
                        <span className="text-xs font-medium text-slate-400">• {dayName(selectedDate)}</span>
                    </div>
                    {groupedByMapel.length === 0 ? (
                        <Empty icon={History} title="Tidak ada presensi" subtitle="Belum ada catatan kehadiran untuk tanggal ini." />
                    ) : (
                        groupedByMapel.map((g) => {
                            if (g.type === 'single') {
                                const p = g.item;
                                const b = getStatusBadge(p.status);
                                const label = p.is_lembur ? 'Lembur' : p.tipe_presensi === 'kantor' ? 'Presensi Kantor' : 'Presensi';
                                const isKantor = p.tipe_presensi === 'kantor';
                                const belumPulang = Boolean(p.jam_masuk) && !p.jam_keluar;
                                const badge = isKantor && belumPulang
                                    ? { tone: 'amber', label: 'Belum pulang', icon: Clock }
                                    : b;
                                return (
                                    <Card key={p.id} className="flex items-center justify-between py-3.5">
                                        <div className="min-w-0">
                                            <p className="truncate font-bold text-slate-800">{label}</p>
                                            <p className="mt-0.5 flex items-center gap-3 text-xs text-slate-500">
                                                <span className="inline-flex items-center gap-1"><Clock className="h-3 w-3 text-emerald-400" />{formatJam(p.jam_masuk)}–{formatJam(p.jam_keluar)}</span>
                                                {p.jarak_meter != null && (
                                                    <span className="inline-flex items-center gap-1"><MapPin className="h-3 w-3 text-emerald-400" />{p.jarak_meter}m</span>
                                                )}
                                            </p>
                                        </div>
                                        <Badge tone={badge.tone} icon={badge.icon}>{badge.label}</Badge>
                                    </Card>
                                );
                            }

                            const expanded = expandedMapel.has(g.nama);
                            const b = getStatusBadge(g.items[0].status);
                            const kelas = g.items[0].jadwal?.kelas_label;
                            return (
                                <div key={g.nama}>
                                    <button type="button" onClick={() => toggleMapel(g.nama)} className="w-full">
                                        <Card className="flex items-center justify-between py-3.5">
                                            <div className="min-w-0">
                                                <div className="flex items-center gap-2">
                                                    <p className="truncate font-bold text-slate-800">{g.nama}</p>
                                                    <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500">{g.count} JP</span>
                                                </div>
                                                <p className="mt-0.5 flex items-center gap-3 text-xs text-slate-500">
                                                    <span className="inline-flex items-center gap-1"><Clock className="h-3 w-3 text-emerald-400" />{g.timeRange}</span>
                                                    {kelas && <span className="inline-flex items-center gap-1 text-slate-400">{kelas}</span>}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Badge tone={b.tone} icon={b.icon}>{b.label}</Badge>
                                                <ChevronDown className={`h-4 w-4 text-slate-400 transition-transform ${expanded ? 'rotate-180' : ''}`} />
                                            </div>
                                        </Card>
                                    </button>
                                    {expanded && (
                                        <div className="ml-4 mt-1 space-y-1 border-l-2 border-slate-100 pl-3">
                                            {g.items.map((p) => {
                                                const ib = getStatusBadge(p.status);
                                                return (
                                                    <div key={p.id} className="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2">
                                                        <span className="text-xs font-semibold text-slate-600">{formatJam(p.jadwal?.jam_mulai)}–{formatJam(p.jadwal?.jam_selesai)}</span>
                                                        <Badge tone={ib.tone} icon={ib.icon}>{ib.label}</Badge>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>
                            );
                        })
                    )}
                </div>
            )}
        </MobileLayout>
    );
}
