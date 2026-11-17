import { Button, Card, FormField, Input } from '@/Components/AdminUI';
import { Link, useForm, usePage } from '@inertiajs/react';
import { Check, Mail, User } from 'lucide-react';
import { useEffect, useState } from 'react';

function FieldIcon({ children }) {
    return (
        <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-text-muted">
            {children}
        </span>
    );
}

export default function UpdateProfileInformation({ mustVerifyEmail, status }) {
    const user = usePage().props.auth.user;
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
        patch(route('profile.update'), { preserveScroll: true });
    };

    return (
        <Card className="p-5 sm:p-7">
            <div className="mb-4">
                <h3 className="text-lg font-bold text-text-primary">Akun Login</h3>
                <p className="mt-0.5 text-sm text-text-secondary">
                    Nama tampilan dan email yang dipakai untuk masuk ke aplikasi.
                </p>
            </div>

            <form onSubmit={submit} className="space-y-4">
                <FormField label="Nama" htmlFor="name" required error={errors.name}>
                    <div className="relative">
                        <FieldIcon>
                            <User className="h-4 w-4" />
                        </FieldIcon>
                        <Input
                            id="name"
                            type="text"
                            autoComplete="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="pl-10"
                            placeholder="Nama lengkap"
                        />
                    </div>
                </FormField>

                <FormField label="Email" htmlFor="email" required error={errors.email}>
                    <div className="relative">
                        <FieldIcon>
                            <Mail className="h-4 w-4" />
                        </FieldIcon>
                        <Input
                            id="email"
                            type="email"
                            autoComplete="username"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className="pl-10"
                            placeholder="email@contoh.com"
                        />
                    </div>
                </FormField>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div className="rounded-lg border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning">
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
                            <p className="mt-2 font-medium">
                                Tautan verifikasi baru sudah dikirim ke email Anda.
                            </p>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-3 pt-1">
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Menyimpan...' : 'Simpan'}
                    </Button>
                    {showSaved && (
                        <span className="inline-flex animate-fade-in items-center gap-1 rounded-full bg-success-light px-2.5 py-1 text-xs font-semibold text-success">
                            <Check className="h-3.5 w-3.5" /> Tersimpan
                        </span>
                    )}
                </div>
            </form>
        </Card>
    );
}
