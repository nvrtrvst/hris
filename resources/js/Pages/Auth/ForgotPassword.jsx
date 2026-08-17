import AuthField from '@/Components/Auth/AuthField';
import AuthShell from '@/Components/Auth/AuthShell';
import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Clock3, Inbox, KeyRound, Mail, MailWarning, ShieldCheck } from 'lucide-react';

const STEPS = [
    {
        icon: Mail,
        title: 'Masukkan email terdaftar',
        desc: 'Gunakan email yang sama dengan akun pegawai Anda.',
    },
    {
        icon: Inbox,
        title: 'Buka tautan di inbox',
        desc: 'Cek inbox — atau folder spam — dalam beberapa menit.',
    },
    {
        icon: KeyRound,
        title: 'Buat kata sandi baru',
        desc: 'Tautan berlaku 60 menit dan hanya bisa dipakai sekali.',
    },
];

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('password.email'));
    };

    return (
        <AuthShell
            title="Lupa Kata Sandi"
            portal="Portal Pegawai"
            eyebrow="Pemulihan akun"
            heading={
                <>
                    Lupa kata sandi?{' '}
                    <span className="bg-gradient-to-r from-emerald-100 to-emerald-300 bg-clip-text text-transparent">
                        Tenang, kami bantu.
                    </span>
                </>
            }
            description="Cukup masukkan email terdaftar, dan tautan reset akan sampai ke inbox Anda dalam beberapa saat."
            heroContent={
                <ol className="space-y-5">
                    {STEPS.map((step, index) => (
                        <li key={step.title} className="flex items-start gap-3.5">
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15">
                                <step.icon className="h-5 w-5 text-emerald-200" />
                            </div>
                            <div>
                                <p className="text-sm font-bold text-white">
                                    <span className="mr-1.5 font-mono text-xs font-bold text-accent-300">0{index + 1}</span>
                                    {step.title}
                                </p>
                                <p className="mt-0.5 text-xs leading-relaxed text-emerald-50/70">{step.desc}</p>
                            </div>
                        </li>
                    ))}
                </ol>
            }
            heroFooter={
                <>
                    <div className="flex items-center gap-5 border-t border-white/10 pt-5 text-[11px] font-semibold text-emerald-100/70">
                        <span className="inline-flex items-center gap-1.5"><ShieldCheck className="h-3.5 w-3.5 text-emerald-200" /> Aman</span>
                        <span className="inline-flex items-center gap-1.5"><Clock3 className="h-3.5 w-3.5 text-emerald-200" /> 60 menit</span>
                        <span className="inline-flex items-center gap-1.5"><KeyRound className="h-3.5 w-3.5 text-emerald-200" /> Sekali pakai</span>
                    </div>
                    <p className="mt-5 text-[11px] text-emerald-100/60">&copy; {new Date().getFullYear()} Yayasan Nuurul Muttaqiin</p>
                </>
            }
            cardBelow={
                <div className="mt-4 rounded-xl border border-slate-200 bg-white/70 px-4 py-3.5 md:hidden">
                    <div className="flex items-center gap-3">
                        <ShieldCheck className="h-5 w-5 shrink-0 text-primary" />
                        <p className="text-xs font-bold text-slate-800">Tautan aman &amp; satu kali pakai</p>
                    </div>
                    <div className="mt-3 flex items-center gap-4 border-t border-slate-100 pt-3 text-[11px] font-semibold text-slate-500">
                        <span className="inline-flex items-center gap-1.5"><Clock3 className="h-3.5 w-3.5 text-primary" /> Kedaluwarsa 60 menit</span>
                        <span className="inline-flex items-center gap-1.5"><KeyRound className="h-3.5 w-3.5 text-primary" /> Terenkripsi</span>
                    </div>
                </div>
            }
        >
            <div className="mb-5">
                <h2 className="text-xl font-bold tracking-tight text-slate-950">Atur ulang kata sandi</h2>
                <p className="mt-1 text-sm text-slate-500">Kami akan mengirim tautan reset ke email Anda.</p>
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
                    label="Alamat email terdaftar"
                    icon={Mail}
                    type="email"
                    autoComplete="email"
                    autoFocus
                    placeholder="nama@yayasan.sch.id"
                    value={data.email}
                    error={errors.email}
                    onChange={(event) => setData('email', event.target.value)}
                />

                <button
                    type="submit"
                    disabled={processing || !data.email}
                    className="flex min-h-14 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-4 text-sm font-bold text-white transition-transform active:scale-[0.99] disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500"
                >
                    {processing ? (
                        <>
                            <span className="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white" aria-hidden="true" />
                            <span>Mengirim tautan...</span>
                        </>
                    ) : (
                        <span>Kirim tautan reset</span>
                    )}
                </button>

                {/* Hint spam — bantu & isi ruang secara informatif */}
                <p className="flex items-start gap-2 rounded-xl bg-slate-50 px-3.5 py-3 text-xs leading-relaxed text-slate-500">
                    <MailWarning className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                    Tidak menerima email? Cek folder spam, atau hubungi admin unit Anda.
                </p>

                <div className="border-t border-slate-100 pt-4 text-center">
                    <Link
                        href={route('login')}
                        className="inline-flex min-h-11 items-center gap-1.5 text-sm font-bold text-primary underline-offset-4 hover:underline focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Kembali ke halaman masuk
                    </Link>
                </div>
            </form>
        </AuthShell>
    );
}
