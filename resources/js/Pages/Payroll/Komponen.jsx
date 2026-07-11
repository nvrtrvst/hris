import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router, Link } from '@inertiajs/react';
import { formatRupiah } from '@/Utils/format';

export default function Komponen({ auth, komponens, units }) {
    const [isEditing, setIsEditing] = useState(false);
    const [editId, setEditId] = useState(null);

    const { data, setData, post, put, reset, processing, errors, clearErrors } = useForm({
        nama: '',
        kode: '',
        tipe: 'pendapatan',
        jenis: 'fixed',
        nilai_default: '',
        unit_sekolah_id: '',
        is_taxable: true,
        is_active: true,
        urutan: 99,
        tampil_di_matrix: true,
    });

    const handleEdit = (k) => {
        setIsEditing(true);
        setEditId(k.id);
        setData({
            nama: k.nama,
            kode: k.kode || '',
            tipe: k.tipe,
            jenis: k.jenis,
            nilai_default: k.nilai_default || '',
            unit_sekolah_id: k.unit_sekolah_id || '',
            is_taxable: k.is_taxable == 1,
            is_active: k.is_active == 1,
            urutan: k.urutan || 99,
            tampil_di_matrix: k.tampil_di_matrix == 1,
        });
        clearErrors();
    };

    const handleCancel = () => {
        setIsEditing(false);
        setEditId(null);
        reset();
        clearErrors();
    };

    const submit = (e) => {
        e.preventDefault();
        if (isEditing) {
            put(route('komponen-gaji.update', editId), {
                onSuccess: () => handleCancel()
            });
        } else {
            post(route('komponen-gaji.store'), {
                onSuccess: () => handleCancel()
            });
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-2xl text-gray-800 leading-tight">Pengaturan Komponen Gaji</h2>}
        >
            <Head title="Komponen Gaji" />

            <div className="py-12 bg-gray-50 min-h-screen">
                <div className="max-w-[1400px] mx-auto sm:px-6 lg:px-8 flex flex-col lg:flex-row gap-8">
                    
                    {/* Form Section */}
                    <div className="w-full lg:w-1/3">
                        <div className="bg-white overflow-hidden shadow-xl sm:rounded-2xl border border-gray-100 p-6 sticky top-6">
                            <h3 className="text-lg font-bold text-gray-900 mb-6 border-b pb-2">{isEditing ? 'Edit Komponen' : 'Tambah Komponen'}</h3>
                            
                            <form onSubmit={submit} className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Nama Komponen</label>
                                    <input type="text" value={data.nama} onChange={e => setData('nama', e.target.value)} placeholder="Contoh: Gaji Pokok / PPh21" className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                    {errors.nama && <p className="mt-1 text-sm text-red-600">{errors.nama}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Kode (stabil untuk logika)</label>
                                    <input type="text" value={data.kode} onChange={e => setData('kode', e.target.value)} placeholder="Contoh: gaji_pokok, kehadiran_telat, kehadiran_alpa, tunjangan_kehadiran" className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                    <p className="text-xs text-gray-500 mt-1">Isi agar payroll tidak bergantung pada nama. Kosong = pakai pencocokan nama (legacy).</p>
                                    {errors.kode && <p className="mt-1 text-sm text-red-600">{errors.kode}</p>}
                                </div>
                                
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Tipe</label>
                                    <select value={data.tipe} onChange={e => setData('tipe', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="pendapatan">Pendapatan (+)</option>
                                        <option value="potongan">Potongan (-)</option>
                                    </select>
                                    {errors.tipe && <p className="mt-1 text-sm text-red-600">{errors.tipe}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Jenis Perhitungan</label>
                                    <select value={data.jenis} onChange={e => setData('jenis', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="fixed">Fixed (Nominal Pasti)</option>
                                        <option value="persentase">Persentase (dari Gaji Pokok)</option>
                                        <option value="dinamis_kehadiran">Dinamis Kehadiran (Uang Makan / Telat)</option>
                                        <option value="dinamis_jam_mengajar">Dinamis Jam Mengajar (Honor JTM)</option>
                                        <option value="dinamis_masa_bakti">Dinamis Masa Bakti (Otomatis dari Skala)</option>
                                    </select>
                                    {errors.jenis && <p className="mt-1 text-sm text-red-600">{errors.jenis}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Nilai Default (Rp / %)</label>
                                    <input type="number" step="0.01" value={data.nilai_default} onChange={e => setData('nilai_default', e.target.value)} placeholder="Contoh: 5000000 atau 5 untuk 5%" className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                    <p className="text-xs text-gray-500 mt-1">Biarkan kosong jika nilai diatur spesifik per pegawai.</p>
                                    {errors.nilai_default && <p className="mt-1 text-sm text-red-600">{errors.nilai_default}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Unit (khusus Honor Jam Mengajar)</label>
                                    <select value={data.unit_sekolah_id} onChange={e => setData('unit_sekolah_id', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="">Semua Unit</option>
                                        {units && units.map(u => (
                                            <option key={u.id} value={u.id}>{u.nama}</option>
                                        ))}
                                    </select>
                                    <p className="text-xs text-gray-500 mt-1">Isi bila komponen hanya berlaku untuk unit tertentu (mis. Honor Mengajar unit TK).</p>
                                    {errors.unit_sekolah_id && <p className="mt-1 text-sm text-red-600">{errors.unit_sekolah_id}</p>}
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Urutan Matrix</label>
                                        <input type="number" value={data.urutan} onChange={e => setData('urutan', e.target.value)} placeholder="Contoh: 1" className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                        <p className="text-[10px] text-gray-500 mt-1">Gaji Pokok biasanya urutan 1</p>
                                    </div>
                                    <div className="flex items-end">
                                        <label className="flex items-center pb-2">
                                            <input type="checkbox" checked={data.tampil_di_matrix} onChange={e => setData('tampil_di_matrix', e.target.checked)} className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                            <span className="ml-2 text-sm text-gray-600">Tampil di Matrix</span>
                                        </label>
                                    </div>
                                </div>

                                <div className="flex items-center space-x-4">
                                    <label className="flex items-center">
                                        <input type="checkbox" checked={data.is_taxable} onChange={e => setData('is_taxable', e.target.checked)} className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                        <span className="ml-2 text-sm text-gray-600">Taxable (Kena PPh21)</span>
                                    </label>
                                    <label className="flex items-center">
                                        <input type="checkbox" checked={data.is_active} onChange={e => setData('is_active', e.target.checked)} className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                        <span className="ml-2 text-sm text-gray-600">Aktif</span>
                                    </label>
                                </div>

                                <div className="pt-4 flex justify-end space-x-3">
                                    {isEditing && (
                                        <button type="button" onClick={handleCancel} className="bg-white text-gray-700 px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50 text-sm font-medium">Batal</button>
                                    )}
                                    <button type="submit" disabled={processing} className="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm font-medium disabled:opacity-50">
                                        {processing ? 'Menyimpan...' : (isEditing ? 'Update Komponen' : 'Simpan Komponen')}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {/* Table Section */}
                    <div className="w-full lg:w-2/3">
                        <div className="bg-white overflow-hidden shadow-xl sm:rounded-2xl border border-gray-100">
                            <div className="px-6 py-5 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                                <h3 className="text-lg font-bold text-gray-900">Daftar Komponen (Master Data)</h3>
                                <Link 
                                    href={route('komponen-gaji.matrix')}
                                    className="bg-green-100 text-green-700 hover:bg-green-200 font-bold px-4 py-2 rounded-lg border border-green-300 transition-colors flex items-center text-sm shadow-sm"
                                >
                                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                    Buka Matrix Gaji
                                </Link>
                            </div>
                            <div className="p-0 overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nilai Default</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {komponens.length > 0 ? komponens.map((k) => (
                                            <tr key={k.id} className="hover:bg-gray-50 group">
                                                <td className="px-6 py-4">
                                                    <div className="text-sm font-bold text-gray-900">{k.nama}</div>
                                                    <div className="flex flex-wrap gap-1 mt-1">
                                                        {k.kode && <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-700">{k.kode}</span>}
                                                        {k.unit_sekolah_id && <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-blue-100 text-blue-800">Unit</span>}
                                                        {k.is_taxable == 1 && <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-purple-100 text-purple-800">Taxable</span>}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${k.tipe === 'pendapatan' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                                        {k.tipe === 'pendapatan' ? '+' : '-'} {k.tipe}
                                                    </span>
                                                    <div className="text-xs text-gray-500 mt-1 uppercase">{k.jenis.replace('_', ' ')}</div>
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-500 font-medium">
                                                    {k.nilai_default ? (k.jenis === 'persentase' ? `${k.nilai_default}%` : formatRupiah(k.nilai_default)) : '-'}
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="flex flex-col gap-1">
                                                        <span className={`px-2 w-max inline-flex text-xs leading-5 font-semibold rounded-full ${k.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                                                            {k.is_active ? 'Aktif' : 'Nonaktif'}
                                                        </span>
                                                        <span className={`px-2 w-max inline-flex text-xs leading-5 font-semibold rounded-full ${k.tampil_di_matrix ? 'bg-indigo-100 text-indigo-800' : 'bg-orange-100 text-orange-800'}`}>
                                                            {k.tampil_di_matrix ? `Matrix (Urutan ${k.urutan})` : 'Sembunyi dari Matrix'}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-right text-sm font-medium">
                                                    <div className="flex justify-end gap-3 flex-wrap">
                                                    <Link 
                                                        href={route('komponen-gaji.pegawai.index', k.id)} 
                                                        className="text-blue-600 hover:text-blue-900 mr-3"
                                                        title="Atur nominal khusus per pegawai"
                                                    >
                                                        Atur Pegawai
                                                    </Link>
                                                    <button onClick={() => handleEdit(k)} className="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                                    <button 
                                                        onClick={() => {
                                                            if(confirm('Hapus komponen ini?')) {
                                                                router.delete(route('komponen-gaji.destroy', k.id));
                                                            }
                                                        }} 
                                                        className="text-red-600 hover:text-red-900"
                                                    >
                                                        Hapus
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        )) : (
                                            <tr>
                                                <td colSpan="5" className="px-6 py-4 text-center text-sm text-gray-500">Belum ada komponen gaji yang dikonfigurasi.</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
