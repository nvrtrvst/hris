import React from 'react';
import LeafletPicker from '@/Components/LeafletPicker';

export default function UnitForm({ data, setData, errors, processing, onSubmit, isEdit, unitName }) {
    const validLat = !isNaN(parseFloat(data.latitude));
    const validLng = !isNaN(parseFloat(data.longitude));
    const mapsUrl = validLat && validLng
        ? `https://www.google.com/maps?q=${data.latitude},${data.longitude}`
        : null;

    return (
        <form onSubmit={onSubmit} className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="md:col-span-2">
                    <label className="block text-sm font-medium text-gray-700">
                        Nama Unit <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        value={data.nama}
                        onChange={(e) => setData('nama', e.target.value)}
                        placeholder="cth. SMP Nunul Muttaqiin"
                        className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm"
                    />
                    {errors.nama && <p className="mt-1 text-sm text-red-600">{errors.nama}</p>}
                </div>

                <div className="md:col-span-2">
                    <label className="block text-sm font-medium text-gray-700">
                        Singkatan <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        value={data.singkatan}
                        onChange={(e) => setData('singkatan', e.target.value)}
                        placeholder="cth. SMP"
                        className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm"
                    />
                    {errors.singkatan && <p className="mt-1 text-sm text-red-600">{errors.singkatan}</p>}
                </div>
            </div>

            <div className="rounded-2xl border border-gray-200 bg-gray-50 p-5">
                <h4 className="text-sm font-extrabold text-gray-700 mb-3">Titik Pusat & Radius Geofence</h4>
                <LeafletPicker
                    lat={data.latitude}
                    lng={data.longitude}
                    radius={data.radius_meter}
                    onChange={(la, ln) => {
                        setData('latitude', la);
                        setData('longitude', ln);
                    }}
                />

                <div className="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label className="block text-xs font-semibold text-gray-600">Latitude</label>
                        <input
                            type="number"
                            step="any"
                            value={data.latitude}
                            onChange={(e) => setData('latitude', e.target.value)}
                            className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary font-mono sm:text-sm"
                        />
                        {errors.latitude && <p className="mt-1 text-xs text-red-600">{errors.latitude}</p>}
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-gray-600">Longitude</label>
                        <input
                            type="number"
                            step="any"
                            value={data.longitude}
                            onChange={(e) => setData('longitude', e.target.value)}
                            className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary font-mono sm:text-sm"
                        />
                        {errors.longitude && <p className="mt-1 text-xs text-red-600">{errors.longitude}</p>}
                    </div>
                </div>

                <div className="mt-4">
                    <div className="flex items-center justify-between">
                        <label className="block text-xs font-semibold text-gray-600">
                            Radius Toleransi Absen (Meter) <span className="text-red-500">*</span>
                        </label>
                        {mapsUrl && (
                            <a
                                href={mapsUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-xs font-bold text-primary hover:underline"
                            >
                                Buka di Google Maps ↗
                            </a>
                        )}
                    </div>
                    <div className="mt-1 flex items-center gap-3">
                        <input
                            type="range"
                            min="10"
                            max="500"
                            value={data.radius_meter}
                            onChange={(e) => setData('radius_meter', e.target.value)}
                            className="flex-1 accent-[#0F3D3E]"
                        />
                        <input
                            type="number"
                            min="10"
                            value={data.radius_meter}
                            onChange={(e) => setData('radius_meter', e.target.value)}
                            className="w-24 rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm"
                        />
                    </div>
                    <p className="mt-1 text-xs text-gray-500">
                        Pegawai hanya bisa absen jika jarak GPS mereka ≤ radius ini dari titik pusat.
                    </p>
                    {errors.radius_meter && <p className="mt-1 text-xs text-red-600">{errors.radius_meter}</p>}
                </div>
            </div>

            <div className="flex items-center justify-end mt-8 border-t pt-6">
                <a
                    href={route('unit-sekolah.index')}
                    className="text-gray-600 hover:text-gray-900 mr-6 font-medium"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    disabled={processing}
                    className="bg-primary hover:bg-[#0c2f30] text-white font-bold py-2.5 px-8 rounded-lg shadow-md transition-all duration-200 disabled:opacity-50"
                >
                    {processing ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Tambah Unit'}
                </button>
            </div>
        </form>
    );
}
