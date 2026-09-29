import { Head, Link } from '@inertiajs/react';

export default function Forbidden() {
    return (
        <>
            <Head title="403 - Forbidden" />

            <div className="min-h-screen flex items-center justify-center bg-gray-50">
                <div className="text-center">
                    <div className="text-8xl font-bold text-gray-800">
                        403
                    </div>

                    <h1 className="mt-4 text-2xl font-semibold text-gray-800">
                        Akses Ditolak
                    </h1>

                    <p className="mt-2 text-gray-500">
                        Anda tidak memiliki izin untuk mengakses halaman ini.
                    </p>

                    <Link
                        href="/dashboard"
                        className="inline-block mt-6 rounded-lg bg-indigo-600 px-5 py-3 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        Kembali ke Dashboard
                    </Link>
                </div>
            </div>
        </>
    );
}
