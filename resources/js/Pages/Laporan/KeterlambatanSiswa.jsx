import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { Download, FileText, Search } from 'lucide-react';

export default function KeterlambatanSiswa({ auth, filters, rows = [], summary, perClass = [], perDay = [], classes = [], units = [], canFilterUnit = false }) {
    const [form, setForm] = useState({
        start_date: filters.start_date,
        end_date: filters.end_date,
        unit_id: filters.unit_id || '',
        class_label: filters.class_label || '',
        search: filters.search || '',
    });

    const submit = (e) => {
        e.preventDefault();
        const params = {
            start_date: form.start_date,
            end_date: form.end_date,
            class_label: form.class_label || undefined,
            search: form.search.trim() || undefined,
        };
        if (form.unit_id) params.unit_id = form.unit_id;
        router.get(route('laporan.keterlambatan-siswa'), params, { preserveState: true, preserveScroll: true });
    };

    const unduh = (kind) => {
        const params = {
            start_date: form.start_date,
            end_date: form.end_date,
            class_label: form.class_label || undefined,
            search: form.search.trim() || undefined,
        };
        if (form.unit_id) params.unit_id = form.unit_id;
        window.location.href = route(`laporan.keterlambatan-siswa.${kind}`, params);
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="page-title">Laporan Keterlambatan Siswa</h2>}>
            <Head title="Laporan Keterlambatan Siswa" />

            <div className="mx-auto w-full max-w-7xl space-y-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-text-secondary">Catatan keterlambatan siswa di pos gerbang.</p>
                    <div className="flex w-full gap-2 sm:w-auto">
                        <button type="button" onClick={() => unduh('export')} className="btn-secondary inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 sm:flex-none">
                            <Download className="h-4 w-4" /> Excel
                        </button>
                        <button type="button" onClick={() => unduh('pdf')} className="btn-secondary inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 sm:flex-none">
                            <FileText className="h-4 w-4" /> PDF
                        </button>
                    </div>
                </div>

                <form onSubmit={submit} className="page-card p-4 sm:p-5">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <label className="block">
                            <span className="form-label">Dari tanggal</span>
                            <input type="date" value={form.start_date} onChange={(e) => setForm({ ...form, start_date: e.target.value })} className="input-field w-full" required />
                        </label>
                        <label className="block">
                            <span className="form-label">Sampai tanggal</span>
                            <input type="date" value={form.end_date} onChange={(e) => setForm({ ...form, end_date: e.target.value })} className="input-field w-full" required />
                        </label>
                        {canFilterUnit && (
                            <label className="block">
                                <span className="form-label">Unit</span>
                                <select value={form.unit_id} onChange={(e) => setForm({ ...form, unit_id: e.target.value })} className="select-field w-full">
                                    <option value="">Semua unit</option>
                                    {units.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}</option>)}
                                </select>
                            </label>
                        )}
                        <label className="block">
                            <span className="form-label">Kelas</span>
                            <select value={form.class_label} onChange={(e) => setForm({ ...form, class_label: e.target.value })} className="select-field w-full">
                                <option value="">Semua kelas</option>
                                {classes.map((label) => <option key={label} value={label}>{label}</option>)}
                            </select>
                        </label>
                        <label className="block">
                            <span className="form-label">Cari siswa</span>
                            <input value={form.search} onChange={(e) => setForm({ ...form, search: e.target.value })} className="input-field w-full" placeholder="Nama atau NIS" maxLength={100} />
                        </label>
                        <div className="flex items-end sm:col-span-2 lg:col-span-1">
                            <button type="submit" className="btn-primary inline-flex min-h-11 w-full items-center justify-center gap-1.5">
                                <Search className="h-4 w-4" /> Tampilkan
                            </button>
                        </div>
                    </div>
                </form>

                <div className="grid grid-cols-3 gap-3">
                    {[
                        ['Total', summary.total],
                        ['Kelas', summary.kelas],
                        ['Siswa', summary.siswa],
                    ].map(([label, value]) => (
                        <div key={label} className="page-card px-3 py-3">
                            <p className="text-xs font-bold uppercase tracking-wide text-text-secondary">{label}</p>
                            <p className="mt-1 text-lg font-extrabold tabular-nums text-text-primary">{value}</p>
                        </div>
                    ))}
                </div>

                <div className="page-card card-table">
                    <div className="border-b border-border px-4 py-3">
                        <h3 className="section-title">Daftar Keterlambatan ({rows.length})</h3>
                        <p className="mt-0.5 text-xs text-text-secondary">Data per siswa per hari.</p>
                    </div>

                    {rows.length === 0 ? (
                        <p className="px-4 py-8 text-center text-sm text-text-secondary">Belum ada keterlambatan pada filter ini.</p>
                    ) : (
                        <>
                            <div className="divide-y divide-border lg:hidden">
                                {rows.map((row) => (
                                    <div key={row.id} className="p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-bold text-text-primary">{row.nama}</p>
                                                <p className="text-xs text-text-secondary">NIS {row.nis} · {row.class_label}</p>
                                            </div>
                                            <span className="shrink-0 font-mono text-sm font-bold text-amber-700">{row.jam}</span>
                                        </div>
                                        <p className="mt-2 text-xs text-text-secondary">{row.tanggal} · {row.unit || '-'} · Petugas {row.petugas || '-'}</p>
                                        {row.keterangan && <p className="mt-1 text-xs text-text-secondary">{row.keterangan}</p>}
                                    </div>
                                ))}
                            </div>

                            <div className="hidden table-wrap lg:block">
                                <table className="table-base">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Unit</th>
                                            <th>Kelas</th>
                                            <th>NIS</th>
                                            <th>Nama</th>
                                            <th>Jam</th>
                                            <th>Petugas</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.map((row) => (
                                            <tr key={row.id}>
                                                <td className="whitespace-nowrap font-mono text-xs">{row.tanggal}</td>
                                                <td>{row.unit || '-'}</td>
                                                <td>{row.class_label}</td>
                                                <td className="font-mono text-xs">{row.nis}</td>
                                                <td className="font-semibold">{row.nama}</td>
                                                <td className="whitespace-nowrap font-mono text-xs">{row.jam}</td>
                                                <td>{row.petugas || '-'}</td>
                                                <td className="text-text-secondary">{row.keterangan || '-'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                </div>

                {(perClass.length > 0 || perDay.length > 0) && (
                    <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
                        {perClass.length > 0 && (
                            <div className="page-card p-4">
                                <h3 className="section-title">Per Kelas</h3>
                                <ul className="mt-2 divide-y divide-border/60 text-sm">
                                    {perClass.map((row) => <li key={row.class_label} className="flex justify-between gap-3 py-2"><span>{row.class_label}</span><span className="font-bold">{row.total}</span></li>)}
                                </ul>
                            </div>
                        )}
                        {perDay.length > 0 && (
                            <div className="page-card p-4">
                                <h3 className="section-title">Per Hari</h3>
                                <ul className="mt-2 divide-y divide-border/60 text-sm">
                                    {perDay.map((row) => <li key={row.tanggal} className="flex justify-between gap-3 py-2"><span className="font-mono text-xs">{row.tanggal}</span><span className="font-bold">{row.total}</span></li>)}
                                </ul>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
