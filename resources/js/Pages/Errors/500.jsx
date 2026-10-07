import { Head, Link, usePage } from '@inertiajs/react';

export default function ServerError() {
    const { portal = 'admin' } = usePage().props;
    return (
        <>
            <Head title="500 - Server Error" />

            <div className="min-h-screen bg-surface flex items-center justify-center px-6">
                <div className="w-full max-w-lg text-center">

                    <div className="mb-6">
                        <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-2xl bg-amber-50">
                            <svg
                                className="h-10 w-10 text-amber-500"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="1.8"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    d="M12 9v4m0 4h.01M10.29 3.86l-8.1 14a2 2 0 001.73 3h16.16a2 2 0 001.73-3l-8.1-14a2 2 0 00-3.42 0z"
                                />
                            </svg>
                        </div>
                    </div>

                    <p className="text-7xl font-bold tracking-tight text-slate-800">
                        500
                    </p>

                    <h1 className="mt-4 text-2xl font-semibold text-slate-800">
                        Terjadi Kesalahan
                    </h1>

                    <p className="mx-auto mt-3 max-w-md text-sm leading-6 text-slate-500">
                        Maaf, terjadi kesalahan pada server. Silakan coba
                        beberapa saat lagi atau kembali ke halaman utama.
                    </p>

                    <div className="mt-8 flex justify-center gap-3">
                        <Link
                            href={portal === 'mobile' ? route('presensi.dashboard') : '/dashboard'}
                            className="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white transition hover:bg-primary-800"
                        >
                            Kembali ke Dashboard
                        </Link>

                        <button
                            onClick={() => window.location.reload()}
                            className="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Coba Lagi
                        </button>
                    </div>

                </div>
            </div>
        </>
    );
}

