import React from 'react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Head, useForm } from '@inertiajs/react';
import { CheckCircle } from 'lucide-react';
import ComboSelect from '@/Components/ComboSelect';

const inputClass = 'w-full rounded-xl border-border bg-surface px-4 py-3 text-sm text-primary ring-1 ring-black/5 placeholder:text-text-secondary/50 focus:outline-none focus:ring-2 focus:ring-primary';
const labelClass = 'mb-1.5 block text-xs font-bold uppercase tracking-wide text-primary/80';

const agamaOptions = [
    { value: 'Islam', label: 'Islam' },
    { value: 'Kristen', label: 'Kristen' },
    { value: 'Katolik', label: 'Katolik' },
    { value: 'Hindu', label: 'Hindu' },
    { value: 'Buddha', label: 'Buddha' },
    { value: 'Konghucu', label: 'Konghucu' },
];

const statusNikahOptions = [
    { value: 'Belum Menikah', label: 'Belum Menikah' },
    { value: 'Menikah', label: 'Menikah' },
    { value: 'Cerai Hidup', label: 'Cerai Hidup' },
    { value: 'Cerai Mati', label: 'Cerai Mati' },
];

const statusKepegOptions = [
    { value: 'tetap', label: 'Tetap' },
    { value: 'kontrak', label: 'Kontrak' },
    { value: 'honorer', label: 'Honorer' },
    { value: 'gtt', label: 'GTT' },
];

const pendidikanOptions = [
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

const SectionCard = ({ title, children }) => (
    <div className="rounded-xl border border-border bg-white p-4 shadow-sm">
        {title && <h3 className="mb-4 text-sm font-bold text-primary">{title}</h3>}
        <div className="space-y-4">{children}</div>
    </div>
);

const Field = ({ label, children, error }) => (
    <div>
        <label className={labelClass}>{label}</label>
        {children}
        {error && <p className="mt-1 text-xs font-semibold text-rose-600">{error}</p>}
    </div>
);

export default function LengkapiData({ auth, pegawai }) {
    const { data, setData, post, processing, errors } = useForm({
        nik: '',
        nip: pegawai?.nip || '',
        nama_lengkap: pegawai?.nama_lengkap || '',
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
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('presensi.lengkapi-data.store'));
    };

    return (
        <MobileLayout user={auth.user}>
            <Head title="Lengkapi Data" />

            <div className="space-y-5 pb-8">
                <div className="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs font-semibold text-amber-800">
                    Harap lengkapi data sebelum menggunakan sistem.
                </div>

                <form onSubmit={submit} className="space-y-5">
                    <SectionCard title="Data Pribadi">
                        <Field label="NIK *" error={errors.nik}>
                            <input type="text" maxLength={16} value={data.nik} onChange={(e) => setData('nik', e.target.value)} className={inputClass} placeholder="16 digit NIK" />
                        </Field>
                        <Field label="NIP" error={errors.nip}>
                            <input type="text" value={data.nip} onChange={(e) => setData('nip', e.target.value)} className={inputClass} placeholder="Nomor Induk Pegawai" />
                        </Field>
                        <Field label="Nama Lengkap *" error={errors.nama_lengkap}>
                            <input type="text" value={data.nama_lengkap} onChange={(e) => setData('nama_lengkap', e.target.value)} className={inputClass} placeholder="Nama sesuai KTP" />
                        </Field>
                        <Field label="Tempat Lahir *" error={errors.tempat_lahir}>
                            <input type="text" value={data.tempat_lahir} onChange={(e) => setData('tempat_lahir', e.target.value)} className={inputClass} />
                        </Field>
                        <Field label="Tanggal Lahir *" error={errors.tanggal_lahir}>
                            <input type="date" value={data.tanggal_lahir} onChange={(e) => setData('tanggal_lahir', e.target.value)} className={inputClass} />
                        </Field>
                        <Field label="Jenis Kelamin *" error={errors.jenis_kelamin}>
                            <ComboSelect value={data.jenis_kelamin} onChange={(v) => setData('jenis_kelamin', v)} options={[{value:'L',label:'Laki-laki'},{value:'P',label:'Perempuan'}]} />
                        </Field>
                        <Field label="Agama *" error={errors.agama}>
                            <ComboSelect value={data.agama} onChange={(v) => setData('agama', v)} options={agamaOptions} />
                        </Field>
                        <Field label="Status Pernikahan *" error={errors.status_pernikahan}>
                            <ComboSelect value={data.status_pernikahan} onChange={(v) => setData('status_pernikahan', v)} options={statusNikahOptions} />
                        </Field>
                        <Field label="Jumlah Tanggungan *" error={errors.jumlah_tanggungan}>
                            <input type="number" min={0} value={data.jumlah_tanggungan} onChange={(e) => setData('jumlah_tanggungan', e.target.value)} className={inputClass} />
                        </Field>
                    </SectionCard>

                    <SectionCard title="Alamat & Kontak">
                        <Field label="Alamat KTP *" error={errors.alamat_ktp}>
                            <textarea value={data.alamat_ktp} onChange={(e) => setData('alamat_ktp', e.target.value)} className={inputClass} rows={3} />
                        </Field>
                        <Field label="Alamat Domisili">
                            <textarea value={data.alamat_domisili} onChange={(e) => setData('alamat_domisili', e.target.value)} className={inputClass} rows={3} />
                        </Field>
                        <Field label="No. HP *" error={errors.no_hp}>
                            <input type="text" value={data.no_hp} onChange={(e) => setData('no_hp', e.target.value)} className={inputClass} placeholder="08xxxxxxxxxx" />
                        </Field>
                        <Field label="No. HP Darurat">
                            <input type="text" value={data.no_hp_darurat} onChange={(e) => setData('no_hp_darurat', e.target.value)} className={inputClass} placeholder="Kontak darurat" />
                        </Field>
                    </SectionCard>

                    <SectionCard title="Data Kepegawaian">
                        <Field label="Status *" error={errors.status_kepegawaian}>
                            <ComboSelect value={data.status_kepegawaian} onChange={(v) => setData('status_kepegawaian', v)} options={statusKepegOptions} />
                        </Field>
                        <Field label="Mulai Kerja *" error={errors.tanggal_mulai_kerja}>
                            <input type="date" value={data.tanggal_mulai_kerja} onChange={(e) => setData('tanggal_mulai_kerja', e.target.value)} className={inputClass} />
                        </Field>
                        <Field label="Akhir Kontrak" error={errors.tanggal_akhir_kontrak}>
                            <input type="date" value={data.tanggal_akhir_kontrak} onChange={(e) => setData('tanggal_akhir_kontrak', e.target.value)} className={inputClass} />
                        </Field>
                        <Field label="Pendidikan *" error={errors.pendidikan_terakhir}>
                            <ComboSelect value={data.pendidikan_terakhir} onChange={(v) => setData('pendidikan_terakhir', v)} options={pendidikanOptions} />
                        </Field>
                        <Field label="Jurusan *" error={errors.pendidikan_jurusan}>
                            <input type="text" value={data.pendidikan_jurusan} onChange={(e) => setData('pendidikan_jurusan', e.target.value)} className={inputClass} placeholder="Misal: Pendidikan Matematika" />
                        </Field>
                    </SectionCard>

                    <SectionCard title="Data Finansial">
                        <Field label="Nama Bank *" error={errors.nama_bank}>
                            <ComboSelect value={data.nama_bank} onChange={(v) => setData('nama_bank', v)} options={bankOptions} />
                        </Field>
                        <Field label="No. Rekening *" error={errors.no_rekening}>
                            <input type="text" value={data.no_rekening} onChange={(e) => setData('no_rekening', e.target.value)} className={inputClass} placeholder="Nomor rekening" />
                        </Field>
                    </SectionCard>

                    <button
                        type="submit"
                        disabled={processing}
                        className="flex w-full items-center justify-center gap-2 rounded-2xl bg-primary px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-primary/20 transition-transform hover:brightness-110 active:scale-[0.98] disabled:opacity-50"
                    >
                        <CheckCircle className="h-5 w-5" />
                        {processing ? 'Menyimpan…' : 'Simpan Data'}
                    </button>
                </form>
            </div>
        </MobileLayout>
    );
}
