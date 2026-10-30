import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Building2, Calendar, Download, Loader2, Search, UserRound, X } from 'lucide-react';

const fieldId = (label) => label.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

const Field = ({ label, children }) => (
    <div>
        <label htmlFor={fieldId(label)} className="form-label text-xs">{label}</label>
        <div className="mt-1">{children}</div>
    </div>
);

const Cell = ({ children, className = '' }) => (
    <td className={`px-2 py-1.5 text-center text-xs text-text-secondary ${className}`}>{children}</td>
);

export default function PresensiGuru({ auth, units = [], gurus = [], years = [], defaults = {} }) {
    const [unitId, setUnitId] = useState(String(defaults.unit_sekolah_id || ''));
    const [pegawaiId, setPegawaiId] = useState(String(defaults.pegawai_id || ''));
    const [periode, setPeriode] = useState(defaults.periode || new Date().toISOString().slice(0, 7));
    const [tahunAjaran, setTahunAjaran] = useState(defaults.tahun_ajaran || '');
    const [semester, setSemester] = useState(String(defaults.semester || 1));
    const [preview, setPreview] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const guruOptions = useMemo(
        () => gurus.filter((guru) => !unitId || guru.unit_ids.includes(Number(unitId))),
        [gurus, unitId],
    );

    const params = () => ({
        unit_sekolah_id: unitId,
        pegawai_id: pegawaiId,
        periode,
        tahun_ajaran: tahunAjaran,
        semester,
    });

    const pilihUnit = (value) => {
        setUnitId(value);
        const first = gurus.find((guru) => guru.unit_ids.includes(Number(value)));
        setPegawaiId(first ? String(first.id) : '');
        setPreview(null);
    };

    const handlePreview = async () => {
        setLoading(true);
        setError('');
        try {
            const res = await axios.get(route('laporan.presensi-guru.preview'), { params: params() });
            setPreview(res.data);
        } catch (e) {
            setError(e?.response?.data?.message || 'Gagal memuat pratinjau. Periksa unit, guru, dan periode.');
        }
        setLoading(false);
    };

    const handleDownload = () => {
        const query = new URLSearchParams(params()).toString();
        window.location.href = `${route('laporan.presensi-guru.pdf')}?${query}`;
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h1 className="page-title">Laporan Presensi Pegawai</h1>}>
            <Head title="Laporan Presensi Pegawai" />
            <div className="min-h-screen bg-surface py-8">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <div>
                        <h2 className="text-xl font-extrabold text-text-primary">Presensi Pegawai Bulanan</h2>
                        <p className="text-sm text-text-muted">
                            Laporan per pegawai dengan JP aktual, persentase kehadiran harian, mingguan, dan bulanan.
                        </p>
                    </div>

                    <div className="card p-5">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                            <Field label="Unit Sekolah">
                                <div className="relative">
                                    <Building2 className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <select id={fieldId('Unit Sekolah')} className="select-field pl-9" value={unitId} onChange={(e) => pilihUnit(e.target.value)}>
                                        <option value="">Pilih unit</option>
                                        {units.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}</option>)}
                                    </select>
                                </div>
                            </Field>

                            <Field label="Pegawai">
                                <div className="relative">
                                    <UserRound className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <select id={fieldId('Pegawai')} className="select-field pl-9" value={pegawaiId} onChange={(e) => { setPegawaiId(e.target.value); setPreview(null); }}>
                                        <option value="">Pilih pegawai</option>
                                        {guruOptions.map((guru) => <option key={guru.id} value={guru.id}>{guru.nama}</option>)}
                                    </select>
                                </div>
                            </Field>

                            <Field label="Bulan">
                                <div className="relative">
                                    <Calendar className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                    <input id={fieldId('Bulan')} type="month" className="input-field pl-9" value={periode} onChange={(e) => { setPeriode(e.target.value); setPreview(null); }} />
                                </div>
                            </Field>

                            <Field label="Tahun Pelajaran">
                                <select className="select-field" value={tahunAjaran} onChange={(e) => { setTahunAjaran(e.target.value); setPreview(null); }}>
                                    {years.length === 0 && <option value={tahunAjaran}>{tahunAjaran}</option>}
                                    {years.map((year) => <option key={year} value={year}>{year}</option>)}
                                </select>
                            </Field>

                            <Field label="Semester">
                                <select className="select-field" value={semester} onChange={(e) => { setSemester(e.target.value); setPreview(null); }}>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                </select>
                            </Field>
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
                            <button type="button" onClick={handlePreview} disabled={loading || !unitId || !pegawaiId} className="btn-primary inline-flex items-center gap-2">
                                {loading ? <><Loader2 className="h-4 w-4 animate-spin" /> Memuat...</> : <><Search className="h-4 w-4" /> Tampilkan Pratinjau</>}
                            </button>
                            <button type="button" onClick={handleDownload} disabled={!unitId || !pegawaiId} className="btn-secondary inline-flex items-center gap-2">
                                <Download className="h-4 w-4" /> Unduh PDF
                            </button>
                        </div>
                    </div>

                    {loading && (
                        <div role="status" className="card flex items-center justify-center gap-3 p-12">
                            <Loader2 className="h-6 w-6 animate-spin text-primary" />
                            <span className="text-sm font-semibold text-text-secondary">Memuat...</span>
                        </div>
                    )}

                    {!loading && preview && (
                        <div className="space-y-4">
                            <div className="card flex flex-wrap items-center justify-between gap-3 p-4">
                                <div>
                                    <p className="text-sm font-extrabold text-text-primary">{preview.pegawai.nama}</p>
                                    <p className="text-xs text-text-muted">
                                        {preview.periode_label} · {preview.tahun_ajaran} · Semester {preview.semester}
                                    </p>
                                </div>
                                <div className="flex gap-3 text-xs font-bold text-text-secondary">
                                    <span>JP aktual: {preview.summary.actual_jp}</span>
                                    <span>JP terjadwal: {preview.summary.scheduled_jp}</span>
                                    <span>B (bulan): {preview.summary.b !== null ? `${preview.summary.b}%` : '-'}</span>
                                </div>
                            </div>

                            {preview.weeks.map((week) => (
                                <div key={week.label} className="card overflow-hidden">
                                    <div className="border-b border-border bg-surface px-4 py-3">
                                        <h3 className="text-sm font-extrabold text-primary">{week.label}</h3>
                                        <p className="mt-0.5 text-xs text-text-muted">
                                            {week.actual_jp} / {week.scheduled_jp} JP · M: {week.m ?? '-'}%
                                        </p>
                                    </div>
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-border text-xs">
                                            <thead className="bg-surface">
                                                <tr>
                                                    <th className="px-2 py-2 text-center font-bold text-text-muted">No</th>
                                                    <th className="px-2 py-2 text-left font-bold text-text-muted">Tanggal</th>
                                                    <th className="px-2 py-2 text-left font-bold text-text-muted">Hari</th>
                                                    <th className="px-2 py-2 text-center font-bold text-text-muted">Datang</th>
                                                    {Array.from({ length: preview.slot_count }, (_, index) => (
                                                        <th key={index} className="px-1.5 py-2 text-center font-bold text-text-muted">{index + 1}</th>
                                                    ))}
                                                    <th className="px-2 py-2 text-center font-bold text-text-muted">JP</th>
                                                    <th className="px-1.5 py-2 text-center font-bold text-text-muted">S</th>
                                                    <th className="px-1.5 py-2 text-center font-bold text-text-muted">I</th>
                                                    <th className="px-1.5 py-2 text-center font-bold text-text-muted">TK</th>
                                                    <th className="px-1.5 py-2 text-center font-bold text-text-muted">Tugas</th>
                                                    <th className="px-2 py-2 text-center font-bold text-text-muted">Pulang</th>
                                                    <th className="px-2 py-2 text-center font-bold text-text-muted">PH</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-border">
                                                {week.rows.map((row, index) => (
                                                    <tr key={row.tanggal_iso} className={row.tanggal_iso === undefined ? '' : ''}>
                                                        <Cell>{index + 1}</Cell>
                                                        <Cell className="text-left font-medium">{row.tanggal}</Cell>
                                                        <Cell className="text-left">{row.hari}</Cell>
                                                        <Cell>{row.jam_datang || '-'}</Cell>
                                                        {Array.from({ length: preview.slot_count }, (_, slot) => (
                                                            <Cell key={slot} className="font-bold text-emerald-700">{row.slots[slot + 1] ? '✓' : ''}</Cell>
                                                        ))}
                                                        <Cell>{row.jumlah_jp}</Cell>
                                                        <Cell>{row.s ? '✓' : ''}</Cell>
                                                        <Cell>{row.i ? '✓' : ''}</Cell>
                                                        <Cell>{row.tk ? '✓' : ''}</Cell>
                                                        <Cell>{row.tugas ? 'TUGAS' : ''}</Cell>
                                                        <Cell>{row.jam_pulang || '-'}</Cell>
                                                        <Cell>{row.ph ?? '-'}</Cell>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            ))}

                            <p className="text-xs text-text-muted">
                                PH = persentase kehadiran harian berbobot unit. M = persentase kehadiran mingguan. B = persentase kehadiran bulanan. JP tetap dihitung dari presensi per jadwal.
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
