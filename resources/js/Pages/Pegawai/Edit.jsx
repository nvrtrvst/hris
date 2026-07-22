import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import FormField from '@/Components/Form/FormField';
import FormSection from '@/Components/Form/FormSection';

const inputClass = 'mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm';

export default function Edit({ auth, pegawai, unitSekolahs, jabatans, mapels }) {
    const { data, setData, post, processing, errors } = useForm({
        _method: 'put',
        nik: pegawai.nik,
        nik_plain: pegawai.nik_plain,
        nip: pegawai.nip || '',
        nama_lengkap: pegawai.nama_lengkap,
        email: pegawai.user?.email || '',
        tempat_lahir: pegawai.tempat_lahir,
        tanggal_lahir: pegawai.tanggal_lahir,
        jenis_kelamin: pegawai.jenis_kelamin,
        agama: pegawai.agama,
        status_pernikahan: pegawai.status_pernikahan,
        alamat_ktp: pegawai.alamat_ktp,
        no_hp: pegawai.no_hp,
        status_kepegawaian: pegawai.status_kepegawaian,
        wajib_kantor: pegawai.wajib_kantor ?? false,
        jatah_cuti_tahunan: pegawai.jatah_cuti_tahunan ?? 12,
        status_aktif: pegawai.status_aktif,
        tanggal_mulai_kerja: pegawai.tanggal_mulai_kerja,
        pendidikan_terakhir: pegawai.pendidikan_terakhir,
        foto: null,
        units: (pegawai.units || []).map((u) => ({
            unit_sekolah_id: u.id,
            jabatan_id: u.pivot?.jabatan_id ?? '',
            is_primary: !!u.pivot?.is_primary,
        })),
        mapels: (pegawai.mapels || []).map((m) => ({
            mata_pelajaran_id: m.id,
            unit_sekolah_id: m.pivot?.unit_sekolah_id ?? '',
        })),
    });

    const updateUnit = (index, field, value) => {
        const next = [...data.units];
        next[index] = { ...next[index], [field]: value };
        setData('units', next);
    };
    const addUnit = () => setData('units', [...data.units, { unit_sekolah_id: '', jabatan_id: '', is_primary: false }]);
    const removeUnit = (index) => setData('units', data.units.filter((_, i) => i !== index));

    const updateMapel = (index, field, value) => {
        const next = [...data.mapels];
        next[index] = { ...next[index], [field]: value };
        setData('mapels', next);
    };
    const addMapel = () => setData('mapels', [...data.mapels, { mata_pelajaran_id: '', unit_sekolah_id: '' }]);
    const removeMapel = (index) => setData('mapels', data.mapels.filter((_, i) => i !== index));

    const submit = (e) => {
        e.preventDefault();
        post(route('pegawai.update', pegawai.id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-2xl text-gray-800 leading-tight">Edit Pegawai: {pegawai.nama_lengkap}</h2>}
        >
            <Head title={`Edit Pegawai - ${pegawai.nama_lengkap}`} />

            <div className="py-12 bg-gray-50 min-h-screen">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
                    <div className="mb-4">
                        <Link href={route('pegawai.index')} className="inline-flex items-center text-sm font-medium text-gray-500 hover:text-indigo-600 transition-colors">
                            <ArrowLeft className="mr-1.5 h-5 w-5" />
                            Kembali ke Daftar Pegawai
                        </Link>
                    </div>

                    <div className="bg-white overflow-hidden shadow-xl sm:rounded-2xl border border-gray-100">
                        <div className="p-8">
                            <form onSubmit={submit} className="space-y-6">
                                <FormSection title="Foto Pegawai">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 items-center md:col-span-2">
                                        {pegawai.foto_url && (
                                            <div>
                                                <p className="text-sm font-medium text-gray-700 mb-2">Foto Saat Ini:</p>
                                                <img src={pegawai.foto_url} alt="Foto Pegawai" className="w-32 h-32 object-cover rounded-md shadow-sm border" />
                                            </div>
                                        )}
                                        <FormField label="Upload Foto Baru (Opsional)" error={errors.foto} hint="Kosongkan jika tidak ingin mengubah foto.">
                                            <input
                                                type="file"
                                                onChange={e => setData('foto', e.target.files[0])}
                                                accept="image/jpeg, image/png, image/jpg"
                                                className="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                                            />
                                        </FormField>
                                    </div>
                                </FormSection>

                                <FormSection title="Informasi Dasar">
                                    <FormField label="NIK" required error={errors.nik}>
                                        <input
                                            type="text"
                                            value={data.nik_plain ?? data.nik ?? ''}
                                            onChange={e => setData('nik', e.target.value)}
                                            className={inputClass}
                                        />
                                    </FormField>
                                    <FormField label="NIP / No Induk Guru" error={errors.nip}>
                                        <input
                                            type="text"
                                            value={data.nip}
                                            onChange={e => setData('nip', e.target.value)}
                                            className={inputClass}
                                            placeholder="NIP / No Induk (Opsional)"
                                        />
                                    </FormField>
                                    <FormField label="Nama Lengkap" required error={errors.nama_lengkap}>
                                        <input
                                            type="text"
                                            value={data.nama_lengkap}
                                            onChange={e => setData('nama_lengkap', e.target.value)}
                                            className={inputClass}
                                        />
                                    </FormField>
                                    <FormField label="Email Login Mobile" required error={errors.email} hint="Email ini dipakai untuk login ke portal mobile.">
                                        <input
                                            type="email"
                                            value={data.email}
                                            onChange={e => setData('email', e.target.value)}
                                            autoComplete="email"
                                            className={inputClass}
                                        />
                                    </FormField>
                                    <FormField label="Tempat Lahir" required error={errors.tempat_lahir}>
                                        <input
                                            type="text"
                                            value={data.tempat_lahir}
                                            onChange={e => setData('tempat_lahir', e.target.value)}
                                            className={inputClass}
                                        />
                                    </FormField>
                                    <FormField label="Tanggal Lahir" required error={errors.tanggal_lahir}>
                                        <input
                                            type="date"
                                            value={data.tanggal_lahir}
                                            onChange={e => setData('tanggal_lahir', e.target.value)}
                                            className={inputClass}
                                        />
                                    </FormField>
                                    <FormField label="Jenis Kelamin" required error={errors.jenis_kelamin}>
                                        <select
                                            value={data.jenis_kelamin}
                                            onChange={e => setData('jenis_kelamin', e.target.value)}
                                            className={inputClass}
                                        >
                                            <option value="">Pilih Jenis Kelamin</option>
                                            <option value="L">Laki-laki</option>
                                            <option value="P">Perempuan</option>
                                        </select>
                                    </FormField>
                                    <FormField label="Agama" required error={errors.agama}>
                                        <select
                                            value={data.agama}
                                            onChange={e => setData('agama', e.target.value)}
                                            className={inputClass}
                                        >
                                            <option value="">Pilih Agama</option>
                                            <option value="Islam">Islam</option>
                                            <option value="Kristen">Kristen</option>
                                            <option value="Katolik">Katolik</option>
                                            <option value="Hindu">Hindu</option>
                                            <option value="Buddha">Buddha</option>
                                            <option value="Konghucu">Konghucu</option>
                                        </select>
                                    </FormField>
                                    <FormField label="Status Pernikahan" required error={errors.status_pernikahan}>
                                        <select
                                            value={data.status_pernikahan}
                                            onChange={e => setData('status_pernikahan', e.target.value)}
                                            className={inputClass}
                                        >
                                            <option value="">Pilih Status</option>
                                            <option value="Belum Menikah">Belum Menikah</option>
                                            <option value="Menikah">Menikah</option>
                                            <option value="Cerai Hidup">Cerai Hidup</option>
                                            <option value="Cerai Mati">Cerai Mati</option>
                                        </select>
                                    </FormField>
                                    <FormField label="No. HP" required error={errors.no_hp}>
                                        <input
                                            type="text"
                                            value={data.no_hp}
                                            onChange={e => setData('no_hp', e.target.value)}
                                            className={inputClass}
                                        />
                                    </FormField>
                                    <div className="md:col-span-2">
                                        <FormField label="Alamat KTP" required error={errors.alamat_ktp}>
                                            <textarea
                                                value={data.alamat_ktp}
                                                onChange={e => setData('alamat_ktp', e.target.value)}
                                                rows={3}
                                                className={inputClass}
                                            />
                                        </FormField>
                                    </div>
                                </FormSection>

                                <FormSection title="Status Kepegawaian">
                                    <FormField label="Status Kepegawaian" error={errors.status_kepegawaian}>
                                        <select
                                            value={data.status_kepegawaian}
                                            onChange={e => setData('status_kepegawaian', e.target.value)}
                                            className={inputClass}
                                        >
                                            <option value="tetap">Tetap</option>
                                            <option value="kontrak">Kontrak</option>
                                            <option value="honorer">Honorer</option>
                                            <option value="gtt">GTT (Guru Tidak Tetap)</option>
                                        </select>
                                    </FormField>
                                    <FormField label="Wajib masuk kantor" error={errors.wajib_kantor} hint="Jika tidak ada jadwal mengajar, tetap harus absen kantor.">
                                        <label className="inline-flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={data.wajib_kantor}
                                                onChange={(e) => setData('wajib_kantor', e.target.checked)}
                                                className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span className="text-sm text-gray-700">Aktifkan</span>
                                        </label>
                                    </FormField>
                                    <FormField label="Jatah Cuti Tahunan (Hari)" error={errors.jatah_cuti_tahunan}>
                                        <input
                                            type="number"
                                            min="0"
                                            value={data.jatah_cuti_tahunan}
                                            onChange={e => setData('jatah_cuti_tahunan', e.target.value)}
                                            className={inputClass}
                                        />
                                    </FormField>
                                    <FormField label="Status Aktif" error={errors.status_aktif}>
                                        <select
                                            value={data.status_aktif}
                                            onChange={e => setData('status_aktif', e.target.value)}
                                            className={inputClass}
                                        >
                                            <option value="aktif">Aktif</option>
                                            <option value="cuti">Cuti</option>
                                            <option value="nonaktif">Nonaktif</option>
                                            <option value="resign">Resign</option>
                                        </select>
                                    </FormField>
                                    <FormField label="Tanggal Mulai Kerja" required error={errors.tanggal_mulai_kerja}>
                                        <input
                                            type="date"
                                            value={data.tanggal_mulai_kerja}
                                            onChange={e => setData('tanggal_mulai_kerja', e.target.value)}
                                            className={inputClass}
                                        />
                                    </FormField>
                                    <FormField label="Pendidikan Terakhir" required error={errors.pendidikan_terakhir}>
                                        <input
                                            type="text"
                                            value={data.pendidikan_terakhir}
                                            onChange={e => setData('pendidikan_terakhir', e.target.value)}
                                            className={inputClass}
                                            placeholder="Contoh: S1 Pendidikan Agama Islam"
                                        />
                                    </FormField>
                                </FormSection>

                                <FormSection
                                    title="Penugasan Unit & Jabatan"
                                    description="Tentukan unit tempat pegawai bertugas beserta jabatannya. Satu unit dapat ditandai Primary."
                                >
                                    <div className="md:col-span-2 space-y-3">
                                        {data.units.map((u, i) => (
                                            <div key={i} className="grid grid-cols-1 md:grid-cols-12 gap-3 items-center bg-gray-50 p-3 rounded-lg">
                                                <div className="md:col-span-5">
                                                    <select
                                                        value={u.unit_sekolah_id}
                                                        onChange={(e) => updateUnit(i, 'unit_sekolah_id', e.target.value)}
                                                        className={inputClass + ' mt-0'}
                                                    >
                                                        <option value="">Pilih Unit</option>
                                                        {unitSekolahs.map((us) => (
                                                            <option key={us.id} value={us.id}>{us.nama}</option>
                                                        ))}
                                                    </select>
                                                </div>
                                                <div className="md:col-span-4">
                                                    <select
                                                        value={u.jabatan_id}
                                                        onChange={(e) => updateUnit(i, 'jabatan_id', e.target.value)}
                                                        className={inputClass + ' mt-0'}
                                                    >
                                                        <option value="">Pilih Jabatan</option>
                                                        {jabatans.map((j) => (
                                                            <option key={j.id} value={j.id}>{j.nama}</option>
                                                        ))}
                                                    </select>
                                                </div>
                                                <div className="md:col-span-2 flex items-center">
                                                    <label className="inline-flex items-center text-sm text-gray-700">
                                                        <input
                                                            type="checkbox"
                                                            checked={!!u.is_primary}
                                                            onChange={(e) => updateUnit(i, 'is_primary', e.target.checked)}
                                                            className="mr-2 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                        />
                                                        Primary
                                                    </label>
                                                </div>
                                                <div className="md:col-span-1 text-right">
                                                    <button type="button" onClick={() => removeUnit(i)} className="text-red-500 hover:text-red-700 text-sm font-medium">Hapus</button>
                                                </div>
                                            </div>
                                        ))}
                                        {data.units.length === 0 && <p className="text-sm text-gray-500">Belum ada penugasan unit.</p>}
                                        <button type="button" onClick={addUnit} className="inline-flex items-center text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                                            + Tambah Unit
                                        </button>
                                    </div>
                                </FormSection>

                                <FormSection
                                    title="Mata Pelajaran (Guru)"
                                    description="Untuk guru: tentukan mata pelajaran yang diampu beserta unitnya. Baris yang tidak lengkap akan diabaikan."
                                >
                                    <div className="md:col-span-2 space-y-3">
                                        {data.mapels.map((m, i) => (
                                            <div key={i} className="grid grid-cols-1 md:grid-cols-12 gap-3 items-center bg-gray-50 p-3 rounded-lg">
                                                <div className="md:col-span-5">
                                                    <select
                                                        value={m.mata_pelajaran_id}
                                                        onChange={(e) => updateMapel(i, 'mata_pelajaran_id', e.target.value)}
                                                        className={inputClass + ' mt-0'}
                                                    >
                                                        <option value="">Pilih Mata Pelajaran</option>
                                                        {mapels.map((mp) => (
                                                            <option key={mp.id} value={mp.id}>{mp.nama}</option>
                                                        ))}
                                                    </select>
                                                </div>
                                                <div className="md:col-span-5">
                                                    <select
                                                        value={m.unit_sekolah_id}
                                                        onChange={(e) => updateMapel(i, 'unit_sekolah_id', e.target.value)}
                                                        className={inputClass + ' mt-0'}
                                                    >
                                                        <option value="">Pilih Unit</option>
                                                        {unitSekolahs.map((us) => (
                                                            <option key={us.id} value={us.id}>{us.nama}</option>
                                                        ))}
                                                    </select>
                                                </div>
                                                <div className="md:col-span-2 text-right">
                                                    <button type="button" onClick={() => removeMapel(i)} className="text-red-500 hover:text-red-700 text-sm font-medium">Hapus</button>
                                                </div>
                                            </div>
                                        ))}
                                        {data.mapels.length === 0 && <p className="text-sm text-gray-500">Bukan guru / belum ada mata pelajaran.</p>}
                                        <button type="button" onClick={addMapel} className="inline-flex items-center text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                                            + Tambah Mata Pelajaran
                                        </button>
                                    </div>
                                </FormSection>

                                <div className="flex items-center justify-end mt-8 border-t pt-6">
                                    <Link href={route('pegawai.show', pegawai.id)} className="text-gray-600 hover:text-gray-900 mr-6 font-medium">Batal</Link>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-8 rounded-lg shadow-md hover:shadow-lg transition-all duration-200 disabled:opacity-50"
                                    >
                                        {processing ? 'Menyimpan...' : 'Perbarui Pegawai'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
