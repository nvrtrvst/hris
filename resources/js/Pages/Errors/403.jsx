import { Head, Link, usePage } from '@inertiajs/react';

export default function Forbidden() {
    const { portal = 'admin', message = null } = usePage().props;
    const isMobile = portal === 'mobile';

    return (
        <>
            <Head title="403 - Forbidden" />

            <div className="min-h-screen flex items-center justify-center bg-surface">
                <div className="text-center px-6">
                    <div className="text-8xl font-bold text-gray-800">
                        403
                    </div>

                    <h1 className="mt-4 text-2xl font-semibold text-gray-800">
                        Akses Ditolak
                    </h1>

                    <p className="mt-2 text-gray-500">
                        {message || 'Anda tidak memiliki izin untuk mengakses halaman ini.'}
                    </p>

                    <Link
                        href={isMobile ? route('presensi.login') : '/dashboard'}
                        className="inline-block mt-6 rounded-lg bg-primary px-5 py-3 text-sm font-medium text-white hover:bg-primary-800"
                    >
                        {isMobile ? 'Kembali ke Halaman Login' : 'Kembali ke Dashboard'}
                    </Link>
                </div>
            </div>
        </>
    );
}
