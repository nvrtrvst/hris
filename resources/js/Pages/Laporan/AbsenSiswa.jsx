import React, { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { Download, Eye, FileText, Loader2, Search, X } from 'lucide-react';

const STATUS_LABEL = {
    hadir: 'Hadir',
    telat: 'Telat',
    izin: 'Izin',
    sakit: 'Sakit',
    alpa: 'Alpa',
};

const STATUS_CLASS = {
    hadir: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    telat: 'bg-amber-50 text-amber-700 border-amber-200',
    izin: 'bg-sky-50 text-sky-700 border-sky-200',
    sakit: 'bg-violet-50 text-violet-700 border-violet-200',
    alpa: 'bg-rose-50 text-rose-700 border-rose-200',
};

const pct = (value) => `${Number(value ?? 0).toFixed(1)}%`;

export default function AbsenSiswa({ auth, filters, summary, sessions = [], students = [], classes = [], classSummary = [], perhatian = [], units = [], canFilterUnit }) {
    const { flash } = usePage().props;
    const [form, setForm] = useState({
        mode: filters.mode || 'daily',
        start_date: filters.start_date,
        end_date: filters.end_date,
        unit_id: filters.unit_id || '',
        class_label: filters.class_label || '',
        status: filters.status || 'semua',
        search: filters.search || '',
    });
    const [detail, setDetail] = useState(null);
    const [detailLoading, setDetailLoading] = useState(false);
    const [detailError, setDetailError] = useState('');

    const params = useMemo(() => {
        const next = { mode: form.mode };
        if (form.mode === 'period') {
            next.start_date = form.start_date;
            next.end_date = form.end_date;
        }
        if (form.unit_id) next.unit_id = form.unit_id;
        if (form.class_label) next.class_label = form.class_label;
        if (form.status && form.status !== 'semua') next.status = form.status;
        if (form.search.trim()) next.search = form.search.trim();
        return next;
    }, [form]);

    const submit = (e) => {
        e.preventDefault();
        router.get(route('laporan.absen-siswa'), params, { preserveState: true, preserveScroll: true });
    };

    const gantiMode = (mode) => {
        const next = { ...form, mode };
        setForm(next);
        const query = { mode };
        if (mode === 'period') {
            query.start_date = next.start_date;
            query.end_date = next.end_date;
        }
        if (next.unit_id) query.unit_id = next.unit_id;
        if (next.class_label) query.class_label = next.class_label;
        router.get(route('laporan.absen-siswa'), query, { preserveState: true, preserveScroll: true });
    };

    const bukaDetail = async (student) => {
        setDetail({ siswa: student, rows: [], revisi: [] });
        setDetailError('');
        setDetailLoading(true);
        try {
            const query = new URLSearchParams({
                nis: student.nis,
                start_date: filters.start_date,
                end_date: filters.end_date,
            });
            if (filters.unit_id) query.set('unit_id', filters.unit_id);
            if (filters.class_label) query.set('class_label', filters.class_label);
            const res = await fetch(`${route('laporan.absen-siswa.detail')}?${query.toString()}`);
            if (!res.ok) throw new Error('Gagal memuat detail siswa.');
            setDetail(await res.json());
        } catch (error) {
            setDetailError(error.message);
        } finally {
            setDetailLoading(false);
        }
    };

    const unduh = (kind) => {
        window.location.href = route(`laporan.absen-siswa.${kind}`, params);
    };

    const summaryItems = [
        ['Sesi', summary.sesi],
        ['Siswa', summary.siswa],
        ['Hadir', summary.hadir],
        ['Telat', summary.telat],
        ['Izin', summary.izin],
        ['Sakit', summary.sakit],
        ['Alpa', summary.alpa],
        ['% Hadir', pct(summary.persen)],
    ];

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="page-title">Laporan Absen Siswa</h2>}>
            <Head title="Laporan Absen Siswa" />

            <div className="mx-auto w-full max-w-7xl space-y-5">
                {flash?.message && (
                    <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                        {flash.message}
                    </div>
                )}

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="inline-flex rounded-lg border border-border bg-white p-1">
                        <button type="button" onClick={() => gantiMode('daily')} className={`rounded-md px-3 py-1.5 text-sm font-bold ${filters.mode === 'daily' ? 'bg-primary text-white' : 'text-text-secondary'}`}>
                            Hari Ini
                        </button>
                        <button type="button" onClick={() => gantiMode('period')} className={`rounded-md px-3 py-1.5 text-sm font-bold ${filters.mode === 'period' ? 'bg-primary text-white' : 'text-text-secondary'}`}>
                            Periode
                        </button>
                    </div>
                    <div className="flex gap-2">
                        <button type="button" onClick={() => unduh('export')} className="btn-secondary inline-flex items-center gap-1.5">
                            <Download className="h-4 w-4" /> Excel
                        </button>
                        <button type="button" onClick={() => unduh('pdf')} className="btn-secondary inline-flex items-center gap-1.5">
                            <FileText className="h-4 w-4" /> PDF
                        </button>
                    </div>
                </div>

                <form onSubmit={submit} className="page-card p-4 sm:p-5">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
                        {filters.mode === 'period' && (
                            <>
                                <label className="block">
                                    <span className="form-label">Dari tanggal</span>
                                    <input type="date" value={form.start_date} onChange={(e) => setForm({ ...form, start_date: e.target.value })} className="input-field w-full" required />
                                </label>
                                <label className="block">
                                    <span className="form-label">Sampai tanggal</span>
                                    <input type="date" value={form.end_date} onChange={(e) => setForm({ ...form, end_date: e.target.value })} className="input-field w-full" required />
                                </label>
                            </>
                        )}
                        {canFilterUnit && (
                            <label className="block">
                                <span className="form-label">Unit</span>
                                <select value={form.unit_id} onChange={(e) => setForm({ ...form, unit_id: e.target.value })} className="select-field w-full">
                                    <option value="">Semua unit</option>
                                    {units.map((u) => <option key={u.id} value={u.id}>{u.nama}</option>)}
                                </select>
                            </label>
                        )}
                        <label className="block">
                            <span className="form-label">Kelas</span>
                            <select value={form.class_label} onChange={(e) => setForm({ ...form, class_label: e.target.value })} className="select-field w-full">
                                <option value="">Semua kelas</option>
                                {classes.map((c) => <option key={c} value={c}>{c}</option>)}
                            </select>
                        </label>
                        <label className="block">
                            <span className="form-label">Status</span>
                            <select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })} className="select-field w-full">
                                <option value="semua">Semua</option>
                                {Object.entries(STATUS_LABEL).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                            </select>
                        </label>
                        <label className="block">
                            <span className="form-label">Cari siswa</span>
                            <input value={form.search} onChange={(e) => setForm({ ...form, search: e.target.value })} className="input-field w-full" placeholder="Nama atau NIS" maxLength={100} />
                        </label>
                        <div className="flex items-end">
                            <button type="submit" className="btn-primary inline-flex w-full items-center justify-center gap-1.5">
                                <Search className="h-4 w-4" /> Tampilkan
                            </button>
                        </div>
                    </div>
                    {filters.mode === 'period' && (
                        <p className="form-hint mt-2">Persentase = (Hadir + Telat) / Sesi tercatat. Hanya sesi kelas yang sudah diabsen.</p>
                    )}
                </form>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                    {summaryItems.map(([label, value]) => (
                        <div key={label} className="page-card px-3 py-3">
                            <p className="text-[10px] font-bold uppercase tracking-wide text-text-muted">{label}</p>
                            <p className="mt-1 text-lg font-extrabold tabular-nums text-text-primary">{value}</p>
                        </div>
                    ))}
                </div>

                {filters.mode === 'daily' ? (
                    <div className="page-card card-table">
                        <div className="border-b border-border px-4 py-3">
                            <h3 className="section-title">Absensi Hari Ini</h3>
                            <p className="mt-0.5 text-xs text-text-muted">{sessions.length} kelas tercatat</p>
                        </div>
                        {sessions.length === 0 ? (
                            <p className="px-4 py-8 text-center text-sm text-text-muted">Belum ada absensi siswa pada filter ini.</p>
                        ) : (
                            <div className="divide-y divide-border">
                                {sessions.map((session) => (
                                    <div key={session.id} className="px-4 py-3">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p className="text-sm font-bold text-text-primary">{session.class_label} <span className="font-normal text-text-muted">· {session.unit}</span></p>
                                            <p className="text-xs text-text-muted">{session.done_at} · {session.pegawai}</p>
                                        </div>
                                        <p className="mt-1 text-xs text-text-secondary">
                                            H {session.counts.hadir} · T {session.counts.telat} · I {session.counts.izin} · S {session.counts.sakit} · A {session.counts.alpa} · {session.total} siswa
                                        </p>
                                        {session.non_hadir.length === 0 ? (
                                            <p className="mt-1 text-xs font-semibold text-emerald-700">Semua siswa hadir.</p>
                                        ) : (
                                            <ul className="mt-2 divide-y divide-border/60 rounded-lg bg-surface px-3 py-1">
                                                {session.non_hadir.map((row) => (
                                                    <li key={`${session.id}-${row.nis}`} className="flex flex-wrap items-center justify-between gap-2 py-1.5 text-xs">
                                                        <span><span className="font-bold">{row.nama}</span> <span className="text-text-muted">({row.nis})</span></span>
                                                        <span className={`inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold ${STATUS_CLASS[row.status] || ''}`}>{STATUS_LABEL[row.status] || row.status}</span>
                                                        {row.keterangan && <span className="w-full text-text-secondary sm:w-auto">{row.keterangan}</span>}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                ) : (
                    <>
                        {perhatian.length > 0 && (
                            <div className="page-card card-table">
                                <div className="border-b border-border px-4 py-3">
                                    <h3 className="section-title">Perlu Perhatian</h3>
                                    <p className="mt-0.5 text-xs text-text-muted">Siswa dengan ketidakhadiran pada periode ini. Wali kelas menentukan tindak lanjut.</p>
                                </div>
                                <div className="table-wrap">
                                    <table className="table-base">
                                        <thead><tr><th>NIS</th><th>Nama</th><th>Kelas</th><th>A</th><th>S</th><th>I</th><th>T</th><th>%</th></tr></thead>
                                        <tbody>
                                            {perhatian.map((student) => (
                                                <tr key={`perhatian-${student.unit_id}-${student.nis}`}>
                                                    <td className="font-mono text-xs">{student.nis}</td>
                                                    <td className="font-semibold">{student.nama}</td>
                                                    <td>{student.class_label}</td>
                                                    <td className="font-bold text-rose-700">{student.alpa}</td>
                                                    <td>{student.sakit}</td>
                                                    <td>{student.izin}</td>
                                                    <td>{student.telat}</td>
                                                    <td>{pct(student.persen)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        <div className="page-card card-table">
                            <div className="border-b border-border px-4 py-3">
                                <h3 className="section-title">Rekap Siswa</h3>
                                <p className="mt-0.5 text-xs text-text-muted">{students.length} siswa · {summary.sesi} sesi tercatat</p>
                            </div>
                            {students.length === 0 ? (
                                <p className="px-4 py-8 text-center text-sm text-text-muted">Belum ada data absensi pada periode ini.</p>
                            ) : (
                                <div className="table-wrap">
                                    <table className="table-base">
                                        <thead><tr><th>NIS</th><th>Nama</th><th>Kelas</th><th>Sesi</th><th>H</th><th>T</th><th>I</th><th>S</th><th>A</th><th>%</th><th></th></tr></thead>
                                        <tbody>
                                            {students.map((student) => (
                                                <tr key={`${student.unit_id}-${student.nis}`}>
                                                    <td className="font-mono text-xs">{student.nis}</td>
                                                    <td className="font-semibold">{student.nama}</td>
                                                    <td>{student.class_label}</td>
                                                    <td>{student.sesi_tercatat}</td>
                                                    <td>{student.hadir}</td>
                                                    <td>{student.telat}</td>
                                                    <td>{student.izin}</td>
                                                    <td>{student.sakit}</td>
                                                    <td className={student.alpa > 0 ? 'font-bold text-rose-700' : ''}>{student.alpa}</td>
                                                    <td>{pct(student.persen)}</td>
                                                    <td>
                                                        <button type="button" onClick={() => bukaDetail(student)} className="inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline">
                                                            <Eye className="h-3.5 w-3.5" /> Detail
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>

                        {classSummary.length > 0 && (
                            <div className="page-card card-table">
                                <div className="border-b border-border px-4 py-3"><h3 className="section-title">Rekap Kelas</h3></div>
                                <div className="table-wrap">
                                    <table className="table-base">
                                        <thead><tr><th>Kelas</th><th>Siswa</th><th>Hadir</th><th>Telat</th><th>Izin</th><th>Sakit</th><th>Alpa</th></tr></thead>
                                        <tbody>
                                            {classSummary.map((row) => (
                                                <tr key={row.class_label}>
                                                    <td className="font-semibold">{row.class_label}</td>
                                                    <td>{row.siswa}</td><td>{row.hadir}</td><td>{row.telat}</td><td>{row.izin}</td><td>{row.sakit}</td>
                                                    <td className={row.alpa > 0 ? 'font-bold text-rose-700' : ''}>{row.alpa}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>

            {detail && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-label="Detail absensi siswa">
                    <div className="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-t-2xl bg-white p-4 shadow-xl sm:rounded-2xl">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <h3 className="text-base font-extrabold text-text-primary">{detail.siswa?.nama || 'Detail Siswa'}</h3>
                                <p className="text-xs text-text-muted">{detail.siswa?.nis} · {detail.siswa?.class_label || '-'}</p>
                            </div>
                            <button type="button" onClick={() => setDetail(null)} className="rounded-lg p-1.5 text-text-muted hover:bg-surface" aria-label="Tutup detail">
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        {detailLoading && <p className="flex items-center gap-2 py-6 text-sm text-text-muted"><Loader2 className="h-4 w-4 animate-spin" /> Memuat detail...</p>}
                        {detailError && <p className="py-4 text-sm font-semibold text-rose-700">{detailError}</p>}
                        {!detailLoading && !detailError && (
                            <>
                                <div className="table-wrap mt-4">
                                    <table className="table-base">
                                        <thead><tr><th>Tanggal</th><th>Kelas</th><th>Status</th><th>Keterangan</th><th>Petugas</th></tr></thead>
                                        <tbody>
                                            {detail.rows.length === 0 && <tr><td colSpan={5} className="py-5 text-center text-sm text-text-muted">Tidak ada detail.</td></tr>}
                                            {detail.rows.map((row, i) => (
                                                <tr key={`${row.tanggal}-${i}`}>
                                                    <td className="whitespace-nowrap font-mono text-xs">{row.tanggal}</td>
                                                    <td>{row.class_label}</td>
                                                    <td><span className={`inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold ${STATUS_CLASS[row.status] || ''}`}>{STATUS_LABEL[row.status] || row.status}</span></td>
                                                    <td>{row.keterangan || '-'}</td>
                                                    <td>{row.petugas}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                {detail.revisi?.length > 0 && (
                                    <div className="mt-4">
                                        <h4 className="text-xs font-bold uppercase tracking-wide text-text-secondary">Riwayat Koreksi</h4>
                                        <ul className="mt-1 space-y-1 text-xs text-text-secondary">
                                            {detail.revisi.map((row, i) => <li key={i}>{row.created_at} · {row.peran} · {row.oleh || '-'} · {row.alasan || '-'}</li>)}
                                        </ul>
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
