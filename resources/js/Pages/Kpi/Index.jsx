import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { BarChart3, CalendarDays, ClipboardCheck, Search, Users } from 'lucide-react';

const PERIOD_LABEL = { daily: 'Harian', weekly: 'Mingguan', monthly: 'Bulanan' };

const Stat = ({ label, value, icon: Icon }) => (
    <div className="rounded-xl border border-border bg-white p-4">
        <div className="flex items-center gap-2 text-xs font-semibold text-text-muted"><Icon className="h-4 w-4 text-primary" />{label}</div>
        <p className="mt-2 text-2xl font-extrabold tabular-nums text-text-primary">{value ?? '—'}</p>
    </div>
);

const pct = (value) => value == null ? '—' : `${Number(value).toFixed(1)}%`;

export default function Index({ auth, rows = [], units = [], jabatans = [], filters = {}, periode = {}, summary = {}, can_manage_templates: canManageTemplates = false }) {
    const [form, setForm] = React.useState({
        periode: filters.periode || 'monthly',
        tanggal: filters.tanggal || new Date().toLocaleDateString('sv-SE'),
        unit_sekolah_id: filters.unit_sekolah_id || '',
        jabatan_id: filters.jabatan_id || '',
        search: filters.search || '',
    });
    const flash = usePage().props.flash || {};

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

    const isMonthly = (filters.periode || 'monthly') === 'monthly';

    return (
        <AuthenticatedLayout user={auth.user} header={<h1 className="page-title">KPI Pegawai</h1>}>
            <Head title="KPI Pegawai" />
            <div className="min-h-screen bg-surface py-6">
                <div className="mx-auto w-full max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="text-xl font-extrabold text-text-primary">Pemantauan & penilaian KPI</h2>
                            <p className="mt-1 text-sm text-text-muted">Harian dan mingguan untuk pemantauan; bulanan untuk nilai resmi.</p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <a href={route('kpi.index', { ...filters, export: 'excel' })} className="btn-secondary">Unduh Excel</a>
                            <a href={route('kpi.index', { ...filters, export: 'pdf' })} aria-disabled={rows.length > 500} className={`btn-secondary ${rows.length > 500 ? 'pointer-events-none opacity-50' : ''}`}>Unduh PDF</a>
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
                            <p className="flex items-center gap-1.5 text-xs font-semibold text-text-muted"><CalendarDays className="h-4 w-4" />{periode.start} s/d {periode.end}{filters.periode === 'monthly' && periode.kalender_selesai !== periode.end ? ' · progres bulan berjalan' : ''}</p>
                            <button type="submit" className="btn-primary">Tampilkan laporan</button>
                        </div>
                    </form>

                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <Stat label="Pegawai dalam cakupan" value={summary.pegawai} icon={Users} />
                        {isMonthly ? <>
                            <Stat label="Evaluasi final" value={summary.evaluasi_final} icon={ClipboardCheck} />
                            <Stat label="Rata-rata nilai jabatan sejenis" value={summary.rata_rata_nilai == null ? 'Pilih satu jabatan' : `${summary.rata_rata_nilai}%`} icon={BarChart3} />
                            <Stat label="Rata-rata kehadiran" value={pct(summary.rata_rata_hadir)} icon={CalendarDays} />
                            <Stat label="Tepat waktu saat hadir" value={pct(summary.rata_rata_tepat_waktu)} icon={CalendarDays} />
                        </> : <>
                            <Stat label="Rata-rata kehadiran" value={pct(summary.rata_rata_hadir)} icon={BarChart3} />
                            <Stat label="Tepat waktu saat hadir" value={pct(summary.rata_rata_tepat_waktu)} icon={CalendarDays} />
                            <Stat label="Rata-rata pemenuhan JP" value={pct(summary.rata_rata_jp)} icon={CalendarDays} />
                        </>}
                    </div>

                    <div className="overflow-hidden rounded-xl border border-border bg-white">
                        <div className="border-b border-border px-4 py-3">
                            <h3 className="text-sm font-extrabold text-text-primary">Laporan KPI {PERIOD_LABEL[filters.periode || 'monthly']}</h3>
                            <p className="mt-0.5 text-xs text-text-muted">Nilai dibandingkan dalam jabatan yang sama. Data kosong tampil sebagai belum dinilai.</p>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-border">
                                <thead className="bg-surface"><tr>
                                    {['Pegawai', 'Jabatan', 'Unit', 'Kehadiran', 'Tepat waktu', 'JP mengajar', ...(isMonthly ? ['Nilai KPI', 'Status', ''] : [])].map((heading) => <th key={heading} className="whitespace-nowrap px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wide text-text-muted">{heading}</th>)}
                                </tr></thead>
                                <tbody className="divide-y divide-border">
                                    {rows.map((row) => (
                                        <tr key={row.assignment_id} className="hover:bg-surface/60">
                                            <td className="whitespace-nowrap px-4 py-3 text-sm font-semibold text-text-primary">{row.pegawai}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-sm text-text-secondary">{row.jabatan}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-sm text-text-secondary">{row.unit}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-sm tabular-nums text-text-secondary">{pct(row.attendance_rate)} <span className="text-xs text-text-muted">{row.hari_kerja > 0 ? `(${row.hadir_hari}/${row.hari_kerja} hari)` : '(tidak ada hari tugas)'}</span></td>
                                            <td className="whitespace-nowrap px-4 py-3 text-sm tabular-nums text-text-secondary">{pct(row.punctuality_rate)}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-sm tabular-nums text-text-secondary">{row.teaching_jp_terjadwal > 0 ? `${pct(row.teaching_jp_rate)} (${row.teaching_jp_hadir}/${row.teaching_jp_terjadwal} JP)` : '—'}</td>
                                            {isMonthly && <>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm font-bold tabular-nums text-primary">{row.score == null ? '—' : `${Number(row.score).toFixed(1)}%`}</td>
                                                <td className="whitespace-nowrap px-4 py-3 text-xs font-semibold text-text-secondary">{row.evaluation_status === 'finalized' ? 'Final' : row.evaluation_status === 'draft' ? 'Draft' : 'Belum dinilai'}</td>
                                                <td className="whitespace-nowrap px-4 py-3 text-right">{row.evaluation_id ? <Link href={route('kpi.evaluations.edit', { assignment: row.assignment_id, tanggal: filters.tanggal })} className="btn-secondary btn-sm">{row.can_evaluate ? 'Buka nilai' : 'Lihat nilai'}</Link> : row.can_evaluate && <Link href={route('kpi.evaluations.edit', { assignment: row.assignment_id, tanggal: filters.tanggal })} className="btn-secondary btn-sm">Nilai KPI</Link>}</td>
                                            </>}
                                        </tr>
                                    ))}
                                    {rows.length === 0 && <tr><td colSpan={isMonthly ? 9 : 6} className="px-4 py-10 text-center text-sm text-text-muted">Tidak ada pegawai pada filter ini.</td></tr>}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
