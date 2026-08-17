import AuthField from '@/Components/Auth/AuthField';
import AuthShell from '@/Components/Auth/AuthShell';
import { useForm } from '@inertiajs/react';
import { Eye, EyeOff, KeyRound, LockKeyhole, Mail, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

export default function ResetPassword({ token, email }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
    });
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);

    const submit = (event) => {
        event.preventDefault();
        post(route('password.store'), {
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
            title="Buat Kata Sandi Baru"
            portal="Portal Admin"
            eyebrow="Pemulihan akun"
            heading={
                <>
                    Buat kata sandi{' '}
                    <span className="bg-gradient-to-r from-emerald-100 to-emerald-300 bg-clip-text text-transparent">
                        baru.
                    </span>
                </>
            }
            description="Silakan masukkan kata sandi baru Anda. Pastikan kuat dan mudah Anda ingat."
            heroContent={
                <ul className="space-y-5">
                    <li className="flex items-start gap-3.5">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15">
                            <ShieldCheck className="h-5 w-5 text-emerald-200" />
                        </div>
                        <div>
                            <p className="text-sm font-bold text-white">Minimal 8 karakter</p>
                            <p className="mt-0.5 text-xs leading-relaxed text-emerald-50/70">Kombinasikan huruf dan angka agar lebih aman.</p>
                        </div>
                    </li>
                    <li className="flex items-start gap-3.5">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15">
                            <KeyRound className="h-5 w-5 text-emerald-200" />
                        </div>
                        <div>
                            <p className="text-sm font-bold text-white">Tautan sekali pakai</p>
                            <p className="mt-0.5 text-xs leading-relaxed text-emerald-50/70">Halaman ini hanya aktif selama 60 menit.</p>
                        </div>
                    </li>
                </ul>
            }
            heroFooter={
                <p className="text-[11px] text-emerald-100/60">&copy; {new Date().getFullYear()} Yayasan Nuurul Muttaqiin</p>
            }
        >
            <div className="mb-5">
                <h2 className="text-xl font-bold tracking-tight text-slate-950">Buat kata sandi baru</h2>
                <p className="mt-1 text-sm text-slate-500">Isi kata sandi baru untuk akun Anda di bawah ini.</p>
            </div>

            <form onSubmit={submit} autoComplete="on" className="space-y-4">
                <AuthField
                    id="email"
                    name="email"
                    label="Alamat email"
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
                    suffix={toggle(showPassword, setShowPassword, showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi', showPassword)}
                />

                <AuthField
                    id="password_confirmation"
                    name="password_confirmation"
                    label="Ulangi kata sandi baru"
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
                    disabled={processing || !data.password || !data.password_confirmation}
                    className="flex min-h-14 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-4 text-sm font-bold text-white transition-transform active:scale-[0.99] disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500"
                >
                    {processing ? (
                        <>
                            <span className="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white" aria-hidden="true" />
                            <span>Menyimpan...</span>
                        </>
                    ) : (
                        <span>Simpan kata sandi</span>
                    )}
                </button>
            </form>
        </AuthShell>
    );
}
