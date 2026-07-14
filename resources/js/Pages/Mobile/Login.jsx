import InputError from '@/Components/InputError';
import ApplicationLogo from '@/Components/ApplicationLogo';
import { Head, useForm, Link } from '@inertiajs/react';
import { useState, useEffect, useMemo } from 'react';
import { Eye, EyeOff, Mail, Users, ArrowRight } from 'lucide-react';

// ── Particles ────────────────────────────────────────────────
function Particles() {
    const dots = useMemo(() =>
        Array.from({ length: 20 }, (_, i) => ({
            id: i,
            x: Math.random() * 100,
            y: Math.random() * 100,
            s: Math.random() * 2 + 1,
            d: Math.random() * 5,
            o: Math.random() * 0.3 + 0.08,
        })),
    []);

    return (
        <div className="pointer-events-none absolute inset-0 overflow-hidden">
            {dots.map((dot) => (
                <div
                    key={dot.id}
                    className="absolute rounded-full bg-white"
                    style={{
                        left: `${dot.x}%`,
                        top: `${dot.y}%`,
                        width: dot.s,
                        height: dot.s,
                        opacity: dot.o,
                        animation: `float ${dot.d + 5}s ease-in-out infinite`,
                        animationDelay: `${dot.d}s`,
                    }}
                />
            ))}
        </div>
    );
}

// ── Ripple ────────────────────────────────────────────────────
function createRipple(e) {
    const btn = e.currentTarget;
    const rect = btn.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = e.clientX - rect.left - size / 2;
    const y = e.clientY - rect.top - size / 2;
    const el = document.createElement('span');
    el.className = 'absolute rounded-full bg-white/30 animate-ripple';
    el.style.width = el.style.height = `${size}px`;
    el.style.left = `${x}px`;
    el.style.top = `${y}px`;
    el.style.pointerEvents = 'none';
    btn.appendChild(el);
    setTimeout(() => el.remove(), 600);
}

// ── Floating Input ───────────────────────────────────────────
function FloatingInput({
    id, label, icon: Icon, value, onChange, error,
    type = 'text', autoComplete, autoFocus,
}) {
    const [focused, setFocused] = useState(false);
    const active = focused || value;

    return (
        <div>
            <div className="relative">
                <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                    <Icon className="h-5 w-5 text-gray-400 transition-colors" style={{ color: active && !error ? '#0F3D3E' : undefined }} />
                </span>
                <input
                    id={id}
                    type={type}
                    value={value}
                    autoComplete={autoComplete}
                    autoFocus={autoFocus}
                    placeholder=" "
                    onFocus={() => setFocused(true)}
                    onBlur={() => setFocused(false)}
                    onChange={(e) => onChange(e.target.value)}
                    className={`peer w-full rounded-xl border bg-white pt-6 pb-2.5 pl-11 pr-4 font-medium text-text-primary shadow-sm outline-none transition-all ${
                        error
                            ? '!border-danger ring-4 ring-danger/10'
                            : focused
                                ? 'border-primary ring-4 ring-primary/10'
                                : 'border-border hover:border-gray-300'
                    }`}
                />
                <label
                    htmlFor={id}
                    className={`pointer-events-none absolute left-11 transition-all ${
                        active
                            ? 'top-1.5 text-xs font-bold text-primary'
                            : 'top-1/2 -translate-y-1/2 text-sm text-gray-400'
                    } ${error ? '!text-danger' : ''}`}
                >
                    {label}
                </label>
            </div>
            <div className="overflow-hidden transition-all" style={{ maxHeight: error ? 40 : 0 }}>
                <InputError message={error} className="mt-2" />
            </div>
        </div>
    );
}

// ── MAIN ──────────────────────────────────────────────────────
export default function MobileLogin({ status }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        login: '',
        password: '',
        remember: true,
    });

    const [showPassword, setShowPassword] = useState(false);
    const [shakeKey, setShakeKey] = useState(0);

    useEffect(() => {
        if (errors.login || errors.password) setShakeKey((k) => k + 1);
    }, [errors]);

    const submit = (e) => {
        e.preventDefault();
        post(route('presensi.login.store'), { onFinish: () => reset('password') });
    };

    return (
        <div className="relative flex min-h-screen flex-col justify-center overflow-hidden bg-primary px-6 font-sans">
            {/* background */}
            <div className="pointer-events-none absolute inset-0">
                <div className="absolute -right-16 -top-16 h-72 w-72 rounded-full bg-accent/20 blur-3xl animate-blob" />
                <div className="absolute -bottom-20 -left-16 h-80 w-80 rounded-full bg-primary-light/70 blur-3xl animate-blob" style={{ animationDelay: '2s' }} />
                <div className="absolute left-1/2 top-1/3 h-48 w-48 -translate-x-1/2 rounded-full bg-white/5 blur-2xl animate-float" />
            </div>
            <Particles />

            <Head title="Login Pegawai" />

            <div className="relative z-10 mx-auto w-full max-w-sm animate-fade-in-up">
                <div className="mb-6 text-center">
                    <div className="relative mx-auto mb-4 inline-flex h-16 w-16 items-center justify-center rounded-3xl bg-white/10 ring-1 ring-white/20 backdrop-blur">
                        <span className="absolute inset-0 rounded-3xl bg-accent/30 animate-pulse-ring" />
                        <ApplicationLogo className="h-9 w-9 text-accent" />
                    </div>
                    <h1 className="text-3xl font-extrabold tracking-tight text-white">
                        HRIS<span className="text-accent"> Mobile</span>
                    </h1>
                    <p className="mt-2 font-medium text-white/70">Portal Absensi & Pegawai</p>
                </div>

                {status && (
                    <div className="mb-5 rounded-xl border border-success/30 bg-success/15 p-3 text-center text-sm font-semibold text-white animate-fade-in">
                        {status}
                    </div>
                )}

                <form
                    onSubmit={submit}
                    autoComplete="off"
                    className="rounded-3xl bg-white/95 p-6 shadow-2xl ring-1 ring-white/20 backdrop-blur"
                    key={shakeKey}
                    style={shakeKey > 0 ? { animation: 'shake 0.5s cubic-bezier(0.36, 0.07, 0.19, 0.97) both' } : undefined}
                >
                    <div className="space-y-5">
                        <FloatingInput
                            id="login"
                            label="Email / No Induk (NIP)"
                            icon={Mail}
                            value={data.login}
                            autoComplete="off"
                            autoFocus
                            error={errors.login}
                            onChange={(v) => setData('login', v)}
                        />

                        <div>
                            <FloatingInput
                                id="password"
                                label="Kata Sandi"
                                icon={Users}
                                type={showPassword ? 'text' : 'password'}
                                value={data.password}
                                autoComplete="new-password"
                                error={errors.password}
                                onChange={(v) => setData('password', v)}
                            />
                        </div>

                        <div className="flex items-center justify-between">
                            <label className="group flex cursor-pointer select-none items-center space-x-2.5">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                    className="h-5 w-5 rounded border-border text-primary shadow-sm transition-all focus:ring-primary/30 checked:border-primary checked:bg-primary"
                                />
                                <span className="text-sm font-medium text-text-secondary transition-colors group-hover:text-text-primary">
                                    Ingat saya
                                </span>
                            </label>
                            <Link
                                href={route('password.request')}
                                className="text-xs font-bold text-primary transition-all hover:text-accent hover:underline underline-offset-2"
                            >
                                Lupa Kata Sandi?
                            </Link>
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            onClick={!processing ? createRipple : undefined}
                            className={`group relative flex w-full items-center justify-center overflow-hidden rounded-xl py-4 text-sm font-bold text-white shadow-lg transition-all duration-300 active:scale-[0.98] ${
                                processing
                                    ? 'cursor-not-allowed bg-primary/70'
                                    : 'bg-primary hover:bg-primary-light hover:shadow-xl hover:shadow-primary/30'
                            }`}
                        >
                            {!processing && (
                                <span className="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/25 to-transparent transition-transform duration-700 group-hover:translate-x-full" />
                            )}
                            {processing ? (
                                <>
                                    <svg className="mr-3 h-5 w-5 animate-spin text-white" viewBox="0 0 24 24" fill="none">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    Memproses...
                                </>
                            ) : (
                                <>
                                    <span>Masuk Sekarang</span>
                                    <ArrowRight className="ml-2 h-5 w-5 transition-transform duration-300 group-hover:translate-x-1.5" />
                                </>
                            )}
                        </button>
                    </div>
                </form>

                <p className="mt-8 text-center text-xs text-white/40 animate-fade-in">
                    &copy; {new Date().getFullYear()} Yayasan Pendidikan.
                </p>
            </div>
        </div>
    );
}
