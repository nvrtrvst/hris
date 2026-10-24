import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarDays, Search } from 'lucide-react';

const PERIOD_LABEL = { daily: 'Harian', weekly: 'Mingguan', monthly: 'Bulanan' };

const pct = (value) => value == null ? '—' : `${Number(value).toFixed(1)}%`;

const STATUS_META = {
    finalized: { label: 'Final', cls: 'border-success/30 bg-success-light text-success' },
    draft: { label: 'Draft', cls: 'border-warning/30 bg-warning-light text-warning' },
};

export default function Index({ auth, rows = [], units = [], jabatans = [], filters = {}, periode = {}, summary = {}, can_manage_templates: canManageTemplates = false }) {
    const [form, setForm] = React.useState({
        periode: filters.periode || 'monthly',
        tanggal: filters.tanggal || new Date().toLocaleDateString('sv-SE'),
        unit_sekolah_id: filters.unit_sekolah_id || '',
        jabatan_id: filters.jabatan_id || '',
        search: filters.search || '',
    });
    const flash = usePage().props.flash || {};
    const isMonthly = (filters.periode || 'monthly') === 'monthly';
    const hasFilter = Boolean(filters.unit_sekolah_id || filters.jabatan_id || filters.search);

    const filter = (event) => {
        event?.preventDefault();
        router.get(route('kpi.index'), {
            periode: form.periode,
            tanggal: form.tanggal,
            unit_sekolah_id: form.unit_sekolah_id || undefined,
            jabatan_id: form.jabatan_id || undefined,
            search: form.search || undefined,
        }, { preserveState: true, preserveScroll: true });
    };

    const belumFinal = rows.filter((row) => row.evaluation_status !== 'finalized');
    const bisaDinilai = belumFinal.filter((row) => row.can_evaluate);
    const pdfTerlaluBesar = rows.length > 500;
    const exportParams = { ...filters, page: undefined };

    return (
        <AuthenticatedLayout user={auth.user} header={<h1 className="page-title">KPI Pegawai</h1>}>
            <Head title="KPI Pegawai" />
            <div className="min-h-screen bg-surface py-6">
                <div className="mx-auto w-full max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="text-xl font-extrabold text-text-primary">Pemantauan & penilaian KPI</h2>
                            <p className="mt-1 max-w-2xl text-sm text-text-muted">Harian dan mingguan untuk memantau; bulanan untuk nilai resmi. Angka per pegawai dibandingkan dalam jabatan yang sama.</p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <a href={route('kpi.index', { ...exportParams, export: 'excel' })} className="btn-secondary">Unduh Excel</a>
                            {pdfTerlaluBesar
                                ? <span className="text-xs font-semibold text-text-muted">PDF maksimal 500 baris — pakai Excel.</span>
                                : <a href={route('kpi.index', { ...exportParams, export: 'pdf' })} className="btn-secondary">Unduh PDF</a>}
                            {canManageTemplates && <Link href={route('kpi.templates.index')} className="btn-secondary">Atur template KPI</Link>}
                        </div>
                    </div>

                    {flash.message && <div role="status" className="rounded-xl border border-success/30 bg-success-light px-4 py-3 text-sm font-semibold text-success">{flash.message}</div>}
                    {flash.error && <div role="alert" className="rounded-xl border border-danger/30 bg-danger-light px-4 py-3 text-sm font-semibold text-danger">{flash.error}</div>}

                    <form onSubmit={filter} className="rounded-xl border border-border bg-white p-4">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                            <label className="form-label text-xs">Periode
                                <select value={form.periode} onChange={(e) => setForm({ ...form, periode: e.target.value })} className="select-field mt-1 w-full">
                                    <option value="daily">Harian</option><option value="weekly">Mingguan</option><option value="monthly">Bulanan</option>
                                </select>
                            </label>
                            <label className="form-label text-xs">Tanggal acuan
                                <input type="date" value={form.tanggal} onChange={(e) => setForm({ ...form, tanggal: e.target.value })} className="input-field mt-1 w-full" />
                            </label>
                            {units.length > 1 && <label className="form-label text-xs">Unit
                                <select value={form.unit_sekolah_id} onChange={(e) => setForm({ ...form, unit_sekolah_id: e.target.value })} className="select-field mt-1 w-full">
                                    <option value="">Semua unit</option>{units.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}</option>)}
                                </select>
                            </label>}
                            <label className="form-label text-xs">Jabatan
                                <select value={form.jabatan_id} onChange={(e) => setForm({ ...form, jabatan_id: e.target.value })} className="select-field mt-1 w-full">
                                    <option value="">Semua jabatan</option>{jabatans.map((jabatan) => <option key={jabatan.id} value={jabatan.id}>{jabatan.nama}</option>)}
                                </select>
                            </label>
                            <label className="form-label text-xs">Cari pegawai
                                <div className="relative mt-1"><Search className="absolute left-3 top-3 h-4 w-4 text-text-muted" /><input value={form.search} onChange={(e) => setForm({ ...form, search: e.target.value })} className="input-field w-full pl-9" placeholder="Nama pegawai" /></div>
                            </label>
                        </div>
                        <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
                            <p className="flex items-center gap-1.5 text-xs font-semibold text-text-muted"><CalendarDays className="h-4 w-4" />{periode.start} s/d {periode.end}{isMonthly && periode.kalender_selesai !== periode.end ? ' · progres bulan berjalan' : ''}</p>
                            <div className="flex items-center gap-2">
                                {hasFilter && <button type="button" onClick={() => { setForm({ ...form, unit_sekolah_id: '', jabatan_id: '', search: '' }); router.get(route('kpi.index'), { periode: form.periode, tanggal: form.tanggal }, { preserveState: true }); }} className="btn-secondary">Reset filter</button>}
                                <button type="submit" className="btn-primary">Tampilkan</button>
                            </div>
                        </div>
                    </form>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {isMonthly ? (
                            <div className="rounded-xl border border-border bg-white p-4">
                                <p className="text-xs font-semibold text-text-muted">Belum final dinilai</p>
                                <p className="mt-1 text-2xl font-extrabold tabular-nums text-text-primary">{belumFinal.length}<span className="text-sm font-semibold text-text-muted"> / {rows.length}</span></p>
                                <p className="mt-1 text-xs text-text-muted">{bisaDinilai.length > 0 ? `${bisaDinilai.length} menjadi tugas Anda` : 'Semua yang dapat Anda nilai sudah final'}</p>
                            </div>
                        ) : (
                            <div className="rounded-xl border border-border bg-white p-4">
                                <p className="text-xs font-semibold text-text-muted">Pegawai dalam cakupan</p>
                                <p className="mt-1 text-2xl font-extrabold tabular-nums text-text-primary">{rows.length}</p>
                                <p className="mt-1 text-xs text-text-muted">Periode {PERIOD_LABEL[filters.periode]?.toLowerCase() || 'bulanan'}</p>
                            </div>
                        )}
                        <div className="rounded-xl border border-border bg-white p-4">
                            <p className="text-xs font-semibold text-text-muted">Rata-rata kehadiran</p>
                            <p className="mt-1 text-2xl font-extrabold tabular-nums text-text-primary">{pct(summary.rata_rata_hadir)}</p>
                        </div>
                        <div className="rounded-xl border border-border bg-white p-4">
                            <p className="text-xs font-semibold text-text-muted">{isMonthly ? 'Tepat waktu saat hadir' : 'Rata-rata pemenuhan JP'}</p>
                            <p className="mt-1 text-2xl font-extrabold tabular-nums text-text-primary">{isMonthly ? pct(summary.rata_rata_tepat_waktu) : pct(summary.rata_rata_jp)}</p>
                        </div>
                    </div>

                    {isMonthly && bisaDinilai.length > 0 && (
                        <div className="rounded-xl border border-warning/30 bg-warning-light/50 p-4">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p className="text-sm font-bold text-text-primary">Perlu dinilai bulan ini</p>
                                    <p className="mt-0.5 text-xs text-text-secondary">Nilai final dibutuhkan sebelum KPI dipakai untuk laporan.</p>
                                </div>
                                <p className="text-xs font-bold text-warning">{bisaDinilai.length} pegawai</p>
                            </div>
                            <ul className="mt-3 flex flex-wrap gap-2">
                                {bisaDinilai.slice(0, 6).map((row) => (
                                    <li key={row.assignment_id}>
                                        <Link href={route('kpi.evaluations.edit', { assignment: row.assignment_id, tanggal: filters.tanggal })} className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-white px-2.5 py-1.5 text-xs font-bold text-text-primary hover:border-primary/40 hover:text-primary">
                                            {row.pegawai}
                                            <span className="font-normal text-text-muted">· {row.jabatan}</span>
                                        </Link>
                                    </li>
                                ))}
                                {bisaDinilai.length > 6 && <li className="self-center text-xs font-semibold text-text-muted">+{bisaDinilai.length - 6} lainnya di tabel</li>}
                            </ul>
                        </div>
                    )}

                    <div className="overflow-hidden rounded-xl border border-border bg-white">
                        <div className="flex flex-wrap items-baseline justify-between gap-2 border-b border-border px-4 py-3">
                            <h3 className="text-sm font-extrabold text-text-primary">Laporan KPI {PERIOD_LABEL[filters.periode || 'monthly']}</h3>
                            <p className="text-xs text-text-muted">{isMonthly && filters.jabatan_id && summary.rata_rata_nilai != null ? `Rata-rata jabatan ini: ${summary.rata_rata_nilai}%` : 'Data kosong tampil sebagai belum dinilai.'}</p>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-border">
                                <thead className="bg-surface"><tr>
                                    {['Pegawai', ...(isMonthly ? ['Nilai KPI', 'Status'] : []), 'Kehadiran', 'Tepat waktu', 'JP mengajar', ''].map((heading) => <th key={heading} className="whitespace-nowrap px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wide text-text-muted">{heading}</th>)}
                                </tr></thead>
                                <tbody className="divide-y divide-border">
                                    {rows.map((row) => {
                                        const status = STATUS_META[row.evaluation_status] || { label: 'Belum dinilai', cls: 'border-border bg-surface text-text-muted' };
                                        return (
                                            <tr key={row.assignment_id} className="hover:bg-surface/60">
                                                <td className="px-4 py-3">
                                                    <p className="text-sm font-semibold text-text-primary">{row.pegawai}</p>
                                                    <p className="text-xs text-text-muted">{row.jabatan} · {row.unit}</p>
                                                </td>
                                                {isMonthly && <>
                                                    <td className="whitespace-nowrap px-4 py-3 text-sm font-bold tabular-nums text-text-primary">{row.score == null ? '—' : `${Number(row.score).toFixed(1)}%`}</td>
                                                    <td className="whitespace-nowrap px-4 py-3"><span className={`inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold ${status.cls}`}>{status.label}</span></td>
                                                </>}
                                                <td className="whitespace-nowrap px-4 py-3 text-sm tabular-nums text-text-secondary">{pct(row.attendance_rate)} <span className="text-xs text-text-muted">{row.hari_kerja > 0 ? `(${row.hadir_hari}/${row.hari_kerja} hari)` : '(tanpa hari tugas)'}</span></td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm tabular-nums text-text-secondary">{pct(row.punctuality_rate)}</td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm tabular-nums text-text-secondary">{row.teaching_jp_terjadwal > 0 ? `${pct(row.teaching_jp_rate)} (${row.teaching_jp_hadir}/${row.teaching_jp_terjadwal} JP)` : '—'}</td>
                                                <td className="whitespace-nowrap px-4 py-3 text-right">
                                                    {row.evaluation_id
                                                        ? <Link href={route('kpi.evaluations.edit', { assignment: row.assignment_id, tanggal: filters.tanggal })} className="btn-secondary btn-sm">{row.can_evaluate ? 'Buka nilai' : 'Lihat nilai'}</Link>
                                                        : row.can_evaluate && isMonthly && <Link href={route('kpi.evaluations.edit', { assignment: row.assignment_id, tanggal: filters.tanggal })} className="btn-secondary btn-sm">Nilai</Link>}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                    {rows.length === 0 && (
                                        <tr><td colSpan={isMonthly ? 7 : 5} className="px-4 py-10 text-center">
                                            <p className="text-sm font-semibold text-text-primary">Tidak ada pegawai untuk filter ini.</p>
                                            <p className="mt-1 text-xs text-text-muted">{hasFilter ? 'Filter unit, jabatan, atau pencarian menyaring semua pegawai.' : 'Belum ada pegawai aktif dengan penugasan pada cakupan Anda.'}</p>
                                            {hasFilter && <button type="button" onClick={() => { setForm({ ...form, unit_sekolah_id: '', jabatan_id: '', search: '' }); router.get(route('kpi.index'), { periode: form.periode, tanggal: form.tanggal }, { preserveState: true }); }} className="btn-secondary btn-sm mt-3">Reset filter</button>}
                                        </td></tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
