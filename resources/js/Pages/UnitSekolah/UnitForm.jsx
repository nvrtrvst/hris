import React from 'react';
import LeafletPicker from '@/Components/LeafletPicker';

export default function UnitForm({ data, setData, errors, processing, onSubmit, isEdit, unitName, unitLogoUrl }) {
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

                <div className="md:col-span-2">
                    <label className="block text-sm font-medium text-gray-700">Logo Unit</label>
                    <div className="mt-2 flex items-center gap-4">
                        {isEdit && data.logo === null && (
                            <div className="flex h-16 w-16 items-center justify-center overflow-hidden rounded-xl border border-gray-200 bg-white">
                                {unitLogoUrl ? (
                                    <img src={unitLogoUrl} alt={`Logo ${unitName}`} width="64" height="64" className="h-full w-full object-contain p-1" />
                                ) : unitName ? (
                                    <span className="text-sm font-bold text-primary">{unitName.slice(0, 3).toUpperCase()}</span>
                                ) : null}
                            </div>
                        )}
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            onChange={(e) => setData('logo', e.target.files[0] || null)}
                            className="block w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-emerald-100"
                        />
                    </div>
                    <p className="mt-1 text-xs text-gray-500">JPEG, PNG, atau WebP. Maksimum 1 MB. Rasio persegi disarankan.</p>
                    {errors.logo && <p className="mt-1 text-xs text-red-600">{errors.logo}</p>}
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
                            max="10000"
                            value={data.radius_meter}
                            onChange={(e) => setData('radius_meter', e.target.value)}
                            className="flex-1 accent-[#0F3D3E]"
                        />
                        <input
                            type="number"
                            min="10"
                            value={data.radius_meter}
                            onChange={(e) => setData('radius_meter', e.target.value)}
                            max="100000"
                            className="w-28 rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm"
                        />
                    </div>
                    <p className="mt-1 text-xs text-gray-500">
                        Pegawai hanya bisa absen jika jarak GPS mereka ≤ radius ini dari titik pusat.
                    </p>
                    {errors.radius_meter && <p className="mt-1 text-xs text-red-600">{errors.radius_meter}</p>}
                </div>

                <div className="mt-4 max-w-xs">
                    <label className="block text-xs font-semibold text-gray-600">
                        Jam Masuk Kantor <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="time"
                        value={data.jam_masuk_kantor}
                        onChange={(e) => setData('jam_masuk_kantor', e.target.value)}
                        className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm"
                    />
                    <p className="mt-1 text-xs text-gray-500">Dipakai untuk status hadir/telat pegawai tetap tanpa jadwal mengajar.</p>
                    {errors.jam_masuk_kantor && <p className="mt-1 text-xs text-red-600">{errors.jam_masuk_kantor}</p>}
                </div>

                <div className="mt-4 max-w-xs">
                    <label className="block text-xs font-semibold text-gray-600">
                        Jam Pulang Kantor
                    </label>
                    <input
                        type="time"
                        value={data.jam_pulang_kantor}
                        onChange={(e) => setData('jam_pulang_kantor', e.target.value)}
                        className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm"
                    />
                    <p className="mt-1 text-xs text-gray-500">Dipakai deteksi pulang awal pegawai mode kantor. Kosongkan jika tidak berlaku.</p>
                    {errors.jam_pulang_kantor && <p className="mt-1 text-xs text-red-600">{errors.jam_pulang_kantor}</p>}
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
