import ApplicationLogo from '@/Components/ApplicationLogo';
import InputError from '@/Components/InputError';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { ArrowRight, Camera, Eye, EyeOff, Fingerprint, LockKeyhole, MapPin, ShieldCheck, UserRound } from 'lucide-react';
import { useState } from 'react';

function LoginField({ id, label, icon: Icon, error, suffix, ...props }) {
    const errorId = error ? `${id}-error` : undefined;

    return (
        <div>
            <label htmlFor={id} className="mb-2 block text-sm font-bold text-slate-800">
                {label}
            </label>
            <div className="relative">
                <Icon className={`pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 ${error ? 'text-rose-500' : 'text-slate-400'}`} />
                <input
                    id={id}
                    aria-invalid={Boolean(error)}
                    aria-describedby={errorId}
                    className={`min-h-14 w-full rounded-xl border bg-slate-50 py-3.5 pl-12 text-[15px] font-medium text-slate-950 outline-none transition-colors placeholder:text-slate-400 focus:bg-white focus:ring-4 ${
                        suffix ? 'pr-14' : 'pr-4'
                    } ${
                        error
                            ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-100'
                            : 'border-slate-200 focus:border-primary focus:ring-primary/10'
                    }`}
                    {...props}
                />
                {suffix}
            </div>
            {error && <InputError id={errorId} message={error} className="mt-2" />}
        </div>
    );
}

export default function MobileLogin({ status }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        login: '',
        password: '',
        remember: true,
    });
    const [showPassword, setShowPassword] = useState(false);

    const submit = (event) => {
        event.preventDefault();
        post(route('presensi.login.store'), {
            preserveScroll: true,
            onFinish: () => reset('password'),
        });
    };

    return (
        <main className="min-h-[100dvh] bg-[#f4f7f5] font-sans text-slate-950">
            <Head title="Login Pegawai" />

            <section className="bg-primary px-5 pb-20 pt-[max(2rem,env(safe-area-inset-top))] text-white">
                <div className="mx-auto max-w-sm">
                    <div className="flex items-center gap-3">
                        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15">
                            <ApplicationLogo className="h-7 w-7 text-white" />
                        </div>
                        <div>
                            <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-100">Yayasan Nuurul Muttaqiin</p>
                            <p className="mt-0.5 text-base font-bold">Portal Pegawai</p>
                        </div>
                    </div>

                    <div className="mt-8">
                        <p className="text-sm font-semibold text-emerald-100">Selamat datang kembali</p>
                        <h1 className="mt-1 max-w-xs text-3xl font-bold leading-tight tracking-tight">Presensi lebih cepat, data tetap aman.</h1>
                        <p className="mt-3 max-w-sm text-sm leading-relaxed text-emerald-50/80">Akses presensi, jadwal kerja, riwayat kehadiran, dan pengajuan izin dari satu tempat.</p>
                    </div>
                </div>
            </section>

            <section className="relative z-10 mx-auto -mt-12 w-full max-w-sm px-4 pb-[max(2rem,env(safe-area-inset-bottom))]">
                {status && (
                    <div role="status" className="mb-3 flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 shadow-sm">
                        <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0" />
                        <span>{status}</span>
                    </div>
                )}

                <form onSubmit={submit} autoComplete="on" className="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_18px_44px_-28px_rgba(15,23,42,0.45)] sm:p-6">
                    <div className="mb-5">
                        <h2 className="text-xl font-bold tracking-tight text-slate-950">Masuk ke akun</h2>
                        <p className="mt-1 text-sm text-slate-500">Gunakan email atau nomor induk pegawai.</p>
                    </div>

                    <div className="space-y-4">
                        <LoginField
                            id="login"
                            name="login"
                            label="Email atau No. Induk"
                            icon={UserRound}
                            type="text"
                            inputMode="email"
                            autoComplete="username"
                            autoCapitalize="none"
                            spellCheck="false"
                            autoFocus
                            placeholder="nama@yayasan.sch.id"
                            value={data.login}
                            error={errors.login}
                            onChange={(event) => setData('login', event.target.value)}
                        />

                        <LoginField
                            id="password"
                            name="password"
                            label="Kata sandi"
                            icon={LockKeyhole}
                            type={showPassword ? 'text' : 'password'}
                            autoComplete="current-password"
                            placeholder="Masukkan kata sandi"
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
                            <Link href={route('password.request')} className="flex min-h-11 items-center text-sm font-bold text-primary underline-offset-4 hover:underline focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30">
                                Lupa sandi?
                            </Link>
                        </div>

                        <button
                            type="submit"
                            disabled={processing || !data.login || !data.password}
                            className="flex min-h-14 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-4 text-sm font-bold text-white transition-transform active:scale-[0.99] disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500"
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
                    </div>
                </form>

                <div className="mt-4 rounded-xl border border-slate-200 bg-white/70 px-4 py-3.5">
                    <div className="flex items-start gap-3">
                        <ShieldCheck className="mt-0.5 h-5 w-5 shrink-0 text-primary" />
                        <div>
                            <p className="text-xs font-bold text-slate-800">Privasi perangkat terjaga</p>
                            <p className="mt-1 text-xs leading-relaxed text-slate-500">Kamera dan lokasi hanya diminta saat Anda membuka proses presensi.</p>
                        </div>
                    </div>
                    <div className="mt-3 flex items-center gap-4 border-t border-slate-100 pt-3 text-[11px] font-semibold text-slate-500">
                        <span className="inline-flex items-center gap-1.5"><MapPin className="h-3.5 w-3.5 text-primary" /> Geofence</span>
                        <span className="inline-flex items-center gap-1.5"><Camera className="h-3.5 w-3.5 text-primary" /> Foto real-time</span>
                        <span className="inline-flex items-center gap-1.5"><ShieldCheck className="h-3.5 w-3.5 text-primary" /> Aman</span>
                    </div>
                </div>

                <PasskeyLoginSection />
            <p className="mt-5 text-center text-[11px] leading-relaxed text-slate-500">
                &copy; {new Date().getFullYear()} Yayasan Nuurul Muttaqiin
            </p>
        </section>
    </main>
);
}

function PasskeyLoginSection() {
    const { verify, isLoading, error, isSupported } = usePasskeyVerify({
        onSuccess(response) {
            router.visit(response.redirect ?? '/mobile');
        },
    });

    if (!isSupported) return null;

    return (
        <div className="mt-4">
            <div className="relative mb-4 flex items-center gap-3">
                <span className="h-px flex-1 bg-slate-200" />
                <span className="text-xs font-semibold text-slate-400">atau</span>
                <span className="h-px flex-1 bg-slate-200" />
            </div>
            <button
                type="button"
                onClick={verify}
                disabled={isLoading}
                className="flex min-h-14 w-full items-center justify-center gap-3 rounded-xl border-2 border-slate-200 bg-white px-4 py-4 text-sm font-bold text-slate-800 transition-all active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-50"
            >
                {isLoading ? (
                    <>
                        <span className="h-5 w-5 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600" aria-hidden="true" />
                        <span>Memeriksa perangkat...</span>
                    </>
                ) : (
                    <>
                        <Fingerprint className="h-5 w-5 text-primary" />
                        <span>Masuk dengan sidik jari / wajah</span>
                    </>
                )}
            </button>
            {error && (
                <p role="alert" className="mt-2 text-center text-xs font-medium text-rose-600">
                    {error}
                </p>
            )}
        </div>
    );
}
