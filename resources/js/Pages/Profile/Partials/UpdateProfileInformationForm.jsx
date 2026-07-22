import FormField from '@/Components/Form/FormField';
import FormSection from '@/Components/Form/FormSection';
import { Link, useForm, usePage } from '@inertiajs/react';
import { Check, Mail, User } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

function FieldIcon({ children }) {
    return (
        <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
            {children}
        </span>
    );
}

const inputClass =
    'block w-full rounded-lg border border-border bg-white pl-10 pr-3 py-2.5 text-sm text-text-primary shadow-sm placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = '',
}) {
    const user = usePage().props.auth.user;
    const nameRef = useRef();
    const [showSaved, setShowSaved] = useState(false);

    const {
        data,
        setData,
        patch,
        errors,
        processing,
        recentlySuccessful,
    } = useForm({
        name: user.name,
        email: user.email,
    });

    useEffect(() => {
        if (!recentlySuccessful) return;
        setShowSaved(true);
        const t = setTimeout(() => setShowSaved(false), 2500);
        return () => clearTimeout(t);
    }, [recentlySuccessful]);

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <FormSection
            title="Informasi Profil"
            description="Perbarui nama tampilan dan alamat email akun Anda."
            contentClassName="space-y-5"
            className={className}
        >
            <form onSubmit={submit} className="space-y-5">
                <FormField
                    label="Nama"
                    htmlFor="name"
                    required
                    error={errors.name}
                >
                    <div className="relative">
                        <FieldIcon>
                            <User className="h-4 w-4" />
                        </FieldIcon>
                        <input
                            id="name"
                            ref={nameRef}
                            type="text"
                            autoComplete="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className={inputClass}
                            placeholder="Nama lengkap"
                        />
                    </div>
                </FormField>

                <FormField
                    label="Email"
                    htmlFor="email"
                    required
                    error={errors.email}
                >
                    <div className="relative">
                        <FieldIcon>
                            <Mail className="h-4 w-4" />
                        </FieldIcon>
                        <input
                            id="email"
                            type="email"
                            autoComplete="username"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className={inputClass}
                            placeholder="email@contoh.com"
                        />
                    </div>
                </FormField>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div className="rounded-lg border border-warning/40 bg-warning/10 px-4 py-3 text-sm text-warning">
                        <p>
                            Email belum terverifikasi.
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="ml-2 font-semibold underline underline-offset-2 hover:text-primary"
                            >
                                Kirim ulang tautan verifikasi
                            </Link>
                        </p>
                        {status === 'verification-link-sent' && (
                            <div className="mt-2 text-sm font-medium text-success">
                                Tautan verifikasi baru sudah dikirim ke email Anda.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary/30 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {processing ? 'Menyimpan...' : 'Simpan'}
                    </button>
                    {showSaved && (
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-success/10 px-3 py-1 text-xs font-medium text-success animate-fade-in">
                            <Check className="h-3.5 w-3.5" /> Tersimpan
                        </span>
                    )}
                </div>
            </form>
        </FormSection>
    );
}
