import ApplicationLogo from '@/Components/ApplicationLogo';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import { Eye, EyeOff, ArrowRight, Check } from 'lucide-react';

function Ripple(e) {
    const btn = e.currentTarget;
    const rect = btn.getBoundingClientRect();
    const s = Math.max(rect.width, rect.height);
    const el = document.createElement('span');
    el.className = 'absolute rounded-full bg-white/25 animate-ripple pointer-events-none';
    el.style.cssText = `width:${s}px;height:${s}px;left:${e.clientX-rect.left-s/2}px;top:${e.clientY-rect.top-s/2}px`;
    btn.appendChild(el);
    setTimeout(() => el.remove(), 600);
}

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        login: '', password: '', remember: true,
    });
    const [show, setShow] = useState(false);
    const [pos, setPos] = useState({ x: 0.5, y: 0.5 });
    const [key, setKey] = useState(0);
    const [focus, setFocus] = useState(null);

    useEffect(() => {
        if (errors.login || errors.password) setKey((k) => k + 1);
    }, [errors]);

    const move = useCallback((e) => {
        setPos({ x: e.clientX / window.innerWidth, y: e.clientY / window.innerHeight });
    }, []);

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    const cx = pos.x * 100;
    const cy = pos.y * 100;

    return (
        <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#0F3D3E] p-4 font-sans lg:p-8" onMouseMove={move}>
            <Head title="Masuk ke Portal" />

            {/* soft ambient gradient background */}
            <div className="pointer-events-none absolute inset-0"
                style={{ background: 'radial-gradient(circle at 30% 20%, #1A5A57 0%, #0F3D3E 45%, #062020 100%)' }}
            />
            {/* cursor-following glow */}
            <div className="pointer-events-none absolute inset-0 transition-[background] duration-150 ease-out"
                style={{ background: `radial-gradient(circle at ${cx}% ${cy}%, rgba(201,162,39,0.10) 0%, transparent 45%)` }}
            />
            {/* geometric accents */}
            <div className="pointer-events-none absolute -left-16 -top-16 h-64 w-64 rounded-full border border-white/5" />
            <div className="pointer-events-none absolute -right-20 -bottom-20 h-80 w-80 rounded-full border border-accent/10" />
            <div className="pointer-events-none absolute right-[12%] top-[14%] h-3 w-3 rounded-full bg-accent/30 blur-[2px]" />
            <div className="pointer-events-none absolute left-[16%] bottom-[18%] h-2 w-2 rounded-full bg-white/15 blur-[2px]" />

            {/* CARD */}
            <div key={key}
                style={key > 0 ? { animation: 'shake 0.5s cubic-bezier(.36,.07,.19,.97) both' } : undefined}
                className="relative w-full max-w-md animate-fade-in-up rounded-3xl bg-white/95 p-8 shadow-2xl backdrop-blur-xl ring-1 ring-white/40 lg:p-10"
            >
                {/* logo */}
                <div className="flex flex-col items-center mb-7">
                    <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary shadow-lg shadow-primary/20">
                        <ApplicationLogo className="h-7 w-7 text-accent" />
                    </div>
                    <span className="mt-3 text-sm font-bold tracking-[0.25em] text-primary">HRIS YAYASAN</span>
                </div>

                {/* heading */}
                <div className="text-center animate-fade-in-up" style={{ animationDelay: '0.1s' }}>
                    <h2 className="text-2xl font-bold text-gray-900">Selamat datang</h2>
                    <p className="mt-1.5 text-sm text-gray-400">Masuk untuk mengakses portal Yayasan.</p>
                </div>

                {status && (
                    <div className="mt-5 flex items-center gap-2.5 rounded-xl border border-success/15 bg-success/8 p-3.5 text-sm font-semibold text-success animate-fade-in">
                        <Check className="h-4 w-4 shrink-0" />
                        {status}
                    </div>
                )}

                <form onSubmit={submit} className="mt-7 space-y-4.5" noValidate>
                    {/* EMAIL */}
                    <div className="animate-fade-in-up" style={{ animationDelay: '0.16s' }}>
                        <label htmlFor="login" className="block text-[13px] font-bold text-gray-700 mb-1.5">
                            Email atau NIP
                        </label>
                        <input id="login" type="text" value={data.login} autoComplete="username" autoFocus
                            placeholder="nama@yayasan.com atau 1980..."
                            onFocus={() => setFocus('login')} onBlur={() => setFocus(null)}
                            onChange={(e) => setData('login', e.target.value)}
                            className={`block w-full rounded-xl border-2 px-4 py-3.5 text-sm text-gray-900 outline-none transition-all placeholder:text-gray-300 ${
                                errors.login
                                    ? 'border-danger/60 bg-danger/5'
                                    : focus === 'login'
                                        ? 'border-primary bg-white shadow-[0_0_0_3px_rgba(15,61,62,0.08)]'
                                        : 'border-gray-200 bg-white hover:border-gray-300'
                            }`}
                        />
                        {errors.login && <p className="mt-1.5 text-xs font-medium text-danger/80">{errors.login}</p>}
                    </div>

                    {/* PASSWORD */}
                    <div className="animate-fade-in-up" style={{ animationDelay: '0.22s' }}>
                        <div className="flex items-center justify-between mb-1.5">
                            <label htmlFor="password" className="block text-[13px] font-bold text-gray-700">Kata sandi</label>
                            {canResetPassword && (
                                <Link href={route('password.request')} className="text-[12px] font-semibold text-gray-400 hover:text-primary transition-colors">
                                    Lupa kata sandi?
                                </Link>
                            )}
                        </div>
                        <div className="relative">
                            <input id="password" type={show ? 'text' : 'password'} value={data.password}
                                autoComplete="current-password"
                                placeholder="Masukkan kata sandi"
                                onFocus={() => setFocus('password')} onBlur={() => setFocus(null)}
                                onChange={(e) => setData('password', e.target.value)}
                                className={`block w-full rounded-xl border-2 px-4 py-3.5 pr-11 text-sm text-gray-900 outline-none transition-all placeholder:text-gray-300 ${
                                    errors.password
                                        ? 'border-danger/60 bg-danger/5'
                                        : focus === 'password'
                                            ? 'border-primary bg-white shadow-[0_0_0_3px_rgba(15,61,62,0.08)]'
                                            : 'border-gray-200 bg-white hover:border-gray-300'
                                }`}
                            />
                            <button type="button" onClick={() => setShow((v) => !v)}
                                className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 hover:text-primary transition-colors">
                                {show ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                            </button>
                        </div>
                        {errors.password && <p className="mt-1.5 text-xs font-medium text-danger/80">{errors.password}</p>}
                    </div>

                    {/* REMEMBER */}
                    <label className="flex items-center justify-between cursor-pointer select-none animate-fade-in-up group py-0.5"
                        style={{ animationDelay: '0.28s' }}>
                        <span className="flex items-center gap-2.5">
                            <input type="checkbox" name="remember" checked={data.remember}
                                onChange={(e) => setData('remember', e.target.checked)}
                                className="h-[16px] w-[16px] rounded border-gray-300 text-primary shadow-sm transition-all focus:ring-primary/20 checked:bg-primary checked:border-primary" />
                            <span className="text-sm text-gray-400 group-hover:text-gray-600 transition-colors">
                                Ingat sesi saya
                            </span>
                        </span>
                    </label>

                    {/* SUBMIT */}
                    <button type="submit" disabled={processing}
                        onClick={!processing ? Ripple : undefined}
                        className={`relative flex w-full items-center justify-center overflow-hidden rounded-xl py-3.5 text-sm font-bold text-white shadow-lg shadow-primary/20 transition-all duration-300 animate-fade-in-up ${
                            processing
                                ? 'cursor-not-allowed bg-gray-300'
                                : 'bg-primary hover:bg-primary-light hover:shadow-xl hover:shadow-primary/30 active:scale-[0.98]'
                        }`}
                        style={{ animationDelay: '0.34s' }}
                    >
                        {!processing && (
                            <span className="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/20 to-transparent transition-transform duration-700 group-hover:translate-x-full" />
                        )}
                        {processing ? (
                            <><svg className="mr-2.5 h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>Memproses...</>
                        ) : (
                            <><span>Masuk</span><ArrowRight className="ml-2 h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" /></>
                        )}
                    </button>
                </form>

                <p className="mt-7 text-center text-[11px] text-gray-300">
                    &copy; {new Date().getFullYear()} Yayasan Pendidikan. Hak cipta dilindungi.
                </p>
            </div>
        </div>
    );
}
