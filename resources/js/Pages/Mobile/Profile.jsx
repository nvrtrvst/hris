import MobileLayout from '@/Layouts/MobileLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { usePasskeyRegister } from '@laravel/passkeys/react';
import { useState, useEffect } from 'react';
import { Card } from '@/Components/MobileUI';
import {
    Pencil, ChevronDown, User, Mail, Phone, Building2, BadgeCheck,
    CalendarDays, GraduationCap, KeyRound, ShieldAlert, LogOut, Check,
    Smartphone, Fingerprint, MapPin, IdCard, Trash2,
} from 'lucide-react';

function Field({ icon: Icon, label, value }) {
    if (value === null || value === undefined || value === '') return null;
    return (
        <div className="flex items-start gap-3 px-5 py-3">
            <div className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-500">
                <Icon className="h-4 w-4" />
            </div>
            <div className="min-w-0 flex-1">
                <p className="text-[11px] uppercase tracking-wider text-slate-400">{label}</p>
                <p className="truncate text-sm font-semibold text-slate-800">{value}</p>
            </div>
        </div>
    );
}

function Section({ title, icon: Icon, open, onToggle, children, accent = 'text-primary' }) {
    return (
        <Card className="overflow-hidden p-0">
            <button
                type="button"
                onClick={onToggle}
                className="flex w-full items-center justify-between px-5 py-4 text-left transition-colors active:bg-slate-50"
            >
                <span className="flex items-center gap-2.5">
                    <Icon className={`h-5 w-5 ${accent}`} />
                    <span className="text-sm font-bold text-slate-800">{title}</span>
                </span>
                <ChevronDown className={`h-5 w-5 text-slate-400 transition-transform duration-300 ${open ? 'rotate-180' : ''}`} />
            </button>
            <div
                className="grid transition-all duration-300 ease-in-out"
                style={{ gridTemplateRows: open ? '1fr' : '0fr' }}
            >
                <div className="overflow-hidden">
                    <div className="border-t border-slate-50 px-5 pb-5 pt-2">{children}</div>
                </div>
            </div>
        </Card>
    );
}

export default function MobileProfile({ mustVerifyEmail, status }) {
    const user = usePage().props.auth.user;
    const pegawai = user?.pegawai;

    const primaryUnit = pegawai?.units?.find((u) => u.pivot?.is_primary) ?? pegawai?.units?.[0];
    const primaryJabatan = pegawai?.jabatans?.find((j) => j.pivot?.is_primary) ?? pegawai?.jabatans?.[0];
    const displayName = pegawai?.nama_lengkap || user?.name;
    const initial = (displayName || 'P').charAt(0).toUpperCase();
    const photo = pegawai?.foto_url;

    const [editOpen, setEditOpen] = useState(false);
    const [passOpen, setPassOpen] = useState(false);

    const editForm = useForm({
        name: user?.name || '',
        email: user?.email || '',
        no_hp: pegawai?.no_hp || '',
    });
    const passForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submitEdit = (e) => {
        e.preventDefault();
        editForm.patch(route('presensi.profile.update'), {
            preserveScroll: true,
            onSuccess: () => setEditOpen(false),
        });
    };

    const submitPass = (e) => {
        e.preventDefault();
        passForm.put(route('presensi.password.update'), {
            preserveScroll: true,
            onSuccess: () => {
                passForm.reset();
                setPassOpen(false);
            },
        });
    };

    return (
        <MobileLayout user={user}>
            <Head title="Profil" />

            <div className="space-y-4">
                {/* COVER */}
                <div className="relative overflow-hidden rounded-2xl bg-primary p-6 text-white shadow-[0_10px_28px_-18px_rgba(15,61,62,0.75)]">
                    <div className="relative flex flex-col items-center text-center">
                        <div className="relative h-24 w-24 overflow-hidden rounded-full border-4 border-white/30 bg-white/10 shadow-xl">
                            {photo ? (
                                <img src={photo} alt={displayName} className="h-full w-full object-cover" />
                            ) : (
                                <div className="flex h-full w-full items-center justify-center text-3xl font-bold text-white">
                                    {initial}
                                </div>
                            )}
                        </div>
                        <h2 className="mt-3 text-xl font-bold text-white">{displayName}</h2>
                        <p className="text-sm text-emerald-100">{primaryJabatan?.nama || 'Pegawai'}</p>
                        <div className="mt-1 flex items-center gap-1.5 text-xs text-emerald-200">
                            {primaryUnit ? (
                                <>
                                    <MapPin className="h-3.5 w-3.5" />
                                    <span>{primaryUnit.nama || primaryUnit.nama_unit}</span>
                                </>
                            ) : (
                                <>
                                    <Building2 className="h-3.5 w-3.5" />
                                    <span>Belum ada unit ditugaskan</span>
                                </>
                            )}
                        </div>
                    </div>
                </div>

                {/* QUICK STATS */}
                <div className="grid grid-cols-2 gap-3">
                    <Card className="flex flex-col items-center py-4">
                        <p className="text-[11px] uppercase tracking-wider text-slate-400">Sisa Cuti</p>
                        <p className="mt-0.5 text-2xl font-extrabold text-primary">{pegawai?.sisa_cuti ?? 0}</p>
                        <p className="text-[11px] text-slate-400">hari</p>
                    </Card>
                    <Card className="flex flex-col items-center py-4">
                        <p className="text-[11px] uppercase tracking-wider text-slate-400">Status</p>
                        <p className="mt-1 text-sm font-bold text-emerald-600">
                            {pegawai?.status_aktif ? 'Aktif' : 'Nonaktif'}
                        </p>
                        <p className="text-[11px] text-slate-400">{pegawai?.status_kepegawaian || '-'}</p>
                    </Card>
                </div>

                {status && (
                    <div className="flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">
                        <Check className="h-4 w-4" />
                        {status}
                    </div>
                )}

                {/* INFO CARD */}
                <Card className="divide-y divide-slate-50 p-0">
                    <Field icon={IdCard} label="NIP" value={pegawai?.nip} />
                    <Field icon={Building2} label="Unit Sekolah" value={primaryUnit?.nama} />
                    <Field icon={User} label="Jabatan" value={primaryJabatan?.nama} />
                    <Field icon={Phone} label="No. HP" value={pegawai?.no_hp} />
                    <Field icon={Mail} label="Email" value={user?.email} />
                    <Field icon={GraduationCap} label="Pendidikan" value={pegawai?.pendidikan_terakhir} />
                    <Field
                        icon={CalendarDays}
                        label="Bergabung Sejak"
                        value={pegawai?.tanggal_mulai_kerja
                            ? new Date(pegawai.tanggal_mulai_kerja).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
                            : null}
                    />
                </Card>

                {/* EDIT PROFILE */}
                <Section title="Edit Profil" icon={Pencil} open={editOpen} onToggle={() => setEditOpen((v) => !v)}>
                    <form onSubmit={submitEdit} className="space-y-3 pt-2">
                        <div>
                            <label className="mb-1 block text-xs font-bold text-gray-600">Nama Lengkap</label>
                            <input
                                value={editForm.data.name}
                                onChange={(e) => editForm.setData('name', e.target.value)}
                                className="block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            />
                            {editForm.errors.name && <p className="mt-1 text-xs text-red-500">{editForm.errors.name}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-bold text-gray-600">Email</label>
                            <input
                                type="email"
                                value={editForm.data.email}
                                onChange={(e) => editForm.setData('email', e.target.value)}
                                className="block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            />
                            {editForm.errors.email && <p className="mt-1 text-xs text-red-500">{editForm.errors.email}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-bold text-gray-600">No. HP</label>
                            <input
                                value={editForm.data.no_hp}
                                onChange={(e) => editForm.setData('no_hp', e.target.value)}
                                className="block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            />
                            {editForm.errors.no_hp && <p className="mt-1 text-xs text-red-500">{editForm.errors.no_hp}</p>}
                        </div>
                        <button
                            type="submit"
                            disabled={editForm.processing}
                            className="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary py-3.5 text-sm font-bold text-white transition-transform active:scale-[0.99] disabled:opacity-60"
                        >
                            {editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                        </button>
                    </form>
                </Section>

                {/* PASSWORD */}
                <Section title="Ubah Kata Sandi" icon={KeyRound} open={passOpen} onToggle={() => setPassOpen((v) => !v)} accent="text-amber-500">
                    <form onSubmit={submitPass} className="space-y-3 pt-2">
                        <div>
                            <label className="mb-1 block text-xs font-bold text-gray-600">Kata Sandi Saat Ini</label>
                            <input
                                type="password"
                                value={passForm.data.current_password}
                                onChange={(e) => passForm.setData('current_password', e.target.value)}
                                autoComplete="current-password"
                                className="block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            />
                            {passForm.errors.current_password && <p className="mt-1 text-xs text-red-500">{passForm.errors.current_password}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-bold text-gray-600">Kata Sandi Baru</label>
                            <input
                                type="password"
                                value={passForm.data.password}
                                onChange={(e) => passForm.setData('password', e.target.value)}
                                autoComplete="new-password"
                                className="block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            />
                            {passForm.errors.password && <p className="mt-1 text-xs text-red-500">{passForm.errors.password}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-bold text-gray-600">Konfirmasi Kata Sandi</label>
                            <input
                                type="password"
                                value={passForm.data.password_confirmation}
                                onChange={(e) => passForm.setData('password_confirmation', e.target.value)}
                                autoComplete="new-password"
                                className="block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            />
                            {passForm.errors.password_confirmation && <p className="mt-1 text-xs text-red-500">{passForm.errors.password_confirmation}</p>}
                        </div>
                        <button
                            type="submit"
                            disabled={passForm.processing}
                            className="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary py-3.5 text-sm font-bold text-white transition-transform active:scale-[0.99] disabled:opacity-60"
                        >
                            {passForm.processing ? 'Memperbarui...' : 'Perbarui Kata Sandi'}
                        </button>
                    </form>
                </Section>

                {/* PASSKEY */}
                <PasskeySection />

                {/* LOGOUT */}
                <Link
                    href={route('presensi.logout')}
                    method="post"
                    as="button"
                    className="flex w-full items-center justify-center gap-2 rounded-2xl border border-red-100 bg-red-50 py-3.5 text-sm font-bold text-red-600 transition active:scale-[0.98]"
                >
                    <LogOut className="h-4 w-4" />
                    Keluar
                </Link>

                {/* PASSKEY */}
                <PasskeySection />

                <div className="h-2" />
            </div>
        </MobileLayout>
    );
}

function PasskeySection() {
    const { register, isLoading, error, isSupported } = usePasskeyRegister({
        onSuccess() {
            router.reload({ only: ['auth'] });
        },
    });
    const [passkeyOpen, setPasskeyOpen] = useState(false);

    if (!isSupported) return null;

    return (
        <Section title="Sidik Jari / Wajah (Passkey)" icon={Fingerprint} accent="text-indigo-500" open={passkeyOpen} onToggle={() => setPasskeyOpen((v) => !v)}>
            <div className="space-y-3 pt-2">
                <p className="text-xs leading-relaxed text-slate-500">
                    Gunakan sidik jari, wajah, atau PIN perangkat untuk masuk lebih cepat tanpa kata sandi.
                </p>
                <p className="text-[11px] text-slate-400">
                    Data biometrik <strong className="text-slate-600">tidak</strong> dikirim ke server. Hanya kunci kriptografi yang disimpan.
                </p>
                <button
                    type="button"
                    onClick={() => register('Perangkat ' + new Date().toLocaleDateString('id-ID'))}
                    disabled={isLoading}
                    className="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-indigo-500 py-3.5 text-sm font-bold text-white transition-transform active:scale-[0.99] disabled:opacity-60"
                >
                    {isLoading ? (
                        <>
                            <span className="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                            <span>Memproses...</span>
                        </>
                    ) : (
                        <>
                            <Smartphone className="h-4 w-4" />
                            <span>Daftarkan Perangkat Ini</span>
                        </>
                    )}
                </button>
                {error && (
                    <p role="alert" className="text-xs font-medium text-rose-600">
                        {error}
                    </p>
                )}
            </div>
        </Section>
    );
}
