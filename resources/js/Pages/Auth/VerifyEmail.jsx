import AuthShell from '@/Components/Auth/AuthShell';
import { Link, useForm } from '@inertiajs/react';
import { LogOut, MailCheck, ShieldCheck } from 'lucide-react';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (event) => {
        event.preventDefault();
        post(route('verification.send'));
    };

    return (
        <AuthShell
            title="Verifikasi Email"
            portal="Portal Admin"
            eyebrow="Aktivasi akun"
            heading={
                <>
                    Verifikasi{' '}
                    <span className="bg-gradient-to-r from-emerald-100 to-emerald-300 bg-clip-text text-transparent">
                        email Anda.
                    </span>
                </>
            }
            description="Sebelum memulai, silakan verifikasi alamat email Anda dengan mengklik tautan yang telah kami kirimkan."
            heroContent={
                <ul className="space-y-5">
                    <li className="flex items-start gap-3.5">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15">
                            <MailCheck className="h-5 w-5 text-emerald-200" />
                        </div>
                        <div>
                            <p className="text-sm font-bold text-white">Tautan dikirim ke email</p>
                            <p className="mt-0.5 text-xs leading-relaxed text-emerald-50/70">Cek inbox — atau folder spam — dalam beberapa menit.</p>
                        </div>
                    </li>
                    <li className="flex items-start gap-3.5">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15">
                            <ShieldCheck className="h-5 w-5 text-emerald-200" />
                        </div>
                        <div>
                            <p className="text-sm font-bold text-white">Aman &amp; satu kali pakai</p>
                            <p className="mt-0.5 text-xs leading-relaxed text-emerald-50/70">Tautan verifikasi kedaluwarsa setelah digunakan.</p>
                        </div>
                    </li>
                </ul>
            }
            heroFooter={
                <p className="text-[11px] text-emerald-100/60">&copy; {new Date().getFullYear()} Yayasan Nuurul Muttaqiin</p>
            }
        >
            <div className="flex flex-col items-center text-center">
                <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary">
                    <MailCheck className="h-7 w-7" />
                </div>
                <h2 className="mt-4 text-xl font-bold tracking-tight text-slate-950">Verifikasi email</h2>
                <p className="mt-2 text-sm leading-relaxed text-slate-500">
                    Terima kasih telah mendaftar! Klik tautan verifikasi yang kami kirimkan untuk mengaktifkan akun Anda.
                </p>
            </div>

            {status === 'verification-link-sent' && (
                <div role="status" className="mt-4 flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                    <MailCheck className="mt-0.5 h-4 w-4 shrink-0" />
                    <span>Tautan verifikasi baru telah dikirim ke email yang Anda daftarkan.</span>
                </div>
            )}

            <form onSubmit={submit} className="mt-6 space-y-3">
                <button
                    type="submit"
                    disabled={processing}
                    className="flex min-h-14 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-4 text-sm font-bold text-white transition-transform active:scale-[0.99] disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500"
                >
                    {processing ? (
                        <>
                            <span className="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white" aria-hidden="true" />
                            <span>Mengirim ulang...</span>
                        </>
                    ) : (
                        <span>Kirim ulang email verifikasi</span>
                    )}
                </button>

                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="flex min-h-11 w-full items-center justify-center gap-1.5 text-sm font-bold text-slate-500 underline-offset-4 hover:text-primary hover:underline focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
                >
                    <LogOut className="h-4 w-4" />
                    Keluar
                </Link>
            </form>
        </AuthShell>
    );
}
