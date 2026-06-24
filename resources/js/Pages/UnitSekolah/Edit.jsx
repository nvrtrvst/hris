import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Edit({ auth, unit }) {
    const { data, setData, put, processing, errors } = useForm({
        nama: unit.nama,
        singkatan: unit.singkatan,
        latitude: unit.latitude,
        longitude: unit.longitude,
        radius_meter: unit.radius_meter,
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('unit-sekolah.update', unit.id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-2xl text-gray-800 leading-tight">Edit Lokasi Unit: {unit.nama}</h2>}
        >
            <Head title={`Edit Unit - ${unit.nama}`} />

            <div className="py-12 bg-gray-50 min-h-screen">
                <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-xl sm:rounded-2xl border border-gray-100">
                        <div className="p-8">
                            <form onSubmit={submit} className="space-y-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div className="md:col-span-2">
                                        <label className="block text-sm font-medium text-gray-700">Nama Unit <span className="text-red-500">*</span></label>
                                        <input type="text" value={data.nama} onChange={e => setData('nama', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                        {errors.nama && <p className="mt-1 text-sm text-red-600">{errors.nama}</p>}
                                    </div>
                                    
                                    <div className="md:col-span-2">
                                        <label className="block text-sm font-medium text-gray-700">Singkatan <span className="text-red-500">*</span></label>
                                        <input type="text" value={data.singkatan} onChange={e => setData('singkatan', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                        {errors.singkatan && <p className="mt-1 text-sm text-red-600">{errors.singkatan}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Latitude <span className="text-red-500">*</span></label>
                                        <input type="text" value={data.latitude} onChange={e => setData('latitude', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono sm:text-sm" />
                                        {errors.latitude && <p className="mt-1 text-sm text-red-600">{errors.latitude}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Longitude <span className="text-red-500">*</span></label>
                                        <input type="text" value={data.longitude} onChange={e => setData('longitude', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono sm:text-sm" />
                                        {errors.longitude && <p className="mt-1 text-sm text-red-600">{errors.longitude}</p>}
                                    </div>

                                    <div className="md:col-span-2">
                                        <label className="block text-sm font-medium text-gray-700">Radius Toleransi Absen (Meter) <span className="text-red-500">*</span></label>
                                        <input type="number" min="10" value={data.radius_meter} onChange={e => setData('radius_meter', e.target.value)} className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                        <p className="mt-1 text-xs text-gray-500">Pegawai hanya bisa absen jika jarak GPS mereka berada di dalam radius ini dari titik koordinat pusat.</p>
                                        {errors.radius_meter && <p className="mt-1 text-sm text-red-600">{errors.radius_meter}</p>}
                                    </div>
                                </div>

                                <div className="flex items-center justify-end mt-8 border-t pt-6">
                                    <Link href={route('unit-sekolah.index')} className="text-gray-600 hover:text-gray-900 mr-6 font-medium">Batal</Link>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-8 rounded-lg shadow-md hover:shadow-lg transition-all duration-200 disabled:opacity-50"
                                    >
                                        {processing ? 'Menyimpan...' : 'Simpan Perubahan'}
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
