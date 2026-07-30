import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Email Verification" />

            <div className="text-center mb-6">
                <div className="mx-auto mb-4 w-14 h-14 rounded-full bg-primary-50 text-primary flex items-center justify-center">
                    <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <h2 className="page-title text-xl">Verifikasi Email</h2>
                <p className="text-text-muted text-sm mt-2 leading-relaxed">
                    Terima kasih telah mendaftar! Sebelum memulai, silakan verifikasi alamat email Anda dengan mengklik tautan yang telah kami kirimkan.
                </p>
            </div>

            {status === 'verification-link-sent' && (
                <div className="mb-5 p-4 bg-success-light text-success text-sm font-medium rounded-card border border-success/30 flex items-start">
                    <svg className="w-5 h-5 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Tautan verifikasi baru telah dikirim ke email yang Anda daftarkan.
                </div>
            )}

            <form onSubmit={submit}>
                <div className="flex flex-col sm:flex-row items-center justify-between gap-3">
                    <PrimaryButton disabled={processing} className="w-full sm:w-auto">
                        Kirim Ulang Email Verifikasi
                    </PrimaryButton>

                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="link text-sm"
                    >
                        Keluar
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
