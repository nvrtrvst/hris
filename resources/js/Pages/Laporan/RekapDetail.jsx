import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, User, Calendar, GraduationCap, Hash } from 'lucide-react';

const STATUS_STYLES = {
    hadir: 'bg-success-light text-success ring-success/30',
    telat: 'bg-warning-light text-warning ring-warning/30',
    izin: 'bg-info-light text-info ring-info/30',
    sakit: 'bg-info-light text-info ring-info/30',
    cuti: 'bg-info-light text-info ring-info/30',
    alpa: 'bg-danger-light text-danger ring-danger/30',
};

const StatusBadge = ({ status }) => {
    const key = String(status ?? '').toLowerCase();
    const cls = STATUS_STYLES[key] || 'bg-surface text-text-secondary ring-border';
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ${cls}`}>
            {status}
        </span>
    );
};

export default function RekapDetail({ auth, pegawai, detail, summary, periode }) {
    const persen = summary.persen ?? (summary.hariKerja > 0 ? Math.min(100, Math.round(((summary.hadir + summary.telat) / summary.hariKerja) * 100)) : 0);

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h1 className="page-title">Detail Kehadiran Pegawai</h1>}
        >
            <Head title={`Kehadiran - ${pegawai.nama}`} />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    {/* Back button */}
                    <button
                        onClick={() => (window.history.length > 1 ? window.history.back() : router.get(route('laporan.index')))}
                        className="inline-flex items-center gap-1.5 text-sm font-semibold text-text-muted hover:text-primary transition-colors"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Kembali ke laporan
                    </button>

                    {/* Header */}
                    <div className="card p-6">
                        <div className="flex items-start gap-4">
                            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <User className="h-6 w-6" />
                            </div>
                            <div>
                                <h2 className="text-xl font-extrabold text-text-primary">{pegawai.nama}</h2>
                                <div className="mt-1 flex flex-wrap items-center gap-3 text-sm text-text-muted">
                                    <span className="inline-flex items-center gap-1">
                                        <GraduationCap className="h-3.5 w-3.5" />
                                        {pegawai.jenis}
                                    </span>
                                    {pegawai.nuptk && (
                                        <span className="inline-flex items-center gap-1">
                                            <Hash className="h-3.5 w-3.5" />
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

                    {/* Ringkasan: satu angka utama + rincian */}
                    <div className="card p-5">
                        <div className="flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wide text-text-muted">% Kehadiran berbobot</p>
                                <p className="mt-1 text-4xl font-extrabold tabular-nums text-primary">{persen}%</p>
                                <p className="mt-1 text-xs text-text-muted">dari {summary.hariKerja} hari kerja</p>
                            </div>
                            <dl className="grid grid-cols-2 gap-x-8 gap-y-1.5 text-sm sm:grid-cols-3">
                                <div className="flex items-baseline justify-between gap-3"><dt className="text-text-secondary">Hadir</dt><dd className="font-bold tabular-nums text-text-primary">{summary.hadir}</dd></div>
                                <div className="flex items-baseline justify-between gap-3"><dt className="text-text-secondary">Telat</dt><dd className="font-bold tabular-nums text-warning">{summary.telat}</dd></div>
                                <div className="flex items-baseline justify-between gap-3"><dt className="text-text-secondary">Alpa</dt><dd className="font-bold tabular-nums text-danger">{summary.alpa}</dd></div>
                                <div className="flex items-baseline justify-between gap-3"><dt className="text-text-secondary">Sakit lengkap</dt><dd className="font-bold tabular-nums text-text-primary">{summary.sakit_surat ?? summary.sakit}</dd></div>
                                <div className="flex items-baseline justify-between gap-3"><dt className="text-text-secondary">Sakit belum lengkap</dt><dd className="font-bold tabular-nums text-text-primary">{summary.sakit_tanpa_surat ?? 0}</dd></div>
                                <div className="flex items-baseline justify-between gap-3"><dt className="text-text-secondary">Izin</dt><dd className="font-bold tabular-nums text-info">{summary.izin}</dd></div>
                                <div className="flex items-baseline justify-between gap-3"><dt className="text-text-secondary">Cuti</dt><dd className="font-bold tabular-nums text-info">{summary.cuti}</dd></div>
                            </dl>
                        </div>
                    </div>

                    {/* Detail Table */}
                    <div className="card overflow-hidden">
                        <div className="border-b border-border bg-surface px-6 py-4">
                            <h3 className="text-sm font-extrabold uppercase tracking-wide text-primary">Rincian Kehadiran Harian</h3>
                            <p className="mt-0.5 text-xs text-text-muted">{detail.length} hari tercatat</p>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-border">
                                <thead className="bg-surface">
                                    <tr>
                                        <th scope="col" className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Tanggal</th>
                                        <th scope="col" className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Hari</th>
                                        <th scope="col" className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Status</th>
                                        <th scope="col" className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Jam Masuk</th>
                                        <th scope="col" className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Jam Keluar</th>
                                        <th scope="col" className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Tipe</th>
                                        <th scope="col" className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Unit</th>
                                        <th scope="col" className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
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
