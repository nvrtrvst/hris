import AuthField from '@/Components/Auth/AuthField';
import AuthShell from '@/Components/Auth/AuthShell';
import { Link, useForm } from '@inertiajs/react';
import { ArrowRight, CalendarClock, Eye, EyeOff, Lock, LockKeyhole, Mail, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

const TRUST_ITEMS = [
    { icon: ShieldCheck, text: 'Enkripsi data sensitif', desc: 'NIK, rekening, NPWP, dan BPJS tersimpan terenkripsi.' },
    { icon: CalendarClock, text: 'Penjadwalan anti-bentrok', desc: 'Satu papan mingguan lintas unit, tanpa tabrakan jadwal.' },
    { icon: Lock, text: 'Session terisolasi per portal', desc: 'Sesi admin dan pegawai dipisahkan secara aman.' },
];

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        login: '',
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
            eyebrow="Selamat datang kembali"
            heading={
                <>
                    Kelola SDM{' '}
                    <span className="bg-gradient-to-r from-emerald-100 to-emerald-300 bg-clip-text text-transparent">
                        terpusat &amp; terstruktur.
                    </span>
                </>
            }
            description="Presensi geofencing, penjadwalan, payroll dinamis, dan self-service untuk seluruh unit Yayasan."
            heroContent={
                <ul className="space-y-5">
                    {TRUST_ITEMS.map((item) => (
                        <li key={item.text} className="flex items-start gap-3.5">
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15">
                                <item.icon className="h-5 w-5 text-emerald-200" />
                            </div>
                            <div>
                                <p className="text-sm font-bold text-white">{item.text}</p>
                                <p className="mt-0.5 text-xs leading-relaxed text-emerald-50/70">{item.desc}</p>
                            </div>
                        </li>
                    ))}
                </ul>
            }
            heroFooter={
                <>
                    <div className="flex items-center gap-5 border-t border-white/10 pt-5 text-[11px] font-semibold text-emerald-100/70">
                        <span className="inline-flex items-center gap-1.5"><ShieldCheck className="h-3.5 w-3.5 text-emerald-200" /> Geofence</span>
                        <span className="inline-flex items-center gap-1.5"><CalendarClock className="h-3.5 w-3.5 text-emerald-200" /> Payroll dinamis</span>
                        <span className="inline-flex items-center gap-1.5"><Lock className="h-3.5 w-3.5 text-emerald-200" /> Aman</span>
                    </div>
                    <p className="mt-5 text-[11px] text-emerald-100/60">&copy; {new Date().getFullYear()} Yayasan. Sistem internal.</p>
                </>
            }
            cardBelow={
                <div className="mt-4 rounded-xl border border-slate-200 bg-white/70 px-4 py-3.5 md:hidden">
                    <div className="flex items-center gap-3">
                        <ShieldCheck className="h-5 w-5 shrink-0 text-primary" />
                        <p className="text-xs font-bold text-slate-800">Akses internal admin</p>
                    </div>
                    <div className="mt-3 flex items-center gap-4 border-t border-slate-100 pt-3 text-[11px] font-semibold text-slate-500">
                        <span className="inline-flex items-center gap-1.5"><CalendarClock className="h-3.5 w-3.5 text-primary" /> Anti-bentrok</span>
                        <span className="inline-flex items-center gap-1.5"><ShieldCheck className="h-3.5 w-3.5 text-primary" /> Terenkripsi</span>
                    </div>
                </div>
            }
        >
            <div className="mb-5">
                <h2 className="text-xl font-bold tracking-tight text-slate-950">Masuk ke akun</h2>
                <p className="mt-1 text-sm text-slate-500">Gunakan email atau nomor induk pegawai.</p>
            </div>

            {status && (
                <div role="status" className="mb-4 flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                    <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0" />
                    <span>{status}</span>
                </div>
            )}

            <form onSubmit={submit} autoComplete="on" className="space-y-4">
                <AuthField
                    id="login"
                    name="login"
                    label="Email atau No. Induk"
                    icon={Mail}
                    type="text"
                    inputMode="email"
                    autoComplete="username"
                    autoCapitalize="none"
                    spellCheck="false"
                    autoFocus
                    placeholder="admin@yayasan.sch.id"
                    value={data.login}
                    error={errors.login}
                    onChange={(event) => setData('login', event.target.value)}
                />

                <AuthField
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
                    {canResetPassword && (
                        <Link href={route('password.request')} className="flex min-h-11 items-center text-sm font-bold text-primary underline-offset-4 hover:underline focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30">
                            Lupa sandi?
                        </Link>
                    )}
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
            </form>
        </AuthShell>
    );
}
