import React, { useState } from 'react';
import { Link } from '@inertiajs/react';
import { Image, Loader2, MapPin, Save, School, Settings2, Timer } from 'lucide-react';
import LeafletPicker from '@/Components/LeafletPicker';
import { validateUpload } from '@/Utils/file';

const inputClass = 'input-field';

const Field = ({ label, required, error, hint, children, className = '' }) => (
    <div className={className}>
        <label className="form-label text-xs">{label} {required && <span className="text-danger">*</span>}</label>
        {children}
        {error && <p className="form-error">{error}</p>}
        {hint && !error && <p className="form-hint">{hint}</p>}
    </div>
);

function SectionCard({ Icon, title, description, children }) {
    return (
        <div className="card p-6">
            <div className="mb-5">
                <h3 className="flex items-center gap-2 text-sm font-extrabold uppercase tracking-wide text-primary">
                    <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                        <Icon className="h-4 w-4 text-primary" />
                    </span>
                    {title}
                </h3>
                {description && <p className="mt-1.5 text-xs text-text-muted">{description}</p>}
            </div>
            {children}
        </div>
    );
}

export default function UnitForm({ data, setData, errors, processing, onSubmit, isEdit, unitName, unitLogoUrl }) {
    const [logoError, setLogoError] = useState(null);
    const validLat = !isNaN(parseFloat(data.latitude));
    const validLng = !isNaN(parseFloat(data.longitude));
    const mapsUrl = validLat && validLng
        ? `https://www.google.com/maps?q=${data.latitude},${data.longitude}`
        : null;

    return (
        <form onSubmit={onSubmit} className="space-y-4">
            {/* Identitas Unit */}
            <SectionCard Icon={School} title="Identitas Unit">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Nama Unit" required error={errors.nama} className="sm:col-span-2">
                        <input type="text" value={data.nama} onChange={(e) => setData('nama', e.target.value)}
                            placeholder="cth. SMP Nunul Muttaqiin" className={inputClass} />
                    </Field>
                    <Field label="Singkatan" required error={errors.singkatan}>
                        <input type="text" value={data.singkatan} onChange={(e) => setData('singkatan', e.target.value)}
                            placeholder="cth. SMP" className={inputClass} />
                    </Field>
                        <Field label="Logo Unit" error={logoError || errors.logo} hint="JPEG, PNG, atau WebP. Maksimum 1 MB. Rasio persegi disarankan.">
                        <div className="flex items-center gap-3">
                            {(isEdit && data.logo === null) && (
                                <div className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-border bg-white">
                                    {unitLogoUrl ? (
                                        <img src={unitLogoUrl} alt={`Logo ${unitName}`} className="h-full w-full object-contain p-1" />
                                    ) : unitName ? (
                                        <span className="text-sm font-bold text-primary">{unitName.slice(0, 3).toUpperCase()}</span>
                                    ) : (
                                        <Image className="h-6 w-6 text-text-muted" />
                                    )}
                                </div>
                            )}
                            <label className="btn-secondary btn-sm inline-flex cursor-pointer items-center gap-2">
                                <Image className="h-4 w-4" /> Pilih File
                                <input type="file" accept="image/jpeg,image/png,image/webp"
                                    onChange={(e) => {
                                        const f = e.target.files[0] || null;
                                        setLogoError(null);
                                        if (f) {
                                            const err = validateUpload(f, { maxBytes: 1 * 1024 * 1024, accept: ['image/jpeg', 'image/png', 'image/webp'], label: 'Logo' });
                                            if (err) {
                                                setLogoError(err);
                                                e.target.value = '';
                                                return;
                                            }
                                        }
                                        setData('logo', f);
                                    }} className="hidden" />
                            </label>
                        </div>
                    </Field>
                </div>
            </SectionCard>

            {/* Titik Pusat & Radius Geofence */}
            <SectionCard Icon={MapPin} title="Titik Pusat & Radius Geofence"
                description="Geser marker atau klik peta untuk memilih lokasi. Radius dipakai validasi absen mobile.">
                <LeafletPicker
                    lat={data.latitude}
                    lng={data.longitude}
                    radius={data.radius_meter}
                    onChange={(la, ln) => {
                        setData('latitude', la);
                        setData('longitude', ln);
                    }}
                />

                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Latitude" required error={errors.latitude}>
                        <input type="number" step="any" value={data.latitude}
                            onChange={(e) => setData('latitude', e.target.value)} className={`${inputClass} font-mono`} />
                    </Field>
                    <Field label="Longitude" required error={errors.longitude}>
                        <input type="number" step="any" value={data.longitude}
                            onChange={(e) => setData('longitude', e.target.value)} className={`${inputClass} font-mono`} />
                    </Field>
                </div>

                <div className="mt-4">
                    <div className="flex items-center justify-between">
                        <label className="form-label text-xs">Radius Toleransi Absen (Meter) <span className="text-danger">*</span></label>
                        {mapsUrl && (
                            <a href={mapsUrl} target="_blank" rel="noopener noreferrer" className="link text-xs">
                                Buka di Google Maps ↗
                            </a>
                        )}
                    </div>
                    <div className="mt-1 flex items-center gap-3">
                        <input type="range" min="10" max="10000" value={data.radius_meter}
                            onChange={(e) => setData('radius_meter', e.target.value)}
                            className="flex-1 accent-[#0F3D3E]" />
                        <input type="number" min="10" max="100000" value={data.radius_meter}
                            onChange={(e) => setData('radius_meter', e.target.value)} className={`${inputClass} w-28`} />
                    </div>
                    <p className="form-hint">Pegawai hanya bisa absen jika jarak GPS mereka ≤ radius ini dari titik pusat.</p>
                    {errors.radius_meter && <p className="form-error">{errors.radius_meter}</p>}
                </div>

                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Durasi 1 JP (Menit)" required error={errors.durasi_jp}
                        hint="Durasi 1 Jam Pelajaran. Default 45. Dipakai auto-hitung jam selesai jadwal & payroll.">
                        <input type="number" min="1" max="255" value={data.durasi_jp}
                            onChange={(e) => setData('durasi_jp', e.target.value)} className={inputClass} />
                    </Field>
                    <Field label="Maks Jam Mengajar/Minggu" required error={errors.max_jam_minggu}
                        hint="Batas maksimal jam mengajar per guru per minggu (hanya jadwal mengajar). Default 30.">
                        <input type="number" min="1" max="168" value={data.max_jam_minggu}
                            onChange={(e) => setData('max_jam_minggu', e.target.value)} className={inputClass} />
                    </Field>
                </div>
            </SectionCard>

            {/* Aturan Presensi */}
            <SectionCard Icon={Settings2} title="Aturan Presensi">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Toleransi Keterlambatan (Menit)" error={errors.toleransi_menit}
                        hint="Batas toleransi sebelum dianggap telat (0-60). Default 0.">
                        <input type="number" min="0" max="60" value={data.toleransi_menit ?? 0}
                            onChange={(e) => setData('toleransi_menit', e.target.value)} className={inputClass} />
                    </Field>
                    <Field label="Batas Tap Jadwal (Menit)" error={errors.toleransi_tap_menit}
                        hint="Tap jadwal mengajar hanya bisa sampai X menit setelah jam selesai (0-60). Default 15.">
                        <input type="number" min="0" max="60" value={data.toleransi_tap_menit ?? 15}
                            onChange={(e) => setData('toleransi_tap_menit', e.target.value)} className={inputClass} />
                    </Field>
                </div>
            </SectionCard>

            {/* Jam Kantor */}
            <SectionCard Icon={Timer} title="Jam Kantor"
                description="Dipakai untuk pegawai tetap tanpa jadwal mengajar (mode kantor).">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Jam Masuk Kantor" required error={errors.jam_masuk_kantor}
                        hint="Dipakai untuk status hadir/telat pegawai tetap tanpa jadwal mengajar.">
                        <input type="time" value={data.jam_masuk_kantor}
                            onChange={(e) => setData('jam_masuk_kantor', e.target.value)} className={inputClass} />
                    </Field>
                    <Field label="Jam Pulang Kantor" error={errors.jam_pulang_kantor}
                        hint="Dipakai deteksi pulang awal pegawai mode kantor. Kosongkan jika tidak berlaku.">
                        <input type="time" value={data.jam_pulang_kantor}
                            onChange={(e) => setData('jam_pulang_kantor', e.target.value)} className={inputClass} />
                    </Field>
                </div>
            </SectionCard>

            {/* Kop Surat (Laporan KCD) */}
            <SectionCard Icon={School} title="Kop Surat (Laporan KCD)"
                description="Data ditampilkan di kop surat laporan presensi KCD. Opsional.">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Website" error={errors.web}>
                        <input type="text" value={data.web ?? ''}
                            onChange={(e) => setData('web', e.target.value)} placeholder="cth. www.smpnm.sch.id" className={inputClass} />
                    </Field>
                    <Field label="Telepon" error={errors.telepon}>
                        <input type="text" value={data.telepon ?? ''}
                            onChange={(e) => setData('telepon', e.target.value)} placeholder="cth. (021) 1234567" className={inputClass} />
                    </Field>
                    <Field label="Alamat" error={errors.alamat} className="sm:col-span-2">
                        <textarea value={data.alamat ?? ''}
                            onChange={(e) => setData('alamat', e.target.value)} rows={2} placeholder="Alamat lengkap unit sekolah"
                            className={inputClass} />
                    </Field>
                </div>
            </SectionCard>

            {/* Actions */}
            <div className="card flex items-center justify-end gap-3 p-5">
                <Link href={route('unit-sekolah.index')} className="btn-secondary">Batal</Link>
                <button type="submit" disabled={processing} className="btn-primary flex items-center gap-2">
                    {processing
                        ? <><Loader2 className="h-4 w-4 animate-spin" /> Menyimpan…</>
                        : <><Save className="h-4 w-4" /> {isEdit ? 'Simpan Perubahan' : 'Tambah Unit'}</>}
                </button>
            </div>
        </form>
    );
}
