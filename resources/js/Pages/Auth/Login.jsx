import AuthField from '@/Components/Auth/AuthField';
import AuthShell from '@/Components/Auth/AuthShell';
import { Link, useForm } from '@inertiajs/react';
import { ArrowRight, Eye, EyeOff, LockKeyhole, Mail, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

export default function Login({ status, canResetPassword, lembaga }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: true,
    });
    const [showPassword, setShowPassword] = useState(false);

    const submit = (event) => {
        event.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthShell
            title="Masuk"
            portal="Portal Admin"
            lembaga={lembaga}
            heading="Kelola SDM terpusat & terstruktur."
            heroFooter={
                <p className="border-t border-white/10 pt-5 text-[11px] text-emerald-100/60">
                    &copy; {new Date().getFullYear()} Yayasan. Sistem internal.
                </p>
            }
        >
            <div className="mb-5">
                <h2 className="text-xl font-bold tracking-tight text-slate-950">Masuk ke akun</h2>
                <p className="mt-1 text-sm text-slate-500">Gunakan email untuk masuk.</p>
            </div>

            {status && (
                <div role="status" className="mb-4 flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                    <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0" />
                    <span>{status}</span>
                </div>
            )}

            <form onSubmit={submit} autoComplete="on" className="space-y-4">
                <AuthField
                    id="email"
                    name="email"
                    label="Email"
                    icon={Mail}
                    type="email"
                    inputMode="email"
                    autoComplete="email"
                    autoCapitalize="none"
                    spellCheck="false"
                    autoFocus
                    placeholder="nama@email.com"
                    required
                    value={data.email}
                    error={errors.email}
                    onChange={(event) => setData('email', event.target.value)}
                />

                <AuthField
                    id="password"
                    name="password"
                    label="Kata sandi"
                    icon={LockKeyhole}
                    type={showPassword ? 'text' : 'password'}
                    autoComplete="current-password"
                    placeholder="Masukkan kata sandi"
                    required
                    value={data.password}
                    error={errors.password}
                    onChange={(event) => setData('password', event.target.value)}
                    suffix={
                        <button
                            type="button"
                            onClick={() => setShowPassword((value) => !value)}
                            className="absolute right-1.5 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-lg text-slate-500 transition-colors active:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
                            aria-label={showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                            aria-pressed={showPassword}
                        >
                            {showPassword ? <EyeOff className="h-5 w-5" /> : <Eye className="h-5 w-5" />}
                        </button>
                    }
                />

                <div className="flex min-h-11 items-center justify-between gap-4">
                    <label className="flex cursor-pointer select-none items-center gap-2.5 text-sm font-medium text-slate-600">
                        <input
                            type="checkbox"
                            name="remember"
                            checked={data.remember}
                            onChange={(event) => setData('remember', event.target.checked)}
                            className="h-5 w-5 rounded border-slate-300 text-primary focus:ring-primary/25"
                        />
                        Ingat saya
                    </label>
                    {canResetPassword && (
                        <Link href={route('password.request')} className="flex min-h-11 items-center text-sm font-bold text-primary underline-offset-4 hover:underline focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30">
                            Lupa sandi?
                        </Link>
                    )}
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="flex min-h-14 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-4 text-sm font-bold text-white transition-transform active:scale-[0.99] hover:bg-primary-600 disabled:cursor-wait disabled:opacity-70"
                >
                    {processing ? (
                        <>
                            <span className="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white" aria-hidden="true" />
                            <span>Memverifikasi...</span>
                        </>
                    ) : (
                        <>
                            <span>Masuk ke portal</span>
                            <ArrowRight className="h-5 w-5" />
                        </>
                    )}
                </button>
            </form>
        </AuthShell>
    );
}
