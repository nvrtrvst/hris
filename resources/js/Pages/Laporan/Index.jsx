import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Building2, Calendar, Download, FileSpreadsheet, Loader2, Search, FileText, Users } from 'lucide-react';
import axios from 'axios';

const Field = ({ label, children }) => (
    <div>
        <label className="form-label text-xs">{label}</label>
        <div className="mt-1">{children}</div>
    </div>
);

const STATUS_STYLES = {
    hadir: 'bg-green-100 text-green-800 ring-green-600/20',
    telat: 'bg-amber-100 text-amber-800 ring-amber-600/20',
    izin: 'bg-sky-100 text-sky-800 ring-sky-600/20',
    sakit: 'bg-indigo-100 text-indigo-800 ring-indigo-600/20',
    cuti: 'bg-violet-100 text-violet-800 ring-violet-600/20',
    alpa: 'bg-red-100 text-red-800 ring-red-600/20',
    disetujui: 'bg-green-100 text-green-800 ring-green-600/20',
    ditolak: 'bg-red-100 text-red-800 ring-red-600/20',
    pending: 'bg-amber-100 text-amber-800 ring-amber-600/20',
    finalized: 'bg-blue-100 text-blue-800 ring-blue-600/20',
    paid: 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
    draft: 'bg-gray-100 text-gray-700 ring-gray-500/20',
};

const KpiCard = ({ label, value, accent }) => (
    <div className={`rounded-xl border border-border p-4 ${accent ? 'bg-primary/5 ring-1 ring-primary/30' : 'bg-surface'}`}>
        <p className="text-xs font-medium text-text-muted">{label}</p>
        <p className={`mt-1 text-2xl font-extrabold ${accent ? 'text-primary' : 'text-text-primary'}`}>{value}</p>
    </div>
);

const StatusBadge = ({ status }) => {
    const key = String(status ?? '').toLowerCase();
    const cls = STATUS_STYLES[key] || 'bg-gray-100 text-gray-700 ring-gray-500/20';
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ${cls}`}>
            {status}
        </span>
    );
};

const computePresensiSummary = (data, headings) => {
    const statusIdx = headings.findIndex((h) => String(h).toLowerCase() === 'status');
    const tally = { hadir: 0, telat: 0, izin: 0, sakit: 0, cuti: 0, alpa: 0 };
    const total = data.length;
    if (statusIdx >= 0) {
        data.forEach((row) => {
            const s = String(row[statusIdx] ?? '').toLowerCase();
            if (s in tally) tally[s] += 1;
        });
    }
    const present = tally.hadir + tally.telat;
    const persen = total > 0 ? Math.round((present / total) * 100) : 0;
    return { total, ...tally, present, persen };
};

export default function LaporanIndex({ auth, units }) {
    const d = new Date();
    const currentMonth = d.getMonth() + 1;
    const currentYear = d.getFullYear();
    const firstDay = `${currentYear}-${String(currentMonth).padStart(2, '0')}-01`;
    const today = d.toISOString().split('T')[0];

    const [filter, setFilter] = useState({
        start_date: firstDay,
        end_date: today,
        report_type: 'presensi',
        unit_sekolah_id: '',
        jenis_filter: ''
    });

    const [previewData, setPreviewData] = useState(null);
    const [activePreview, setActivePreview] = useState(null);
    const [loading, setLoading] = useState(false);

    const handlePreview = async () => {
        setLoading(true);
        try {
            const res = await axios.get(route('laporan.preview'), {
                params: {
                    type: filter.report_type,
                    start_date: filter.start_date,
                    end_date: filter.end_date,
                    unit_sekolah_id: filter.unit_sekolah_id,
                    jenis_filter: filter.jenis_filter
                }
            });
            setPreviewData(res.data);
            setActivePreview({ ...filter });
        } catch (error) {
            console.error('Preview failed', error);
            alert('Gagal memuat pratinjau data. Pastikan rentang tanggal valid.');
        }
        setLoading(false);
    };

    const handleDownload = () => {
        let url = '';
        if (filter.report_type === 'presensi') url = route('laporan.presensi');
        if (filter.report_type === 'penggajian') url = route('laporan.penggajian');
        if (filter.report_type === 'lemburan') url = route('laporan.lemburan');

        const params = new URLSearchParams();
        params.append('type', filter.report_type);
        params.append('start_date', filter.start_date);
        params.append('end_date', filter.end_date);
        if (filter.unit_sekolah_id) {
            params.append('unit_sekolah_id', filter.unit_sekolah_id);
        }
        if (filter.jenis_filter) {
            params.append('jenis_filter', filter.jenis_filter);
        }

        window.location.href = `${url}?${params.toString()}`;
    };

    const handleDownloadPdf = () => {
        const url = route('laporan.pdf');
        const params = new URLSearchParams();
        params.append('type', filter.report_type);
        params.append('start_date', filter.start_date);
        params.append('end_date', filter.end_date);
        if (filter.unit_sekolah_id) {
            params.append('unit_sekolah_id', filter.unit_sekolah_id);
        }
        if (filter.jenis_filter) {
            params.append('jenis_filter', filter.jenis_filter);
        }

        window.location.href = `${url}?${params.toString()}`;
    };

    const REPORT_LABELS = {
        presensi: 'Presensi',
        penggajian: 'Rekap Gaji',
        lemburan: 'Detail Lembur & Potongan',
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Modul Laporan</h2>}
        >
            <Head title="Laporan" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    {/* Header */}
                    <div>
                        <h3 className="text-xl font-extrabold text-text-primary">Ekspor & Pratinjau Laporan</h3>
                        <p className="text-sm text-text-muted">Pilih jenis laporan, atur periode, lalu pratinjau atau unduh ke Excel.</p>
                    </div>

                    {/* Filter Card */}
                    <div className="card p-6">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                            <Field label="Jenis Laporan">
                                <div className="relative">
                                    <FileText className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <select
                                        className="select-field pl-9"
                                        value={filter.report_type}
                                        onChange={(e) => setFilter({ ...filter, report_type: e.target.value })}
                                    >
                                        <option value="presensi">Laporan Presensi</option>
                                        <option value="penggajian">Laporan Rekap Gaji</option>
                                        <option value="lemburan">Laporan Detail Lembur & Potongan</option>
                                    </select>
                                </div>
                            </Field>
                            <Field label="Tanggal Mulai">
                                <div className="relative">
                                    <Calendar className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <input
                                        type="date"
                                        className="input-field pl-9"
                                        value={filter.start_date}
                                        onChange={(e) => setFilter({ ...filter, start_date: e.target.value })}
                                    />
                                </div>
                            </Field>
                            <Field label="Tanggal Akhir">
                                <div className="relative">
                                    <Calendar className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <input
                                        type="date"
                                        className="input-field pl-9"
                                        value={filter.end_date}
                                        onChange={(e) => setFilter({ ...filter, end_date: e.target.value })}
                                    />
                                </div>
                            </Field>
                            <Field label="Unit Sekolah (Opsional)">
                                <div className="relative">
                                    <Building2 className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <select
                                        className="select-field pl-9"
                                        value={filter.unit_sekolah_id}
                                        onChange={(e) => setFilter({ ...filter, unit_sekolah_id: e.target.value })}
                                    >
                                        <option value="">-- Semua Unit Sekolah --</option>
                                        {units.map((u) => (
                                            <option key={u.id} value={u.id}>{u.nama}</option>
                                        ))}
                                    </select>
                                </div>
                            </Field>
                            <Field label="Jenis Pegawai (Opsional)">
                                <div className="relative">
                                    <Users className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <select
                                        className="select-field pl-9"
                                        value={filter.jenis_filter}
                                        onChange={(e) => setFilter({ ...filter, jenis_filter: e.target.value })}
                                    >
                                        <option value="">-- Semua Jenis --</option>
                                        <option value="pendidik">Pendidik (Guru)</option>
                                        <option value="kependidikan">Tenaga Kependidikan</option>
                                    </select>
                                </div>
                            </Field>
                        </div>

                        <div className="mt-6 flex flex-col gap-3 border-t border-border pt-5 sm:flex-row">
                            <button onClick={handlePreview} disabled={loading} className="btn-primary inline-flex items-center gap-2">
                                {loading ? <><Loader2 className="h-4 w-4 animate-spin" /> Memuat…</> : <><Search className="h-4 w-4" /> Tampilkan Data</>}
                            </button>
                            <button onClick={handleDownload} className="btn-secondary inline-flex items-center gap-2">
                                <Download className="h-4 w-4" /> Download Excel
                            </button>
                            <button onClick={handleDownloadPdf} className="btn-secondary inline-flex items-center gap-2">
                                <FileText className="h-4 w-4" /> Download PDF
                            </button>
                        </div>
                    </div>

                    {/* Preview */}
                    {loading && (
                        <div className="card flex items-center justify-center gap-3 p-12">
                            <Loader2 className="h-6 w-6 animate-spin text-primary" />
                            <span className="text-sm font-semibold text-text-secondary">Memuat Data Pratinjau...</span>
                        </div>
                    )}

                    {!loading && previewData && activePreview && (
                        <div className="card overflow-hidden">
                            <div className="flex flex-col gap-1 border-b border-border bg-surface px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 className="flex items-center gap-2 text-base font-extrabold uppercase tracking-wide text-primary">
                                        <FileSpreadsheet className="h-5 w-5" />
                                        Pratinjau: Laporan {REPORT_LABELS[activePreview.report_type] || activePreview.report_type}
                                    </h3>
                                    <p className="mt-0.5 text-xs text-text-muted">
                                        Periode: {activePreview.start_date} s/d {activePreview.end_date}
                                    </p>
                                </div>
                            </div>

                            {activePreview.report_type === 'presensi' && (() => {
                                const s = computePresensiSummary(previewData.data, previewData.headings);
                                const segs = [
                                    { key: 'hadir', label: 'Hadir', val: s.hadir, color: 'bg-green-500' },
                                    { key: 'telat', label: 'Telat', val: s.telat, color: 'bg-amber-500' },
                                    { key: 'izin', label: 'Izin', val: s.izin, color: 'bg-sky-500' },
                                    { key: 'sakit', label: 'Sakit', val: s.sakit, color: 'bg-indigo-500' },
                                    { key: 'cuti', label: 'Cuti', val: s.cuti, color: 'bg-violet-500' },
                                    { key: 'alpa', label: 'Alpa', val: s.alpa, color: 'bg-red-500' },
                                ].filter((x) => x.val > 0);
                                return (
                                    <div className="space-y-4 px-6 py-5">
                                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                                            <KpiCard label="Total Presensi" value={s.total} />
                                            <KpiCard label="% Kehadiran" value={`${s.persen}%`} accent />
                                            <KpiCard label="Hadir" value={s.hadir} />
                                            <KpiCard label="Telat" value={s.telat} />
                                            <KpiCard label="Izin/Sakit/Cuti" value={s.izin + s.sakit + s.cuti} />
                                            <KpiCard label="Alpa" value={s.alpa} />
                                        </div>
                                        <div>
                                            <div className="mb-1.5 flex items-center justify-between text-xs font-semibold text-text-muted">
                                                <span>Distribusi Status Kehadiran</span>
                                                <span>{s.total} record</span>
                                            </div>
                                            <div className="flex h-3 w-full overflow-hidden rounded-full bg-border">
                                                {segs.length === 0 ? (
                                                    <div className="h-full w-full bg-border" />
                                                ) : (
                                                    segs.map((seg) => (
                                                        <div
                                                            key={seg.key}
                                                            className={seg.color}
                                                            style={{ width: `${(seg.val / s.total) * 100}%` }}
                                                            title={`${seg.label}: ${seg.val}`}
                                                        />
                                                    ))
                                                )}
                                            </div>
                                            <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1">
                                                {segs.map((seg) => (
                                                    <span key={seg.key} className="flex items-center gap-1.5 text-xs text-text-secondary">
                                                        <span className={`h-2.5 w-2.5 rounded-full ${seg.color}`} />
                                                        {seg.label} ({seg.val})
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                );
                            })()}

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-border">
                                    <thead className="bg-surface">
                                        <tr>
                                            {previewData.headings.map((head, idx) => (
                                                <th key={idx} className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">{head}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border bg-white">
                                        {previewData.data.map((row, rowIdx) => (
                                            <tr key={rowIdx} className="transition-colors hover:bg-surface">
                                                {row.map((cell, cellIdx) => {
                                                    const headerStr = previewData.headings[cellIdx] ? previewData.headings[cellIdx].toLowerCase() : '';
                                                    const isStatus = headerStr === 'status';
                                                    const isCurrency = headerStr.includes('(rp)') || headerStr.includes('nominal') || headerStr.includes('rp)');
                                                    let displayValue = cell;

                                                    if (isCurrency && cell !== null && cell !== '-' && !isNaN(cell)) {
                                                        displayValue = new Intl.NumberFormat('id-ID').format(cell);
                                                    }

                                                    return (
                                                        <td key={cellIdx} className="whitespace-nowrap px-4 py-3 text-sm text-text-secondary tabular-nums">
                                                            {isStatus ? <StatusBadge status={cell} /> : displayValue}
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        ))}
                                        {previewData.data.length === 0 && (
                                            <tr>
                                                <td colSpan={previewData.headings.length} className="px-4 py-10 text-center text-sm text-text-muted">
                                                    Tidak ada data untuk filter dan rentang tanggal yang dipilih.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
