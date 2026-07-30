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
                    <label className="form-label">
                        Nama Unit <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        value={data.nama}
                        onChange={(e) => setData('nama', e.target.value)}
                        placeholder="cth. SMP Nunul Muttaqiin"
                        className="input-field"
                    />
                    {errors.nama && <p className="form-error">{errors.nama}</p>}
                </div>

                <div className="md:col-span-2">
                    <label className="form-label">
                        Singkatan <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        value={data.singkatan}
                        onChange={(e) => setData('singkatan', e.target.value)}
                        placeholder="cth. SMP"
                        className="input-field"
                    />
                    {errors.singkatan && <p className="form-error">{errors.singkatan}</p>}
                </div>

                <div className="md:col-span-2">
                    <label className="form-label">Logo Unit</label>
                    <div className="mt-2 flex items-center gap-4">
                        {isEdit && data.logo === null && (
                            <div className="flex h-16 w-16 items-center justify-center overflow-hidden rounded-card border border-border bg-white">
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
                            className="block w-full text-sm text-text-muted file:mr-4 file:rounded-button file:border-0 file:bg-success-light file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-emerald-100"
                        />
                    </div>
                    <p className="form-hint">JPEG, PNG, atau WebP. Maksimum 1 MB. Rasio persegi disarankan.</p>
                    {errors.logo && <p className="form-error">{errors.logo}</p>}
                </div>
            </div>

            <div className="rounded-card border border-border bg-surface p-5">
                <h4 className="section-title text-sm font-extrabold text-text-secondary mb-3">Titik Pusat & Radius Geofence</h4>
                <LeafletPicker
                    lat={data.latitude}
                    lng={data.longitude}
                    radius={data.radius_meter}
                    onChange={(la, ln) => {
                        setData('latitude', la);
                        setData('longitude', ln);
                    }}
                />

                <div className="form-grid mt-4 gap-4">
                    <div>
                        <label className="form-label text-xs">Latitude</label>
                        <input
                            type="number"
                            step="any"
                            value={data.latitude}
                            onChange={(e) => setData('latitude', e.target.value)}
                            className="input-field font-mono"
                        />
                        {errors.latitude && <p className="form-error">{errors.latitude}</p>}
                    </div>
                    <div>
                        <label className="form-label text-xs">Longitude</label>
                        <input
                            type="number"
                            step="any"
                            value={data.longitude}
                            onChange={(e) => setData('longitude', e.target.value)}
                            className="input-field font-mono"
                        />
                        {errors.longitude && <p className="form-error">{errors.longitude}</p>}
                    </div>
                </div>

                <div className="mt-4">
                    <div className="flex items-center justify-between">
                        <label className="form-label text-xs">
                            Radius Toleransi Absen (Meter) <span className="text-red-500">*</span>
                        </label>
                        {mapsUrl && (
                            <a
                                href={mapsUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="link text-xs"
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
                            className="input-field w-28"
                        />
                    </div>
                    <p className="form-hint">
                        Pegawai hanya bisa absen jika jarak GPS mereka ≤ radius ini dari titik pusat.
                    </p>
                    {errors.radius_meter && <p className="form-error">{errors.radius_meter}</p>}
                </div>

                <div className="mt-4 max-w-xs">
                    <label className="form-label text-xs">
                        Durasi 1 JP (Menit) <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="number"
                        min="1"
                        max="255"
                        value={data.durasi_jp}
                        onChange={(e) => setData('durasi_jp', e.target.value)}
                        className="input-field"
                    />
                    <p className="form-hint">Durasi 1 Jam Pelajaran dalam menit. Default 45. Dipakai untuk auto-hitung jam selesai jadwal & payroll.</p>
                    {errors.durasi_jp && <p className="form-error">{errors.durasi_jp}</p>}
                </div>

                <div className="mt-4 max-w-xs">
                    <label className="form-label text-xs">
                        Toleransi Keterlambatan (Menit)
                    </label>
                    <input
                        type="number"
                        min="0"
                        max="60"
                        value={data.toleransi_menit ?? 0}
                        onChange={(e) => setData('toleransi_menit', e.target.value)}
                        className="input-field"
                    />
                    <p className="form-hint">Batas toleransi sebelum dianggap telat (0-60 menit). Default 0.</p>
                    {errors.toleransi_menit && <p className="form-error">{errors.toleransi_menit}</p>}
                </div>

                <div className="mt-4 max-w-xs">
                    <label className="form-label text-xs">
                        Maks Jam Mengajar/Minggu <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="number"
                        min="1"
                        max="168"
                        value={data.max_jam_minggu}
                        onChange={(e) => setData('max_jam_minggu', e.target.value)}
                        className="input-field"
                    />
                    <p className="form-hint">Batas maksimal jam mengajar per guru per minggu (hanya jadwal mengajar). Default 30.</p>
                    {errors.max_jam_minggu && <p className="form-error">{errors.max_jam_minggu}</p>}
                </div>

                <div className="mt-4 max-w-xs">
                    <label className="form-label text-xs">
                        Jam Masuk Kantor <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="time"
                        value={data.jam_masuk_kantor}
                        onChange={(e) => setData('jam_masuk_kantor', e.target.value)}
                        className="input-field"
                    />
                    <p className="form-hint">Dipakai untuk status hadir/telat pegawai tetap tanpa jadwal mengajar.</p>
                    {errors.jam_masuk_kantor && <p className="form-error">{errors.jam_masuk_kantor}</p>}
                </div>

                <div className="mt-4 max-w-xs">
                    <label className="form-label text-xs">
                        Jam Pulang Kantor
                    </label>
                    <input
                        type="time"
                        value={data.jam_pulang_kantor}
                        onChange={(e) => setData('jam_pulang_kantor', e.target.value)}
                        className="input-field"
                    />
                    <p className="form-hint">Dipakai deteksi pulang awal pegawai mode kantor. Kosongkan jika tidak berlaku.</p>
                    {errors.jam_pulang_kantor && <p className="form-error">{errors.jam_pulang_kantor}</p>}
                </div>
            </div>

            <div className="flex items-center justify-end mt-8 border-t border-border pt-6">
                <a
                    href={route('unit-sekolah.index')}
                    className="btn-secondary mr-6"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    disabled={processing}
                    className="btn-primary"
                >
                    {processing ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Tambah Unit'}
                </button>
            </div>
        </form>
    );
}
