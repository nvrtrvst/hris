import FormField from '@/Components/Form/FormField';
import FormSection from '@/Components/Form/FormSection';
import { useForm } from '@inertiajs/react';
import { Check, Eye, EyeOff, KeyRound, Lock, ShieldCheck } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';

const basePasswordInput =
    'block w-full rounded-lg border border-border bg-white pl-10 pr-10 py-2.5 text-sm text-text-primary shadow-sm placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';

function FieldIcon({ children }) {
    return (
        <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
            {children}
        </span>
    );
}

function ToggleVisibility({ visible, onToggle }) {
    return (
        <button
            type="button"
            onClick={onToggle}
            tabIndex={-1}
            className="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-primary"
            aria-label={visible ? 'Sembunyikan' : 'Tampilkan'}
        >
            {visible ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
        </button>
    );
}

function StrengthMeter({ password }) {
    const { score, label, barColor, percentage } = useMemo(() => {
        let s = 0;
        if (password.length >= 8) s++;
        if (/[A-Z]/.test(password)) s++;
        if (/[0-9]/.test(password)) s++;
        if (/[^A-Za-z0-9]/.test(password)) s++;
        if (password.length >= 12) s++;
        const levels = [
            { label: '', barColor: 'bg-gray-200', percentage: 0 },
            { label: 'Lemah', barColor: 'bg-danger', percentage: 25 },
            { label: 'Cukup', barColor: 'bg-warning', percentage: 50 },
            { label: 'Bagus', barColor: 'bg-accent', percentage: 75 },
            { label: 'Kuat', barColor: 'bg-success', percentage: 95 },
            { label: 'Sangat Kuat', barColor: 'bg-success', percentage: 100 },
        ];
        const lvl = levels[Math.min(s, 5)];
        return { score: s, ...lvl };
    }, [password]);

    if (!password) return null;

    return (
        <div className="mt-2 space-y-1.5">
            <div className="h-1.5 w-full overflow-hidden rounded-full bg-gray-200">
                <div
                    className={`h-full ${barColor} transition-all duration-300`}
                    style={{ width: `${percentage}%` }}
                />
            </div>
            <p className="text-xs text-text-secondary">
            Kekuatan: <span className="font-semibold">{label}</span> ({score}/5)
            </p>
        </div>
    );
}

export default function UpdatePasswordForm({ className = '' }) {
    const passwordInput = useRef();
    const currentPasswordInput = useRef();
    const [show, setShow] = useState({
        current: false,
        next: false,
        confirm: false,
    });
    const [showSaved, setShowSaved] = useState(false);

    const {
        data,
        setData,
        errors,
        put,
        reset,
        processing,
        recentlySuccessful,
    } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const confirmMatch =
        !data.password ||
        !data.password_confirmation ||
        data.password === data.password_confirmation;

    const updatePassword = (e) => {
        e.preventDefault();
        setShowSaved(false);
        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setShowSaved(true);
                setTimeout(() => setShowSaved(false), 2500);
            },
            onError: (errs) => {
                if (errs.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }
                if (errs.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    const toggle = (k) => setShow((s) => ({ ...s, [k]: !s[k] }));

    return (
        <FormSection
            title="Keamanan &amp; Password"
            description="Pastikan akun Anda menggunakan password yang panjang dan acak untuk tetap aman."
            contentClassName="space-y-5"
            className={className}
        >
            <form onSubmit={updatePassword} className="space-y-5">
                <FormField
                    label="Password Saat Ini"
                    htmlFor="current_password"
                    required
                    error={errors.current_password}
                >
                    <div className="relative">
                        <FieldIcon>
                            <KeyRound className="h-4 w-4" />
                        </FieldIcon>
                        <input
                            id="current_password"
                            ref={currentPasswordInput}
                            type={show.current ? 'text' : 'password'}
                            autoComplete="current-password"
                            value={data.current_password}
                            onChange={(e) =>
                                setData('current_password', e.target.value)
                            }
                            className={basePasswordInput}
                        />
                        <ToggleVisibility
                            visible={show.current}
                            onToggle={() => toggle('current')}
                        />
                    </div>
                </FormField>

                <FormField
                    label="Password Baru"
                    htmlFor="password"
                    required
                    error={errors.password}
                    hint="Minimal 8 karakter dengan huruf besar, angka, dan simbol."
                >
                    <div className="relative">
                        <FieldIcon>
                            <Lock className="h-4 w-4" />
                        </FieldIcon>
                        <input
                            id="password"
                            ref={passwordInput}
                            type={show.next ? 'text' : 'password'}
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                            className={basePasswordInput}
                        />
                        <ToggleVisibility
                            visible={show.next}
                            onToggle={() => toggle('next')}
                        />
                    </div>
                    <StrengthMeter password={data.password} />
                </FormField>

                <FormField
                    label="Konfirmasi Password Baru"
                    htmlFor="password_confirmation"
                    required
                    error={errors.password_confirmation}
                >
                    <div className="relative">
                        <FieldIcon>
                            <ShieldCheck className="h-4 w-4" />
                        </FieldIcon>
                        <input
                            id="password_confirmation"
                            type={show.confirm ? 'text' : 'password'}
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={(e) =>
                                setData(
                                    'password_confirmation',
                                    e.target.value
                                )
                            }
                            className={basePasswordInput}
                        />
                        <ToggleVisibility
                            visible={show.confirm}
                            onToggle={() => toggle('confirm')}
                        />
                    </div>
                    {data.password_confirmation && !confirmMatch && (
                        <p className="mt-1 text-xs text-danger">
                            Password tidak cocok.
                        </p>
                    )}
                </FormField>

                <div className="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        disabled={processing || !confirmMatch}
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
