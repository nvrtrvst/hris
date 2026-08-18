import React, { useState, Component, useMemo } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import usePolling from '@/Utils/usePolling';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import { avatarTone, initials } from '@/Utils/avatar';
import {
    AlertTriangle,
    ArrowRight,
    Banknote,
    Building2,
    CalendarDays,
    CalendarClock,
    CheckCircle2,
    Clock3,
    FileWarning,
    Hourglass,
    School,
    TrendingUp,
    UserCheck,
    Users,
} from 'lucide-react';

class ErrorBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false, error: null, errorInfo: null };
    }

    static getDerivedStateFromError() {
        return { hasError: true };
    }

    componentDidCatch(error, errorInfo) {
        this.setState({ error, errorInfo });
        console.error('Dashboard Error:', error, errorInfo);
    }

    render() {
        if (this.state.hasError) {
            return (
                <div className="card p-6">
                    <h2 className="font-semibold text-danger">Terjadi kesalahan di Dashboard.</h2>
                    <details className="mt-2 text-sm text-text-muted whitespace-pre-wrap">
                        {this.state.error && this.state.error.toString()}
                        <br />
                        {this.state.errorInfo && this.state.errorInfo.componentStack}
                    </details>
                </div>
            );
        }
        return this.props.children;
    }
}

const STATUS_META = {
    hadir: { label: 'Hadir', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    telat: { label: 'Telat', badge: 'bg-amber-50 text-amber-700 border-amber-200' },
    sakit: { label: 'Sakit', badge: 'bg-purple-50 text-purple-700 border-purple-200' },
    izin: { label: 'Izin', badge: 'bg-blue-50 text-blue-700 border-blue-200' },
    cuti: { label: 'Cuti', badge: 'bg-cyan-50 text-cyan-700 border-cyan-200' },
    alpa: { label: 'Alpa', badge: 'bg-rose-50 text-rose-700 border-rose-200' },
};

const StatusBadge = ({ status }) => {
    const meta = STATUS_META[status] || { label: status || '−', badge: 'bg-gray-50 text-gray-500 border-gray-200' };

    return (
        <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ${meta.badge}`}>
            {meta.label}
        </span>
    );
};

const StatCard = ({ label, value, Icon, iconBg, iconCls, alert }) => (
    <div className="stat-card group hover:shadow-card-hover transition-shadow">
        <div className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${iconBg} transition-transform group-hover:scale-105`}>
            <Icon className={`h-5 w-5 ${iconCls}`} />
        </div>
        <div className="min-w-0">
            <p className={`text-2xl font-extrabold leading-none tabular-nums ${alert ? 'text-danger' : 'text-primary'}`}>{value}</p>
            <p className="mt-1.5 text-[11px] font-semibold uppercase tracking-wide text-text-secondary">{label}</p>
        </div>
    </div>
);

export default function Dashboard(props) {
    return (
        <ErrorBoundary>
            <DashboardContent {...props} />
        </ErrorBoundary>
    );
}

function DashboardContent({ auth, roleType, stats, trends, kontrakBerakhir, jadwalHariIni, presensiHariIni, units = [], selectedUnitId = null, selectedUnitNama = null }) {
    const [detailPresensi, setDetailPresensi] = useState(null);

    // Live polling: refresh data kehadiran/jadwal/presensi tiap 60 detik (partial reload
    // Inertia — hanya prop yang di-`only` dikirim ulang). Berhenti saat tab tidak aktif.
    const isAdmin = roleType === 'Admin';
    usePolling({ enabled: isAdmin, only: ['stats', 'trends', 'jadwalHariIni', 'presensiHariIni'] });

    // Filter unit (superadmin): reload penuh agar semua angka ter-scope ke unit.
    const setUnitFilter = (e) => {
        const unitId = e.target.value;
        router.get(route('dashboard'), unitId ? { unit_id: unitId } : {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const presensiMap = useMemo(() => {
        const map = {};
        (presensiHariIni || []).forEach((p) => {
            const key = p.pegawai_id;
            if (!map[key] || p.jam_masuk > (map[key].jam_masuk || '')) map[key] = p;
        });
        return map;
    }, [presensiHariIni]);

    const presensiByJadwal = useMemo(() => {
        const map = {};
        (presensiHariIni || []).forEach((p) => {
            if (p.jadwal_id) map[p.jadwal_id] = p;
        });
        return map;
    }, [presensiHariIni]);

    const formatRupiah = (angka) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(angka || 0);

    const todayString = new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    const s = stats || {};
    const hasKontrak = (kontrakBerakhir || []).length > 0;
    const chartData = Array.isArray(trends) ? trends : [];

    const kontrakTone = (sisa) => {
        if (sisa <= 0) return { badge: 'bg-rose-50 text-rose-700 border-rose-200', label: 'Lewat' };
        if (sisa <= 7) return { badge: 'bg-amber-50 text-amber-700 border-amber-200', label: `${sisa} hari` };
        return { badge: 'bg-emerald-50 text-emerald-700 border-emerald-200', label: `${sisa} hari` };
    };

    return (
        <AuthenticatedLayout
            user={auth?.user}
            header={
                <div className="flex w-full flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex flex-col gap-1">
                        <h1 className="page-title">Dashboard {roleType}</h1>
                        <p className="page-subtitle">{todayString}</p>
                    </div>
                    {units.length > 0 && (
                        <div className="relative sm:mb-1 sm:w-64">
                            <Building2 className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                            <select
                                aria-label="Filter unit"
                                value={selectedUnitId || ''}
                                onChange={setUnitFilter}
                                className="select-field h-9 pl-9 text-xs font-semibold"
                            >
                                <option value="">Semua Unit</option>
                                {units.map((u) => (
                                    <option key={u.id} value={u.id}>{u.nama}</option>
                                ))}
                            </select>
                        </div>
                    )}
                </div>
            }
        >
            <Head title={`Dashboard ${roleType}`} />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    {/* Ringkasan utama */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="card p-6 relative overflow-hidden">
                            <div className="absolute -right-6 -top-6 h-28 w-28 rounded-full bg-primary/5" />
                            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-text-muted">
                                <Banknote className="h-4 w-4 text-primary" />
                                Total Biaya Payroll Bulan Ini
                            </div>
                            <p className="mt-2 text-3xl font-extrabold text-primary tabular-nums">
                                {s.pengeluaran_gaji > 0 ? formatRupiah(s.pengeluaran_gaji) : 'Rp 0'}
                            </p>
                            <div className="mt-3">
                                {s.is_estimasi_payroll ? (
                                    <span className="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[11px] font-bold text-amber-700">
                                        <Hourglass className="h-3.5 w-3.5" /> Estimasi Sementara
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-bold text-emerald-700">
                                        <CheckCircle2 className="h-3.5 w-3.5" /> Sudah Diproses
                                    </span>
                                )}
                            </div>
                        </div>

                        <div className="card p-6 relative overflow-hidden">
                            <div className="absolute -right-6 -top-6 h-28 w-28 rounded-full bg-emerald-500/5" />
                            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-text-muted">
                                <UserCheck className="h-4 w-4 text-emerald-600" />
                                Tingkat Kehadiran Hari Ini
                            </div>
                            <div className="mt-2 flex items-baseline gap-3">
                                <p className="text-3xl font-extrabold text-success tabular-nums">{s.hadir_percentage || 0}%</p>
                                <p className="text-sm text-text-secondary">
                                    ({s.hadir_hari_ini_count || 0} dari {s.pegawai_dijadwalkan || 0} guru dijadwalkan)
                                </p>
                            </div>
                            <div className="mt-4 h-2 w-full overflow-hidden rounded-full bg-border/60">
                                <div
                                    className="h-full rounded-full bg-emerald-500 transition-all duration-700"
                                    style={{ width: `${Math.min(100, s.hadir_percentage || 0)}%` }}
                                />
                            </div>
                        </div>
                    </div>

                    {/* Stat cards */}
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <StatCard label="Pegawai Aktif" value={s.total_pegawai || 0} Icon={Users} iconBg="bg-primary/10" iconCls="text-primary" />
                        <StatCard label={selectedUnitNama ? `Unit: ${selectedUnitNama}` : 'Unit Sekolah'} value={selectedUnitNama || s.total_unit || 0} Icon={School} iconBg="bg-blue-100" iconCls="text-blue-600" />
                        <StatCard
                            label="Kontrak Berakhir"
                            value={s.kontrak_berakhir_count || 0}
                            Icon={FileWarning}
                            iconBg={s.kontrak_berakhir_count > 0 ? 'bg-amber-100' : 'bg-surface'}
                            iconCls={s.kontrak_berakhir_count > 0 ? 'text-amber-600' : 'text-border'}
                            alert={s.kontrak_berakhir_count > 0}
                        />
                        <StatCard label="Pengajuan Pending" value={s.pengajuan_pending || 0} Icon={Clock3} iconBg={s.pengajuan_pending > 0 ? 'bg-rose-100' : 'bg-surface'} iconCls={s.pengajuan_pending > 0 ? 'text-rose-600' : 'text-border'} alert={s.pengajuan_pending > 0} />
                    </div>

                    {/* Jadwal Hari Ini */}
                    <div className="card p-0 overflow-hidden">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-border/60 bg-surface/50 px-5 py-4">
                            <h3 className="flex items-center gap-2 text-base font-bold text-primary">
                                <CalendarDays className="h-5 w-5 text-primary" />
                                Jadwal Hari Ini
                                <span className="text-xs font-semibold text-text-secondary normal-case">
                                    {new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}
                                </span>
                            </h3>
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/5 px-3 py-1 text-[11px] font-bold text-primary">
                                <Users className="h-3.5 w-3.5" /> {s.pegawai_dijadwalkan || 0} guru dijadwalkan
                            </span>
                        </div>

                        {jadwalHariIni && jadwalHariIni.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-border text-sm">
                                    <thead className="bg-surface/80 sticky top-0 z-10 backdrop-blur-sm">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Jam</th>
                                            <th className="px-4 py-3 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Mapel</th>
                                            <th className="px-4 py-3 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Kelas</th>
                                            <th className="px-4 py-3 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Guru</th>
                                            <th className="px-4 py-3 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Unit</th>
                                            <th className="px-4 py-3 text-center text-[11px] font-bold text-text-secondary uppercase tracking-wider">Ngajar</th>
                                            <th className="px-4 py-3 text-center text-[11px] font-bold text-text-secondary uppercase tracking-wider">Harian</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border/50">
                                        {jadwalHariIni.map((j, i) => {
                                            const ngajar = presensiByJadwal[j.id];
                                            const harian = presensiMap[j.pegawai_id];

                                            return (
                                                <tr
                                                    key={j.id || i}
                                                    onClick={() => setDetailPresensi(detailPresensi === j.pegawai_id ? null : j.pegawai_id)}
                                                    className={`hover:bg-surface/70 transition-colors cursor-pointer ${detailPresensi === j.pegawai_id ? 'bg-surface/70' : ''}`}
                                                >
                                                    <td className="px-4 py-3 font-mono text-sm font-semibold text-primary whitespace-nowrap">
                                                        {j.jam_mulai?.substring(0, 5)}–{j.jam_selesai?.substring(0, 5)}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <span className="font-medium text-text-primary">{j.mata_pelajaran?.nama || j.jenis_jadwal || '-'}</span>
                                                    </td>
                                                    <td className="px-4 py-3 text-text-secondary">{j.kelas_label || '-'}</td>
                                                    <td className="px-4 py-3 whitespace-nowrap">
                                                        <div className="flex items-center gap-2">
                                                            <span className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[10px] font-extrabold ${avatarTone(j.pegawai?.nama_lengkap)}`}>
                                                                {initials(j.pegawai?.nama_lengkap)}
                                                            </span>
                                                            <span className="font-semibold text-text-primary">{j.pegawai?.nama_lengkap || '-'}</span>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3 text-xs text-text-muted">{j.unit_sekolah?.singkatan || j.unit_sekolah?.nama || '-'}</td>
                                                    <td className="px-4 py-3 text-center">{ngajar ? <StatusBadge status={ngajar.status} /> : <span className="text-xs text-text-muted">—</span>}</td>
                                                    <td className="px-4 py-3 text-center">{harian ? <StatusBadge status={harian.status} /> : <span className="text-xs text-text-muted">—</span>}</td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className="flex flex-col items-center py-12 text-center">
                                <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-surface">
                                    <CalendarDays className="h-7 w-7 text-border" />
                                </div>
                                <p className="mt-3 text-sm font-bold text-primary">Belum ada jadwal untuk hari ini</p>
                                <p className="mt-1 text-xs text-text-secondary">Jadwal mengajar akan tampil di sini.</p>
                            </div>
                        )}

                        {detailPresensi && (() => {
                            const pList = (presensiHariIni || []).filter((pr) => pr.pegawai_id === detailPresensi);
                            if (pList.length === 0) return null;
                            const nama = pList[0]?.pegawai?.nama_lengkap || 'Guru';
                            const harian = presensiMap[detailPresensi];
                            const pNgajar = pList.filter((pr) => pr.jadwal_id);

                            return (
                                <div className="border-t border-border bg-surface/40 px-5 py-4">
                                    <div className="mb-3 flex items-center justify-between">
                                        <h4 className="text-sm font-bold text-primary">{nama}</h4>
                                        <button type="button" onClick={() => setDetailPresensi(null)} className="cursor-pointer text-xs font-semibold text-text-muted transition-colors hover:text-primary">
                                            Tutup
                                        </button>
                                    </div>
                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                        <div className="rounded-lg border border-border bg-white p-3">
                                            <p className="mb-2 text-[10px] font-bold uppercase tracking-wider text-text-muted">Presensi Kantor</p>
                                            <div className="grid grid-cols-2 gap-2 text-xs">
                                                <div>
                                                    <span className="text-text-muted">Masuk</span>
                                                    <p className="font-semibold text-primary">{harian?.jam_masuk?.substring(0, 5) || '—'}</p>
                                                </div>
                                                <div>
                                                    <span className="text-text-muted">Keluar</span>
                                                    <p className="font-semibold text-primary">{harian?.jam_keluar?.substring(0, 5) || '—'}</p>
                                                </div>
                                                <div>
                                                    <span className="text-text-muted">Status</span>
                                                    <p className="mt-0.5">{harian ? <StatusBadge status={harian.status} /> : '—'}</p>
                                                </div>
                                                <div>
                                                    <span className="text-text-muted">Jarak</span>
                                                    <p className="font-semibold">{harian?.jarak_meter != null ? `${harian.jarak_meter}m` : '—'}</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="rounded-lg border border-border bg-white p-3">
                                            <p className="mb-2 text-[10px] font-bold uppercase tracking-wider text-text-muted">Per Jadwal Mengajar</p>
                                            {pNgajar.length > 0 ? pNgajar.slice(0, 3).map((p, i) => (
                                                <div key={i} className="mb-1.5 flex items-center gap-2 text-xs last:mb-0">
                                                    <span className="font-mono text-text-muted">{p.jam_masuk?.substring(0, 5)}–{p.jam_keluar?.substring(0, 5) || '?'}</span>
                                                    <StatusBadge status={p.status} />
                                                </div>
                                            )) : <p className="text-xs text-text-muted">Belum ada absen per jadwal</p>}
                                        </div>
                                    </div>
                                    {harian?.lokasi_perlu_review && (
                                        <div className="mt-3 inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-[11px] font-semibold text-amber-700">
                                            <AlertTriangle className="h-3.5 w-3.5" /> Lokasi perlu ditinjau admin
                                        </div>
                                    )}
                                </div>
                            );
                        })()}
                    </div>

                    {/* Charts & kontrak */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div className="card p-6">
                            <h3 className="mb-4 flex items-center gap-2 text-base font-bold text-primary">
                                <TrendingUp className="h-5 w-5 text-primary" />
                                Tren Kehadiran 7 Hari
                            </h3>
                            <div className="h-64 w-full">
                                {chartData.length === 0 ? (
                                    <div className="flex h-full flex-col items-center justify-center text-center">
                                        <TrendingUp className="mb-2 h-8 w-8 text-border" />
                                        <p className="text-sm text-text-secondary">Belum ada data kehadiran.</p>
                                    </div>
                                ) : (
                                    <ResponsiveContainer width="100%" height="100%">
                                        <LineChart data={chartData} margin={{ top: 5, right: 20, bottom: 5, left: 0 }}>
                                            <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E5E7EB" />
                                            <XAxis dataKey="day" tick={{ fontSize: 11 }} />
                                            <YAxis tick={{ fontSize: 11 }} allowDecimals={false} />
                                            <Tooltip
                                                formatter={(value) => [value, 'Hadir']}
                                                contentStyle={{ borderRadius: 12, border: '1px solid #E5E7EB', fontSize: 12 }}
                                            />
                                            <Line type="monotone" dataKey="hadir" stroke="#1B4A4A" strokeWidth={3} dot={{ r: 4 }} activeDot={{ r: 6 }} />
                                        </LineChart>
                                    </ResponsiveContainer>
                                )}
                            </div>
                        </div>

                        <div className="card p-0 overflow-hidden">
                            <div className="flex items-center justify-between border-b border-border/60 bg-surface/50 px-5 py-4">
                                <h3 className="flex items-center gap-2 text-base font-bold text-primary">
                                    <CalendarClock className="h-5 w-5 text-amber-600" />
                                    Kontrak Berakhir (30 Hari)
                                </h3>
                                {hasKontrak && (
                                    <Link href={route('pegawai.index')} className="flex items-center gap-1 text-xs font-bold text-primary transition-colors hover:text-primary/70">
                                        Lihat pegawai <ArrowRight className="h-3.5 w-3.5" />
                                    </Link>
                                )}
                            </div>
                            {hasKontrak ? (
                                <div className="divide-y divide-border/50">
                                    {kontrakBerakhir.slice(0, 5).map((pegawai) => {
                                        const tone = kontrakTone(pegawai.sisa_hari);

                                        return (
                                            <div key={pegawai.id} className="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-surface/60">
                                                <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[11px] font-extrabold ${avatarTone(pegawai.nama_lengkap)}`}>
                                                    {initials(pegawai.nama_lengkap)}
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <div className="truncate text-sm font-bold text-primary">{pegawai.nama_lengkap}</div>
                                                    <div className="text-[11px] text-text-secondary">{pegawai.unit_nama}</div>
                                                </div>
                                                <div className="shrink-0 text-right">
                                                    <div className="text-xs font-semibold text-text-primary">{pegawai.kontrak_berakhir}</div>
                                                    <span className={`mt-0.5 inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold ${tone.badge}`}>
                                                        {pegawai.sisa_hari <= 0 ? 'Lewat' : `Sisa ${tone.label}`}
                                                    </span>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            ) : (
                                <div className="flex flex-col items-center py-12 text-center">
                                    <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-surface">
                                        <CheckCircle2 className="h-7 w-7 text-emerald-500" />
                                    </div>
                                    <p className="mt-3 text-sm font-bold text-primary">Tidak ada kontrak berakhir</p>
                                    <p className="mt-1 text-xs text-text-secondary">Semua kontrak aman dalam 30 hari ke depan.</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
