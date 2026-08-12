import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { AlertTriangle, Database, Download, ShieldCheck } from 'lucide-react';

export default function BackupIndex({ auth, errors }) {
    const handleBackup = (e) => {
        e.preventDefault();
        window.location.href = route('backup.download');
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Backup Database</h2>}
        >
            <Head title="Backup Database" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    <div>
                        <h3 className="text-xl font-extrabold text-text-primary">Pencadangan Sistem (Database)</h3>
                        <p className="text-sm text-text-muted">Unduh salinan mentah (raw dump) database HRIS sebagai arsip keamanan.</p>
                    </div>

                    <div className="card p-6">
                        <div className="flex flex-col items-start gap-6 sm:flex-row sm:items-start">
                            <span className="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-primary/10">
                                <Database className="h-8 w-8 text-primary" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm leading-relaxed text-text-secondary">
                                    File hasil unduhan berformat <code className="rounded bg-surface px-1.5 py-0.5 text-xs font-bold text-danger">.sql</code>{' '}
                                    yang dapat digunakan untuk proses <em>restore</em> atau migrasi server di masa depan.
                                </p>

                                {errors?.backup && (
                                    <div className="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3">
                                        <p className="text-sm font-bold text-rose-800">Gagal!</p>
                                        <p className="mt-0.5 text-sm text-rose-700">{errors.backup}</p>
                                    </div>
                                )}

                                <div className="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-5">
                                    <h4 className="flex items-center gap-2 text-sm font-extrabold text-amber-800">
                                        <AlertTriangle className="h-4 w-4" /> Perhatian
                                    </h4>
                                    <ul className="mt-2 list-inside list-disc space-y-1 text-xs leading-relaxed text-amber-800/90">
                                        <li>Proses backup mungkin membutuhkan waktu beberapa detik hingga menit tergantung ukuran data.</li>
                                        <li>Pastikan koneksi internet stabil.</li>
                                        <li>Simpan file <code className="rounded bg-white px-1 py-0.5 font-bold">.sql</code> di tempat yang aman karena memuat data rahasia yayasan.</li>
                                    </ul>

                                    <button onClick={handleBackup} className="btn-primary mt-4 inline-flex items-center gap-2">
                                        <Download className="h-4 w-4" /> Backup & Download (.sql)
                                    </button>
                                </div>

                                <div className="mt-4 flex items-start gap-2 text-xs text-text-muted">
                                    <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                                    <p>Backup memuat seluruh data termasuk field terenkripsi (NIK, rekening, NPWP, BPJS). Simpan hanya di lokasi yang Anda kendalikan.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
