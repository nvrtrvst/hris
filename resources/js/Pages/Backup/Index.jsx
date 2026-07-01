import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import PrimaryButton from '@/Components/PrimaryButton';

export default function BackupIndex({ auth, errors }) {
    const { post, processing } = useForm();

    const handleBackup = (e) => {
        e.preventDefault();
        // Native form submission to trigger download
        window.location.href = route('backup.download');
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-bold text-xl text-primary leading-tight">Backup Database</h2>}
        >
            <Head title="Backup Database" />

            <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div className="bg-white overflow-hidden shadow-sm sm:rounded-xl border border-border">
                    <div className="p-8">
                        <div className="flex items-start space-x-6">
                            <div className="bg-accent/20 p-4 rounded-full">
                                <svg className="w-12 h-12 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                                </svg>
                            </div>
                            <div className="flex-1">
                                <h3 className="text-2xl font-bold text-primary mb-2">Pencadangan Sistem (Database)</h3>
                                <p className="text-text-secondary mb-6 max-w-2xl">
                                    Fitur ini memungkinkan Anda untuk mengunduh salinan mentah (raw dump) dari database sistem HRIS. 
                                    File hasil unduhan berformat <code className="bg-gray-100 px-2 py-1 rounded text-sm text-pink-600">.sql</code> yang dapat digunakan untuk proses *restore* atau migrasi server di masa depan.
                                </p>

                                {errors?.backup && (
                                    <div className="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700">
                                        <p className="font-bold">Gagal!</p>
                                        <p>{errors.backup}</p>
                                    </div>
                                )}

                                <div className="bg-surface p-6 rounded-xl border border-secondary/20 inline-block">
                                    <h4 className="font-bold text-primary mb-2 flex items-center">
                                        <svg className="w-5 h-5 mr-2 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                        Perhatian
                                    </h4>
                                    <ul className="list-disc list-inside text-sm text-text-secondary space-y-1 mb-6">
                                        <li>Proses *backup* mungkin membutuhkan waktu beberapa detik hingga menit tergantung ukuran data.</li>
                                        <li>Pastikan koneksi internet stabil.</li>
                                        <li>Simpan file `.sql` di tempat yang aman karena memuat data rahasia yayasan.</li>
                                    </ul>

                                    <button 
                                        onClick={handleBackup}
                                        className="inline-flex items-center px-6 py-3 bg-primary border border-transparent rounded-lg font-bold text-white uppercase tracking-widest hover:bg-secondary focus:bg-secondary active:bg-primary focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 transition ease-in-out duration-150 shadow-lg hover:shadow-xl hover:-translate-y-0.5"
                                    >
                                        <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                        Backup & Download (.sql)
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
