import MobileLayout from '@/Layouts/MobileLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Card, Toggle } from '@/Components/MobileUI';
import { format, parseISO } from 'date-fns';
import { id as idLocale } from 'date-fns/locale';
import { initPush, disablePush } from '@/push';
import {
    Pencil, ChevronDown, ChevronRight, Building2, MapPin, KeyRound,
    BellRing, LogOut, Check, IdCard,
} from 'lucide-react';

const inputClass = 'w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-primary ring-1 ring-black/5 placeholder:text-text-secondary/50 focus:outline-none focus:ring-2 focus:ring-primary';
const labelClass = 'mb-1.5 block text-xs font-bold text-text-secondary';

const pushToneClass = {
    success: 'bg-success-light text-success',
    danger: 'bg-danger-light text-danger',
    info: 'bg-info-light text-info',
};

function InfoRow({ label, value }) {
    if (value === null || value === undefined || value === '') return null;
    return (
        <div className="px-5 py-3">
            <dt className="text-[11px] font-bold uppercase tracking-[0.14em] text-text-secondary">{label}</dt>
            <dd className="mt-0.5 break-words text-sm font-semibold text-text-primary">{value}</dd>
        </div>
    );
}

function TextField({ id, label, error, ...props }) {
    return (
        <div>
            <label htmlFor={id} className={labelClass}>{label}</label>
            <input
                id={id}
                className={inputClass}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? `${id}-error` : undefined}
                {...props}
            />
            {error && <p id={`${id}-error`} className="mt-1 text-xs font-semibold text-danger">{error}</p>}
        </div>
    );
}

function Section({ id, title, icon: Icon, open, onToggle, children }) {
    const panelId = `${id}-panel`;
    return (
        <Card className="overflow-hidden !p-0">
            <button
                type="button"
                onClick={onToggle}
                aria-expanded={open}
                aria-controls={panelId}
                className="flex min-h-12 w-full items-center justify-between gap-3 px-5 py-4 text-left transition-colors active:bg-surface"
            >
                <span className="flex items-center gap-2.5">
                    <Icon className="h-5 w-5 text-primary" />
                    <span className="text-sm font-bold text-text-primary">{title}</span>
                </span>
                <ChevronDown className={`h-5 w-5 text-text-secondary transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>
            <div id={panelId} hidden={!open} className="border-t border-border px-5 pb-5 pt-4">
                {children}
            </div>
        </Card>
    );
}

export default function MobileProfile({ status }) {
    const user = usePage().props.auth.user;
    const pegawai = user?.pegawai;

    const primaryUnit = pegawai?.units?.find((u) => u.pivot?.is_primary) ?? pegawai?.units?.[0];
    const jabatanNama = [...new Set((pegawai?.jabatans || []).map((j) => j.nama))].join(', ') || 'Pegawai';
    const displayName = pegawai?.nama_lengkap || user?.name;
    const initial = (displayName || 'P').charAt(0).toUpperCase();
    const photo = pegawai?.foto_url;
    const statusKepegawaian = pegawai?.status_ref?.label || pegawai?.statusRef?.label || pegawai?.status_kepegawaian;
    const isAktif = pegawai?.status_aktif === 'aktif';

    const [editOpen, setEditOpen] = useState(false);
    const [passOpen, setPassOpen] = useState(false);
    const [notifOpen, setNotifOpen] = useState(false);
    const [pushEnabled, setPushEnabled] = useState(false);
    const [pushBusy, setPushBusy] = useState(false);
    const [pushMessage, setPushMessage] = useState(null);

    useEffect(() => {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
        navigator.serviceWorker.getRegistration('/sw.js?v=21')
            .then((reg) => (reg ? reg.pushManager.getSubscription() : null))
            .then((sub) => setPushEnabled(Boolean(sub)))
            .catch(() => {});
    }, []);

    const handleTogglePush = async (enabled) => {
        setPushBusy(true);
        setPushMessage(null);
        try {
            if (enabled) {
                const ok = await initPush();
                if (ok) {
                    setPushEnabled(true);
                    setPushMessage({ text: 'Notifikasi aktif. Anda akan menerima pengingat presensi & status izin.', tone: 'success' });
                } else {
                    setPushEnabled(false);
                    setPushMessage({ text: 'Tidak dapat mengaktifkan notifikasi. Periksa izin browser atau gunakan HTTPS.', tone: 'danger' });
                }
            } else {
                await disablePush();
                setPushEnabled(false);
                setPushMessage({ text: 'Notifikasi dimatikan.', tone: 'info' });
            }
        } catch (err) {
            setPushMessage({ text: 'Gagal mengubah pengaturan notifikasi.', tone: 'danger' });
        } finally {
            setPushBusy(false);
        }
    };

    const editForm = useForm({
        nama_lengkap: pegawai?.nama_lengkap || user?.name || '',
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
        editForm.patch(route('presensi.profile.data.update'), {
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
                <div className="rounded-2xl bg-primary px-5 py-6 text-center text-white shadow-[0_1px_2px_rgba(15,23,42,0.06)]">
                    <div className="mx-auto h-24 w-24 overflow-hidden rounded-full border-4 border-white/20 bg-white/10">
                        {photo ? (
                            <img src={photo} alt={displayName} className="h-full w-full object-cover" />
                        ) : (
                            <div className="flex h-full w-full items-center justify-center text-3xl font-bold text-white">
                                {initial}
                            </div>
                        )}
                    </div>
                    <h2 className="mt-3 break-words text-xl font-bold text-white">{displayName}</h2>
                    <p className="break-words text-sm text-white/80">{jabatanNama}</p>
                    <div className="mt-1 flex min-w-0 items-center justify-center gap-1.5 text-xs text-white/70">
                        {primaryUnit ? (
                            <>
                                <MapPin className="h-3.5 w-3.5" />
                                <span className="break-words">{primaryUnit.nama || primaryUnit.nama_unit}</span>
                            </>
                        ) : (
                            <>
                                <Building2 className="h-3.5 w-3.5" />
                                <span>Belum ada unit ditugaskan</span>
                            </>
                        )}
                    </div>
                </div>

                {pegawai ? (
                    <>
                        <div className="grid grid-cols-2 gap-3">
                            <Card className="flex flex-col items-center px-3 py-4">
                                <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-text-secondary">Sisa Cuti</p>
                                <p className="mt-0.5 text-2xl font-extrabold text-primary">{pegawai.sisa_cuti ?? 0}</p>
                                <p className="text-[11px] text-text-secondary">hari</p>
                            </Card>
                            <Card className="flex flex-col items-center px-3 py-4">
                                <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-text-secondary">Status</p>
                                <p className={`mt-1 text-sm font-bold ${isAktif ? 'text-success' : 'text-danger'}`}>
                                    {isAktif ? 'Aktif' : 'Nonaktif'}
                                </p>
                                <p className="text-[11px] text-text-secondary">{statusKepegawaian || '-'}</p>
                            </Card>
                        </div>

                        <dl className="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
                            <InfoRow label="NIP" value={pegawai.nuptk} />
                            <InfoRow label="Email" value={user?.email} />
                            <InfoRow label="No. HP" value={pegawai.no_hp} />
                            <InfoRow label="Pendidikan" value={pegawai.pendidikan_terakhir} />
                            <InfoRow
                                label="Bergabung Sejak"
                                value={pegawai.tmt_mengajar
                                    ? format(parseISO(pegawai.tmt_mengajar), 'd MMMM yyyy', { locale: idLocale })
                                    : null}
                            />
                            {!pegawai.is_tetap && pegawai.tanggal_akhir_kontrak && (
                                <InfoRow
                                    label="Akhir Kontrak"
                                    value={`${format(parseISO(pegawai.tanggal_akhir_kontrak), 'd MMMM yyyy', { locale: idLocale })}, sisa ${Math.max(0, Math.ceil((new Date(pegawai.tanggal_akhir_kontrak) - new Date()) / (1000 * 60 * 60 * 24)))} hari`}
                                />
                            )}
                        </dl>

                        <Link
                            href={route('presensi.lengkapi-data')}
                            className="flex min-h-12 items-center justify-between gap-3 rounded-2xl border border-border bg-white px-5 py-3 text-sm font-bold text-primary shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition-transform active:scale-[0.99]"
                        >
                            <span className="flex items-center gap-2">
                                <IdCard className="h-4 w-4" />
                                Lengkapi data lengkap
                            </span>
                            <ChevronRight className="h-4 w-4 text-text-secondary" />
                        </Link>
                    </>
                ) : (
                    <Card>
                        <p className="text-sm font-bold text-text-primary">Data pegawai belum tertaut</p>
                        <p className="mt-1 text-xs text-text-secondary">
                            Akun Anda belum terhubung ke data pegawai. Hubungi admin unit untuk menautkannya.
                        </p>
                    </Card>
                )}

                {status && (
                    <div className="flex items-center gap-2 rounded-xl bg-success-light p-3 text-sm font-semibold text-success">
                        <Check className="h-4 w-4" />
                        {status}
                    </div>
                )}

                <Section id="edit-profil" title="Edit Profil" icon={Pencil} open={editOpen} onToggle={() => setEditOpen((v) => !v)}>
                    <form onSubmit={submitEdit} className="space-y-3">
                        <TextField
                            id="nama_lengkap"
                            label="Nama Lengkap"
                            value={editForm.data.nama_lengkap}
                            onChange={(e) => editForm.setData('nama_lengkap', e.target.value)}
                            error={editForm.errors.nama_lengkap}
                            autoComplete="name"
                        />
                        <TextField
                            id="email"
                            type="email"
                            label="Email"
                            value={editForm.data.email}
                            onChange={(e) => editForm.setData('email', e.target.value)}
                            error={editForm.errors.email}
                            autoComplete="email"
                        />
                        <TextField
                            id="no_hp"
                            type="tel"
                            label="No. HP"
                            value={editForm.data.no_hp}
                            onChange={(e) => editForm.setData('no_hp', e.target.value)}
                            error={editForm.errors.no_hp}
                            autoComplete="tel"
                        />
                        <button
                            type="submit"
                            disabled={editForm.processing}
                            className="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3.5 text-sm font-bold text-white transition-transform active:scale-[0.98] disabled:opacity-50"
                        >
                            {editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                        </button>
                    </form>
                </Section>

                <Section id="notifikasi" title="Notifikasi" icon={BellRing} open={notifOpen} onToggle={() => setNotifOpen((v) => !v)}>
                    <div className="space-y-3">
                        <div className="flex items-center justify-between gap-3 rounded-xl border border-border bg-surface px-4 py-3">
                            <div>
                                <p className="text-sm font-bold text-text-primary">Pengingat presensi & status izin</p>
                                <p className="mt-0.5 text-xs text-text-secondary">Butuh izin notifikasi browser. Untuk hasil terbaik gunakan HTTPS dan buka lewat "Add to Home Screen".</p>
                            </div>
                            <Toggle checked={pushEnabled} onChange={handleTogglePush} disabled={pushBusy} tone="sky" />
                        </div>
                        {pushMessage && (
                            <p className={`rounded-xl px-3 py-2 text-xs font-semibold ${pushToneClass[pushMessage.tone]}`}>
                                {pushMessage.text}
                            </p>
                        )}
                    </div>
                </Section>

                <Section id="ubah-password" title="Ubah Kata Sandi" icon={KeyRound} open={passOpen} onToggle={() => setPassOpen((v) => !v)}>
                    <form onSubmit={submitPass} className="space-y-3">
                        <TextField
                            id="current_password"
                            type="password"
                            label="Kata Sandi Saat Ini"
                            value={passForm.data.current_password}
                            onChange={(e) => passForm.setData('current_password', e.target.value)}
                            error={passForm.errors.current_password}
                            autoComplete="current-password"
                        />
                        <TextField
                            id="password"
                            type="password"
                            label="Kata Sandi Baru"
                            value={passForm.data.password}
                            onChange={(e) => passForm.setData('password', e.target.value)}
                            error={passForm.errors.password}
                            autoComplete="new-password"
                        />
                        <TextField
                            id="password_confirmation"
                            type="password"
                            label="Konfirmasi Kata Sandi"
                            value={passForm.data.password_confirmation}
                            onChange={(e) => passForm.setData('password_confirmation', e.target.value)}
                            error={passForm.errors.password_confirmation}
                            autoComplete="new-password"
                        />
                        <button
                            type="submit"
                            disabled={passForm.processing}
                            className="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3.5 text-sm font-bold text-white transition-transform active:scale-[0.98] disabled:opacity-50"
                        >
                            {passForm.processing ? 'Memperbarui...' : 'Perbarui Kata Sandi'}
                        </button>
                    </form>
                </Section>

                <Link
                    href={route('presensi.logout')}
                    method="post"
                    as="button"
                    className="flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl border border-danger/20 bg-danger-light px-6 py-3.5 text-sm font-bold text-danger transition-transform active:scale-[0.98]"
                >
                    <LogOut className="h-4 w-4" />
                    Keluar
                </Link>

                <div className="h-2" />
            </div>
        </MobileLayout>
    );
}
