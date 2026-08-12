import React, { useState, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { avatarTone, initials } from '@/Utils/avatar';
import {
    ArrowRight,
    Banknote,
    CalendarDays,
    CheckCircle2,
    Clock3,
    Eye,
    FileText,
    Loader2,
    Lock,
    RotateCcw,
    Settings2,
    Sparkles,
    Trash2,
    Users,
    Wallet,
} from 'lucide-react';

const STATUS_META = {
    draft: { label: 'Draft', badge: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-500' },
    finalized: { label: 'Finalized', badge: 'bg-blue-50 text-blue-700 border-blue-200', dot: 'bg-blue-500' },
    paid: { label: 'Dibayar', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' },
};

const StatusBadge = ({ status }) => {
    const meta = STATUS_META[status] || { label: status, badge: 'bg-gray-50 text-gray-700 border-gray-200', dot: 'bg-gray-400' };

    return (
        <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border ${meta.badge}`}>
            <span className={`h-1.5 w-1.5 rounded-full ${meta.dot}`} />
            {meta.label}
        </span>
    );
};

const StatCard = ({ label, value, sub, Icon, iconBg, iconCls }) => (
    <div className="stat-card group hover:shadow-card-hover transition-shadow">
        <div className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${iconBg} transition-transform group-hover:scale-105`}>
            <Icon className={`h-5 w-5 ${iconCls}`} />
        </div>
        <div className="min-w-0">
            <p className="text-2xl font-extrabold leading-none text-primary tabular-nums">{value}</p>
            <p className="mt-1.5 text-[11px] font-semibold uppercase tracking-wide text-text-secondary">{label}</p>
            {sub && <p className="mt-0.5 text-[10px] text-text-muted truncate">{sub}</p>}
        </div>
    </div>
);

const formatRupiah = (angka) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(angka || 0);

export default function Index({ auth, penggajians, stats = {}, periodeOptions = [], filters = {} }) {
    const isAdmin = auth.permissions?.includes('view_payroll');
    const isAdminUnit = isAdmin && !auth.permissions?.includes('view_all_units');
    const { flash = {} } = usePage().props;
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        const onStart = () => setProcessing(true);
        const onFinish = () => setProcessing(false);
        router.on('start', onStart);
        router.on('finish', onFinish);

        return () => {
            router.off('start', onStart);
            router.off('finish', onFinish);
        };
    }, []);

    const [filterPeriode, setFilterPeriode] = useState(filters.periode_bulan || '');
    const [filterStatus, setFilterStatus] = useState(filters.status || '');

    const hasFilter = Boolean(filterPeriode || filterStatus);

    const resetFilters = () => {
        setFilterPeriode('');
        setFilterStatus('');
        router.get(route('penggajian.index'), {}, { preserveState: true });
    };

    const s = stats || { total: 0, total_bersih: 0, draft: 0, finalized: 0, paid: 0 };

    const statCards = [
        { label: 'Total Slip', value: s.total, sub: 'terfilter', Icon: FileText, iconBg: 'bg-primary/10', iconCls: 'text-primary' },
        { label: 'Total Gaji Bersih', value: formatRupiah(s.total_bersih), Icon: Wallet, iconBg: 'bg-emerald-100', iconCls: 'text-emerald-600' },
        { label: 'Draft', value: s.draft, Icon: Clock3, iconBg: 'bg-amber-100', iconCls: 'text-amber-600' },
        { label: 'Finalized', value: s.finalized, Icon: Lock, iconBg: 'bg-blue-100', iconCls: 'text-blue-600' },
        { label: 'Dibayar', value: s.paid, Icon: CheckCircle2, iconBg: 'bg-cyan-100', iconCls: 'text-cyan-600' },
    ];

    const filterSelect = 'select-field text-xs h-9 w-full md:w-auto md:min-w-[150px]';

    const unitName = (p) => p.pegawai?.units?.[0]?.singkatan || p.pegawai?.units?.[0]?.nama || '—';

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Sistem Penggajian (Payroll)</h2>}
        >
            <Head title="Penggajian" />
            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    {/* Flash */}
                    {flash.message && <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.message}</div>}
                    {flash.error && <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{flash.error}</div>}

                    {/* Header */}
                    <div className="flex flex-col md:flex-row md:items-end justify-between gap-3">
                        <div>
                            <h3 className="text-xl font-bold text-primary">Riwayat Penggajian Pegawai</h3>
                            <p className="mt-1 text-sm text-text-secondary">Pantau slip gaji per periode, status draft → final → dibayar.</p>
                        </div>
                        {filterPeriode ? (
                            <p className="flex items-center gap-2 text-xs font-medium text-text-secondary">
                                <CalendarDays className="h-4 w-4 text-primary" />
                                Periode {filterPeriode}
                            </p>
                        ) : null}
                    </div>

                    {/* Stats */}
                    {isAdmin && (
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                            {statCards.map((card) => <StatCard key={card.label} {...card} />)}
                        </div>
                    )}

                    {/* Quick actions */}
                    {isAdmin && (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="card p-5 flex items-center justify-between gap-4 transition-shadow hover:shadow-card-hover">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                                        <Settings2 className="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <h4 className="text-sm font-bold text-primary">Konfigurasi Komponen</h4>
                                        <p className="mt-0.5 text-xs text-text-secondary">Gaji Pokok, PPh21, BPJS, Tunjangan & Potongan</p>
                                    </div>
                                </div>
                                <Link href={route('komponen-gaji.index')} className="btn-secondary btn-sm flex items-center gap-1.5 shrink-0">
                                    Kelola <ArrowRight className="h-3.5 w-3.5" />
                                </Link>
                            </div>
                            <div className="rounded-xl bg-primary p-5 flex items-center justify-between gap-4 shadow-card">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/10">
                                        <Sparkles className="h-5 w-5 text-accent" />
                                    </div>
                                    <div>
                                        <h4 className="text-sm font-bold text-white">Proses Penggajian Baru</h4>
                                        <p className="mt-0.5 text-xs text-white/70">Wizard: tarik absensi, jadwal, dan hitung otomatis</p>
                                    </div>
                                </div>
                                <Link href={route('penggajian.run')} className="btn-sm shrink-0 rounded-lg bg-white text-primary hover:bg-surface flex items-center gap-1.5 font-bold">
                                    Run Wizard <ArrowRight className="h-3.5 w-3.5" />
                                </Link>
                            </div>
                        </div>
                    )}

                    {/* Filter bar */}
                    {isAdmin && (
                        <div className="card p-5">
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <select
                                    className={filterSelect}
                                    value={filterPeriode}
                                    onChange={(e) => {
                                        const val = e.target.value;
                                        setFilterPeriode(val);
                                        router.get(route('penggajian.index'), { periode_bulan: val, status: filterStatus }, { preserveState: true, preserveScroll: true });
                                    }}
                                >
                                    <option value="">Semua Periode</option>
                                    {periodeOptions.map((p) => <option key={p} value={p}>{p}</option>)}
                                </select>
                                <select
                                    className={filterSelect}
                                    value={filterStatus}
                                    onChange={(e) => {
                                        const val = e.target.value;
                                        setFilterStatus(val);
                                        router.get(route('penggajian.index'), { periode_bulan: filterPeriode, status: val }, { preserveState: true, preserveScroll: true });
                                    }}
                                >
                                    <option value="">Semua Status</option>
                                    {Object.entries(STATUS_META).map(([key, m]) => <option key={key} value={key}>{m.label}</option>)}
                                </select>
                            </div>
                            <div className="flex items-center gap-2 border-t border-border/60 pt-4">
                                {hasFilter && (
                                    <button type="button" onClick={resetFilters} className="btn-secondary btn-sm flex items-center gap-1.5">
                                        <RotateCcw className="h-3.5 w-3.5" /> Reset Filter
                                    </button>
                                )}
                                <p className="ml-auto text-[11px] text-text-muted">Filter diterapkan otomatis saat dipilih.</p>
                            </div>
                        </div>
                    )}

                    {/* Table */}
                    <div className={`card p-0 overflow-hidden transition-opacity ${processing ? 'opacity-60 pointer-events-none' : ''}`}>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-border">
                                <thead className="bg-surface/80 sticky top-0 z-10 backdrop-blur-sm">
                                    <tr>
                                        <th className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Periode</th>
                                        <th className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Pegawai</th>
                                        <th className="px-4 py-3.5 text-right text-[11px] font-bold text-text-secondary uppercase tracking-wider">Pendapatan</th>
                                        <th className="px-4 py-3.5 text-right text-[11px] font-bold text-text-secondary uppercase tracking-wider">Potongan</th>
                                        <th className="px-4 py-3.5 text-right text-[11px] font-bold text-text-secondary uppercase tracking-wider">Gaji Bersih</th>
                                        <th className="px-4 py-3.5 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Status</th>
                                        <th className="px-4 py-3.5 text-right text-[11px] font-bold text-text-secondary uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/50">
                                    {penggajians.data.length > 0 ? penggajians.data.map((p) => {
                                        const nama = p.pegawai?.nama_lengkap || '-';

                                        return (
                                            <tr key={p.id} className="group hover:bg-surface/70 transition-colors">
                                                <td className="px-4 py-3.5 whitespace-nowrap">
                                                    <span className="inline-flex items-center rounded-full bg-primary/5 px-3 py-1 font-mono text-xs font-bold text-primary">{p.periode_bulan}</span>
                                                </td>
                                                <td className="px-4 py-3.5 whitespace-nowrap">
                                                    <div className="flex items-center gap-3">
                                                        <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[11px] font-extrabold ${avatarTone(nama)}`}>
                                                            {initials(nama)}
                                                        </span>
                                                        <div className="min-w-0">
                                                            <div className="text-sm font-bold text-primary truncate max-w-[200px]">{nama}</div>
                                                            <div className="text-[11px] text-text-secondary">{unitName(p)}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3.5 whitespace-nowrap text-right text-sm font-medium text-emerald-600 tabular-nums">
                                                    {formatRupiah(p.total_pendapatan)}
                                                </td>
                                                <td className="px-4 py-3.5 whitespace-nowrap text-right text-sm font-medium text-rose-600 tabular-nums">
                                                    {formatRupiah(p.total_potongan)}
                                                </td>
                                                <td className="px-4 py-3.5 whitespace-nowrap text-right text-base font-bold text-primary tabular-nums">
                                                    {formatRupiah(p.gaji_bersih)}
                                                </td>
                                                <td className="px-4 py-3.5 whitespace-nowrap">
                                                    <StatusBadge status={p.status} />
                                                </td>
                                                <td className="px-4 py-3.5 whitespace-nowrap text-right">
                                                    <div className="flex items-center justify-end gap-1.5">
                                                        {isAdminUnit && p.status === 'draft' && (
                                                            <>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => {
                                                                        if (confirm(`Finalisasi slip gaji ${nama} (${p.periode_bulan})?`)) {
                                                                            router.post(route('penggajian.finalize', p.id), {}, { preserveState: true });
                                                                        }
                                                                    }}
                                                                    className="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-[11px] font-bold text-emerald-700 transition-colors hover:bg-emerald-100"
                                                                    title="Finalisasi slip gaji ini"
                                                                >
                                                                    <Lock className="h-3 w-3" /> Finalisasi
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => {
                                                                        if (confirm(`Hapus draft slip gaji ${nama} (${p.periode_bulan})?`)) {
                                                                            router.delete(route('penggajian.destroy', p.id), { preserveState: true });
                                                                        }
                                                                    }}
                                                                    className="inline-flex items-center justify-center rounded-lg border border-rose-200 bg-rose-50 p-1.5 text-rose-600 transition-colors hover:bg-rose-100"
                                                                    title="Hapus draft slip gaji"
                                                                >
                                                                    <Trash2 className="h-3.5 w-3.5" />
                                                                </button>
                                                            </>
                                                        )}
                                                        <Link
                                                            href={route('penggajian.show', p.id)}
                                                            className="btn-primary btn-sm flex items-center gap-1.5"
                                                        >
                                                            <Eye className="h-3.5 w-3.5" /> Slip
                                                        </Link>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    }) : (
                                        <tr>
                                            <td colSpan="7" className="px-6 py-16">
                                                <div className="flex flex-col items-center text-center">
                                                    <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-surface">
                                                        <Banknote className="h-8 w-8 text-border" />
                                                    </div>
                                                    <p className="mt-4 text-base font-bold text-primary">Belum ada data penggajian</p>
                                                    <p className="mt-1 text-sm text-text-secondary">
                                                        {hasFilter ? 'Coba ubah atau bersihkan filter.' : 'Jalankan Run Payroll Wizard untuk membuat draft gaji pertama.'}
                                                    </p>
                                                    {hasFilter ? (
                                                        <button onClick={resetFilters} className="btn-secondary btn-sm mt-4 flex items-center gap-1.5">
                                                            <RotateCcw className="h-3.5 w-3.5" /> Reset Filter
                                                        </button>
                                                    ) : isAdmin ? (
                                                        <Link href={route('penggajian.run')} className="btn-primary btn-sm mt-4 flex items-center gap-1.5">
                                                            <Sparkles className="h-3.5 w-3.5" /> Buka Run Payroll Wizard
                                                        </Link>
                                                    ) : null}
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Pagination */}
                    {penggajians.total > 0 && (
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-xs text-text-secondary">
                                Menampilkan <b className="text-primary">{penggajians.from || 0}</b>–<b className="text-primary">{penggajians.to || 0}</b> dari <b className="text-primary">{penggajians.total}</b> data
                            </p>
                            <Pagination links={penggajians.links} pagination={{ current_page: penggajians.current_page, last_page: penggajians.last_page }} />
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
