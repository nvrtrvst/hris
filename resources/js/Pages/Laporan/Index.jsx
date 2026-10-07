import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Calendar, Download, FileSpreadsheet, Loader2, Search, FileText, Users, X } from 'lucide-react';
import axios from 'axios';
import ComboSelect from '@/Components/ComboSelect';
import { fmtJp } from '@/Utils/waktuPresensi';

const fieldId = (label) => label.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

const Field = ({ label, children }) => (
    <div>
        <label htmlFor={fieldId(label)} className="form-label text-xs">{label}</label>
        <div className="mt-1">{children}</div>
    </div>
);

const NEUTRAL_BADGE = 'bg-surface text-text-secondary ring-border';

const STATUS_STYLES = {
    hadir: 'bg-success-light text-success ring-success/30',
    telat: 'bg-warning-light text-warning ring-warning/30',
    izin: 'bg-info-light text-info ring-info/30',
    sakit: 'bg-info-light text-info ring-info/30',
    cuti: 'bg-info-light text-info ring-info/30',
    alpa: 'bg-danger-light text-danger ring-danger/30',
    disetujui: 'bg-success-light text-success ring-success/30',
    ditolak: 'bg-danger-light text-danger ring-danger/30',
    pending: 'bg-warning-light text-warning ring-warning/30',
    finalized: 'bg-info-light text-info ring-info/30',
    paid: 'bg-success-light text-success ring-success/30',
    draft: 'bg-surface text-text-secondary ring-border',
};

const KpiCard = ({ label, value, accent }) => (
    <div className={`rounded-xl border border-border p-4 ${accent ? 'bg-primary/5 ring-1 ring-primary/30' : 'bg-surface'}`}>
        <p className="text-xs font-medium text-text-muted">{label}</p>
        <p className={`mt-1 text-2xl font-extrabold ${accent ? 'text-primary' : 'text-text-primary'}`}>{value}</p>
    </div>
);

const StatusBadge = ({ status }) => {
    const key = String(status ?? '').toLowerCase();
    const cls = STATUS_STYLES[key] || NEUTRAL_BADGE;
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ${cls}`}>
            {status}
        </span>
    );
};

const TIPE_STYLES = {
    mengajar: 'bg-success-light text-success ring-success/30',
    kantor: 'bg-info-light text-info ring-info/30',
    lembur: 'bg-warning-light text-warning ring-warning/30',
    'tugas luar': 'bg-accent-100 text-accent-700 ring-accent-500/30',
};

const TipeBadge = ({ tipe }) => {
    const key = String(tipe ?? '').toLowerCase();
    const cls = TIPE_STYLES[key] || NEUTRAL_BADGE;
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ${cls}`}>
            {tipe || '-'}
        </span>
    );
};

const LETTER_STYLES = {
    H: 'bg-success-light text-success',
    T: 'bg-warning-light text-warning',
    S: 'bg-info-light text-info',
    I: 'bg-info-light text-info',
    C: 'bg-info-light text-info',
    A: 'bg-danger-light text-danger',
    L: 'bg-border/50 text-text-muted',
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

    const isSuperadmin = auth.permissions?.includes('view_all_units');
    const userUnitId = auth.user?.unit_sekolah_id;

    const [filter, setFilter] = useState({
        start_date: firstDay,
        end_date: today,
        report_type: 'presensi',
        unit_sekolah_id: isSuperadmin ? '' : (userUnitId || ''),
        jenis_filter: '',
        tipe_filter: '',
        search: ''
    });

    const [previewData, setPreviewData] = useState(null);
    const [activePreview, setActivePreview] = useState(null);
    const [loading, setLoading] = useState(false);
    const [viewMode, setViewMode] = useState('rekap');
    const [error, setError] = useState('');

    const handlePreview = async () => {
        setLoading(true);
        setError('');
        try {
            const res = await axios.get(route('laporan.preview'), {
                params: {
                    type: filter.report_type,
                    start_date: filter.start_date,
                    end_date: filter.end_date,
                    unit_sekolah_id: filter.unit_sekolah_id,
                    jenis_filter: filter.jenis_filter,
                    tipe_filter: filter.tipe_filter,
                    search: filter.search
                }
            });
            setPreviewData(res.data);
            setActivePreview({ ...filter });
        } catch (error) {
            console.error('Preview failed', error);
            const data = error?.response?.data;
            const msg = data?.message
                || Object.values(data?.errors || {}).flat().join('\n')
                || 'Gagal memuat pratinjau data. Pastikan rentang tanggal valid.';
            setError(msg);
        }
        setLoading(false);
    };

    const handleDownload = () => {
        setError('');
        if (filter.report_type === 'rekap_kehadiran_harian') {
            const s = new Date(filter.start_date + 'T00:00:00');
            const e = new Date(filter.end_date + 'T00:00:00');
            if ((e - s) / 86400000 > 30) {
                setError('Rekap Kehadiran Harian maksimal 31 hari per generate. Pecah periode menjadi beberapa generate.');
                return;
            }
        }

        let url = '';
        if (filter.report_type === 'presensi') url = route('laporan.presensi');
        if (filter.report_type === 'penggajian') url = route('laporan.penggajian');
        if (filter.report_type === 'lemburan') url = route('laporan.lemburan');
        if (filter.report_type === 'rekap_mengajar') url = route('laporan.rekap-mengajar');
        if (filter.report_type === 'rekap_kehadiran') url = route('laporan.rekap-kehadiran');
        if (filter.report_type === 'rekap_kehadiran_harian') url = route('laporan.rekap-kehadiran-harian');

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
        if (filter.tipe_filter) {
            params.append('tipe_filter', filter.tipe_filter);
        }
        if (filter.search) {
            params.append('search', filter.search);
        }

        window.location.href = `${url}?${params.toString()}`;
    };

    const handleDownloadPdf = async () => {
        setError('');
        if (!filter.start_date) {
            setError('Isi tanggal mulai terlebih dahulu.');
            return;
        }
        if (!filter.end_date) {
            setError('Isi tanggal akhir terlebih dahulu.');
            return;
        }

        // Rekap Presensi Harian punya route terpisah
        if (filter.report_type === 'rekap_presensi_harian') {
            const url = route('presensi.rekap-pdf');
            const params = new URLSearchParams();
            params.append('start_date', filter.start_date);
            params.append('end_date', filter.end_date);
            if (filter.unit_sekolah_id) params.append('unit_id', filter.unit_sekolah_id);
            if (filter.jenis_filter) params.append('jenis_filter', filter.jenis_filter);
            if (filter.search) params.append('search', filter.search);

            try {
                const res = await fetch(`${url}?${params.toString()}`);
                if (!res.ok) {
                    let msg = 'PDF gagal dibuat. Coba lagi atau hubungi administrator.';
                    try {
                        const body = await res.json();
                        msg = body.message || body.error || msg;
                    } catch { /* non-JSON */ }
                    setError(msg);
                    return;
                }
                const blob = await res.blob();
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = `Rekap_Presensi_Harian_${filter.start_date}.pdf`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(a.href);
            } catch {
                setError('Koneksi terputus saat membuat PDF.');
            }
            return;
        }

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
        if (filter.tipe_filter) {
            params.append('tipe_filter', filter.tipe_filter);
        }
        if (filter.search) {
            params.append('search', filter.search);
        }

        try {
            const res = await fetch(`${url}?${params.toString()}`);
            if (!res.ok) {
                let msg = 'PDF gagal dibuat. Coba lagi atau hubungi administrator.';
                try {
                    const body = await res.json();
                    msg = body.message || body.error || msg;
                    if (body.errors) {
                        msg = Object.values(body.errors).flat().join('\n');
                    }
                } catch {
                    const text = await res.text();
                    msg = text.startsWith('PDF gagal') ? text : msg;
                }
                setError(msg);
                return;
            }
            const blob = await res.blob();
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `${filter.report_type}_${filter.start_date}_to_${filter.end_date}.pdf`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(a.href);
        } catch {
            setError('Koneksi terputus saat membuat PDF.');
        }
    };

    const REPORT_LABELS = {
        presensi: 'Presensi',
        penggajian: 'Rekap Gaji',
        lemburan: 'Detail Lembur & Potongan',
        rekap_mengajar: 'Rekap Mengajar',
        rekap_kehadiran: 'Rekap Kehadiran',
        rekap_kehadiran_harian: 'Rekap Kehadiran Harian',
        rekap_presensi_harian: 'Rekap Presensi Harian',
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h1 className="page-title">Modul Laporan</h1>}
        >
            <Head title="Laporan" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    {/* Header */}
                    <div>
                        <h2 className="text-xl font-extrabold text-text-primary">Ekspor & Pratinjau Laporan</h2>
                        <p className="text-sm text-text-muted">Pilih jenis laporan, atur periode, lalu pratinjau atau unduh ke Excel.</p>
                    </div>

                    {/* Filter Card */}
                    <div className="card p-6">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                            <Field label="Jenis Laporan">
                                <div className="relative">
                                    <FileText className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <select
                                        id={fieldId('Jenis Laporan')}
                                        className="select-field pl-9"
                                        value={filter.report_type}
                                        onChange={(e) => setFilter({ ...filter, report_type: e.target.value, tipe_filter: e.target.value === 'presensi' ? filter.tipe_filter : '' })}
                                    >
                                        <option value="presensi">Laporan Presensi</option>
                                        <option value="rekap_kehadiran">Rekap Kehadiran</option>
                                        <option value="rekap_kehadiran_harian">Rekap Kehadiran Harian (per Tanggal)</option>
                                        <option value="rekap_mengajar">Rekap Presensi Mengajar</option>
                                        <option value="rekap_presensi_harian">Rekap Presensi Harian</option>
                                        <option value="penggajian">Laporan Rekap Gaji</option>
                                        <option value="lemburan">Laporan Detail Lembur & Potongan</option>
                                    </select>
                                </div>
                            </Field>
                            <Field label="Tanggal Mulai">
                                <div className="relative">
                                    <Calendar className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <input
                                        id={fieldId('Tanggal Mulai')}
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
                                        id={fieldId('Tanggal Akhir')}
                                        type="date"
                                        className="input-field pl-9"
                                        value={filter.end_date}
                                        onChange={(e) => setFilter({ ...filter, end_date: e.target.value })}
                                    />
                                </div>
                            </Field>
                            <Field label="Unit Sekolah">
                                <ComboSelect
                                    id={fieldId('Unit Sekolah')}
                                    value={filter.unit_sekolah_id}
                                    onChange={(v) => setFilter({ ...filter, unit_sekolah_id: v })}
                                    options={[
                                        ...(isSuperadmin ? [{ value: '', label: '-- Semua Unit Sekolah --' }] : []),
                                        ...units.map((u) => ({ value: u.id, label: u.nama }))
                                    ]}
                                    disabled={!isSuperadmin}
                                    placeholder={isSuperadmin ? 'Ketik untuk cari unit…' : 'Unit saya'}
                                />
                            </Field>
                            <Field label="Jenis Pegawai (Opsional)">
                                <div className="relative">
                                    <Users className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <select
                                        id={fieldId('Jenis Pegawai (Opsional)')}
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
                            <Field label="Cari Nama Pegawai">
                                <div className="relative">
                                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <input
                                        id={fieldId('Cari Nama Pegawai')}
                                        type="text"
                                        className="input-field pl-9 pr-9"
                                        placeholder="Nama pegawai…"
                                        value={filter.search}
                                        onChange={(e) => setFilter({ ...filter, search: e.target.value })}
                                    />
                                    {filter.search && (
                                        <button
                                            type="button"
                                            aria-label="Bersihkan pencarian"
                                            onClick={() => setFilter({ ...filter, search: '' })}
                                            className="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-text-muted hover:bg-black/5 hover:text-primary"
                                        >
                                            <X className="h-4 w-4" />
                                        </button>
                                    )}
                                </div>
                            </Field>
                            {filter.report_type === 'presensi' && (
                                <Field label="Tipe Presensi (Opsional)">
                                    <div className="relative">
                                        <FileText className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                        <select
                                            id={fieldId('Tipe Presensi (Opsional)')}
                                            className="select-field pl-9"
                                            value={filter.tipe_filter}
                                            onChange={(e) => setFilter({ ...filter, tipe_filter: e.target.value })}
                                        >
                                            <option value="">Semua (Kantor + Mengajar)</option>
                                            <option value="kantor">Kantor (Harian)</option>
                                            <option value="mengajar">Mengajar (per JP)</option>
                                        </select>
                                    </div>
                                </Field>
                            )}
                        </div>

                        {error && (
                            <div role="alert" className="mt-4 flex items-start justify-between gap-3 rounded-xl border border-danger/30 bg-danger-light px-4 py-3 text-sm font-semibold text-danger">
                                <span className="whitespace-pre-line">{error}</span>
                                <button
                                    type="button"
                                    aria-label="Tutup pesan"
                                    onClick={() => setError('')}
                                    className="shrink-0 rounded-md p-1 hover:bg-danger/10"
                                >
                                    <X className="h-4 w-4" />
                                </button>
                            </div>
                        )}

                        <div className="mt-6 flex flex-col gap-3 border-t border-border pt-5 sm:flex-row sm:items-center">
                            {filter.report_type !== 'rekap_presensi_harian' && (
                                <button onClick={handlePreview} disabled={loading} className="btn-primary inline-flex items-center gap-2">
                                    {loading ? <><Loader2 className="h-4 w-4 animate-spin" /> Memuat…</> : <><Search className="h-4 w-4" /> Tampilkan Data</>}
                                </button>
                            )}
                            {filter.report_type === 'rekap_presensi_harian' && (
                                <p className="text-xs text-text-muted italic">Jenis ini langsung menghasilkan PDF (tanpa pratinjau). Atur filter, lalu klik &quot;Download PDF&quot;.</p>
                            )}
                            <button onClick={handleDownload} disabled={loading} className="btn-secondary inline-flex items-center gap-2 disabled:cursor-not-allowed disabled:opacity-60">
                                <Download className="h-4 w-4" /> Download Excel
                            </button>
                            <button onClick={handleDownloadPdf} disabled={loading} className="btn-secondary inline-flex items-center gap-2 disabled:cursor-not-allowed disabled:opacity-60">
                                <FileText className="h-4 w-4" /> Download PDF
                            </button>
                        </div>
                    </div>

                    {/* Preview */}
                    {loading && (
                        <div role="status" className="card flex items-center justify-center gap-3 p-12">
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
                                {activePreview.report_type === 'rekap_mengajar' && previewData.calendar && (
                                    <div className="inline-flex rounded-xl border border-border bg-surface p-1">
                                        {[
                                            { k: 'rekap', label: 'Rekap' },
                                            { k: 'kalender', label: 'Kalender' },
                                        ].map((v) => (
                                            <button
                                                key={v.k}
                                                type="button"
                                                aria-pressed={viewMode === v.k}
                                                onClick={() => setViewMode(v.k)}
                                                className={`rounded-lg px-3.5 py-1.5 text-xs font-bold transition-colors ${viewMode === v.k ? 'bg-primary text-white shadow-sm' : 'text-text-secondary hover:text-primary'}`}
                                            >
                                                {v.label}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {typeof previewData.total === 'number' && previewData.total > previewData.data.length && (
                                <div className="border-b border-warning/30 bg-warning-light px-6 py-2.5 text-xs font-semibold text-warning">
                                    Menampilkan {previewData.data.length} baris pertama dari {previewData.total.toLocaleString('id-ID')} baris. Persempit filter periode/pencarian atau gunakan Export Excel untuk data lengkap.
                                </div>
                            )}

                            {activePreview.report_type === 'rekap_mengajar' && viewMode === 'kalender' && previewData.calendar && (() => {
                                const cal = previewData.calendar;
                                const dateLabel = (ds) => {
                                    const dt = new Date(ds + 'T00:00:00');
                                    return dt.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
                                };
                                const dayShort = (ds) => {
                                    const dt = new Date(ds + 'T00:00:00');
                                    return ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'][dt.getDay()];
                                };
                                const cellStyle = (cell) => {
                                    if (!cell) return 'bg-border/40 text-text-muted';
                                    const [jp, hadir, telat, alpa] = cell;
                                    if (alpa > 0) return 'bg-danger-light text-danger';
                                    if (telat > 0) return 'bg-warning-light text-warning';
                                    if (hadir >= jp) return 'bg-success-light text-success';
                                    return 'bg-surface text-text-secondary';
                                };
                                return (
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-border">
                                            <thead className="bg-surface">
                                                <tr>
                                                    <th scope="col" className="sticky left-0 z-10 bg-surface px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Nama Guru</th>
                                                    {cal.dates.map((ds) => (
                                                        <th key={ds} scope="col" className="px-2 py-3 text-center text-xs font-bold uppercase tracking-wider text-text-muted">
                                                            <div>{dayShort(ds)}</div>
                                                            <div className="font-extrabold text-text-secondary">{dateLabel(ds)}</div>
                                                        </th>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-border">
                                                {cal.guru.map((g) => (
                                                    <tr key={g.id ?? g.nama} className="transition-colors hover:bg-surface">
                                                        <td className="sticky left-0 z-10 bg-surface-card px-4 py-2.5 text-sm font-semibold text-primary whitespace-nowrap">
                                                            {g.id ? (
                                                                <Link
                                                                    href={route('laporan.rekap-mengajar-detail', {
                                                                        pegawai_id: g.id,
                                                                        start_date: activePreview.start_date,
                                                                        end_date: activePreview.end_date,
                                                                    })}
                                                                    className="hover:underline"
                                                                >
                                                                    {g.nama}
                                                                </Link>
                                                            ) : (
                                                                g.nama
                                                            )}
                                                        </td>
                                                        {cal.dates.map((ds) => {
                                                            const cell = g.cells[ds];
                                                            const [jp, hadir, telat, alpa] = cell || [0, 0, 0, 0];
                                                            const title = cell
                                                                ? `${fmtJp(jp)} JP: ${fmtJp(hadir)} hadir, ${fmtJp(telat)} telat, ${fmtJp(alpa)} alpa`
                                                                : 'Tidak ada jadwal mengajar';
                                                            return (
                                                                <td key={ds} className="px-1 py-1.5 text-center" title={title}>
                                                                    <span className={`inline-flex min-w-[2.2rem] items-center justify-center rounded-lg px-1.5 py-1 text-xs font-bold tabular-nums ${cellStyle(cell)}`}>
                                                                        {cell ? `${fmtJp(hadir + telat)}/${fmtJp(jp)}` : '—'}
                                                                    </span>
                                                                </td>
                                                            );
                                                        })}
                                                    </tr>
                                                ))}
                                                {cal.guru.length === 0 && (
                                                    <tr>
                                                        <td colSpan={cal.dates.length + 1} className="px-4 py-10 text-center text-sm text-text-muted">
                                                            Tidak ada data mengajar untuk periode ini. Ubah filter periode atau unit.
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </table>
                                        <div className="flex flex-wrap gap-x-4 gap-y-1 border-t border-border px-6 py-3 text-xs text-text-secondary">
                                            <span className="flex items-center gap-1.5"><span className="h-3 w-3 rounded bg-success" /> Semua JP hadir</span>
                                            <span className="flex items-center gap-1.5"><span className="h-3 w-3 rounded bg-warning" /> Ada telat</span>
                                            <span className="flex items-center gap-1.5"><span className="h-3 w-3 rounded bg-danger" /> Ada alpa</span>
                                            <span className="flex items-center gap-1.5"><span className="h-3 w-3 rounded bg-border" /> Tanpa jadwal</span>
                                            <span className="ml-auto text-text-muted">Format: hadir+telat / JP</span>
                                        </div>
                                    </div>
                                );
                            })()}

                            {activePreview.report_type === 'rekap_kehadiran_harian' && previewData.matrix && (() => {
                                const mx = previewData.matrix;
                                const dayShort = (ds) => {
                                    const dt = new Date(ds + 'T00:00:00');
                                    return ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'][dt.getDay()];
                                };
                                const dateLabel = (ds) => {
                                    const dt = new Date(ds + 'T00:00:00');
                                    return dt.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
                                };
                                const LETTER_NAMES = { H: 'Hadir', T: 'Telat', S: 'Sakit', I: 'Izin', C: 'Cuti', A: 'Alpa / Tidak Hadir', L: 'Libur (Akhir Pekan / Hari Libur)' };
                                return (
                                    <div className="overflow-x-auto">
                                        <div className="px-4 py-2 text-xs text-text-muted bg-surface border-b border-border italic">
                                            % Kehadiran = (Hadir + Telat) / Hari Kerja &times; 100. 1 sel = 1 status per tanggal.
                                        </div>
                                        <table className="min-w-full divide-y divide-border">
                                            <thead className="bg-surface">
                                                <tr>
                                                    <th scope="col" className="sticky left-0 z-10 bg-surface px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Nama Pegawai</th>
                                                    {mx.dates.map((ds) => (
                                                        <th key={ds} scope="col" className="px-2 py-3 text-center text-xs font-bold uppercase tracking-wider text-text-muted">
                                                            <div>{dayShort(ds)}</div>
                                                            <div className="font-extrabold text-text-secondary">{dateLabel(ds)}</div>
                                                        </th>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-border">
                                                {mx.rows.map((r) => (
                                                    <tr key={r.id ?? r.nama} className="transition-colors hover:bg-surface">
                                                        <td className="sticky left-0 z-10 bg-surface-card px-4 py-2.5 text-sm font-semibold text-primary whitespace-nowrap">
                                                            {r.id ? (
                                                                <Link
                                                                    href={route('laporan.rekap-detail', {
                                                                        pegawai_id: r.id,
                                                                        start_date: activePreview.start_date,
                                                                        end_date: activePreview.end_date,
                                                                    })}
                                                                    className="hover:underline"
                                                                >
                                                                    {r.nama}
                                                                </Link>
                                                            ) : (
                                                                r.nama
                                                            )}
                                                        </td>
                                                        {mx.dates.map((ds) => {
                                                            const letter = r.cells[ds];
                                                            return (
                                                                <td key={ds} className="px-1 py-1.5 text-center" title={letter && LETTER_NAMES[letter] ? LETTER_NAMES[letter] : 'Tanpa record'}>
                                                                    <span className={`inline-flex min-w-[2.2rem] items-center justify-center rounded-lg px-1.5 py-1 text-xs font-bold ${LETTER_STYLES[letter] || 'bg-border/40 text-text-muted'}`}>
                                                                        {letter || '—'}
                                                                    </span>
                                                                </td>
                                                            );
                                                        })}
                                                    </tr>
                                                ))}
                                                {mx.rows.length === 0 && (
                                                    <tr>
                                                        <td colSpan={mx.dates.length + 1} className="px-4 py-10 text-center text-sm text-text-muted">
                                                            Tidak ada data kehadiran untuk periode ini. Ubah filter periode atau unit.
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </table>
                                        <div className="flex flex-wrap gap-x-4 gap-y-1 border-t border-border px-6 py-3 text-xs text-text-secondary">
                                            <span className="flex items-center gap-1.5"><span className="h-3 w-3 rounded bg-success" /> H Hadir</span>
                                            <span className="flex items-center gap-1.5"><span className="h-3 w-3 rounded bg-warning" /> T Telat</span>
                                            <span className="flex items-center gap-1.5"><span className="h-3 w-3 rounded bg-info" /> S Sakit</span>
                                            <span className="flex items-center gap-1.5"><span className="h-3 w-3 rounded bg-info" /> I Izin</span>
                                            <span className="flex items-center gap-1.5"><span className="h-3 w-3 rounded bg-info" /> C Cuti</span>
                                            <span className="flex items-center gap-1.5"><span className="h-3 w-3 rounded bg-danger" /> A Alpa / Tidak Hadir</span>
                                            <span className="flex items-center gap-1.5"><span className="h-3 w-3 rounded bg-border/50" /> L Libur</span>
                                        </div>
                                    </div>
                                );
                            })()}

                            {activePreview.report_type === 'presensi' && (() => {
                                const s = computePresensiSummary(previewData.data, previewData.headings);
                                const segs = [
                                    { key: 'hadir', label: 'Hadir', val: s.hadir, color: 'bg-success' },
                                    { key: 'telat', label: 'Telat', val: s.telat, color: 'bg-warning' },
                                    { key: 'izin', label: 'Izin/Sakit/Cuti', val: s.izin + s.sakit + s.cuti, color: 'bg-info' },
                                    { key: 'alpa', label: 'Alpa', val: s.alpa, color: 'bg-danger' },
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

                            {activePreview.report_type === 'rekap_mengajar' && previewData.summary && (() => {
                                const s = previewData.summary;
                                return (
                                    <div className="px-6 py-5">
                                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                                            <KpiCard label="Total Pegawai" value={s.totalPegawai} />
                                            <KpiCard label="Rata-rata Kehadiran" value={`${s.avgKehadiran}%`} accent />
                                            <KpiCard label="JP Terjadwal" value={fmtJp(s.totalTerjadwal)} />
                                            <KpiCard label="Hadir" value={fmtJp(s.totalHadir)} />
                                            <KpiCard label="Telat" value={fmtJp(s.totalTelat)} />
                                            <KpiCard label="Alpa" value={fmtJp(s.totalAlpa)} />
                                        </div>
                                    </div>
                                );
                            })()}

                            {['rekap_kehadiran', 'rekap_kehadiran_harian'].includes(activePreview.report_type) && previewData.summary && (() => {
                                const s = previewData.summary;
                                return (
                                    <div className="px-6 py-5">
                                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                            <KpiCard label="Total Pegawai" value={s.totalPegawai} />
                                            <KpiCard label="Rata-rata Kehadiran" value={`${s.avgKehadiran}%`} accent />
                                        </div>
                                    </div>
                                );
                            })()}

                            {activePreview.report_type === 'rekap_kehadiran' && (
                            <div className="overflow-x-auto">
                                <div className="px-4 py-2 text-xs text-text-muted bg-surface border-b border-border italic">
                                    % Kehadiran = (Hadir + Telat) / Hari Kerja &times; 100. Sakit, Izin, Cuti, Alpa tidak dihitung sebagai hadir.
                                </div>
                                <table className="min-w-full divide-y divide-border">
                                    <thead className="bg-surface">
                                        <tr>
                                            {previewData.headings.map((head, idx) => (
                                                <th key={idx} scope="col" className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">{head}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {previewData.data.map((row, rowIdx) => (
                                            <tr key={rowIdx} className="transition-colors hover:bg-surface">
                                                {row.map((cell, cellIdx) => {
                                                    const headerStr = previewData.headings[cellIdx] ? previewData.headings[cellIdx].toLowerCase() : '';
                                                    const isPersen = headerStr.includes('%');
                                                    let displayValue = cell;

                                                    if (isPersen && cell !== null && cell !== '-') {
                                                        displayValue = cell;
                                                    } else if (typeof cell === 'number' && !isPersen) {
                                                        displayValue = new Intl.NumberFormat('id-ID').format(cell);
                                                    }

                                                    // Nama pegawai (kolom pertama) - clickable untuk drill-down
                                                    if (cellIdx === 0 && previewData.pegawai_ids) {
                                                        const pegawaiId = previewData.pegawai_ids[rowIdx];

                                                        if (!pegawaiId) {
                                                            return (
                                                                <td key={cellIdx} className="whitespace-nowrap px-4 py-3 text-sm font-semibold text-primary">
                                                                    {displayValue}
                                                                </td>
                                                            );
                                                        }

                                                        return (
                                                            <td key={cellIdx} className="whitespace-nowrap px-4 py-3 text-sm font-semibold text-primary">
                                                                <Link
                                                                    href={route('laporan.rekap-detail', {
                                                                        pegawai_id: pegawaiId,
                                                                        start_date: activePreview.start_date,
                                                                        end_date: activePreview.end_date,
                                                                    })}
                                                                    className="hover:underline"
                                                                >
                                                                    {displayValue}
                                                                </Link>
                                                            </td>
                                                        );
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
                                                    Tidak ada data untuk filter dan rentang tanggal yang dipilih. Ubah filter tanggal atau unit.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            )}

                            {!['rekap_kehadiran', 'rekap_kehadiran_harian'].includes(activePreview.report_type) && !(activePreview.report_type === 'rekap_mengajar' && viewMode === 'kalender') && (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-border">
                                    <thead className="bg-surface">
                                        <tr>
                                            {previewData.headings.map((head, idx) => (
                                                <th key={idx} scope="col" className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">{head}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {previewData.data.map((row, rowIdx) => (
                                            <tr key={rowIdx} className="transition-colors hover:bg-surface">
                                                {row.map((cell, cellIdx) => {
                                                    const headerStr = previewData.headings[cellIdx] ? previewData.headings[cellIdx].toLowerCase() : '';
                                                    const isStatus = headerStr === 'status';
                                                    const isTipe = headerStr === 'tipe presensi';
                                                    const isCurrency = headerStr.includes('(rp)') || headerStr.includes('nominal') || headerStr.includes('rp)');
                                                    let displayValue = cell;

                                                     if (isCurrency && cell !== null && cell !== '-' && !isNaN(cell)) {
                                                        displayValue = new Intl.NumberFormat('id-ID').format(cell);
                                                    }

                                                    // Nama pegawai (kolom pertama) - clickable untuk drill-down
                                                    if (cellIdx === 0 && previewData.pegawai_ids) {
                                                        const pegawaiId = previewData.pegawai_ids[rowIdx];

                                                        if (!pegawaiId) {
                                                            return (
                                                                <td key={cellIdx} className="whitespace-nowrap px-4 py-3 text-sm font-semibold text-primary">
                                                                    {displayValue}
                                                                </td>
                                                            );
                                                        }

                                                        return (
                                                            <td key={cellIdx} className="whitespace-nowrap px-4 py-3 text-sm font-semibold text-primary">
                                                                <Link
                                                                    href={route('laporan.rekap-mengajar-detail', {
                                                                        pegawai_id: pegawaiId,
                                                                        start_date: activePreview.start_date,
                                                                        end_date: activePreview.end_date,
                                                                    })}
                                                                    className="hover:underline"
                                                                >
                                                                    {displayValue}
                                                                </Link>
                                                            </td>
                                                        );
                                                    }

                                                    return (
                                                        <td key={cellIdx} className="whitespace-nowrap px-4 py-3 text-sm text-text-secondary tabular-nums">
                                                            {isStatus ? <StatusBadge status={cell} /> : isTipe ? <TipeBadge tipe={cell} /> : displayValue}
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        ))}
                                        {previewData.data.length === 0 && (
                                            <tr>
                                                <td colSpan={previewData.headings.length} className="px-4 py-10 text-center text-sm text-text-muted">
                                                    Tidak ada data untuk filter dan rentang tanggal yang dipilih. Ubah filter tanggal atau unit.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
