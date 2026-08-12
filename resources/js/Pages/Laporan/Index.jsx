import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Building2, Calendar, Download, FileSpreadsheet, Loader2, Search, FileText } from 'lucide-react';
import axios from 'axios';

const Field = ({ label, children }) => (
    <div>
        <label className="form-label text-xs">{label}</label>
        <div className="mt-1">{children}</div>
    </div>
);

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
        unit_sekolah_id: ''
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
                    unit_sekolah_id: filter.unit_sekolah_id
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
        params.append('start_date', filter.start_date);
        params.append('end_date', filter.end_date);
        if (filter.unit_sekolah_id) {
            params.append('unit_sekolah_id', filter.unit_sekolah_id);
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
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
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
                        </div>

                        <div className="mt-6 flex flex-col gap-3 border-t border-border pt-5 sm:flex-row">
                            <button onClick={handlePreview} disabled={loading} className="btn-primary inline-flex items-center gap-2">
                                {loading ? <><Loader2 className="h-4 w-4 animate-spin" /> Memuat…</> : <><Search className="h-4 w-4" /> Tampilkan Data</>}
                            </button>
                            <button onClick={handleDownload} className="btn-secondary inline-flex items-center gap-2">
                                <Download className="h-4 w-4" /> Download Excel
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
                                                    const isCurrency = headerStr.includes('(rp)') || headerStr.includes('nominal');
                                                    let displayValue = cell;

                                                    if (isCurrency && cell !== null && cell !== '-' && !isNaN(cell)) {
                                                        displayValue = new Intl.NumberFormat('id-ID').format(cell);
                                                    }

                                                    return (
                                                        <td key={cellIdx} className="whitespace-nowrap px-4 py-3 text-sm text-text-secondary tabular-nums">
                                                            {displayValue}
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
