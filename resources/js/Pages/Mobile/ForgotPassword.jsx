import AuthField from '@/Components/Auth/AuthField';
import MobileAuthShell from '@/Components/Auth/MobileAuthShell';
import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Mail, ShieldCheck } from 'lucide-react';

export default function MobileForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('presensi.password.email'), {
            preserveScroll: true,
        });
    };

    return (
        <MobileAuthShell
            title="Lupa Kata Sandi"
            heading="Lupa kata sandi?"
            description="Masukkan email terdaftar. Kami akan mengirim tautan reset ke inbox Anda."
        >
            {status && (
                <div role="status" className="mb-3 flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 shadow-sm">
                    <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0" />
                    <span>{status}</span>
                </div>
            )}

            <form onSubmit={submit} autoComplete="on" className="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_18px_44px_-28px_rgba(15,23,42,0.45)] sm:p-6">
                <div className="mb-5">
                    <h2 className="text-xl font-bold tracking-tight text-slate-950">Atur ulang kata sandi</h2>
                    <p className="mt-1 text-sm text-slate-500">Kami akan mengirim tautan reset ke email Anda.</p>
                </div>

                <div className="space-y-4">
                    <AuthField
                        id="email"
                        name="email"
                        label="Email terdaftar"
                        icon={Mail}
                        type="email"
                        inputMode="email"
                        autoComplete="email"
                        autoCapitalize="none"
                        spellCheck="false"
                        autoFocus
                        placeholder="nama@yayasan.sch.id"
                        value={data.email}
                        error={errors.email}
                        onChange={(event) => setData('email', event.target.value)}
                    />

                    <button
                        type="submit"
                        disabled={processing || !data.email}
                        className="flex min-h-14 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-4 text-sm font-bold text-white transition-transform active:scale-[0.99] disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500"
                    >
                        {processing ? (
                            <>
                                <span className="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white" aria-hidden="true" />
                                <span>Mengirim tautan...</span>
                            </>
                        ) : (
                            <span>Kirim tautan reset</span>
                        )}
                    </button>

                    <Link
                        href={route('presensi.login')}
                        className="flex min-h-11 items-center justify-center gap-1.5 text-sm font-bold text-primary underline-offset-4 hover:underline focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
                    >
                        <ArrowLeft className="h-4 w-4" /> Kembali ke login
                    </Link>
                </div>
            </form>
        </MobileAuthShell>
    );
}
