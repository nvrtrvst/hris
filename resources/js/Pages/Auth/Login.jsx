import ApplicationLogo from '@/Components/ApplicationLogo';
import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Eye, EyeOff, ArrowRight, ShieldCheck, Lock, Mail } from 'lucide-react';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        login: '',
        password: '',
        remember: true,
    });

    const [showPassword, setShowPassword] = useState(false);

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <>
            <Head title="Masuk" />

            <div className="min-h-screen bg-surface font-figtree antialiased">
                <div className="flex min-h-screen flex-col lg:flex-row">
                    {/* Left brand panel */}
                    <aside className="relative hidden overflow-hidden bg-primary px-12 py-16 text-white lg:flex lg:w-2/5 lg:flex-col lg:justify-between">
                        <div className="absolute -right-32 -top-32 h-96 w-96 rounded-full bg-primary-600/40 blur-3xl" />
                        <div className="absolute -bottom-40 -left-20 h-96 w-96 rounded-full bg-accent/15 blur-3xl" />

                        <div className="relative">
                            <div className="flex items-center gap-3">
                                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/20 backdrop-blur">
                                    <ApplicationLogo className="h-6 w-6 fill-current text-white" />
                                </div>
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-accent">HRIS</p>
                                    <p className="text-[11px] text-white/50">Yayasan Pendidikan</p>
                                </div>
                            </div>
                        </div>

                        <div className="relative space-y-5">
                            <h1 className="text-3xl font-semibold leading-tight">
                                Kelola SDM<br />
                                <span className="text-accent">terpusat &amp; terstruktur.</span>
                            </h1>
                            <p className="max-w-sm text-sm text-white/60 leading-relaxed">
                                Presensi geofencing, penjadwalan, payroll dinamis, dan self-service untuk seluruh unit Yayasan.
                            </p>
                            <ul className="space-y-2.5 pt-2">
                                <li className="flex items-center gap-2.5 text-xs text-white/70">
                                    <ShieldCheck className="h-3.5 w-3.5 text-accent flex-shrink-0" />
                                    Enkripsi field sensitif (NIK, rekening, NPWP, BPJS)
                                </li>
                                <li className="flex items-center gap-2.5 text-xs text-white/70">
                                    <Lock className="h-3.5 w-3.5 text-accent flex-shrink-0" />
                                    Session terisolasi per portal (admin / mobile)
                                </li>
                            </ul>
                        </div>

                        <p className="relative text-xs text-white/30">
                            &copy; {new Date().getFullYear()} Yayasan. Sistem internal.
                        </p>
                    </aside>

                    {/* Right form panel */}
                    <main className="flex flex-1 items-center justify-center px-6 py-12 lg:px-16">
                        <div className="w-full max-w-sm">
                            {/* Mobile-only logo */}
                            <div className="mb-8 flex items-center gap-3 lg:hidden">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary text-white">
                                    <ApplicationLogo className="h-6 w-6 fill-current" />
                                </div>
                                <span className="text-sm font-semibold text-text-primary">HRIS Yayasan</span>
                            </div>

                            <div className="mb-8">
                                <h2 className="text-xl font-semibold text-text-primary">Selamat datang kembali</h2>
                                <p className="mt-1 text-sm text-text-muted">
                                    Masuk ke dasbor admin.
                                </p>
                            </div>

                            {status && (
                                <div className="mb-6 rounded-card border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
                                    {status}
                                </div>
                            )}

                            <form onSubmit={submit} className="space-y-4">
                                <div>
                                    <label htmlFor="login" className="form-label">
                                        Email atau NIP
                                    </label>
                                    <div className="relative mt-1">
                                        <Mail className="input-icon" />
                                        <input
                                            id="login"
                                            type="text"
                                            autoComplete="username"
                                            autoFocus
                                            value={data.login}
                                            onChange={(e) => setData('login', e.target.value)}
                                            className={`input-field input-affix ${errors.login ? 'input-error' : ''}`}
                                            placeholder="admin@yayasan.id"
                                        />
                                    </div>
                                    <InputError message={errors.login} className="mt-1.5" />
                                </div>

                                <div>
                                    <div className="flex items-center justify-between">
                                        <label htmlFor="password" className="form-label">
                                            Kata Sandi
                                        </label>
                                        {canResetPassword && (
                                            <Link
                                                href={route('password.request')}
                                                className="text-xs font-medium text-primary hover:text-primary-600"
                                            >
                                                Lupa?
                                            </Link>
                                        )}
                                    </div>
                                    <div className="relative mt-1">
                                        <Lock className="input-icon" />
                                        <input
                                            id="password"
                                            type={showPassword ? 'text' : 'password'}
                                            autoComplete="current-password"
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            className={`input-field input-affix pr-10 ${errors.password ? 'input-error' : ''}`}
                                            placeholder="••••••••"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword(!showPassword)}
                                            tabIndex={-1}
                                            className="absolute right-3 top-1/2 -translate-y-1/2 text-text-muted hover:text-text-primary transition-colors"
                                            aria-label={showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                                        >
                                            {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                        </button>
                                    </div>
                                    <InputError message={errors.password} className="mt-1.5" />
                                </div>

                                <label className="flex items-center gap-2 text-sm text-text-secondary cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={data.remember}
                                        onChange={(e) => setData('remember', e.target.checked)}
                                        className="h-4 w-4 rounded border-border text-primary focus:ring-2 focus:ring-primary/20"
                                    />
                                    Ingat saya
                                </label>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="btn-primary w-full"
                                >
                                    {processing ? 'Memproses…' : 'Masuk'}
                                    {!processing && <ArrowRight className="h-4 w-4" />}
                                </button>
                            </form>

                            <p className="mt-8 text-center text-xs text-text-muted">
                                Akses internal. Hubungi admin jika mengalami kendala login.
                            </p>
                        </div>
                    </main>
                </div>
            </div>
        </>
    );
}