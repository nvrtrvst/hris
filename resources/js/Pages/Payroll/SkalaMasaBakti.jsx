import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';

export default function SkalaMasaBakti({ auth, skalas }) {
    const { data, setData, post, reset, processing, errors, clearErrors } = useForm({
        masa_kerja_tahun: '',
        nominal_gaji: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('skala-masa-bakti.store'), {
            onSuccess: () => {
                reset();
                clearErrors();
            }
        });
    };

    const formatRupiah = (angka) => {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-2xl text-gray-800 leading-tight">Pengaturan Skala Gaji Masa Bakti</h2>}
        >
            <Head title="Skala Gaji Masa Bakti" />

            <div className="py-12 bg-gray-50 min-h-screen">
                <div className="max-w-[1400px] mx-auto sm:px-6 lg:px-8 flex flex-col lg:flex-row gap-8">
                    
                    {/* Form Section */}
                    <div className="w-full lg:w-1/3">
                        <div className="bg-white overflow-hidden shadow-xl sm:rounded-2xl border border-gray-100 p-6 sticky top-6">
                            <h3 className="text-lg font-bold text-gray-900 mb-6 border-b pb-2">Tambah Skala</h3>
                            
                            <form onSubmit={submit} className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Masa Kerja (Tahun)</label>
                                    <input type="number" value={data.masa_kerja_tahun} onChange={e => setData('masa_kerja_tahun', e.target.value)} placeholder="Contoh: 1" className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                    {errors.masa_kerja_tahun && <p className="mt-1 text-sm text-red-600">{errors.masa_kerja_tahun}</p>}
                                </div>
                                
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Nominal Gaji Pokok</label>
                                    <input type="number" step="0.01" value={data.nominal_gaji} onChange={e => setData('nominal_gaji', e.target.value)} placeholder="Contoh: 2000000" className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                    {errors.nominal_gaji && <p className="mt-1 text-sm text-red-600">{errors.nominal_gaji}</p>}
                                </div>

                                <div className="pt-4 flex justify-end space-x-3">
                                    <button type="submit" disabled={processing} className="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm font-medium disabled:opacity-50">
                                        {processing ? 'Menyimpan...' : 'Simpan Skala'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {/* Table Section */}
                    <div className="w-full lg:w-2/3">
                        <div className="bg-white overflow-hidden shadow-xl sm:rounded-2xl border border-gray-100">
                            <div className="px-6 py-5 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                                <h3 className="text-lg font-bold text-gray-900">Daftar Skala Masa Bakti</h3>
                            </div>
                            <div className="p-0 overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Masa Kerja (Tahun)</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nominal Gaji Pokok</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {skalas.length > 0 ? skalas.map((s) => (
                                            <tr key={s.id} className="hover:bg-gray-50 group">
                                                <td className="px-6 py-4">
                                                    <div className="text-sm font-bold text-gray-900">{s.masa_kerja_tahun} Tahun</div>
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-900 font-medium">
                                                    {formatRupiah(s.nominal_gaji)}
                                                </td>
                                                <td className="px-6 py-4 text-right text-sm font-medium">
                                                    <button 
                                                        onClick={() => {
                                                            if(confirm('Hapus skala ini?')) {
                                                                router.delete(route('skala-masa-bakti.destroy', s.id));
                                                            }
                                                        }} 
                                                        className="text-red-600 hover:text-red-900"
                                                    >
                                                        Hapus
                                                    </button>
                                                </td>
                                            </tr>
                                        )) : (
                                            <tr>
                                                <td colSpan="3" className="px-6 py-4 text-center text-sm text-gray-500">Belum ada skala yang ditambahkan.</td>
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
