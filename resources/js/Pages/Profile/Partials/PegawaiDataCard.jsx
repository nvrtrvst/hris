import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { format, parseISO } from 'date-fns';
import { id as idLocale } from 'date-fns/locale';
import ComboSelect from '@/Components/ComboSelect';
import {
    Pencil, X, IdCard, User, Mail, Phone, Building2, BadgeCheck, CalendarDays,
    CalendarClock, GraduationCap, ShieldAlert, Landmark, CreditCard, Users, Home,
    Baby, Briefcase, MapPin, Save, Loader2,
} from 'lucide-react';

const inputClass =
    'block w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10';
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
    { value: 'SD/Sederajat', label: 'SD/Sederajat' },
    { value: 'SMP/Sederajat', label: 'SMP/Sederajat' },
    { value: 'SMA/SMK', label: 'SMA/SMK' },
    { value: 'D1', label: 'D1' },
    { value: 'D2', label: 'D2' },
    { value: 'D3', label: 'D3' },
    { value: 'D4', label: 'D4' },
    { value: 'S1', label: 'S1' },
    { value: 'S2', label: 'S2' },
    { value: 'S3', label: 'S3' },
];
const bankOptions = [
    { value: 'Bank Mandiri', label: 'Bank Mandiri' },
    { value: 'BRI', label: 'BRI' },
    { value: 'BNI', label: 'BNI' },
    { value: 'BTN', label: 'BTN' },
    { value: 'BSI', label: 'BSI' },
    { value: 'Bank BJB', label: 'Bank BJB' },
    { value: 'Bank Jatim', label: 'Bank Jatim' },
    { value: 'Bank Muamalat', label: 'Bank Muamalat' },
    { value: 'Lainnya', label: 'Lainnya' },
];

function Field({ icon: Icon, label, value }) {
    if (value === null || value === undefined || value === '') return null;
    return (
        <div className="flex items-start gap-3 py-3">
            <div className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <Icon className="h-4 w-4" />
            </div>
            <div className="min-w-0 flex-1">
                <p className="text-[11px] uppercase tracking-wider text-slate-400">{label}</p>
                <p className="break-words text-sm font-semibold text-slate-800">{value}</p>
            </div>
        </div>
    );
}

function Section({ title, children }) {
    return (
        <div>
            <h4 className="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">{title}</h4>
            <div className="divide-y divide-slate-100">{children}</div>
        </div>
    );
}

function fmtDate(value) {
    if (!value) return null;
    try {
        return format(parseISO(value), 'd MMMM yyyy', { locale: idLocale });
    } catch {
        return value;
    }
}

export default function PegawaiDataCard() {
    const user = usePage().props.auth.user;
    const pegawai = user?.pegawai;

    const primaryUnit = pegawai?.units?.find((u) => u.pivot?.is_primary) ?? pegawai?.units?.[0];
    const primaryJabatan = pegawai?.jabatans?.find((j) => j.pivot?.is_primary) ?? pegawai?.jabatans?.[0];

    const [editing, setEditing] = useState(false);

    const form = useForm({
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

    const submit = (e) => {
        e.preventDefault();
        form.patch(route('profile.data.update'), {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    if (!pegawai) {
        return (
            <div className="card p-5 sm:p-7">
                <p className="text-sm text-slate-500">
                    Akun ini tidak terhubung dengan data pegawai. Hubungi admin untuk menautkannya.
                </p>
            </div>
        );
    }

    const kontrakAktif = ['honorer', 'kontrak', 'gtt'].includes(pegawai?.status_kepegawaian) && pegawai?.tanggal_akhir_kontrak;
    const sisaKontrak = kontrakAktif
        ? Math.max(0, Math.ceil((new Date(pegawai.tanggal_akhir_kontrak) - new Date()) / (1000 * 60 * 60 * 24)))
        : null;

    return (
        <div className="card p-5 sm:p-7">
            <div className="mb-5 flex items-center justify-between gap-3">
                <div>
                    <h3 className="text-lg font-bold text-text-primary">Data Kepegawaian</h3>
                    <p className="mt-0.5 text-sm text-text-secondary">
                        Data pribadi & kepegawaian Anda — hanya Anda yang dapat melihatnya.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={() => setEditing((v) => !v)}
                    className={`inline-flex shrink-0 items-center gap-1.5 rounded-lg px-3.5 py-2 text-sm font-semibold transition ${
                        editing
                            ? 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                            : 'bg-primary text-white hover:bg-primary-dark'
                    }`}
                >
                    {editing ? <X className="h-4 w-4" /> : <Pencil className="h-4 w-4" />}
                    {editing ? 'Batal' : 'Edit Data'}
                </button>
            </div>

            {editing ? (
                <form onSubmit={submit} className="space-y-5">
                    <p className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs font-semibold text-amber-700">
                        NIK dikelola admin. Kosongkan field yang tidak ingin diubah — isi yang kosong tetap dipertahankan.
                    </p>

                    <Section title="Akun & Identitas">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label className={labelClass}>Nama Lengkap</label>
                                <input value={form.data.nama_lengkap} onChange={(e) => form.setData('nama_lengkap', e.target.value)} className={inputClass} />
                                {labelErr(form.errors, 'nama_lengkap')}
                            </div>
                            <div>
                                <label className={labelClass}>Email (login)</label>
                                <input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} className={inputClass} />
                                {labelErr(form.errors, 'email')}
                            </div>
                            <div>
                                <label className={labelClass}>Tempat Lahir</label>
                                <input value={form.data.tempat_lahir} onChange={(e) => form.setData('tempat_lahir', e.target.value)} className={inputClass} />
                                {labelErr(form.errors, 'tempat_lahir')}
                            </div>
                            <div>
                                <label className={labelClass}>Tanggal Lahir</label>
                                <input type="date" value={form.data.tanggal_lahir} onChange={(e) => form.setData('tanggal_lahir', e.target.value)} className={inputClass} />
                                {labelErr(form.errors, 'tanggal_lahir')}
                            </div>
                            <div>
                                <label className={labelClass}>Jenis Kelamin</label>
                                <ComboSelect
                                    value={form.data.jenis_kelamin}
                                    onChange={(v) => form.setData('jenis_kelamin', v)}
                                    options={[
                                        { value: 'L', label: 'Laki-laki' },
                                        { value: 'P', label: 'Perempuan' },
                                    ]}
                                />
                                {labelErr(form.errors, 'jenis_kelamin')}
                            </div>
                            <div>
                                <label className={labelClass}>Agama</label>
                                <ComboSelect
                                    value={form.data.agama}
                                    onChange={(v) => form.setData('agama', v)}
                                    options={['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'].map((x) => ({ value: x, label: x }))}
                                />
                                {labelErr(form.errors, 'agama')}
                            </div>
                            <div>
                                <label className={labelClass}>Status Pernikahan</label>
                                <ComboSelect
                                    value={form.data.status_pernikahan}
                                    onChange={(v) => form.setData('status_pernikahan', v)}
                                    options={['Belum Menikah', 'Menikah', 'Cerai Hidup', 'Cerai Mati'].map((x) => ({ value: x, label: x }))}
                                />
                                {labelErr(form.errors, 'status_pernikahan')}
                            </div>
                            <div>
                                <label className={labelClass}>Jumlah Tanggungan</label>
                                <input type="number" min="0" value={form.data.jumlah_tanggungan} onChange={(e) => form.setData('jumlah_tanggungan', e.target.value)} className={inputClass} />
                                {labelErr(form.errors, 'jumlah_tanggungan')}
                            </div>
                        </div>
                    </Section>

                    <Section title="Alamat & Kontak">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <label className={labelClass}>Alamat KTP</label>
                                <textarea rows="2" value={form.data.alamat_ktp} onChange={(e) => form.setData('alamat_ktp', e.target.value)} className={inputClass} />
                                {labelErr(form.errors, 'alamat_ktp')}
                            </div>
                            <div className="sm:col-span-2">
                                <label className={labelClass}>Alamat Domisili</label>
                                <textarea rows="2" value={form.data.alamat_domisili} onChange={(e) => form.setData('alamat_domisili', e.target.value)} className={inputClass} />
                                {labelErr(form.errors, 'alamat_domisili')}
                            </div>
                            <div>
                                <label className={labelClass}>No. HP</label>
                                <input value={form.data.no_hp} onChange={(e) => form.setData('no_hp', e.target.value)} className={inputClass} />
                                {labelErr(form.errors, 'no_hp')}
                            </div>
                            <div>
                                <label className={labelClass}>No. HP Darurat</label>
                                <input value={form.data.no_hp_darurat} onChange={(e) => form.setData('no_hp_darurat', e.target.value)} className={inputClass} />
                                {labelErr(form.errors, 'no_hp_darurat')}
                            </div>
                        </div>
                    </Section>

                    <Section title="Kepegawaian">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label className={labelClass}>Status Kepegawaian</label>
                                <ComboSelect value={form.data.status_kepegawaian} onChange={(v) => form.setData('status_kepegawaian', v)} options={kepegOptions} />
                                {labelErr(form.errors, 'status_kepegawaian')}
                            </div>
                            <div>
                                <label className={labelClass}>Mulai Kerja</label>
                                <input type="date" value={form.data.tanggal_mulai_kerja} onChange={(e) => form.setData('tanggal_mulai_kerja', e.target.value)} className={inputClass} />
                                {labelErr(form.errors, 'tanggal_mulai_kerja')}
                            </div>
                            {['honorer', 'kontrak', 'gtt'].includes(form.data.status_kepegawaian) && (
                                <div>
                                    <label className={labelClass}>Akhir Kontrak</label>
                                    <input type="date" value={form.data.tanggal_akhir_kontrak} onChange={(e) => form.setData('tanggal_akhir_kontrak', e.target.value)} className={inputClass} />
                                    {labelErr(form.errors, 'tanggal_akhir_kontrak')}
                                </div>
                            )}
                            <div>
                                <label className={labelClass}>Pendidikan Terakhir</label>
                                <ComboSelect value={form.data.pendidikan_terakhir} onChange={(v) => form.setData('pendidikan_terakhir', v)} options={pendidikanOptions} />
                                {labelErr(form.errors, 'pendidikan_terakhir')}
                            </div>
                            <div>
                                <label className={labelClass}>Jurusan</label>
                                <input value={form.data.pendidikan_jurusan} onChange={(e) => form.setData('pendidikan_jurusan', e.target.value)} className={inputClass} placeholder="Misal: Pendidikan Matematika" />
                                {labelErr(form.errors, 'pendidikan_jurusan')}
                            </div>
                        </div>
                    </Section>

                    <Section title="Keuangan (Bank, NPWP, BPJS)">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label className={labelClass}>Nama Bank</label>
                                <ComboSelect value={form.data.nama_bank} onChange={(v) => form.setData('nama_bank', v)} options={bankOptions} />
                                {labelErr(form.errors, 'nama_bank')}
                            </div>
                            <div>
                                <label className={labelClass}>No. Rekening</label>
                                <input value={form.data.no_rekening} onChange={(e) => form.setData('no_rekening', e.target.value)} className={inputClass} />
                                {labelErr(form.errors, 'no_rekening')}
                            </div>
                            <div>
                                <label className={labelClass}>NPWP</label>
                                <input value={form.data.npwp} onChange={(e) => form.setData('npwp', e.target.value)} className={inputClass} />
                                {labelErr(form.errors, 'npwp')}
                            </div>
                            <div>
                                <label className={labelClass}>BPJS Kesehatan</label>
                                <input value={form.data.no_bpjs_kesehatan} onChange={(e) => form.setData('no_bpjs_kesehatan', e.target.value)} className={inputClass} />
                                {labelErr(form.errors, 'no_bpjs_kesehatan')}
                            </div>
                            <div>
                                <label className={labelClass}>BPJS Ketenagakerjaan</label>
                                <input value={form.data.no_bpjs_ketenagakerjaan} onChange={(e) => form.setData('no_bpjs_ketenagakerjaan', e.target.value)} className={inputClass} />
                                {labelErr(form.errors, 'no_bpjs_ketenagakerjaan')}
                            </div>
                        </div>
                    </Section>

                    <div className="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                        <button
                            type="button"
                            onClick={() => setEditing(false)}
                            className="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-bold text-white transition hover:bg-primary-dark disabled:opacity-60"
                        >
                            {form.processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                            {form.processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                        </button>
                    </div>
                </form>
            ) : (
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Section title="Identitas">
                        <Field icon={IdCard} label="NIK" value={pegawai?.nik_masked} />
                        <Field icon={IdCard} label="NIP" value={pegawai?.nip} />
                        <Field icon={Building2} label="Unit" value={primaryUnit?.nama || primaryUnit?.nama_unit} />
                        <Field icon={Briefcase} label="Jabatan" value={primaryJabatan?.nama} />
                        <Field
                            icon={Baby}
                            label="Tempat, Tanggal Lahir"
                            value={[pegawai?.tempat_lahir, fmtDate(pegawai?.tanggal_lahir)].filter(Boolean).join(', ') || null}
                        />
                        <Field icon={User} label="Jenis Kelamin" value={jkLabel(pegawai?.jenis_kelamin)} />
                        <Field icon={BadgeCheck} label="Agama" value={pegawai?.agama} />
                        <Field icon={BadgeCheck} label="Status Pernikahan" value={pegawai?.status_pernikahan} />
                        <Field icon={Users} label="Jumlah Tanggungan" value={pegawai?.jumlah_tanggungan != null ? `${pegawai.jumlah_tanggungan} orang` : null} />
                    </Section>

                    <Section title="Alamat & Kontak">
                        <Field icon={Home} label="Alamat KTP" value={pegawai?.alamat_ktp} />
                        <Field icon={Home} label="Alamat Domisili" value={pegawai?.alamat_domisili} />
                        <Field icon={Phone} label="No. HP" value={pegawai?.no_hp} />
                        <Field icon={Phone} label="No. HP Darurat" value={pegawai?.no_hp_darurat} />
                        <Field icon={Mail} label="Email" value={user?.email} />
                    </Section>

                    <Section title="Kepegawaian">
                        <Field icon={MapPin} label="Status Kepegawaian" value={kepegLabel[pegawai?.status_kepegawaian] || pegawai?.status_kepegawaian} />
                        <Field icon={CalendarDays} label="Mulai Kerja" value={fmtDate(pegawai?.tanggal_mulai_kerja)} />
                        {kontrakAktif && (
                            <Field
                                icon={CalendarClock}
                                label="Akhir Kontrak"
                                value={`${fmtDate(pegawai.tanggal_akhir_kontrak)} · sisa ${sisaKontrak} hari`}
                            />
                        )}
                        <Field
                            icon={GraduationCap}
                            label="Pendidikan"
                            value={[pegawai?.pendidikan_terakhir, pegawai?.pendidikan_jurusan].filter(Boolean).join(' — ') || null}
                        />
                    </Section>

                    <Section title="Keuangan">
                        <Field icon={Landmark} label="Bank" value={pegawai?.nama_bank} />
                        <Field icon={CreditCard} label="No. Rekening" value={pegawai?.no_rekening} />
                        <Field icon={ShieldAlert} label="NPWP" value={pegawai?.npwp} />
                        <Field icon={ShieldAlert} label="BPJS Kesehatan" value={pegawai?.no_bpjs_kesehatan} />
                        <Field icon={ShieldAlert} label="BPJS Ketenagakerjaan" value={pegawai?.no_bpjs_ketenagakerjaan} />
                    </Section>
                </div>
            )}
        </div>
    );
}
