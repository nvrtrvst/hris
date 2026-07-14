import React, { useState, useMemo, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card, SectionTitle, Badge, Empty } from '@/Components/MobileUI';
import { parseISO, format, startOfMonth, endOfMonth, eachDayOfInterval, isSameDay } from 'date-fns';
import { id } from 'date-fns/locale';
import { History, CalendarDays, Clock, MapPin, CheckCircle2, AlertTriangle, FileText, XCircle, Ban } from 'lucide-react';

export default function Riwayat({ auth, presensi, filters }) {
    const [monthFilter, setMonthFilter] = useState(filters?.bulan || '');

    useEffect(() => {
        setMonthFilter(filters?.bulan || '');
    }, [filters]);

    const months = useMemo(() => {
        const set = new Set();
        (presensi || []).forEach((p) => {
            if (p.tanggal) set.add(p.tanggal.substring(0, 7));
        });
        const arr = Array.from(set).sort().reverse();
        if (arr.length === 0) arr.push(format(new Date(), 'yyyy-MM'));
        return arr;
    }, [presensi]);

    const firstMonth = months[0] || format(new Date(), 'yyyy-MM');
    const activeMonth = monthFilter || firstMonth;

    const handleFilter = (e) => {
        const val = e.target.value;
        setMonthFilter(val);
        router.get(
            route('mobile.riwayat'),
            { bulan: val },
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

    const stats = useMemo(() => {
        const s = { hadir: 0, telat: 0, sakit: 0, izin: 0, cuti: 0, alpa: 0 };
        (presensi || []).forEach((p) => {
            if (s[p.status] !== undefined) s[p.status]++;
        });
        return s;
    }, [presensi]);

    const statList = [
        { key: 'hadir', label: 'Hadir', tone: 'emerald', icon: CheckCircle2 },
        { key: 'telat', label: 'Telat', tone: 'amber', icon: AlertTriangle },
        { key: 'sakit', label: 'Sakit', tone: 'sky', icon: FileText },
        { key: 'izin', label: 'Izin', tone: 'sky', icon: FileText },
        { key: 'cuti', label: 'Cuti', tone: 'sky', icon: FileText },
        { key: 'alpa', label: 'Alpa', tone: 'rose', icon: XCircle },
    ];

    return (
        <MobileLayout user={auth.user}>
            <Head title="Riwayat Presensi" />

            <div className="mb-5 px-1">
                <h1 className="text-2xl font-extrabold tracking-tight text-slate-800">Riwayat</h1>
                <p className="mt-0.5 text-sm text-slate-500">Rekap kehadiran Anda</p>
            </div>

            {/* Month filter */}
            <div className="mb-5 flex gap-2 overflow-x-auto pb-1">
                {months.map((m) => {
                    const isActive = m === activeMonth;
                    return (
                        <button
                            key={m}
                            value={m}
                            onClick={() => handleFilter({ target: { value: m } })}
                            className={`flex-shrink-0 rounded-2xl px-4 py-2.5 text-sm font-bold transition-all active:scale-95 ${
                                isActive ? 'bg-emerald-500 text-white shadow-[0_8px_20px_-6px_rgba(79,70,229,0.6)]' : 'bg-white text-slate-500 ring-1 ring-slate-200'
                            }`}
                        >
                            {format(parseISO(m + '-01'), 'MMM yyyy', { locale: id })}
                        </button>
                    );
                })}
            </div>

            {/* Stats */}
            <div className="mb-5 grid grid-cols-3 gap-2.5">
                {statList.map((s) => (
                    <Card key={s.key} className="flex flex-col items-center py-3.5">
                        <s.icon className={`mb-1 h-5 w-5 ${
                            s.tone === 'emerald' ? 'text-emerald-500' : s.tone === 'amber' ? 'text-amber-500' : s.tone === 'sky' ? 'text-emerald-500' : 'text-rose-500'
                        }`} />
                        <p className="text-xl font-extrabold leading-none text-slate-800">{stats[s.key]}</p>
                        <p className="mt-0.5 text-[11px] font-medium text-slate-500">{s.label}</p>
                    </Card>
                ))}
            </div>

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
