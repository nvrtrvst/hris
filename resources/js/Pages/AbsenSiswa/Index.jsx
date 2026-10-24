import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { format } from 'date-fns';
import { id } from 'date-fns/locale/id';
import { ClipboardList, Search } from 'lucide-react';

const STATUS_META = {
    telat: { label: 'Telat', badge: 'bg-amber-50 text-amber-700 border-amber-200' },
    izin: { label: 'Izin', badge: 'bg-sky-50 text-sky-700 border-sky-200' },
    sakit: { label: 'Sakit', badge: 'bg-purple-50 text-purple-700 border-purple-200' },
    alpa: { label: 'Alpa', badge: 'bg-rose-50 text-rose-700 border-rose-200' },
    hadir: { label: 'Hadir', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
};

const KPI = [
    { key: 'hadir', label: 'Hadir', cls: 'text-emerald-600' },
    { key: 'telat', label: 'Telat', cls: 'text-amber-600' },
    { key: 'izin', label: 'Izin', cls: 'text-sky-600' },
    { key: 'sakit', label: 'Sakit', cls: 'text-purple-600' },
    { key: 'alpa', label: 'Alpa', cls: 'text-rose-600' },
];

const fmtTanggal = (d) => {
    if (!d) return '-';

    try {
        return format(new Date(`${d}T00:00:00`), 'dd MMM yyyy', { locale: id });
    } catch {
        return d;
    }
};

export default function Index({ auth, sesi = [], filters = {}, units = [] }) {
    const [tanggal, setTanggal] = React.useState(filters.tanggal || format(new Date(), 'yyyy-MM-dd'));
    const [unitId, setUnitId] = React.useState(filters.unit_id || '');
    const [cari, setCari] = React.useState('');

    const apply = (overrides = {}) => {
        router.get(
            route('absen-siswa.index'),
            { tanggal: tanggal || undefined, unit_id: unitId || undefined, ...overrides },
            { preserveState: true, preserveScroll: true },
        );
    };

    const total = React.useMemo(() => sesi.reduce((acc, s) => {
        KPI.forEach(({ key }) => { acc[key] += Number(s.counts[key] || 0); });
        acc.siswa += Number(s.total || 0);

        return acc;
    }, { hadir: 0, telat: 0, izin: 0, sakit: 0, alpa: 0, siswa: 0 }), [sesi]);

    const persenHadir = total.siswa > 0 ? Math.round((total.hadir / total.siswa) * 100) : 0;
    const q = cari.trim().toLowerCase();
    const tampil = q
        ? sesi.filter((s) => s.class_label?.toLowerCase().includes(q) || s.pegawai?.toLowerCase().includes(q))
        : sesi;

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="page-title">Absen Siswa</h2>}>
            <Head title="Absen Siswa" />
            <div className="bg-surface min-h-screen">
                <div className="mx-auto w-full space-y-6">
                    <div className="rounded-xl border border-border bg-white p-4">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <label className="mb-1 block text-[11px] font-bold text-text-secondary">Tanggal</label>
                                <input type="date" value={tanggal} onChange={(e) => { setTanggal(e.target.value); apply({ tanggal: e.target.value }); }} className="select-field h-11 w-full text-xs" />
                            </div>
                            {units.length > 0 && (
                                <div>
                                    <label className="mb-1 block text-[11px] font-bold text-text-secondary">Unit</label>
                                    <select value={unitId} onChange={(e) => { setUnitId(e.target.value); apply({ unit_id: e.target.value || undefined }); }} className="select-field h-11 w-full text-xs">
                                        <option value="">Semua Unit</option>
                                        {units.map((u) => <option key={u.id} value={u.id}>{u.nama}</option>)}
                                    </select>
                                </div>
                            )}
                            <div>
                                <label className="mb-1 block text-[11px] font-bold text-text-secondary">Cari kelas / petugas</label>
                                <div className="relative">
                                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-secondary" />
                                    <input value={cari} onChange={(e) => setCari(e.target.value)} placeholder="Mis. VII A" className="select-field h-11 w-full pl-9 text-xs" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                        <div className="rounded-xl border border-border bg-white p-3">
                            <p className="text-[11px] font-bold text-text-secondary">Kelas diabsen</p>
                            <p className="mt-1 text-xl font-bold tabular-nums text-text-primary">{sesi.length}</p>
                        </div>
                        <div className="rounded-xl border border-border bg-white p-3">
                            <p className="text-[11px] font-bold text-text-secondary">Siswa</p>
                            <p className="mt-1 text-xl font-bold tabular-nums text-text-primary">{total.siswa}</p>
                        </div>
                        {KPI.map((k) => (
                            <div key={k.key} className="rounded-xl border border-border bg-white p-3">
                                <p className="text-[11px] font-bold text-text-secondary">{k.label}</p>
                                <p className={`mt-1 text-xl font-bold tabular-nums ${k.cls}`}>{total[k.key]}</p>
                            </div>
                        ))}
                    </div>

                    <p className="text-xs font-semibold text-text-secondary">
                        {fmtTanggal(tanggal)} · kehadiran {persenHadir}%
                    </p>

                    {tampil.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-border bg-white px-4 py-10 text-center">
                            <ClipboardList className="mx-auto h-8 w-8 text-text-secondary" />
                            <p className="mt-2 text-sm font-semibold text-text-secondary">Belum ada kelas yang diabsen pada tanggal ini.</p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
                            {tampil.map((s) => (
                                <div key={s.id} className="rounded-xl border border-border bg-white p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-bold text-text-primary">{s.class_label}</p>
                                            <p className="text-xs text-text-secondary">{s.unit || '-'}</p>
                                        </div>
                                        <span className="shrink-0 text-xs font-semibold text-text-secondary">{s.done_at}</span>
                                    </div>

                                    <div className="mt-3 grid grid-cols-5 gap-1 text-center">
                                        {KPI.map((k) => (
                                            <div key={k.key} className="rounded-lg bg-surface py-1.5">
                                                <p className="text-[10px] font-bold text-text-secondary">{k.label}</p>
                                                <p className={`text-sm font-bold tabular-nums ${k.cls}`}>{s.counts[k.key]}</p>
                                            </div>
                                        ))}
                                    </div>

                                    {s.non_hadir.length > 0 ? (
                                        <ul className="mt-3 space-y-1">
                                            {s.non_hadir.map((d) => {
                                                const meta = STATUS_META[d.status] || STATUS_META.alpa;

                                                return (
                                                    <li key={d.nis} className="flex items-start justify-between gap-2 text-xs">
                                                        <span className="min-w-0">
                                                            <span className="font-semibold text-text-primary">{d.nama}</span>
                                                            {d.keterangan && <span className="text-text-secondary"> — {d.keterangan}</span>}
                                                        </span>
                                                        <span className={`shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-bold ${meta.badge}`}>{meta.label}</span>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    ) : (
                                        <p className="mt-3 text-xs font-semibold text-emerald-600">Semua siswa hadir.</p>
                                    )}

                                    <p className="mt-3 border-t border-border pt-2 text-[11px] text-text-secondary">
                                        Diabsen oleh {s.pegawai || '-'} · {s.total} siswa
                                    </p>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
