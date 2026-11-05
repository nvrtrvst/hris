import AuthField from '@/Components/Auth/AuthField';
import MobileAuthShell from '@/Components/Auth/MobileAuthShell';
import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Eye, EyeOff, LockKeyhole, Mail, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

function PasswordToggle({ shown, onClick, label }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="absolute right-1.5 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-lg text-slate-500 transition-colors active:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
            aria-label={label}
            aria-pressed={shown}
        >
            {shown ? <EyeOff className="h-5 w-5" /> : <Eye className="h-5 w-5" />}
        </button>
    );
}

export default function MobileResetPassword({ token, email }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmation, setShowConfirmation] = useState(false);

    const submit = (event) => {
        event.preventDefault();
        post(route('presensi.password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <MobileAuthShell
            title="Buat Kata Sandi Baru"
            heading="Hampir selesai."
            description="Buat kata sandi baru untuk akun Anda."
        >
            <form onSubmit={submit} autoComplete="on" className="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_18px_44px_-28px_rgba(15,23,42,0.45)] sm:p-6">
                <div className="mb-5">
                    <h2 className="text-xl font-bold tracking-tight text-slate-950">Atur kata sandi baru</h2>
                    <p className="mt-1 text-sm text-slate-500">Minimal 8 karakter.</p>
                </div>

                <div className="space-y-4">
                    <AuthField
                        id="email"
                        name="email"
                        label="Email"
                        icon={Mail}
                        type="email"
                        autoComplete="username"
                        value={data.email}
                        error={errors.email}
                        readOnly
                    />

                    <AuthField
                        id="password"
                        name="password"
                        label="Kata sandi baru"
                        icon={LockKeyhole}
                        type={showPassword ? 'text' : 'password'}
                        autoComplete="new-password"
                        autoFocus
                        placeholder="Minimal 8 karakter"
                        value={data.password}
                        error={errors.password}
                        onChange={(event) => setData('password', event.target.value)}
                        suffix={
                            <PasswordToggle
                                shown={showPassword}
                                onClick={() => setShowPassword((value) => !value)}
                                label={showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                            />
                        }
                    />

                    <AuthField
                        id="password_confirmation"
                        name="password_confirmation"
                        label="Ulangi kata sandi"
                        icon={ShieldCheck}
                        type={showConfirmation ? 'text' : 'password'}
                        autoComplete="new-password"
                        placeholder="Ulangi kata sandi yang sama"
                        value={data.password_confirmation}
                        error={errors.password_confirmation}
                        onChange={(event) => setData('password_confirmation', event.target.value)}
                        suffix={
                            <PasswordToggle
                                shown={showConfirmation}
                                onClick={() => setShowConfirmation((value) => !value)}
                                label={showConfirmation ? 'Sembunyikan konfirmasi' : 'Tampilkan konfirmasi'}
                            />
                        }
                    />

                    <button
                        type="submit"
                        disabled={processing || !data.password || !data.password_confirmation}
                        className="flex min-h-14 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-4 text-sm font-bold text-white transition-transform active:scale-[0.99] disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500"
                    >
                        {processing ? (
                            <>
                                <span className="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white" aria-hidden="true" />
                                <span>Menyimpan...</span>
                            </>
                        ) : (
                            <span>Simpan kata sandi baru</span>
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
