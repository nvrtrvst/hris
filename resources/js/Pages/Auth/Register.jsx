import AuthField from '@/Components/Auth/AuthField';
import AuthShell from '@/Components/Auth/AuthShell';
import { Link, useForm } from '@inertiajs/react';
import { Eye, EyeOff, LockKeyhole, Mail, ShieldCheck, UserRound } from 'lucide-react';
import { useState } from 'react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);

    const submit = (event) => {
        event.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    const toggle = (open, setOpen, label, show) => (
        <button
            type="button"
            onClick={() => setOpen(!open)}
            className="absolute right-1.5 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-lg text-slate-500 transition-colors active:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
            aria-label={label}
            aria-pressed={open}
        >
            {show ? <EyeOff className="h-5 w-5" /> : <Eye className="h-5 w-5" />}
        </button>
    );

    return (
        <AuthShell
            title="Daftar Akun"
            portal="Portal Admin"
            eyebrow="Selamat datang"
            heading={
                <>
                    Daftar akun{' '}
                    <span className="bg-gradient-to-r from-emerald-100 to-emerald-300 bg-clip-text text-transparent">
                        baru.
                    </span>
                </>
            }
            description="Isi data di bawah untuk membuat akun akses portal admin."
            heroContent={
                <ul className="space-y-5">
                    <li className="flex items-start gap-3.5">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15">
                            <ShieldCheck className="h-5 w-5 text-emerald-200" />
                        </div>
                        <div>
                            <p className="text-sm font-bold text-white">Data Anda aman</p>
                            <p className="mt-0.5 text-xs leading-relaxed text-emerald-50/70">Field sensitif disimpan terenkripsi di server.</p>
                        </div>
                    </li>
                </ul>
            }
            heroFooter={
                <p className="text-[11px] text-emerald-100/60">&copy; {new Date().getFullYear()} Yayasan Nuurul Muttaqiin</p>
            }
        >
            <div className="mb-5">
                <h2 className="text-xl font-bold tracking-tight text-slate-950">Daftar akun baru</h2>
                <p className="mt-1 text-sm text-slate-500">Lengkapi data untuk membuat akun.</p>
            </div>

            <form onSubmit={submit} autoComplete="on" className="space-y-4">
                <AuthField
                    id="name"
                    name="name"
                    label="Nama"
                    icon={UserRound}
                    type="text"
                    autoComplete="name"
                    autoFocus
                    placeholder="Nama lengkap Anda"
                    value={data.name}
                    error={errors.name}
                    onChange={(event) => setData('name', event.target.value)}
                />

                <AuthField
                    id="email"
                    name="email"
                    label="Email"
                    icon={Mail}
                    type="email"
                    autoComplete="username"
                    placeholder="nama@yayasan.sch.id"
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
                    autoComplete="new-password"
                    placeholder="Minimal 8 karakter"
                    value={data.password}
                    error={errors.password}
                    onChange={(event) => setData('password', event.target.value)}
                    suffix={toggle(showPassword, setShowPassword, showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi', showPassword)}
                />

                <AuthField
                    id="password_confirmation"
                    name="password_confirmation"
                    label="Konfirmasi kata sandi"
                    icon={ShieldCheck}
                    type={showConfirm ? 'text' : 'password'}
                    autoComplete="new-password"
                    placeholder="Ulangi kata sandi yang sama"
                    value={data.password_confirmation}
                    error={errors.password_confirmation}
                    onChange={(event) => setData('password_confirmation', event.target.value)}
                    suffix={toggle(showConfirm, setShowConfirm, showConfirm ? 'Sembunyikan konfirmasi' : 'Tampilkan konfirmasi', showConfirm)}
                />

                <button
                    type="submit"
                    disabled={processing || !data.name || !data.email || !data.password || !data.password_confirmation}
                    className="flex min-h-14 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-4 text-sm font-bold text-white transition-transform active:scale-[0.99] disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500"
                >
                    {processing ? (
                        <>
                            <span className="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white" aria-hidden="true" />
                            <span>Mendaftar...</span>
                        </>
                    ) : (
                        <span>Daftar</span>
                    )}
                </button>

                <div className="border-t border-slate-100 pt-4 text-center">
                    <Link
                        href={route('login')}
                        className="inline-flex min-h-11 items-center text-sm font-bold text-primary underline-offset-4 hover:underline focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
                    >
                        Sudah punya akun? Masuk
                    </Link>
                </div>
            </form>
        </AuthShell>
    );
}
