import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { Camera, CheckCircle } from 'lucide-react';
import ComboSelect from '@/Components/ComboSelect';
import { validateUpload } from '@/Utils/file';

const inputClass = 'input-field';
const labelClass = 'form-label';

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

const SectionCard = ({ title, desc, children }) => (
    <div className="page-card">
        {title && (
            <div className="page-card-header">
                <h3 className="section-title text-base font-bold text-primary">{title}</h3>
                {desc && <p className="mt-0.5 text-xs text-text-secondary">{desc}</p>}
            </div>
        )}
        <div className="grid grid-cols-1 gap-5 md:grid-cols-2">{children}</div>
    </div>
);

const Field = ({ label, children, error, full }) => (
    <div className={full ? 'md:col-span-2' : ''}>
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

    const [fotoPreview, setFotoPreview] = useState(null);
    const [fotoError, setFotoError] = useState(null);

    const handleFoto = (e) => {
        const file = e.target.files[0];
        setFotoError(null);
        if (file) {
            const err = validateUpload(file, { maxBytes: 2 * 1024 * 1024, accept: ['image/jpeg', 'image/png', 'image/jpg'], label: 'Foto' });
            if (err) {
                setFotoError(err);
                e.target.value = '';
                return;
            }
            setData('foto', file);
            setFotoPreview(URL.createObjectURL(file));
        }
    };

    const submit = (e) => {
        e.preventDefault();
        if (fotoError) return;
        post(route('lengkapi-data.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="text-xl font-bold text-primary">Lengkapi Data Pegawai</h2>}
        >
            <Head title="Lengkapi Data" />

            <div className="mx-auto max-w-3xl space-y-6 py-6">
                <div className="rounded-card border border-warning/30 bg-warning-light p-4 text-sm font-medium text-warning">
                    Harap lengkapi data diri Anda sebelum menggunakan sistem. Field bertanda * wajib diisi.
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <SectionCard title="Data Pribadi" desc="Data identitas diri">
                        <Field label="NIK *" error={errors.nik}>
                            <input type="text" maxLength={16} value={data.nik} onChange={(e) => setData('nik', e.target.value)} className={inputClass} placeholder="16 digit NIK" />
                        </Field>
                        <Field label="NIP" error={errors.nip}>
                            <input type="text" value={data.nip} onChange={(e) => setData('nip', e.target.value)} className={inputClass} placeholder="Nomor Induk Pegawai" />
                        </Field>
                        <Field label="Nama Lengkap *" error={errors.nama_lengkap}>
                            <input type="text" value={data.nama_lengkap} onChange={(e) => setData('nama_lengkap', e.target.value)} className={inputClass} placeholder="Nama lengkap sesuai KTP" />
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

                    <SectionCard title="Alamat & Kontak" desc="Alamat dan nomor telepon">
                        <Field label="Alamat KTP *" error={errors.alamat_ktp} full>
                            <textarea value={data.alamat_ktp} onChange={(e) => setData('alamat_ktp', e.target.value)} className={inputClass} rows={3} />
                        </Field>
                        <Field label="Alamat Domisili" error={errors.alamat_domisili} full>
                            <textarea value={data.alamat_domisili} onChange={(e) => setData('alamat_domisili', e.target.value)} className={inputClass} rows={3} />
                        </Field>
                        <Field label="No. HP *" error={errors.no_hp}>
                            <input type="text" value={data.no_hp} onChange={(e) => setData('no_hp', e.target.value)} className={inputClass} placeholder="08xxxxxxxxxx" />
                        </Field>
                        <Field label="No. HP Darurat" error={errors.no_hp_darurat}>
                            <input type="text" value={data.no_hp_darurat} onChange={(e) => setData('no_hp_darurat', e.target.value)} className={inputClass} placeholder="Kontak darurat" />
                        </Field>
                    </SectionCard>

                    <SectionCard title="Data Kepegawaian" desc="Status kepegawaian dan pendidikan">
                        <Field label="Status Kepegawaian *" error={errors.status_kepegawaian}>
                            <ComboSelect value={data.status_kepegawaian} onChange={(v) => setData('status_kepegawaian', v)} options={statusKepegOptions} />
                        </Field>
                        <Field label="Tanggal Mulai Kerja *" error={errors.tanggal_mulai_kerja}>
                            <input type="date" value={data.tanggal_mulai_kerja} onChange={(e) => setData('tanggal_mulai_kerja', e.target.value)} className={inputClass} />
                        </Field>
                        <Field label="Tanggal Akhir Kontrak" error={errors.tanggal_akhir_kontrak}>
                            <input type="date" value={data.tanggal_akhir_kontrak} onChange={(e) => setData('tanggal_akhir_kontrak', e.target.value)} className={inputClass} />
                        </Field>
                        <div />
                        <Field label="Pendidikan Terakhir *" error={errors.pendidikan_terakhir}>
                            <ComboSelect value={data.pendidikan_terakhir} onChange={(v) => setData('pendidikan_terakhir', v)} options={pendidikanOptions} />
                        </Field>
                        <Field label="Jurusan / Program Studi *" error={errors.pendidikan_jurusan}>
                            <input type="text" value={data.pendidikan_jurusan} onChange={(e) => setData('pendidikan_jurusan', e.target.value)} className={inputClass} placeholder="Misal: Pendidikan Matematika" />
                        </Field>
                    </SectionCard>

                    <SectionCard title="Data Finansial" desc="Informasi rekening bank">
                        <Field label="Nama Bank *" error={errors.nama_bank}>
                            <ComboSelect value={data.nama_bank} onChange={(v) => setData('nama_bank', v)} options={bankOptions} />
                        </Field>
                        <Field label="No. Rekening *" error={errors.no_rekening}>
                            <input type="text" value={data.no_rekening} onChange={(e) => setData('no_rekening', e.target.value)} className={inputClass} placeholder="Nomor rekening bank" />
                        </Field>
                    </SectionCard>

                    <SectionCard title="Foto Profil" desc="Upload foto diri (opsional)">
                        <div className="md:col-span-2">
                                <label className="flex cursor-pointer flex-col items-center gap-2 rounded-card border-2 border-dashed border-border bg-surface p-6 transition hover:border-primary">
                                <Camera className="h-8 w-8 text-text-secondary" />
                                <span className="text-sm font-medium text-text-secondary">Klik untuk upload foto</span>
                                <span className="text-xs text-text-secondary/60">Format: JPG/PNG, max 2MB</span>
                                <input type="file" accept="image/jpeg,image/png,image/jpg" onChange={handleFoto} className="hidden" />
                            </label>
                                {fotoPreview && (
                                    <div className="mt-3 flex items-center gap-3">
                                        <img src={fotoPreview} alt="Preview" className="h-16 w-16 rounded-full object-cover ring-2 ring-border-light" />
                                        <span className="text-sm text-text-secondary">Foto siap diupload</span>
                                    </div>
                                )}
                                {fotoError && <p className="mt-1 text-xs font-medium text-rose-600">{fotoError}</p>}
                        </div>
                    </SectionCard>

                    <button
                        type="submit"
                        disabled={processing}
                        className="btn-primary btn-lg w-full"
                    >
                        <CheckCircle className="h-5 w-5" />
                        {processing ? 'Menyimpan…' : 'Simpan Data'}
                    </button>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
