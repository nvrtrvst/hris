import AuthField from '@/Components/Auth/AuthField';
import AuthShell from '@/Components/Auth/AuthShell';
import { useForm } from '@inertiajs/react';
import { Eye, EyeOff, LockKeyhole, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });
    const [showPassword, setShowPassword] = useState(false);

    const submit = (event) => {
        event.preventDefault();
        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthShell
            title="Konfirmasi Kata Sandi"
            portal="Portal Admin"
            eyebrow="Area aman"
            heading={
                <>
                    Konfirmasi{' '}
                    <span className="bg-gradient-to-r from-emerald-100 to-emerald-300 bg-clip-text text-transparent">
                        kata sandi.
                    </span>
                </>
            }
            description="Ini adalah area aman dari aplikasi. Harap konfirmasi kata sandi Anda sebelum melanjutkan."
            heroContent={
                <ul className="space-y-5">
                    <li className="flex items-start gap-3.5">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15">
                            <ShieldCheck className="h-5 w-5 text-emerald-200" />
                        </div>
                        <div>
                            <p className="text-sm font-bold text-white">Perlindungan ekstra</p>
                            <p className="mt-0.5 text-xs leading-relaxed text-emerald-50/70">Konfirmasi sandi sebelum mengubah data sensitif.</p>
                        </div>
                    </li>
                    <li className="flex items-start gap-3.5">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15">
                            <LockKeyhole className="h-5 w-5 text-emerald-200" />
                        </div>
                        <div>
                            <p className="text-sm font-bold text-white">Session terisolasi</p>
                            <p className="mt-0.5 text-xs leading-relaxed text-emerald-50/70">Sesi admin dijaga terpisah dari portal pegawai.</p>
                        </div>
                    </li>
                </ul>
            }
            heroFooter={
                <p className="text-[11px] text-emerald-100/60">&copy; {new Date().getFullYear()} Yayasan Nuurul Muttaqiin</p>
            }
        >
            <div className="mb-5">
                <h2 className="text-xl font-bold tracking-tight text-slate-950">Konfirmasi kata sandi</h2>
                <p className="mt-1 text-sm text-slate-500">Masukkan kata sandi Anda untuk melanjutkan.</p>
            </div>

            <form onSubmit={submit} autoComplete="on" className="space-y-4">
                <AuthField
                    id="password"
                    name="password"
                    label="Kata sandi"
                    icon={LockKeyhole}
                    type={showPassword ? 'text' : 'password'}
                    autoComplete="current-password"
                    autoFocus
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

                <button
                    type="submit"
                    disabled={processing || !data.password}
                    className="flex min-h-14 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-4 text-sm font-bold text-white transition-transform active:scale-[0.99] disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500"
                >
                    {processing ? (
                        <>
                            <span className="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white" aria-hidden="true" />
                            <span>Memverifikasi...</span>
                        </>
                    ) : (
                        <span>Konfirmasi</span>
                    )}
                </button>
            </form>
        </AuthShell>
    );
}
