import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

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
                                <div>
                                    <h3 className="text-lg font-bold text-gray-900 border-b pb-2 mb-4">Foto Pegawai</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
{pegawai.foto_url && (
                                             <div>
                                                 <p className="text-sm font-medium text-gray-700 mb-2">Foto Saat Ini:</p>
                                                 <img src={pegawai.foto_url} alt="Foto Pegawai" className="w-32 h-32 object-cover rounded-md shadow-sm border" />
                                            </div>
                                        )}
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Upload Foto Baru (Opsional)</label>
                                            <input type="file" onChange={e => setData('foto', e.target.files[0])} className="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" accept="image/jpeg, image/png, image/jpg" />
                                            <p className="text-xs text-gray-500 mt-1">Kosongkan jika tidak ingin mengubah foto.</p>
                                            {errors.foto && <p className="mt-1 text-sm text-red-600">{errors.foto}</p>}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h3 className="text-lg font-bold text-gray-900 border-b pb-2 mb-4 mt-8">Informasi Dasar</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                        <label className="block text-sm font-medium text-gray-700">NIK <span className="text-red-500">*</span></label>
                                        <input type="text" value={data.nik_plain ?? data.nik ?? ''} onChange={e => setData('nik', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                        {errors.nik && <p className="mt-1 text-sm text-red-600">{errors.nik}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">NIP / No Induk Guru</label>
                                            <input type="text" value={data.nip} onChange={e => setData('nip', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="NIP / No Induk (Opsional)" />
                                            {errors.nip && <p className="mt-1 text-sm text-red-600">{errors.nip}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Nama Lengkap <span className="text-red-500">*</span></label>
                                            <input type="text" value={data.nama_lengkap} onChange={e => setData('nama_lengkap', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.nama_lengkap && <p className="mt-1 text-sm text-red-600">{errors.nama_lengkap}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Email Login Mobile <span className="text-red-500">*</span></label>
                                            <input type="email" value={data.email} onChange={e => setData('email', e.target.value)} autoComplete="email" className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            <p className="mt-1 text-xs text-gray-500">Email ini dipakai untuk login ke portal mobile.</p>
                                            {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Tempat Lahir <span className="text-red-500">*</span></label>
                                            <input type="text" value={data.tempat_lahir} onChange={e => setData('tempat_lahir', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.tempat_lahir && <p className="mt-1 text-sm text-red-600">{errors.tempat_lahir}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Tanggal Lahir <span className="text-red-500">*</span></label>
                                            <input type="date" value={data.tanggal_lahir} onChange={e => setData('tanggal_lahir', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.tanggal_lahir && <p className="mt-1 text-sm text-red-600">{errors.tanggal_lahir}</p>}
                                        </div>
                                    </div>
                                    
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Jenis Kelamin <span className="text-red-500">*</span></label>
                                            <select value={data.jenis_kelamin} onChange={e => setData('jenis_kelamin', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="">Pilih Jenis Kelamin</option>
                                                <option value="L">Laki-laki</option>
                                                <option value="P">Perempuan</option>
                                            </select>
                                            {errors.jenis_kelamin && <p className="mt-1 text-sm text-red-600">{errors.jenis_kelamin}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Agama <span className="text-red-500">*</span></label>
                                            <select value={data.agama} onChange={e => setData('agama', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
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
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Status Pernikahan <span className="text-red-500">*</span></label>
                                            <select value={data.status_pernikahan} onChange={e => setData('status_pernikahan', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
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
                                            <input type="text" value={data.no_hp} onChange={e => setData('no_hp', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.no_hp && <p className="mt-1 text-sm text-red-600">{errors.no_hp}</p>}
                                        </div>
                                    </div>

                                    <div className="mt-4">
                                        <label className="block text-sm font-medium text-gray-700">Alamat KTP <span className="text-red-500">*</span></label>
                                        <textarea value={data.alamat_ktp} onChange={e => setData('alamat_ktp', e.target.value)} rows="3" className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                                        {errors.alamat_ktp && <p className="mt-1 text-sm text-red-600">{errors.alamat_ktp}</p>}
                                    </div>
                                </div>

                                <div>
                                    <h3 className="text-lg font-bold text-gray-900 border-b pb-2 mb-4 mt-8">Status Kepegawaian</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Status Kepegawaian</label>
                                            <select value={data.status_kepegawaian} onChange={e => setData('status_kepegawaian', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="tetap">Tetap</option>
                                                <option value="kontrak">Kontrak</option>
                                                <option value="honorer">Honorer</option>
                                                <option value="gtt">GTT (Guru Tidak Tetap)</option>
                                            </select>
                                            {errors.status_kepegawaian && <p className="mt-1 text-sm text-red-600">{errors.status_kepegawaian}</p>}
                                        </div>
                                        <div>
                                            <label className="inline-flex items-center gap-2 mt-6 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    checked={data.wajib_kantor}
                                                    onChange={(e) => setData('wajib_kantor', e.target.checked)}
                                                    className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                                <span className="text-sm font-medium text-gray-700">Wajib masuk kantor</span>
                                            </label>
                                            <p className="text-xs text-gray-500 mt-0.5">Jika tidak ada jadwal mengajar, tetap harus absen kantor.</p>
                                            {errors.wajib_kantor && <p className="mt-1 text-sm text-red-600">{errors.wajib_kantor}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Jatah Cuti Tahunan (Hari)</label>
                                            <input type="number" min="0" value={data.jatah_cuti_tahunan} onChange={e => setData('jatah_cuti_tahunan', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.jatah_cuti_tahunan && <p className="mt-1 text-sm text-red-600">{errors.jatah_cuti_tahunan}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Status Aktif</label>
                                            <select value={data.status_aktif} onChange={e => setData('status_aktif', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="aktif">Aktif</option>
                                                <option value="cuti">Cuti</option>
                                                <option value="nonaktif">Nonaktif</option>
                                                <option value="resign">Resign</option>
                                            </select>
                                            {errors.status_aktif && <p className="mt-1 text-sm text-red-600">{errors.status_aktif}</p>}
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Tanggal Mulai Kerja <span className="text-red-500">*</span></label>
                                            <input type="date" value={data.tanggal_mulai_kerja} onChange={e => setData('tanggal_mulai_kerja', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.tanggal_mulai_kerja && <p className="mt-1 text-sm text-red-600">{errors.tanggal_mulai_kerja}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Pendidikan Terakhir <span className="text-red-500">*</span></label>
                                            <input type="text" value={data.pendidikan_terakhir} onChange={e => setData('pendidikan_terakhir', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Contoh: S1 Pendidikan Agama Islam" />
                                            {errors.pendidikan_terakhir && <p className="mt-1 text-sm text-red-600">{errors.pendidikan_terakhir}</p>}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h3 className="text-lg font-bold text-gray-900 border-b pb-2 mb-4 mt-8">Penugasan Unit &amp; Jabatan</h3>
                                    <p className="text-xs text-gray-500 mb-3">Tentukan unit tempat pegawai bertugas beserta jabatannya. Satu unit dapat ditandai <b>Primary</b>.</p>
                                    <div className="space-y-3">
                                        {data.units.map((u, i) => (
                                            <div key={i} className="grid grid-cols-1 md:grid-cols-12 gap-3 items-center bg-gray-50 p-3 rounded-lg">
                                                <div className="md:col-span-5">
                                                    <select value={u.unit_sekolah_id} onChange={(e) => updateUnit(i, 'unit_sekolah_id', e.target.value)} className="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                        <option value="">Pilih Unit</option>
                                                        {unitSekolahs.map((us) => (
                                                            <option key={us.id} value={us.id}>{us.nama}</option>
                                                        ))}
                                                    </select>
                                                </div>
                                                <div className="md:col-span-4">
                                                    <select value={u.jabatan_id} onChange={(e) => updateUnit(i, 'jabatan_id', e.target.value)} className="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                        <option value="">Pilih Jabatan</option>
                                                        {jabatans.map((j) => (
                                                            <option key={j.id} value={j.id}>{j.nama}</option>
                                                        ))}
                                                    </select>
                                                </div>
                                                <div className="md:col-span-2 flex items-center">
                                                    <label className="inline-flex items-center text-sm text-gray-700">
                                                        <input type="checkbox" checked={!!u.is_primary} onChange={(e) => updateUnit(i, 'is_primary', e.target.checked)} className="mr-2 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                                        Primary
                                                    </label>
                                                </div>
                                                <div className="md:col-span-1 text-right">
                                                    <button type="button" onClick={() => removeUnit(i)} className="text-red-500 hover:text-red-700 text-sm font-medium">Hapus</button>
                                                </div>
                                            </div>
                                        ))}
                                        {data.units.length === 0 && <p className="text-sm text-gray-500">Belum ada penugasan unit.</p>}
                                    </div>
                                    <button type="button" onClick={addUnit} className="mt-3 inline-flex items-center text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                                        + Tambah Unit
                                    </button>
                                </div>

                                <div>
                                    <h3 className="text-lg font-bold text-gray-900 border-b pb-2 mb-4 mt-8">Mata Pelajaran (Guru)</h3>
                                    <p className="text-xs text-gray-500 mb-3">Untuk guru: tentukan mata pelajaran yang diampu beserta unitnya. Baris yang tidak lengkap akan diabaikan.</p>
                                    <div className="space-y-3">
                                        {data.mapels.map((m, i) => (
                                            <div key={i} className="grid grid-cols-1 md:grid-cols-12 gap-3 items-center bg-gray-50 p-3 rounded-lg">
                                                <div className="md:col-span-5">
                                                    <select value={m.mata_pelajaran_id} onChange={(e) => updateMapel(i, 'mata_pelajaran_id', e.target.value)} className="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                        <option value="">Pilih Mata Pelajaran</option>
                                                        {mapels.map((mp) => (
                                                            <option key={mp.id} value={mp.id}>{mp.nama}</option>
                                                        ))}
                                                    </select>
                                                </div>
                                                <div className="md:col-span-5">
                                                    <select value={m.unit_sekolah_id} onChange={(e) => updateMapel(i, 'unit_sekolah_id', e.target.value)} className="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
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
                                    </div>
                                    <button type="button" onClick={addMapel} className="mt-3 inline-flex items-center text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                                        + Tambah Mata Pelajaran
                                    </button>
                                </div>

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
