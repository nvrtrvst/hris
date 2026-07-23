import React, { useState, useRef } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Camera, Trash2, Plus, X as XIcon } from 'lucide-react';

const inputClass = 'mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm';
const selectClass = 'mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

function SectionCard({ title, description, children }) {
    return (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            {title && (
                <div className="border-b border-gray-100 pb-3 mb-5">
                    <h3 className="text-base font-bold text-gray-900">{title}</h3>
                    {description && <p className="text-xs text-gray-500 mt-0.5">{description}</p>}
                </div>
            )}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                {children}
            </div>
        </div>
    );
}

export default function Edit({ auth, pegawai, unitSekolahs, jabatans, mapels }) {
    const { data, setData, post, processing, errors } = useForm({
        _method: 'put',
        nik: pegawai.nik_plain ?? pegawai.nik,
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
        hapus_foto: false,
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

    const [fotoPreview, setFotoPreview] = useState(null);
    const fileInputRef = useRef(null);

    const handleFotoChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            setData('foto', file);
            setData('hapus_foto', false);
            const reader = new FileReader();
            reader.onload = (ev) => setFotoPreview(ev.target.result);
            reader.readAsDataURL(file);
        }
    };

    const clearFoto = () => {
        setData('foto', null);
        setData('hapus_foto', true);
        setFotoPreview(null);
        if (fileInputRef.current) fileInputRef.current.value = '';
    };

    const hasFoto = pegawai.foto_url && !data.hapus_foto && !fotoPreview;

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
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    <Link href={route('pegawai.index')} className="inline-flex items-center text-sm font-medium text-gray-500 hover:text-primary transition-colors">
                        <ArrowLeft className="mr-1.5 h-5 w-5" />
                        Kembali ke Daftar Pegawai
                    </Link>

                    <form onSubmit={submit} className="space-y-6">
                        {/* Foto */}
                        <SectionCard title="Foto Pegawai">
                            <div className="md:col-span-2">
                                <div className="flex flex-col sm:flex-row gap-6 items-start">
                                    {(hasFoto || fotoPreview) && (
                                        <div className="relative shrink-0">
                                            <p className="text-xs font-medium text-gray-500 mb-2">{hasFoto ? 'Foto Saat Ini' : 'Foto Baru'}</p>
                                            <div className="relative">
                                                <img
                                                    src={fotoPreview || pegawai.foto_url}
                                                    alt="Foto Pegawai"
                                                    className="w-40 h-48 object-cover rounded-xl border border-gray-200 shadow-sm"
                                                />
                                                {fotoPreview && (
                                                    <button type="button" onClick={clearFoto}
                                                        className="absolute -top-2 -right-2 bg-white rounded-full p-1 shadow border border-gray-200 text-gray-400 hover:text-red-500 transition-colors"
                                                    >
                                                        <XIcon className="w-4 h-4" />
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                    <div className="flex-1 min-w-0">
                                        <label className="block text-sm font-medium text-gray-700 mb-1.5">Upload Foto Baru</label>
                                        <div className="flex items-center gap-3">
                                            <label className="cursor-pointer inline-flex items-center gap-2 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 shadow-sm transition-colors">
                                                <Camera className="w-4 h-4" />
                                                Pilih File
                                                <input ref={fileInputRef} type="file" onChange={handleFotoChange}
                                                    accept="image/jpeg, image/png, image/jpg" className="hidden" />
                                            </label>
                                            {pegawai.foto_url && !fotoPreview && (
                                                <button type="button" onClick={clearFoto}
                                                    className="inline-flex items-center gap-1.5 px-3 py-2.5 text-sm font-medium text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition-colors"
                                                >
                                                    <Trash2 className="w-4 h-4" />
                                                    Hapus Foto
                                                </button>
                                            )}
                                        </div>
                                        {errors.foto && <p className="mt-1.5 text-sm text-red-600">{errors.foto}</p>}
                                        <p className="mt-1.5 text-xs text-gray-400">JPEG/PNG, maks 2MB. Kosongkan jika tidak ingin mengubah.</p>
                                    </div>
                                </div>
                            </div>
                        </SectionCard>

                        {/* Informasi Dasar */}
                        <SectionCard title="Informasi Dasar">
                            <div>
                                <label className="block text-sm font-medium text-gray-700">NIK <span className="text-red-500">*</span></label>
                                <input type="text" value={data.nik}
                                    onChange={e => setData('nik', e.target.value)} className={inputClass} />
                                {errors.nik && <p className="mt-1 text-sm text-red-600">{errors.nik}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">NIP / No Induk Guru</label>
                                <input type="text" value={data.nip} onChange={e => setData('nip', e.target.value)}
                                    className={inputClass} placeholder="NIP / No Induk (Opsional)" />
                                {errors.nip && <p className="mt-1 text-sm text-red-600">{errors.nip}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Nama Lengkap <span className="text-red-500">*</span></label>
                                <input type="text" value={data.nama_lengkap} onChange={e => setData('nama_lengkap', e.target.value)} className={inputClass} />
                                {errors.nama_lengkap && <p className="mt-1 text-sm text-red-600">{errors.nama_lengkap}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Email Login Mobile <span className="text-red-500">*</span></label>
                                <input type="email" value={data.email} onChange={e => setData('email', e.target.value)}
                                    autoComplete="email" className={inputClass} />
                                {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                                <p className="mt-1 text-xs text-gray-400">Email ini dipakai untuk login ke portal mobile.</p>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Tempat Lahir <span className="text-red-500">*</span></label>
                                <input type="text" value={data.tempat_lahir} onChange={e => setData('tempat_lahir', e.target.value)} className={inputClass} />
                                {errors.tempat_lahir && <p className="mt-1 text-sm text-red-600">{errors.tempat_lahir}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Tanggal Lahir <span className="text-red-500">*</span></label>
                                <input type="date" value={data.tanggal_lahir} onChange={e => setData('tanggal_lahir', e.target.value)} className={inputClass} />
                                {errors.tanggal_lahir && <p className="mt-1 text-sm text-red-600">{errors.tanggal_lahir}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Jenis Kelamin <span className="text-red-500">*</span></label>
                                <select value={data.jenis_kelamin} onChange={e => setData('jenis_kelamin', e.target.value)} className={selectClass}>
                                    <option value="">Pilih Jenis Kelamin</option>
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                                {errors.jenis_kelamin && <p className="mt-1 text-sm text-red-600">{errors.jenis_kelamin}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Agama <span className="text-red-500">*</span></label>
                                <select value={data.agama} onChange={e => setData('agama', e.target.value)} className={selectClass}>
                                    <option value="">Pilih Agama</option>
                                    <option value="Islam">Islam</option>
                                    <option value="Kristen">Kristen</option>
                                    <option value="Katolik">Katolik</option>
                                    <option value="Hindu">Hindu</option>
                                    <option value="Buddha">Buddha</option>
                                    <option value="Konghucu">Konghucu</option>
                                </select>
                                {errors.agama && <p className="mt-1 text-sm text-red-600">{errors.agama}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Status Pernikahan <span className="text-red-500">*</span></label>
                                <select value={data.status_pernikahan} onChange={e => setData('status_pernikahan', e.target.value)} className={selectClass}>
                                    <option value="">Pilih Status</option>
                                    <option value="Belum Menikah">Belum Menikah</option>
                                    <option value="Menikah">Menikah</option>
                                    <option value="Cerai Hidup">Cerai Hidup</option>
                                    <option value="Cerai Mati">Cerai Mati</option>
                                </select>
                                {errors.status_pernikahan && <p className="mt-1 text-sm text-red-600">{errors.status_pernikahan}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">No. HP <span className="text-red-500">*</span></label>
                                <input type="text" value={data.no_hp} onChange={e => setData('no_hp', e.target.value)} className={inputClass} />
                                {errors.no_hp && <p className="mt-1 text-sm text-red-600">{errors.no_hp}</p>}
                            </div>
                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-700">Alamat KTP <span className="text-red-500">*</span></label>
                                <textarea value={data.alamat_ktp} onChange={e => setData('alamat_ktp', e.target.value)}
                                    rows={3} className={inputClass} />
                                {errors.alamat_ktp && <p className="mt-1 text-sm text-red-600">{errors.alamat_ktp}</p>}
                            </div>
                        </SectionCard>

                        {/* Status Kepegawaian */}
                        <SectionCard title="Status Kepegawaian">
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Status Kepegawaian</label>
                                <select value={data.status_kepegawaian} onChange={e => setData('status_kepegawaian', e.target.value)} className={selectClass}>
                                    <option value="tetap">Tetap</option>
                                    <option value="kontrak">Kontrak</option>
                                    <option value="honorer">Honorer</option>
                                    <option value="gtt">GTT (Guru Tidak Tetap)</option>
                                </select>
                                {errors.status_kepegawaian && <p className="mt-1 text-sm text-red-600">{errors.status_kepegawaian}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Wajib Masuk Kantor</label>
                                <label className="mt-1.5 inline-flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" checked={data.wajib_kantor}
                                        onChange={(e) => setData('wajib_kantor', e.target.checked)}
                                        className="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary" />
                                    <div>
                                        <span className="text-sm font-medium text-gray-700">Aktifkan</span>
                                        <p className="text-xs text-gray-400">Jika tidak ada jadwal mengajar, tetap harus absen kantor.</p>
                                    </div>
                                </label>
                                {errors.wajib_kantor && <p className="mt-1 text-sm text-red-600">{errors.wajib_kantor}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Jatah Cuti Tahunan (Hari)</label>
                                <input type="number" min="0" value={data.jatah_cuti_tahunan}
                                    onChange={e => setData('jatah_cuti_tahunan', e.target.value)} className={inputClass} />
                                {errors.jatah_cuti_tahunan && <p className="mt-1 text-sm text-red-600">{errors.jatah_cuti_tahunan}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Status Aktif</label>
                                <select value={data.status_aktif} onChange={e => setData('status_aktif', e.target.value)} className={selectClass}>
                                    <option value="aktif">Aktif</option>
                                    <option value="cuti">Cuti</option>
                                    <option value="nonaktif">Nonaktif</option>
                                    <option value="resign">Resign</option>
                                </select>
                                {errors.status_aktif && <p className="mt-1 text-sm text-red-600">{errors.status_aktif}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Tanggal Mulai Kerja <span className="text-red-500">*</span></label>
                                <input type="date" value={data.tanggal_mulai_kerja}
                                    onChange={e => setData('tanggal_mulai_kerja', e.target.value)} className={inputClass} />
                                {errors.tanggal_mulai_kerja && <p className="mt-1 text-sm text-red-600">{errors.tanggal_mulai_kerja}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Pendidikan Terakhir <span className="text-red-500">*</span></label>
                                <input type="text" value={data.pendidikan_terakhir}
                                    onChange={e => setData('pendidikan_terakhir', e.target.value)}
                                    className={inputClass} placeholder="Contoh: S1 Pendidikan Agama Islam" />
                                {errors.pendidikan_terakhir && <p className="mt-1 text-sm text-red-600">{errors.pendidikan_terakhir}</p>}
                            </div>
                        </SectionCard>

                        {/* Penugasan Unit & Jabatan */}
                        <SectionCard title="Penugasan Unit & Jabatan" description="Tentukan unit tempat pegawai bertugas beserta jabatannya. Satu unit dapat ditandai Primary.">
                            <div className="md:col-span-2 space-y-3">
                                {data.units.map((u, i) => (
                                    <div key={i} className="flex flex-col sm:flex-row gap-3 items-start sm:items-center bg-gray-50 rounded-lg p-4 border border-gray-100">
                                        <div className="flex-1 w-full sm:w-auto">
                                            <label className="text-xs font-medium text-gray-500 sm:hidden mb-1 block">Unit</label>
                                            <select value={u.unit_sekolah_id}
                                                onChange={(e) => updateUnit(i, 'unit_sekolah_id', e.target.value)}
                                                className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm">
                                                <option value="">Pilih Unit</option>
                                                {unitSekolahs.map((us) => (
                                                    <option key={us.id} value={us.id}>{us.nama}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="flex-1 w-full sm:w-auto">
                                            <label className="text-xs font-medium text-gray-500 sm:hidden mb-1 block">Jabatan</label>
                                            <select value={u.jabatan_id}
                                                onChange={(e) => updateUnit(i, 'jabatan_id', e.target.value)}
                                                className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm">
                                                <option value="">Pilih Jabatan</option>
                                                {jabatans.map((j) => (
                                                    <option key={j.id} value={j.id}>{j.nama}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <label className="inline-flex items-center gap-2 text-sm text-gray-700 shrink-0 pt-1 sm:pt-0">
                                            <input type="checkbox" checked={!!u.is_primary}
                                                onChange={(e) => updateUnit(i, 'is_primary', e.target.checked)}
                                                className="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary" />
                                            Primary
                                        </label>
                                        <button type="button" onClick={() => removeUnit(i)}
                                            className="text-red-500 hover:text-red-700 shrink-0 p-1.5 rounded-lg hover:bg-red-50 transition-colors">
                                            <Trash2 className="w-4 h-4" />
                                        </button>
                                    </div>
                                ))}
                                {data.units.length === 0 && <p className="text-sm text-gray-400 text-center py-6 border-2 border-dashed border-gray-200 rounded-lg">Belum ada penugasan unit.</p>}
                                <button type="button" onClick={addUnit}
                                    className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:text-primary-dark transition-colors">
                                    <Plus className="w-4 h-4" /> Tambah Unit
                                </button>
                            </div>
                        </SectionCard>

                        {/* Mata Pelajaran */}
                        <SectionCard title="Mata Pelajaran (Guru)" description="Untuk guru: tentukan mata pelajaran yang diampu beserta unitnya. Baris yang tidak lengkap akan diabaikan.">
                            <div className="md:col-span-2 space-y-3">
                                {data.mapels.map((m, i) => (
                                    <div key={i} className="flex flex-col sm:flex-row gap-3 items-start sm:items-center bg-gray-50 rounded-lg p-4 border border-gray-100">
                                        <div className="flex-1 w-full sm:w-auto">
                                            <label className="text-xs font-medium text-gray-500 sm:hidden mb-1 block">Mata Pelajaran</label>
                                            <select value={m.mata_pelajaran_id}
                                                onChange={(e) => updateMapel(i, 'mata_pelajaran_id', e.target.value)}
                                                className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm">
                                                <option value="">Pilih Mata Pelajaran</option>
                                                {mapels.map((mp) => (
                                                    <option key={mp.id} value={mp.id}>{mp.nama}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="flex-1 w-full sm:w-auto">
                                            <label className="text-xs font-medium text-gray-500 sm:hidden mb-1 block">Unit</label>
                                            <select value={m.unit_sekolah_id}
                                                onChange={(e) => updateMapel(i, 'unit_sekolah_id', e.target.value)}
                                                className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm">
                                                <option value="">Pilih Unit</option>
                                                {unitSekolahs.map((us) => (
                                                    <option key={us.id} value={us.id}>{us.nama}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <button type="button" onClick={() => removeMapel(i)}
                                            className="text-red-500 hover:text-red-700 shrink-0 p-1.5 rounded-lg hover:bg-red-50 transition-colors self-end sm:self-center">
                                            <Trash2 className="w-4 h-4" />
                                        </button>
                                    </div>
                                ))}
                                {data.mapels.length === 0 && <p className="text-sm text-gray-400 text-center py-6 border-2 border-dashed border-gray-200 rounded-lg">Bukan guru / belum ada mata pelajaran.</p>}
                                <button type="button" onClick={addMapel}
                                    className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:text-primary-dark transition-colors">
                                    <Plus className="w-4 h-4" /> Tambah Mata Pelajaran
                                </button>
                            </div>
                        </SectionCard>

                        {/* Actions */}
                        <div className="flex items-center justify-end gap-4 bg-white rounded-xl border border-gray-200 shadow-sm px-6 py-4">
                            <Link href={route('pegawai.show', pegawai.id)}
                                className="text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">
                                Batal
                            </Link>
                            <button type="submit" disabled={processing}
                                className="bg-primary hover:bg-primary-dark text-white font-semibold py-2.5 px-8 rounded-lg shadow-md hover:shadow-lg transition-all duration-200 disabled:opacity-50">
                                {processing ? 'Menyimpan...' : 'Perbarui Pegawai'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
