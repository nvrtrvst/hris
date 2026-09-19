import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, User, Calendar, Building2 } from 'lucide-react';

const STATUS_STYLES = {
    hadir: 'bg-green-100 text-green-800 ring-green-600/20',
    telat: 'bg-amber-100 text-amber-800 ring-amber-600/20',
    izin: 'bg-sky-100 text-sky-800 ring-sky-600/20',
    sakit: 'bg-indigo-100 text-indigo-800 ring-indigo-600/20',
    cuti: 'bg-violet-100 text-violet-800 ring-violet-600/20',
    alpa: 'bg-red-100 text-red-800 ring-red-600/20',
};

const StatusBadge = ({ status }) => {
    const key = String(status ?? '').toLowerCase();
    const cls = STATUS_STYLES[key] || 'bg-gray-100 text-gray-700 ring-gray-500/20';
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ${cls}`}>
            {status}
        </span>
    );
};

const KpiCard = ({ label, value, accent }) => (
    <div className={`rounded-xl border border-border p-4 ${accent ? 'bg-primary/5 ring-1 ring-primary/30' : 'bg-surface'}`}>
        <p className="text-xs font-medium text-text-muted">{label}</p>
        <p className={`mt-1 text-2xl font-extrabold ${accent ? 'text-primary' : 'text-text-primary'}`}>{value}</p>
    </div>
);

export default function RekapDetail({ auth, pegawai, detail, summary, periode }) {
    const totalStatus = summary.hadir + summary.telat + summary.sakit + summary.izin + summary.cuti + summary.alpa;
    const persen = totalStatus > 0 ? Math.round(((summary.hadir + summary.telat) / totalStatus) * 100) : 0;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Detail Kehadiran Pegawai</h2>}
        >
            <Head title={`Kehadiran - ${pegawai.nama}`} />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    {/* Back button */}
                    <button
                        onClick={() => router.get(route('laporan.index'))}
                        className="inline-flex items-center gap-1.5 text-sm font-semibold text-text-muted hover:text-primary transition-colors"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Kembali ke Laporan
                    </button>

                    {/* Header */}
                    <div className="card p-6">
                        <div className="flex items-start gap-4">
                            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <User className="h-6 w-6" />
                            </div>
                            <div>
                                <h3 className="text-xl font-extrabold text-text-primary">{pegawai.nama}</h3>
                                <div className="mt-1 flex flex-wrap items-center gap-3 text-sm text-text-muted">
                                    <span className="inline-flex items-center gap-1">
                                        <Building2 className="h-3.5 w-3.5" />
                                        {pegawai.jenis}
                                    </span>
                                    {pegawai.nuptk && (
                                        <span className="inline-flex items-center gap-1">
                                            <User className="h-3.5 w-3.5" />
                                            {pegawai.nuptk}
                                        </span>
                                    )}
                                    <span className="inline-flex items-center gap-1">
                                        <Calendar className="h-3.5 w-3.5" />
                                        {periode.start_date} s/d {periode.end_date}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Summary Cards */}
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-7">
                        <KpiCard label="% Kehadiran" value={`${persen}%`} accent />
                        <KpiCard label="Hadir" value={summary.hadir} />
                        <KpiCard label="Telat" value={summary.telat} />
                        <KpiCard label="Sakit" value={summary.sakit} />
                        <KpiCard label="Izin" value={summary.izin} />
                        <KpiCard label="Cuti" value={summary.cuti} />
                        <KpiCard label="Alpa" value={summary.alpa} />
                    </div>

                    {/* Detail Table */}
                    <div className="card overflow-hidden">
                        <div className="border-b border-border bg-surface px-6 py-4">
                            <h4 className="text-sm font-extrabold uppercase tracking-wide text-primary">Rincian Kehadiran Harian</h4>
                            <p className="mt-0.5 text-xs text-text-muted">{detail.length} hari tercatat</p>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-border">
                                <thead className="bg-surface">
                                    <tr>
                                        <th className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Tanggal</th>
                                        <th className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Hari</th>
                                        <th className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Status</th>
                                        <th className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Jam Masuk</th>
                                        <th className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Jam Keluar</th>
                                        <th className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Tipe</th>
                                        <th className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Unit</th>
                                        <th className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border bg-white">
                                    {detail.map((row, idx) => (
                                        <tr key={idx} className="transition-colors hover:bg-surface">
                                            <td className="whitespace-nowrap px-4 py-3 text-sm text-text-secondary tabular-nums">{row.tanggal}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-sm text-text-secondary">{row.hari}</td>
                                            <td className="whitespace-nowrap px-4 py-3"><StatusBadge status={row.status} /></td>
                                            <td className="whitespace-nowrap px-4 py-3 text-sm text-text-secondary tabular-nums">{row.jam_masuk || '-'}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-sm text-text-secondary tabular-nums">{row.jam_keluar || '-'}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-sm text-text-secondary">{row.tipe_presensi || '-'}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-sm text-text-secondary">{row.unit}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-sm text-text-secondary max-w-[200px] truncate" title={row.keterangan}>{row.keterangan || '-'}</td>
                                        </tr>
                                    ))}
                                    {detail.length === 0 && (
                                        <tr>
                                            <td colSpan={8} className="px-4 py-10 text-center text-sm text-text-muted">
                                                Tidak ada data kehadiran untuk periode ini.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
