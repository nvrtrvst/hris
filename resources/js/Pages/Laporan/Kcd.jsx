import { Fragment, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Building2, Calendar, Download, Loader2, Search } from 'lucide-react';

const Field = ({ label, children }) => (
    <div>
        <label className="form-label text-xs">{label}</label>
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
        try {
            const params = { unit_sekolah_id: unitId, periode: period };
            if (minggu) params.minggu = minggu;
            const res = await axios.get(route('laporan.kcd.preview'), { params });
            setPreview(res.data);
        } catch (e) {
            alert('Gagal memuat pratinjau. Pastikan unit & periode valid.');
        }
        setLoading(false);
    };

    const handleDownload = () => {
        const params = new URLSearchParams();
        params.append('unit_sekolah_id', unitId);
        params.append('periode', period);
        if (minggu) params.append('minggu', minggu);
        window.location.href = `${route('laporan.kcd.pdf')}?${params.toString()}`;
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="page-title">Laporan KCD</h2>}>
            <Head title="Laporan KCD" />
            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    <div>
                        <h3 className="text-xl font-extrabold text-text-primary">Laporan Presensi KCD (Bulanan)</h3>
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
                                        <select className="select-field pl-9" value={unitId} onChange={(e) => setUnitId(e.target.value)}>
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
                                    <input type="month" className="input-field pl-9" value={period} onChange={(e) => setPeriod(e.target.value)} />
                                </div>
                            </Field>
                            <Field label="Minggu">
                                <div className="relative">
                                    <Calendar className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <select className="select-field pl-9" value={minggu} onChange={(e) => setMinggu(e.target.value)}>
                                        <option value="">Semua (1 bulan)</option>
                                        {Array.from({ length: weekCount }, (_, i) => (
                                            <option key={i + 1} value={i + 1}>Minggu {i + 1}</option>
                                        ))}
                                    </select>
                                </div>
                            </Field>
                        </div>
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
                        <div className="card flex items-center justify-center gap-3 p-12">
                            <Loader2 className="h-6 w-6 animate-spin text-primary" />
                            <span className="text-sm font-semibold text-text-secondary">Memuat…</span>
                        </div>
                    )}

                    {!loading && preview && (
                        <div className="space-y-4">
                            {preview.weeks.map((week, wi) => (
                                <div key={wi} className="card overflow-hidden">
                                    <div className="px-6 py-3 border-b border-border bg-surface">
                                        <h4 className="text-sm font-extrabold text-primary">Minggu {wi + 1} &mdash; {preview.periode}</h4>
                                    </div>
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-border text-sm">
                                            <thead className="bg-surface">
                                                <tr>
                                                    <th className="px-3 py-2 text-left text-xs font-bold text-text-muted">No</th>
                                                    <th className="px-3 py-2 text-left text-xs font-bold text-text-muted">Nama</th>
                                                    {week.days.map((d) => (
                                                        <th key={d.date} className="px-3 py-2 text-center text-xs font-bold text-text-muted" colSpan={2}>
                                                            {d.label} {d.short}
                                                        </th>
                                                    ))}
                                                </tr>
                                                <tr>
                                                    <th></th>
                                                    <th></th>
                                                    {week.days.map((d) => (
                                                        <Fragment key={d.date}>
                                                            <th className="px-2 py-1 text-center text-[10px] font-semibold text-text-muted">Masuk</th>
                                                            <th className="px-2 py-1 text-center text-[10px] font-semibold text-text-muted">Pulang</th>
                                                        </Fragment>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-border bg-white">
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
