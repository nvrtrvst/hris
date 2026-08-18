import MobileLayout from '@/Layouts/MobileLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Card, Toggle } from '@/Components/MobileUI';
import { format, parseISO } from 'date-fns';
import { id as idLocale } from 'date-fns/locale';
import { initPush, disablePush } from '@/push';
import ComboSelect from '@/Components/ComboSelect';
import {
    Pencil, ChevronDown, User, Mail, Phone, Building2, BadgeCheck,
    CalendarDays, CalendarClock, GraduationCap, KeyRound, ShieldAlert, LogOut, Check,
    MapPin, IdCard, BellRing, Landmark, CreditCard, BookOpen, Users, Home, Baby, Briefcase,
} from 'lucide-react';

const inputClass = 'block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100';
const labelClass = 'mb-1 block text-xs font-bold text-gray-600';
const labelErr = (errors, key) => (errors[key] ? <p className="mt-1 text-xs text-red-500">{errors[key]}</p> : null);

const jkLabel = (v) => (v === 'L' ? 'Laki-laki' : v === 'P' ? 'Perempuan' : v);
const kepegLabel = { tetap: 'Tetap', kontrak: 'Kontrak', honorer: 'Honorer', gtt: 'GTT' };

const kepegOptions = [
    { value: 'tetap', label: 'Tetap' },
    { value: 'kontrak', label: 'Kontrak' },
    { value: 'honorer', label: 'Honorer' },
    { value: 'gtt', label: 'GTT' },
];
const pendidikanOptions = [
    { value: 'SD/Sederajat', label: 'SD/Sederajat' }, { value: 'SMP/Sederajat', label: 'SMP/Sederajat' },
    { value: 'SMA/SMK', label: 'SMA/SMK' }, { value: 'D1', label: 'D1' }, { value: 'D2', label: 'D2' },
    { value: 'D3', label: 'D3' }, { value: 'D4', label: 'D4' }, { value: 'S1', label: 'S1' },
    { value: 'S2', label: 'S2' }, { value: 'S3', label: 'S3' },
];
const bankOptions = [
    { value: 'Bank Mandiri', label: 'Bank Mandiri' }, { value: 'BRI', label: 'BRI' }, { value: 'BNI', label: 'BNI' },
    { value: 'BTN', label: 'BTN' }, { value: 'BSI', label: 'BSI' }, { value: 'Bank BJB', label: 'Bank BJB' },
    { value: 'Bank Jatim', label: 'Bank Jatim' }, { value: 'Bank Muamalat', label: 'Bank Muamalat' }, { value: 'Lainnya', label: 'Lainnya' },
];

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

export default function MobileProfile({ status }) {
    const user = usePage().props.auth.user;
    const pegawai = user?.pegawai;

    const primaryUnit = pegawai?.units?.find((u) => u.pivot?.is_primary) ?? pegawai?.units?.[0];
    const primaryJabatan = pegawai?.jabatans?.find((j) => j.pivot?.is_primary) ?? pegawai?.jabatans?.[0];
    const displayName = pegawai?.nama_lengkap || user?.name;
    const initial = (displayName || 'P').charAt(0).toUpperCase();
    const photo = pegawai?.foto_url;

    const [editOpen, setEditOpen] = useState(false);
    const [passOpen, setPassOpen] = useState(false);
    const [notifOpen, setNotifOpen] = useState(false);
    const [pushEnabled, setPushEnabled] = useState(false);
    const [pushBusy, setPushBusy] = useState(false);
    const [pushMessage, setPushMessage] = useState(null);

    useEffect(() => {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
        navigator.serviceWorker.getRegistration('/sw.js')
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
                    setPushMessage('Notifikasi aktif. Anda akan menerima pengingat presensi & status izin.');
                } else {
                    setPushEnabled(false);
                    setPushMessage('Tidak dapat mengaktifkan notifikasi. Periksa izin browser atau gunakan HTTPS.');
                }
            } else {
                await disablePush();
                setPushEnabled(false);
                setPushMessage('Notifikasi dimatikan.');
            }
        } catch (err) {
            setPushMessage('Gagal mengubah pengaturan notifikasi.');
        } finally {
            setPushBusy(false);
        }
    };

    const editForm = useForm({
        nama_lengkap: pegawai?.nama_lengkap || '',
        email: user?.email || '',
        tempat_lahir: pegawai?.tempat_lahir || '',
        tanggal_lahir: pegawai?.tanggal_lahir || '',
        jenis_kelamin: pegawai?.jenis_kelamin || '',
        agama: pegawai?.agama || '',
        status_pernikahan: pegawai?.status_pernikahan || '',
        jumlah_tanggungan: pegawai?.jumlah_tanggungan ?? 0,
        alamat_ktp: pegawai?.alamat_ktp || '',
        alamat_domisili: pegawai?.alamat_domisili || '',
        no_hp: pegawai?.no_hp || '',
        no_hp_darurat: pegawai?.no_hp_darurat || '',
        status_kepegawaian: pegawai?.status_kepegawaian || '',
        tanggal_mulai_kerja: pegawai?.tanggal_mulai_kerja || '',
        tanggal_akhir_kontrak: pegawai?.tanggal_akhir_kontrak || '',
        pendidikan_terakhir: pegawai?.pendidikan_terakhir || '',
        pendidikan_jurusan: pegawai?.pendidikan_jurusan || '',
        nama_bank: pegawai?.nama_bank || '',
        no_rekening: pegawai?.no_rekening || '',
        npwp: pegawai?.npwp || '',
        no_bpjs_kesehatan: pegawai?.no_bpjs_kesehatan || '',
        no_bpjs_ketenagakerjaan: pegawai?.no_bpjs_ketenagakerjaan || '',
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

                {/* INFO CARD — semua data pribadi */}
                <Card className="divide-y divide-slate-50 p-0">
                    <Field icon={IdCard} label="NIK" value={pegawai?.nik_masked} />
                    <Field icon={IdCard} label="NIP" value={pegawai?.nip} />
                    <Field icon={Building2} label="Unit Sekolah" value={primaryUnit?.nama} />
                    <Field icon={User} label="Jabatan" value={primaryJabatan?.nama} />
                    <Field
                        icon={Baby}
                        label="Tempat, Tanggal Lahir"
                        value={[pegawai?.tempat_lahir, pegawai?.tanggal_lahir
                            ? format(parseISO(pegawai.tanggal_lahir), 'd MMMM yyyy', { locale: idLocale })
                            : null].filter(Boolean).join(', ') || null}
                    />
                    <Field icon={User} label="Jenis Kelamin" value={jkLabel(pegawai?.jenis_kelamin)} />
                    <Field icon={BadgeCheck} label="Agama" value={pegawai?.agama} />
                    <Field icon={BadgeCheck} label="Status Pernikahan" value={pegawai?.status_pernikahan} />
                    <Field icon={Users} label="Jumlah Tanggungan" value={pegawai?.jumlah_tanggungan != null ? `${pegawai.jumlah_tanggungan} orang` : null} />
                    <Field icon={Home} label="Alamat KTP" value={pegawai?.alamat_ktp} />
                    <Field icon={Home} label="Alamat Domisili" value={pegawai?.alamat_domisili} />
                    <Field icon={Phone} label="No. HP" value={pegawai?.no_hp} />
                    <Field icon={Phone} label="No. HP Darurat" value={pegawai?.no_hp_darurat} />
                    <Field icon={Mail} label="Email" value={user?.email} />
                    <Field icon={Briefcase} label="Status Kepegawaian" value={kepegLabel[pegawai?.status_kepegawaian] || pegawai?.status_kepegawaian} />
                    <Field
                        icon={CalendarDays}
                        label="Mulai Kerja"
                        value={pegawai?.tanggal_mulai_kerja
                            ? format(parseISO(pegawai.tanggal_mulai_kerja), 'd MMMM yyyy', { locale: idLocale })
                            : null}
                    />
                    {['honorer', 'kontrak', 'gtt'].includes(pegawai?.status_kepegawaian) && pegawai?.tanggal_akhir_kontrak && (
                        <Field
                            icon={CalendarClock}
                            label="Akhir Kontrak"
                            value={`${format(parseISO(pegawai.tanggal_akhir_kontrak), 'd MMMM yyyy', { locale: idLocale })} · sisa ${Math.max(0, Math.ceil((new Date(pegawai.tanggal_akhir_kontrak) - new Date()) / (1000 * 60 * 60 * 24)))} hari`}
                        />
                    )}
                    <Field icon={GraduationCap} label="Pendidikan" value={[pegawai?.pendidikan_terakhir, pegawai?.pendidikan_jurusan].filter(Boolean).join(' — ') || null} />
                    <Field icon={Landmark} label="Bank" value={pegawai?.nama_bank} />
                    <Field icon={CreditCard} label="No. Rekening" value={pegawai?.no_rekening} />
                    <Field icon={ShieldAlert} label="NPWP" value={pegawai?.npwp} />
                    <Field icon={ShieldAlert} label="BPJS Kesehatan" value={pegawai?.no_bpjs_kesehatan} />
                    <Field icon={ShieldAlert} label="BPJS Ketenagakerjaan" value={pegawai?.no_bpjs_ketenagakerjaan} />
                </Card>

                {/* EDIT PROFILE — data lengkap */}
                <Section title="Edit Data Diri" icon={Pencil} open={editOpen} onToggle={() => setEditOpen((v) => !v)}>
                    <form onSubmit={submitEdit} className="space-y-3 pt-2">
                        <p className="text-xs text-slate-500">NIK dikelola admin. Kosongkan field yang tidak ingin diubah.</p>
                        <div>
                            <label className={labelClass}>Nama Lengkap</label>
                            <input value={editForm.data.nama_lengkap} onChange={(e) => editForm.setData('nama_lengkap', e.target.value)} className={inputClass} />
                            {labelErr(editForm.errors, 'nama_lengkap')}
                        </div>
                        <div>
                            <label className={labelClass}>Email (login)</label>
                            <input type="email" value={editForm.data.email} onChange={(e) => editForm.setData('email', e.target.value)} className={inputClass} />
                            {labelErr(editForm.errors, 'email')}
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className={labelClass}>Tempat Lahir</label>
                                <input value={editForm.data.tempat_lahir} onChange={(e) => editForm.setData('tempat_lahir', e.target.value)} className={inputClass} />
                                {labelErr(editForm.errors, 'tempat_lahir')}
                            </div>
                            <div>
                                <label className={labelClass}>Tanggal Lahir</label>
                                <input type="date" value={editForm.data.tanggal_lahir} onChange={(e) => editForm.setData('tanggal_lahir', e.target.value)} className={inputClass} />
                                {labelErr(editForm.errors, 'tanggal_lahir')}
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className={labelClass}>Jenis Kelamin</label>
                                <ComboSelect value={editForm.data.jenis_kelamin} onChange={(v) => editForm.setData('jenis_kelamin', v)} options={[{ value: 'L', label: 'Laki-laki' }, { value: 'P', label: 'Perempuan' }]} />
                                {labelErr(editForm.errors, 'jenis_kelamin')}
                            </div>
                            <div>
                                <label className={labelClass}>Agama</label>
                                <ComboSelect value={editForm.data.agama} onChange={(v) => editForm.setData('agama', v)} options={['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'].map((x) => ({ value: x, label: x }))} />
                                {labelErr(editForm.errors, 'agama')}
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className={labelClass}>Status Pernikahan</label>
                                <ComboSelect value={editForm.data.status_pernikahan} onChange={(v) => editForm.setData('status_pernikahan', v)} options={['Belum Menikah', 'Menikah', 'Cerai Hidup', 'Cerai Mati'].map((x) => ({ value: x, label: x }))} />
                                {labelErr(editForm.errors, 'status_pernikahan')}
                            </div>
                            <div>
                                <label className={labelClass}>Jumlah Tanggungan</label>
                                <input type="number" min="0" value={editForm.data.jumlah_tanggungan} onChange={(e) => editForm.setData('jumlah_tanggungan', e.target.value)} className={inputClass} />
                                {labelErr(editForm.errors, 'jumlah_tanggungan')}
                            </div>
                        </div>
                        <div>
                            <label className={labelClass}>Alamat KTP</label>
                            <textarea rows="2" value={editForm.data.alamat_ktp} onChange={(e) => editForm.setData('alamat_ktp', e.target.value)} className={inputClass} />
                            {labelErr(editForm.errors, 'alamat_ktp')}
                        </div>
                        <div>
                            <label className={labelClass}>Alamat Domisili</label>
                            <textarea rows="2" value={editForm.data.alamat_domisili} onChange={(e) => editForm.setData('alamat_domisili', e.target.value)} className={inputClass} />
                            {labelErr(editForm.errors, 'alamat_domisili')}
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className={labelClass}>No. HP</label>
                                <input value={editForm.data.no_hp} onChange={(e) => editForm.setData('no_hp', e.target.value)} className={inputClass} />
                                {labelErr(editForm.errors, 'no_hp')}
                            </div>
                            <div>
                                <label className={labelClass}>No. HP Darurat</label>
                                <input value={editForm.data.no_hp_darurat} onChange={(e) => editForm.setData('no_hp_darurat', e.target.value)} className={inputClass} />
                                {labelErr(editForm.errors, 'no_hp_darurat')}
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className={labelClass}>Status Kepegawaian</label>
                                <ComboSelect value={editForm.data.status_kepegawaian} onChange={(v) => editForm.setData('status_kepegawaian', v)} options={kepegOptions} />
                                {labelErr(editForm.errors, 'status_kepegawaian')}
                            </div>
                            <div>
                                <label className={labelClass}>Mulai Kerja</label>
                                <input type="date" value={editForm.data.tanggal_mulai_kerja} onChange={(e) => editForm.setData('tanggal_mulai_kerja', e.target.value)} className={inputClass} />
                                {labelErr(editForm.errors, 'tanggal_mulai_kerja')}
                            </div>
                        </div>
                        {['honorer', 'kontrak', 'gtt'].includes(editForm.data.status_kepegawaian) && (
                            <div>
                                <label className={labelClass}>Akhir Kontrak</label>
                                <input type="date" value={editForm.data.tanggal_akhir_kontrak} onChange={(e) => editForm.setData('tanggal_akhir_kontrak', e.target.value)} className={inputClass} />
                                {labelErr(editForm.errors, 'tanggal_akhir_kontrak')}
                            </div>
                        )}
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className={labelClass}>Pendidikan Terakhir</label>
                                <ComboSelect value={editForm.data.pendidikan_terakhir} onChange={(v) => editForm.setData('pendidikan_terakhir', v)} options={pendidikanOptions} />
                                {labelErr(editForm.errors, 'pendidikan_terakhir')}
                            </div>
                            <div>
                                <label className={labelClass}>Jurusan</label>
                                <input value={editForm.data.pendidikan_jurusan} onChange={(e) => editForm.setData('pendidikan_jurusan', e.target.value)} className={inputClass} placeholder="Misal: Pendidikan Matematika" />
                                {labelErr(editForm.errors, 'pendidikan_jurusan')}
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className={labelClass}>Nama Bank</label>
                                <ComboSelect value={editForm.data.nama_bank} onChange={(v) => editForm.setData('nama_bank', v)} options={bankOptions} />
                                {labelErr(editForm.errors, 'nama_bank')}
                            </div>
                            <div>
                                <label className={labelClass}>No. Rekening</label>
                                <input value={editForm.data.no_rekening} onChange={(e) => editForm.setData('no_rekening', e.target.value)} className={inputClass} />
                                {labelErr(editForm.errors, 'no_rekening')}
                            </div>
                        </div>
                        <div>
                            <label className={labelClass}>NPWP</label>
                            <input value={editForm.data.npwp} onChange={(e) => editForm.setData('npwp', e.target.value)} className={inputClass} />
                            {labelErr(editForm.errors, 'npwp')}
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className={labelClass}>BPJS Kesehatan</label>
                                <input value={editForm.data.no_bpjs_kesehatan} onChange={(e) => editForm.setData('no_bpjs_kesehatan', e.target.value)} className={inputClass} />
                                {labelErr(editForm.errors, 'no_bpjs_kesehatan')}
                            </div>
                            <div>
                                <label className={labelClass}>BPJS Ketenagakerjaan</label>
                                <input value={editForm.data.no_bpjs_ketenagakerjaan} onChange={(e) => editForm.setData('no_bpjs_ketenagakerjaan', e.target.value)} className={inputClass} />
                                {labelErr(editForm.errors, 'no_bpjs_ketenagakerjaan')}
                            </div>
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

                {/* NOTIFIKASI PUSH */}
                <Section title="Notifikasi" icon={BellRing} open={notifOpen} onToggle={() => setNotifOpen((v) => !v)} accent="text-sky-500">
                    <div className="space-y-3 pt-2">
                        <div className="flex items-center justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                            <div>
                                <p className="text-sm font-bold text-slate-800">Pengingat presensi & status izin</p>
                                <p className="mt-0.5 text-xs text-slate-500">Butuh izin notifikasi browser. Untuk hasil terbaik gunakan HTTPS & buka via "Add to Home Screen".</p>
                            </div>
                            <Toggle checked={pushEnabled} onChange={handleTogglePush} disabled={pushBusy} tone="sky" />
                        </div>
                        {pushMessage && (
                            <p className="rounded-xl bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-700">{pushMessage}</p>
                        )}
                    </div>
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

                <div className="h-2" />
            </div>
        </MobileLayout>
    );
}
