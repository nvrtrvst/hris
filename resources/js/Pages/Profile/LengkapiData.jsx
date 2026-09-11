import React, { useEffect, useState } from 'react';
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

const FIELD_LABELS = {
    nik: 'NIK',
    nuptk: 'NIP',
    nama_lengkap: 'Nama Lengkap',
    tempat_lahir: 'Tempat Lahir',
    tanggal_lahir: 'Tanggal Lahir',
    jenis_kelamin: 'Jenis Kelamin',
    agama: 'Agama',
    status_pernikahan: 'Status Pernikahan',
    jumlah_tanggungan: 'Jumlah Tanggungan',
    alamat: 'Alamat KTP',
    no_hp: 'No. HP',
    no_hp_darurat: 'No. HP Darurat',
    status_kepegawaian: 'Status Kepegawaian',
    tmt_mengajar: 'Tanggal Mulai Kerja',
    tanggal_akhir_kontrak: 'Tanggal Akhir Kontrak',
    pendidikan_terakhir: 'Pendidikan Terakhir',
    pendidikan_jurusan: 'Jurusan / Program Studi',
    pendidikan_tahun_lulus: 'Tahun Lulus',
    pendidikan_asal_sekolah: 'Asal Sekolah / Perguruan Tinggi',
    nama_bank: 'Nama Bank',
    no_rekening: 'No. Rekening',
};

const Field = ({ name, label, children, error, full }) => (
    <div id={`field-${name}`} className={full ? 'md:col-span-2' : ''}>
        <label className={labelClass}>{label}</label>
        {children}
        {error && <p className="mt-1 text-xs font-semibold text-rose-600">{error}</p>}
    </div>
);

export default function LengkapiData({ auth, pegawai, statusKepegawaian }) {
    const statusKepegOptions = (statusKepegawaian || []).map((s) => ({ value: s.kode, label: s.label }));
    const { data, setData, post, processing, errors } = useForm({
        nik: '',
        nuptk: pegawai?.nuptk || '',
        nama_lengkap: pegawai?.nama_lengkap || '',
        tempat_lahir: pegawai?.tempat_lahir || '',
        tanggal_lahir: pegawai?.tanggal_lahir || '',
        jenis_kelamin: pegawai?.jenis_kelamin || '',
        agama: pegawai?.agama || '',
        status_pernikahan: pegawai?.status_pernikahan || '',
        jumlah_tanggungan: pegawai?.jumlah_tanggungan ?? 0,
        alamat: pegawai?.alamat || '',
        no_hp: pegawai?.no_hp || '',
        no_hp_darurat: pegawai?.no_hp_darurat || '',
        status_kepegawaian: pegawai?.status_kepegawaian || '',
        tmt_mengajar: pegawai?.tmt_mengajar || '',
        tanggal_akhir_kontrak: pegawai?.tanggal_akhir_kontrak || '',
        pendidikan_terakhir: pegawai?.pendidikan_terakhir || '',
        pendidikan_jurusan: pegawai?.pendidikan_jurusan || '',
        pendidikan_tahun_lulus: pegawai?.pendidikan_tahun_lulus || '',
        pendidikan_asal_sekolah: pegawai?.pendidikan_asal_sekolah || '',
        sk_nomor: pegawai?.sk_nomor || '',
        sk_tanggal: pegawai?.sk_tanggal || '',
        nama_bank: pegawai?.nama_bank || '',
        no_rekening: pegawai?.no_rekening || '',
    });

    const [fotoPreview, setFotoPreview] = useState(null);
    const [fotoError, setFotoError] = useState(null);

    const errorFields = Object.keys(errors);

    useEffect(() => {
        if (errorFields.length > 0) {
            document.getElementById(`field-${errorFields[0]}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, [errorFields.join(',')]);

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
                {errorFields.length > 0 ? (
                    <div role="alert" className="rounded-card border border-rose-300 bg-rose-50 p-4 text-sm font-semibold text-rose-800">
                        Periksa kembali field berikut: {errorFields.map((f) => FIELD_LABELS[f] || f).join(', ')}.
                    </div>
                ) : (
                    <div className="rounded-card border border-warning/30 bg-warning-light p-4 text-sm font-medium text-warning">
                        Harap lengkapi data diri Anda sebelum menggunakan sistem. Field bertanda * wajib diisi.
                    </div>
                )}

                <form onSubmit={submit} className="space-y-6">
                    <SectionCard title="Data Pribadi" desc="Data identitas diri">
                        <Field name="nik" label="NIK *" error={errors.nik}>
                            <input type="text" maxLength={16} value={data.nik} onChange={(e) => setData('nik', e.target.value)} className={inputClass} placeholder="16 digit NIK" />
                        </Field>
                        <Field name="nuptk" label="NIP" error={errors.nuptk}>
                            <input type="text" value={data.nuptk} onChange={(e) => setData('nuptk', e.target.value)} className={inputClass} placeholder="Nomor Induk Pegawai" />
                        </Field>
                        <Field name="nama_lengkap" label="Nama Lengkap *" error={errors.nama_lengkap}>
                            <input type="text" value={data.nama_lengkap} onChange={(e) => setData('nama_lengkap', e.target.value)} className={inputClass} placeholder="Nama lengkap sesuai KTP" />
                        </Field>
                        <Field name="tempat_lahir" label="Tempat Lahir *" error={errors.tempat_lahir}>
                            <input type="text" value={data.tempat_lahir} onChange={(e) => setData('tempat_lahir', e.target.value)} className={inputClass} />
                        </Field>
                        <Field name="tanggal_lahir" label="Tanggal Lahir *" error={errors.tanggal_lahir}>
                            <input type="date" value={data.tanggal_lahir} onChange={(e) => setData('tanggal_lahir', e.target.value)} className={inputClass} />
                        </Field>
                        <Field name="jenis_kelamin" label="Jenis Kelamin *" error={errors.jenis_kelamin}>
                            <ComboSelect value={data.jenis_kelamin} onChange={(v) => setData('jenis_kelamin', v)} options={[{value:'L',label:'Laki-laki'},{value:'P',label:'Perempuan'}]} />
                        </Field>
                        <Field name="agama" label="Agama *" error={errors.agama}>
                            <ComboSelect value={data.agama} onChange={(v) => setData('agama', v)} options={agamaOptions} />
                        </Field>
                        <Field name="status_pernikahan" label="Status Pernikahan *" error={errors.status_pernikahan}>
                            <ComboSelect value={data.status_pernikahan} onChange={(v) => setData('status_pernikahan', v)} options={statusNikahOptions} />
                        </Field>
                        <Field name="jumlah_tanggungan" label="Jumlah Tanggungan *" error={errors.jumlah_tanggungan}>
                            <input type="number" min={0} value={data.jumlah_tanggungan} onChange={(e) => setData('jumlah_tanggungan', e.target.value)} className={inputClass} />
                        </Field>
                    </SectionCard>

                    <SectionCard title="Alamat & Kontak" desc="Alamat dan nomor telepon">
                        <Field name="alamat" label="Alamat KTP *" error={errors.alamat} full>
                            <textarea value={data.alamat} onChange={(e) => setData('alamat', e.target.value)} className={inputClass} rows={3} />
                        </Field>
                        <Field name="no_hp" label="No. HP *" error={errors.no_hp}>
                            <input type="text" value={data.no_hp} onChange={(e) => setData('no_hp', e.target.value)} className={inputClass} placeholder="08xxxxxxxxxx" />
                        </Field>
                        <Field name="no_hp_darurat" label="No. HP Darurat" error={errors.no_hp_darurat}>
                            <input type="text" value={data.no_hp_darurat} onChange={(e) => setData('no_hp_darurat', e.target.value)} className={inputClass} placeholder="Kontak darurat" />
                        </Field>
                    </SectionCard>

                    <SectionCard title="Data Kepegawaian" desc="Status kepegawaian dan pendidikan">
                        <Field name="status_kepegawaian" label="Status Kepegawaian *" error={errors.status_kepegawaian}>
                            <ComboSelect value={data.status_kepegawaian} onChange={(v) => setData('status_kepegawaian', v)} options={statusKepegOptions} />
                        </Field>
                        <Field name="tmt_mengajar" label="Tanggal Mulai Kerja *" error={errors.tmt_mengajar}>
                            <input type="date" value={data.tmt_mengajar} onChange={(e) => setData('tmt_mengajar', e.target.value)} className={inputClass} />
                        </Field>
                        <Field name="tanggal_akhir_kontrak" label="Tanggal Akhir Kontrak" error={errors.tanggal_akhir_kontrak}>
                            <input type="date" value={data.tanggal_akhir_kontrak} onChange={(e) => setData('tanggal_akhir_kontrak', e.target.value)} className={inputClass} />
                        </Field>
                        <div />
                        <Field name="pendidikan_terakhir" label="Pendidikan Terakhir *" error={errors.pendidikan_terakhir}>
                            <ComboSelect value={data.pendidikan_terakhir} onChange={(v) => setData('pendidikan_terakhir', v)} options={pendidikanOptions} />
                        </Field>
                        <Field name="pendidikan_jurusan" label="Jurusan / Program Studi *" error={errors.pendidikan_jurusan}>
                            <input type="text" value={data.pendidikan_jurusan} onChange={(e) => setData('pendidikan_jurusan', e.target.value)} className={inputClass} placeholder="Misal: Pendidikan Matematika" />
                        </Field>
                        <Field name="pendidikan_tahun_lulus" label="Tahun Lulus" error={errors.pendidikan_tahun_lulus}>
                            <input type="number" min="1950" max="2100" value={data.pendidikan_tahun_lulus} onChange={(e) => setData('pendidikan_tahun_lulus', e.target.value)} className={inputClass} placeholder="Misal: 2020" />
                        </Field>
                        <Field name="pendidikan_asal_sekolah" label="Asal Sekolah / Perguruan Tinggi" error={errors.pendidikan_asal_sekolah}>
                            <input type="text" value={data.pendidikan_asal_sekolah} onChange={(e) => setData('pendidikan_asal_sekolah', e.target.value)} className={inputClass} placeholder="Misal: UIN Syarif Hidayatullah" />
                        </Field>
                    </SectionCard>

                    <SectionCard title="Data Finansial" desc="Informasi rekening bank">
                        <Field name="nama_bank" label="Nama Bank *" error={errors.nama_bank}>
                            <ComboSelect value={data.nama_bank} onChange={(v) => setData('nama_bank', v)} options={bankOptions} />
                        </Field>
                        <Field name="no_rekening" label="No. Rekening *" error={errors.no_rekening}>
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
