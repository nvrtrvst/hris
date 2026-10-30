import { useEffect, useMemo, useRef, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import {
    AlertTriangle,
    Building2,
    Calendar,
    Download,
    FileSpreadsheet,
    Loader2,
    Search,
    Users,
    X,
} from 'lucide-react';

const today = () => new Date().toISOString().slice(0, 10);

const dateValue = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const presetRange = (preset) => {
    const now = new Date();

    if (preset === 'week') {
        const day = now.getDay() || 7;
        const start = new Date(now);
        start.setDate(now.getDate() - day + 1);
        const end = new Date(start);
        end.setDate(start.getDate() + 6);

        return { from: dateValue(start), to: dateValue(end) };
    }

    if (preset === 'month') {
        return {
            from: dateValue(new Date(now.getFullYear(), now.getMonth(), 1)),
            to: dateValue(new Date(now.getFullYear(), now.getMonth() + 1, 0)),
        };
    }

    return { from: dateValue(now), to: dateValue(now) };
};

const fmtDate = (value) => {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date);
};

const fmtJp = (value) => {
    const number = Number(value) || 0;

    return Number.isInteger(number) ? String(number) : number.toFixed(2).replace('.', ',');
};

const metricLabel = {
    alpa: 'Alpa',
    hadir_tanpa_jp: 'Hari hadir tanpa JP',
    jp_kurang: 'Hari JP kurang',
    izin_mengajar: 'Hari izin tetapi mengajar',
    kelas_belum: 'Kelas belum terpantau',
};

const statusClass = {
    belum_terpantau: 'badge-danger',
    kelas_kosong: 'badge-warning',
    perlu_cek: 'badge-info',
    digantikan: 'badge-info',
};

const statusLabel = {
    belum_terpantau: 'Belum terpantau',
    kelas_kosong: 'Kelas kosong',
    perlu_cek: 'Perlu cek',
    digantikan: 'Digantikan',
};

const DetailTable = ({ metric, rows }) => {
    if (metric === 'kelas_belum') {
        return (
            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-border text-xs">
                    <thead className="bg-surface">
                        <tr>
                            <th className="px-3 py-2 text-left font-bold text-text-muted">Tanggal</th>
                            <th className="px-3 py-2 text-left font-bold text-text-muted">Jam</th>
                            <th className="px-3 py-2 text-left font-bold text-text-muted">Kelas</th>
                            <th className="px-3 py-2 text-left font-bold text-text-muted">Mapel</th>
                            <th className="px-3 py-2 text-left font-bold text-text-muted">Guru</th>
                            <th className="px-3 py-2 text-left font-bold text-text-muted">Status</th>
                            <th className="px-3 py-2 text-left font-bold text-text-muted">Sumber</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                        {rows.map((row, index) => (
                            <tr key={`${row.tanggal}-${row.jam}-${index}`}>
                                <td className="whitespace-nowrap px-3 py-2">{fmtDate(row.tanggal)}</td>
                                <td className="whitespace-nowrap px-3 py-2">{row.jam}</td>
                                <td className="px-3 py-2">{row.kelas || '-'}</td>
                                <td className="px-3 py-2">{row.mapel || '-'}</td>
                                <td className="px-3 py-2">{row.guru || '-'}</td>
                                <td className="px-3 py-2">
                                    <span className={`badge ${statusClass[row.status] || 'badge-info'}`}>
                                        {statusLabel[row.status] || row.status}
                                    </span>
                                </td>
                                <td className="px-3 py-2 text-text-muted">{row.sumber}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        );
    }

    return (
        <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-border text-xs">
                <thead className="bg-surface">
                    <tr>
                        <th className="px-3 py-2 text-left font-bold text-text-muted">Pegawai</th>
                        <th className="px-3 py-2 text-left font-bold text-text-muted">Unit</th>
                        <th className="px-3 py-2 text-left font-bold text-text-muted">Tanggal</th>
                        <th className="px-3 py-2 text-left font-bold text-text-muted">Keterangan</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-border">
                    {rows.map((row, index) => (
                        <tr key={`${row.pegawai_id}-${row.tanggal}-${index}`}>
                            <td className="px-3 py-2 font-semibold text-text-primary">{row.pegawai}</td>
                            <td className="px-3 py-2 text-text-secondary">{row.unit}</td>
                            <td className="whitespace-nowrap px-3 py-2">{fmtDate(row.tanggal)}</td>
                            <td className="px-3 py-2 text-text-secondary">
                                {metric === 'alpa' && row.sumber}
                                {metric === 'hadir_tanpa_jp' && `JP terjadwal ${fmtJp(row.scheduled_jp)}, JP aktual ${fmtJp(row.actual_jp)}`}
                                {metric === 'jp_kurang' && `JP terjadwal ${fmtJp(row.scheduled_jp)}, JP aktual ${fmtJp(row.actual_jp)}, kurang ${fmtJp(row.kurang)} JP`}
                                {metric === 'izin_mengajar' && `Izin ${row.izin}, realisasi ${row.realisasi}`}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
};

export default function Pengawasan({ auth, units = [], defaults = {} }) {
    const initial = useRef(false);
    const [unitId, setUnitId] = useState(String(defaults.unit_sekolah_id || ''));
    const [from, setFrom] = useState(defaults.from || today());
    const [to, setTo] = useState(defaults.to || today());
    const [jenisPegawai, setJenisPegawai] = useState(defaults.jenis_pegawai || 'all');
    const [preview, setPreview] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [metric, setMetric] = useState(null);

    const params = useMemo(() => {
        const query = { from, to, jenis_pegawai: jenisPegawai };
        if (unitId) query.unit_sekolah_id = unitId;

        return query;
    }, [unitId, from, to, jenisPegawai]);

    const detailRows = metric ? preview?.details?.[metric] || [] : [];

    const loadPreview = async (query = params) => {
        setLoading(true);
        setError('');
        setMetric(null);

        try {
            const res = await axios.get(route('laporan.pengawasan.preview'), { params: query });
            setPreview(res.data);
        } catch (e) {
            setPreview(null);
            setError(e?.response?.data?.message || 'Gagal memuat laporan. Periksa periode dan unit.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (initial.current) return;
        initial.current = true;
        loadPreview();
    }, []);

    const applyPreset = (preset) => {
        const range = presetRange(preset);
        setFrom(range.from);
        setTo(range.to);
        setMetric(null);
    };

    const download = (type) => {
        const query = new URLSearchParams(params).toString();
        window.location.href = `${route(`laporan.pengawasan.${type}`)}?${query}`;
    };

    const summary = preview?.summary;
    const cards = summary ? [
        { key: 'alpa', label: 'Alpa', value: summary.alpa, description: 'Tanpa presensi dan tanpa izin' },
        { key: 'hadir_tanpa_jp', label: 'Hari hadir tanpa JP', value: summary.hadir_tanpa_jp, description: 'Guru hadir, JP kosong' },
        { key: 'jp_kurang', label: 'Hari JP kurang', value: summary.jp_kurang, description: 'JP aktual kurang dari jadwal' },
        { key: 'izin_mengajar', label: 'Hari izin tetapi mengajar', value: summary.izin_mengajar, description: 'Approval dan realisasi berbeda' },
        { key: 'kelas_belum', label: 'Kelas belum terpantau', value: summary.kelas_belum, description: 'Jadwal tanpa presensi dan piket' },
    ] : [];

    return (
        <AuthenticatedLayout user={auth.user} header={<h1 className="page-title">Laporan Pengawasan</h1>}>
            <Head title="Laporan Pengawasan Kehadiran & Mengajar" />
            <div className="min-h-screen bg-surface py-8">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <div>
                        <h2 className="text-xl font-extrabold text-text-primary">Laporan Pengawasan Kehadiran &amp; Mengajar</h2>
                        <p className="text-sm text-text-muted">
                            Ringkasan seluruh pegawai, pengecualian kehadiran, JP guru, dan kelas yang belum terpantau.
                        </p>
                    </div>

                    <div className="card p-5">
                        <div className="flex flex-wrap gap-2">
                            <button type="button" className="btn-secondary text-xs" onClick={() => applyPreset('day')}>Hari ini</button>
                            <button type="button" className="btn-secondary text-xs" onClick={() => applyPreset('week')}>Minggu ini</button>
                            <button type="button" className="btn-secondary text-xs" onClick={() => applyPreset('month')}>Bulan ini</button>
                        </div>

                        <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                            <div>
                                <label htmlFor="unit" className="form-label text-xs">Unit</label>
                                <div className="relative mt-1">
                                    <Building2 className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <select id="unit" className="select-field pl-9" value={unitId} onChange={(e) => setUnitId(e.target.value)}>
                                        {units.length > 1 && <option value="">Semua unit</option>}
                                        {units.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}</option>)}
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label htmlFor="from" className="form-label text-xs">Dari</label>
                                <div className="relative mt-1">
                                    <Calendar className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <input id="from" type="date" className="input-field pl-9" value={from} onChange={(e) => setFrom(e.target.value)} />
                                </div>
                            </div>
                            <div>
                                <label htmlFor="to" className="form-label text-xs">Sampai</label>
                                <div className="relative mt-1">
                                    <Calendar className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <input id="to" type="date" className="input-field pl-9" value={to} onChange={(e) => setTo(e.target.value)} />
                                </div>
                            </div>
                            <div>
                                <label htmlFor="jenis" className="form-label text-xs">Jenis Pegawai</label>
                                <select id="jenis" className="select-field mt-1" value={jenisPegawai} onChange={(e) => setJenisPegawai(e.target.value)}>
                                    <option value="all">Semua pegawai</option>
                                    <option value="guru">Pendidik</option>
                                    <option value="non_guru">Tenaga kependidikan</option>
                                </select>
                            </div>
                            <div className="flex items-end">
                                <button type="button" onClick={() => loadPreview()} disabled={loading} className="btn-primary inline-flex w-full items-center justify-center gap-2">
                                    {loading ? <><Loader2 className="h-4 w-4 animate-spin" /> Memuat...</> : <><Search className="h-4 w-4" /> Tampilkan</>}
                                </button>
                            </div>
                        </div>

                        {error && (
                            <div role="alert" className="mt-4 flex items-start justify-between gap-3 rounded-xl border border-danger/30 bg-danger-light px-4 py-3 text-sm font-semibold text-danger">
                                <span>{error}</span>
                                <button type="button" aria-label="Tutup pesan" onClick={() => setError('')} className="shrink-0 rounded-md p-1 hover:bg-danger/10">
                                    <X className="h-4 w-4" />
                                </button>
                            </div>
                        )}

                        <div className="mt-5 flex flex-col gap-3 border-t border-border pt-5 sm:flex-row">
                            <button type="button" onClick={() => download('pdf')} disabled={!preview || loading} className="btn-secondary inline-flex items-center gap-2">
                                <Download className="h-4 w-4" /> Unduh PDF
                            </button>
                            <button type="button" onClick={() => download('excel')} disabled={!preview || loading} className="btn-secondary inline-flex items-center gap-2">
                                <FileSpreadsheet className="h-4 w-4" /> Unduh Excel
                            </button>
                        </div>
                    </div>

                    {loading && (
                        <div role="status" className="card flex items-center justify-center gap-3 p-12">
                            <Loader2 className="h-6 w-6 animate-spin text-primary" />
                            <span className="text-sm font-semibold text-text-secondary">Memuat laporan...</span>
                        </div>
                    )}

                    {!loading && preview && summary && (
                        <>
                            <div className="card p-5">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-extrabold text-text-primary">{preview.unit?.nama || 'Semua Unit'}</p>
                                        <p className="text-xs text-text-muted">
                                            {preview.period.label} · {preview.period.tahun_ajaran} · Semester {preview.period.semester}
                                        </p>
                                    </div>
                                    <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-text-muted">
                                        <Users className="h-4 w-4" /> {summary.employees} pegawai
                                    </span>
                                </div>

                                <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                                    <div className="rounded-lg border border-border p-3"><p className="text-[10px] font-bold uppercase text-text-muted">Hari pegawai</p><p className="text-xl font-extrabold">{summary.employee_days}</p></div>
                                    <div className="rounded-lg border border-border p-3"><p className="text-[10px] font-bold uppercase text-text-muted">Hadir</p><p className="text-xl font-extrabold text-success">{summary.hadir}</p></div>
                                    <div className="rounded-lg border border-border p-3"><p className="text-[10px] font-bold uppercase text-text-muted">Telat</p><p className="text-xl font-extrabold text-warning">{summary.telat}</p></div>
                                    <div className="rounded-lg border border-border p-3"><p className="text-[10px] font-bold uppercase text-text-muted">Izin/Sakit/Cuti</p><p className="text-xl font-extrabold">{summary.izin_sakit_cuti}</p></div>
                                    <div className="rounded-lg border border-border p-3"><p className="text-[10px] font-bold uppercase text-text-muted">Alpa</p><p className="text-xl font-extrabold text-danger">{summary.alpa}</p></div>
                                    <div className="rounded-lg border border-border p-3"><p className="text-[10px] font-bold uppercase text-text-muted">JP (jam pelajaran)</p><p className="text-xl font-extrabold">{fmtJp(summary.actual_jp)} dari {fmtJp(summary.scheduled_jp)}</p></div>
                                </div>
                            </div>

                            <div className="card p-5">
                                <h3 className="text-sm font-extrabold uppercase tracking-wide text-primary">Perlu Tindakan</h3>
                                <div className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-5">
                                    {cards.map((card) => (
                                        <button
                                            key={card.key}
                                            type="button"
                                            aria-expanded={metric === card.key}
                                            onClick={() => setMetric(metric === card.key ? null : card.key)}
                                            className={`rounded-lg border p-3 text-left transition-colors ${metric === card.key ? 'border-danger bg-danger-light' : 'border-border bg-white hover:border-danger/40'}`}
                                        >
                                            <span className="block text-lg font-extrabold text-danger">{card.value}</span>
                                            <span className="block text-xs font-bold text-text-primary">{card.label}</span>
                                            <span className="mt-1 block text-[10px] text-text-muted">{card.description}</span>
                                        </button>
                                    ))}
                                </div>

                                {metric && (
                                    <div className="mt-4 border-t border-border pt-4">
                                        <div className="mb-3 flex items-center justify-between gap-3">
                                            <p className="text-xs font-bold text-text-secondary">
                                                Detail: {metricLabel[metric]} ({detailRows.length} baris)
                                            </p>
                                            <button type="button" className="btn-secondary text-xs" onClick={() => setMetric(null)}>Tutup</button>
                                        </div>
                                        {detailRows.length === 0 ? (
                                            <div className="rounded-lg border border-dashed border-border bg-surface p-4 text-center text-xs text-text-muted">
                                                Tidak ada temuan untuk filter ini.
                                            </div>
                                        ) : (
                                            <DetailTable metric={metric} rows={detailRows} />
                                        )}
                                    </div>
                                )}
                            </div>

                            <div className="card overflow-hidden">
                                <div className="border-b border-border bg-surface px-4 py-3">
                                    <h3 className="text-sm font-extrabold uppercase tracking-wide text-primary">Matriks Pegawai</h3>
                                    <p className="text-xs text-text-muted">{Math.round(preview.period.days)} hari · JP hanya untuk guru</p>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-border text-xs">
                                        <thead className="bg-surface">
                                            <tr>
                                                <th className="px-3 py-2 text-left font-bold text-text-muted">Pegawai</th>
                                                <th className="px-3 py-2 text-left font-bold text-text-muted">Unit</th>
                                                <th className="px-3 py-2 text-center font-bold text-text-muted">Hadir (hari)</th>
                                                <th className="px-3 py-2 text-center font-bold text-text-muted">Telat (hari)</th>
                                                <th className="px-3 py-2 text-center font-bold text-text-muted">Izin/Sakit/Cuti (hari)</th>
                                                <th className="px-3 py-2 text-center font-bold text-text-muted">Alpa (hari)</th>
                                                <th className="px-3 py-2 text-center font-bold text-text-muted">JP (aktual/terjadwal)</th>
                                                <th className="px-3 py-2 text-left font-bold text-text-muted">Temuan</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border">
                                            {preview.matrix.map((row) => (
                                                <tr key={row.pegawai_id}>
                                                    <td className="px-3 py-2 font-semibold text-text-primary">{row.pegawai}</td>
                                                    <td className="px-3 py-2 text-text-secondary">{row.unit}</td>
                                                    <td className="px-3 py-2 text-center">{row.hadir}</td>
                                                    <td className="px-3 py-2 text-center">{row.telat}</td>
                                                    <td className="px-3 py-2 text-center">{row.izin + row.sakit + row.cuti}</td>
                                                    <td className="px-3 py-2 text-center">{row.alpa}</td>
                                                    <td className="px-3 py-2 text-center">{row.is_guru ? `${fmtJp(row.actual_jp)} dari ${fmtJp(row.scheduled_jp)}` : '-'}</td>
                                                    <td className="px-3 py-2 text-text-secondary">{row.temuan}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                <p className="border-t border-border px-4 py-3 text-[10px] text-text-muted">
                                    Hadir/Telat/Izin/Alpa dihitung per hari. JP = jam pelajaran; durasi mengikuti durasi JP unit.
                                </p>
                            </div>

                            <div className="card overflow-hidden">
                                <div className="border-b border-border bg-surface px-4 py-3">
                                    <h3 className="text-sm font-extrabold uppercase tracking-wide text-primary">Kelas &amp; Mengajar</h3>
                                    <p className="text-xs text-text-muted">Hanya jadwal yang bermasalah</p>
                                </div>
                                {preview.class_issues.length === 0 ? (
                                    <div className="p-6 text-center text-xs text-text-muted">Tidak ada kelas bermasalah pada filter ini.</div>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-border text-xs">
                                            <thead className="bg-surface">
                                                <tr>
                                                    <th className="px-3 py-2 text-left font-bold text-text-muted">Tanggal</th>
                                                    <th className="px-3 py-2 text-left font-bold text-text-muted">Jam</th>
                                                    <th className="px-3 py-2 text-left font-bold text-text-muted">Kelas</th>
                                                    <th className="px-3 py-2 text-left font-bold text-text-muted">Guru</th>
                                                    <th className="px-3 py-2 text-left font-bold text-text-muted">Status</th>
                                                    <th className="px-3 py-2 text-left font-bold text-text-muted">Sumber</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-border">
                                                {preview.class_issues.map((row, index) => (
                                                    <tr key={`${row.tanggal}-${row.jam}-${index}`}>
                                                        <td className="whitespace-nowrap px-3 py-2">{fmtDate(row.tanggal)}</td>
                                                        <td className="whitespace-nowrap px-3 py-2">{row.jam}</td>
                                                        <td className="px-3 py-2">{row.kelas || '-'}</td>
                                                        <td className="px-3 py-2">{row.guru || '-'}</td>
                                                        <td className="px-3 py-2"><span className={`badge ${statusClass[row.status] || 'badge-info'}`}>{statusLabel[row.status] || row.status}</span></td>
                                                        <td className="px-3 py-2 text-text-muted">{row.sumber}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        </>
                    )}

                    {!loading && !preview && !error && (
                        <div className="card p-6 text-center text-sm text-text-muted">
                            Pilih periode lalu klik Tampilkan.
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
