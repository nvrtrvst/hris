import { Fragment, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Building2, Calendar, Download, Loader2, Search, X } from 'lucide-react';

const fieldId = (label) => label.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

const Field = ({ label, children }) => (
    <div>
        <label htmlFor={fieldId(label)} className="form-label text-xs">{label}</label>
        <div className="mt-1">{children}</div>
    </div>
);

export default function Kcd({ auth, units }) {
    const [unitId, setUnitId] = useState(units.length ? String(units[0].id) : '');
    const now = new Date();
    const [period, setPeriod] = useState(`${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`);
    const [minggu, setMinggu] = useState('');
    const [preview, setPreview] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const weekCount = (() => {
        const [y, m] = period.split('-').map(Number);
        if (!y || !m) return 0;
        const first = new Date(y, m - 1, 1);
        const daysInMonth = new Date(y, m, 0).getDate();
        const offsetMon = (first.getDay() + 6) % 7;

        return Math.ceil((offsetMon + daysInMonth) / 7);
    })();

    const handlePreview = async () => {
        setLoading(true);
        setError('');
        try {
            const params = { unit_sekolah_id: unitId, periode: period };
            if (minggu) params.minggu = minggu;
            const res = await axios.get(route('laporan.kcd.preview'), { params });
            setPreview(res.data);
        } catch (e) {
            setError('Gagal memuat pratinjau. Pastikan unit & periode valid.');
        }
        setLoading(false);
    };

    const handleDownload = () => {
        setError('');
        const params = new URLSearchParams();
        params.append('unit_sekolah_id', unitId);
        params.append('periode', period);
        if (minggu) params.append('minggu', minggu);
        window.location.href = `${route('laporan.kcd.pdf')}?${params.toString()}`;
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h1 className="page-title">Laporan KCD</h1>}>
            <Head title="Laporan KCD" />
            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    <div>
                        <h2 className="text-xl font-extrabold text-text-primary">Laporan Presensi KCD (Bulanan)</h2>
                        <p className="text-sm text-text-muted">
                            Pilih unit &amp; bulan, lalu pratinjau atau unduh PDF daftar hadir per minggu (Senin&ndash;Jumat).
                        </p>
                    </div>

                    <div className="card p-6">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            {units.length > 1 && (
                                <Field label="Unit Sekolah">
                                    <div className="relative">
                                        <Building2 className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                        <select id={fieldId('Unit Sekolah')} className="select-field pl-9" value={unitId} onChange={(e) => setUnitId(e.target.value)}>
                                            {units.map((u) => (
                                                <option key={u.id} value={u.id}>{u.nama}</option>
                                            ))}
                                        </select>
                                    </div>
                                </Field>
                            )}
                            <Field label="Bulan">
                                <div className="relative">
                                    <Calendar className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <input id={fieldId('Bulan')} type="month" className="input-field pl-9" value={period} onChange={(e) => setPeriod(e.target.value)} />
                                </div>
                            </Field>
                            <Field label="Minggu">
                                <div className="relative">
                                    <Calendar className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <select id={fieldId('Minggu')} className="select-field pl-9" value={minggu} onChange={(e) => setMinggu(e.target.value)}>
                                        <option value="">Semua (1 bulan)</option>
                                        {Array.from({ length: weekCount }, (_, i) => (
                                            <option key={i + 1} value={i + 1}>Minggu {i + 1}</option>
                                        ))}
                                    </select>
                                </div>
                            </Field>
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

                        <div className="mt-6 flex flex-col gap-3 border-t border-border pt-5 sm:flex-row">
                            <button onClick={handlePreview} disabled={loading} className="btn-primary inline-flex items-center gap-2">
                                {loading ? <><Loader2 className="h-4 w-4 animate-spin" /> Memuat…</> : <><Search className="h-4 w-4" /> Tampilkan Pratinjau</>}
                            </button>
                            <button onClick={handleDownload} className="btn-secondary inline-flex items-center gap-2">
                                <Download className="h-4 w-4" /> Unduh PDF
                            </button>
                        </div>
                    </div>

                    {loading && (
                        <div role="status" className="card flex items-center justify-center gap-3 p-12">
                            <Loader2 className="h-6 w-6 animate-spin text-primary" />
                            <span className="text-sm font-semibold text-text-secondary">Memuat…</span>
                        </div>
                    )}

                    {!loading && preview && (
                        <div className="space-y-4">
                            {preview.weeks.map((week, wi) => (
                                <div key={wi} className="card overflow-hidden">
                                    <div className="px-6 py-3 border-b border-border bg-surface">
                                        <h3 className="text-sm font-extrabold text-primary">Minggu {wi + 1}, {preview.periode}</h3>
                                    </div>
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-border text-sm">
                                            <thead className="bg-surface">
                                                <tr>
                                                    <th scope="col" className="px-3 py-2 text-left text-xs font-bold text-text-muted">No</th>
                                                    <th scope="col" className="px-3 py-2 text-left text-xs font-bold text-text-muted">Nama</th>
                                                    {week.days.map((d) => (
                                                        <th key={d.date} scope="col" className="px-3 py-2 text-center text-xs font-bold text-text-muted" colSpan={2}>
                                                            {d.label} {d.short}
                                                        </th>
                                                    ))}
                                                </tr>
                                                <tr>
                                                    <th scope="col"></th>
                                                    <th scope="col"></th>
                                                    {week.days.map((d) => (
                                                        <Fragment key={d.date}>
                                                            <th scope="col" className="px-2 py-1 text-center text-xs font-semibold text-text-muted">Masuk</th>
                                                            <th scope="col" className="px-2 py-1 text-center text-xs font-semibold text-text-muted">Pulang</th>
                                                        </Fragment>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-border">
                                                {preview.pegawai.map((p) => (
                                                    <tr key={p.no}>
                                                        <td className="px-3 py-2 text-text-secondary">{p.no}</td>
                                                        <td className="px-3 py-2 text-text-secondary">{p.nama}</td>
                                                        {week.days.map((d) => {
                                                            const c = p.days[d.date];

                                                            return (
                                                                <Fragment key={d.date}>
                                                                    <td className="px-2 py-2 text-center tabular-nums text-text-secondary">{c.masuk}</td>
                                                                    <td className="px-2 py-2 text-center tabular-nums text-text-secondary">{c.pulang}{c.koordinasi ? '.' : ''}</td>
                                                                </Fragment>
                                                            );
                                                        })}
                                                    </tr>
                                                ))}
                                                {preview.pegawai.length === 0 && (
                                                    <tr>
                                                        <td colSpan={2 + week.days.length * 2} className="px-4 py-10 text-center text-sm text-text-muted">
                                                            Tidak ada pegawai aktif pada unit ini.
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            ))}
                            <p className="text-xs text-text-muted">
                                Pratinjau menampilkan {minggu ? `Minggu ${minggu}` : 'seluruh bulan'}. Tombol &quot;Unduh PDF&quot; menghasilkan PDF sesuai pilihan minggu (atau seluruh bulan bila &quot;Semua&quot;).
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
