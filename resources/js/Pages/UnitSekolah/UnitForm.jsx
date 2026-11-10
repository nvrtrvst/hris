import React, { useState } from 'react';
import { Link } from '@inertiajs/react';
import { ChevronDown, Image, Loader2, MapPin, Percent, Save, School, Settings2, Timer, Users } from 'lucide-react';
import { Button, Card } from '@/Components/AdminUI';
import LeafletPicker from '@/Components/LeafletPicker';
import { validateUpload } from '@/Utils/file';
import CollapsibleSection from './Partials/CollapsibleSection';

const inputClass = 'input-field';

const Field = ({ label, required, error, hint, children, className = '' }) => (
    <div className={className}>
        <label className="form-label text-xs">{label} {required && <span className="text-danger">*</span>}</label>
        {children}
        {error && <p className="form-error">{error}</p>}
        {hint && !error && <p className="form-hint">{hint}</p>}
    </div>
);

function SectionCard({ id, Icon, title, description, children }) {
    return (
        <Card id={id} className="scroll-mt-32 p-5 sm:p-6">
            <div className="mb-5">
                <h3 className="flex items-center gap-2 text-sm font-bold text-primary">
                    <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                        <Icon className="h-4 w-4 text-primary" />
                    </span>
                    {title}
                </h3>
                {description && <p className="mt-1.5 text-xs text-text-secondary">{description}</p>}
            </div>
            {children}
        </Card>
    );
}

function SubHeading({ title, description }) {
    return (
        <div className="mb-3">
            <h4 className="text-xs font-bold text-text-primary">{title}</h4>
            {description && <p className="mt-1 text-[10px] leading-relaxed text-text-muted">{description}</p>}
        </div>
    );
}

export default function UnitForm({
    data,
    setData,
    errors,
    processing,
    onSubmit,
    isEdit,
    unitName,
    unitLogoUrl,
    jabatans = [],
    showTembusan = false,
    showActions = true,
    formId,
}) {
    const [logoError, setLogoError] = useState(null);
    const validLat = !isNaN(parseFloat(data.latitude));
    const validLng = !isNaN(parseFloat(data.longitude));
    const mapsUrl = validLat && validLng
        ? `https://www.google.com/maps?q=${data.latitude},${data.longitude}`
        : null;
    const tembusanIds = (data.tembusan_jabatan_ids || []).map(String);
    const bobotError = [
        'sakit_wajib_tugas',
        'bobot_sakit_surat',
        'bobot_sakit_tanpa_surat',
        'bobot_izin',
        'bobot_izin_setengah',
        'bobot_cuti',
        'bobot_alpa',
    ].some((key) => errors[key]);
    const kopError = ['web', 'telepon', 'alamat'].some((key) => errors[key]);

    return (
        <form id={formId} onSubmit={onSubmit} className="space-y-4">
            <SectionCard id="unit-identitas" Icon={School} title="Identitas Unit">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-[110px_minmax(0,1fr)]">
                    <div>
                        <label className="form-label text-xs">Logo Unit</label>
                        <div className={`mt-1 flex h-24 w-24 items-center justify-center overflow-hidden rounded-xl ${unitLogoUrl ? '' : 'border border-border bg-surface'}`}>
                            {unitLogoUrl ? (
                                <img src={unitLogoUrl} alt={`Logo ${unitName}`} className="h-full w-full object-contain" />
                            ) : unitName ? (
                                <span className="text-lg font-bold text-primary">{unitName.slice(0, 3).toUpperCase()}</span>
                            ) : (
                                <Image className="h-7 w-7 text-text-muted" />
                            )}
                        </div>
                        <label className="btn-secondary btn-sm mt-2 inline-flex cursor-pointer items-center gap-2">
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
                        {(logoError || errors.logo) && <p className="form-error">{logoError || errors.logo}</p>}
                        <p className="form-hint">JPEG, PNG, atau WebP. Maks. 1 MB.</p>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field label="Nama Unit" required error={errors.nama} className="sm:col-span-2">
                            <input type="text" value={data.nama} onChange={(e) => setData('nama', e.target.value)}
                                placeholder="cth. SMP Nuurul Muttaqiin" className={inputClass} />
                        </Field>
                        <Field label="Singkatan" required error={errors.singkatan}>
                            <input type="text" value={data.singkatan} onChange={(e) => setData('singkatan', e.target.value)}
                                placeholder="cth. SMP" className={inputClass} />
                        </Field>
                    </div>
                </div>
            </SectionCard>

            <SectionCard id="unit-geofence" Icon={MapPin} title="Lokasi & Geofence"
                description="Titik pusat absen dan batas jarak yang dipakai validasi mobile.">
                <LeafletPicker
                    lat={data.latitude}
                    lng={data.longitude}
                    radius={data.radius_meter}
                    onChange={(la, ln) => {
                        setData('latitude', la);
                        setData('longitude', ln);
                    }}
                />

                <div className="mt-4">
                    <div className="flex items-center justify-between">
                        <label className="form-label text-xs">Radius Toleransi Absen (Meter) <span className="text-danger">*</span></label>
                        <span className="text-sm font-bold text-primary">{data.radius_meter} m</span>
                    </div>
                    <div className="mt-1 flex items-center gap-3">
                        <input type="range" min="10" max="10000" value={data.radius_meter}
                            onChange={(e) => setData('radius_meter', e.target.value)}
                            className="flex-1 accent-[#0F3D3E]" />
                        <input type="number" min="10" max="100000" value={data.radius_meter}
                            onChange={(e) => setData('radius_meter', e.target.value)} className={`${inputClass} w-28`} />
                    </div>
                    <p className="form-hint">Pegawai hanya bisa absen jika jarak GPS mereka di dalam radius ini.</p>
                    {errors.radius_meter && <p className="form-error">{errors.radius_meter}</p>}
                </div>

                <details className="group mt-4 overflow-hidden rounded-xl border border-border">
                    <summary className="flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-2.5 text-xs font-semibold text-text-secondary [&::-webkit-details-marker]:hidden">
                        Koordinat manual
                        <ChevronDown className="h-4 w-4 text-text-muted transition-transform group-open:rotate-180" />
                    </summary>
                    <div className="grid grid-cols-1 gap-4 border-t border-border p-3 sm:grid-cols-2">
                        <Field label="Latitude" required error={errors.latitude}>
                            <input type="number" step="any" value={data.latitude}
                                onChange={(e) => setData('latitude', e.target.value)} className={`${inputClass} font-mono`} />
                        </Field>
                        <Field label="Longitude" required error={errors.longitude}>
                            <input type="number" step="any" value={data.longitude}
                                onChange={(e) => setData('longitude', e.target.value)} className={`${inputClass} font-mono`} />
                        </Field>
                        {mapsUrl && (
                            <a href={mapsUrl} target="_blank" rel="noopener noreferrer" className="link text-xs sm:col-span-2">
                                Buka titik ini di Google Maps
                            </a>
                        )}
                    </div>
                </details>
            </SectionCard>

            <SectionCard id="unit-jam" Icon={Timer} title="Jam Operasional"
                description="Dipakai untuk pegawai mode kantor dan penentuan hari kerja unit.">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Jam Masuk Kantor" required error={errors.jam_masuk_kantor}
                        hint="Dipakai untuk status hadir/telat pegawai tanpa jadwal mengajar.">
                        <input type="time" value={data.jam_masuk_kantor}
                            onChange={(e) => setData('jam_masuk_kantor', e.target.value)} className={inputClass} />
                    </Field>
                    <Field label="Jam Pulang Kantor" error={errors.jam_pulang_kantor}
                        hint="Dipakai deteksi pulang awal pegawai mode kantor.">
                        <input type="time" value={data.jam_pulang_kantor}
                            onChange={(e) => setData('jam_pulang_kantor', e.target.value)} className={inputClass} />
                    </Field>
                </div>

                <div className="mt-5 border-t border-border pt-5">
                    <SubHeading title="Hari kerja tambahan" description="Aktifkan jika unit beroperasi pada hari tersebut." />
                    <div className="space-y-4">
                        <div>
                            <label className="flex cursor-pointer items-center gap-2">
                                <input type="checkbox" checked={!!data.jam_kerja_sabtu_mulai}
                                    onChange={(e) => {
                                        if (e.target.checked) {
                                            setData('jam_kerja_sabtu_mulai', data.jam_kerja_sabtu_mulai || '07:00');
                                            setData('jam_kerja_sabtu_selesai', data.jam_kerja_sabtu_selesai || '12:00');
                                        } else {
                                            setData('jam_kerja_sabtu_mulai', null);
                                            setData('jam_kerja_sabtu_selesai', null);
                                        }
                                    }}
                                    className="accent-[#0F3D3E]" />
                                <span className="text-sm font-semibold text-primary">Sabtu masuk</span>
                            </label>
                            {data.jam_kerja_sabtu_mulai && (
                                <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <Field label="Jam Masuk Sabtu" error={errors.jam_kerja_sabtu_mulai}>
                                        <input type="time" value={data.jam_kerja_sabtu_mulai}
                                            onChange={(e) => setData('jam_kerja_sabtu_mulai', e.target.value)} className={inputClass} />
                                    </Field>
                                    <Field label="Jam Pulang Sabtu" error={errors.jam_kerja_sabtu_selesai}>
                                        <input type="time" value={data.jam_kerja_sabtu_selesai}
                                            onChange={(e) => setData('jam_kerja_sabtu_selesai', e.target.value)} className={inputClass} />
                                    </Field>
                                </div>
                            )}
                        </div>

                        <div className="border-t border-border pt-4">
                            <label className="flex cursor-pointer items-center gap-2">
                                <input type="checkbox" checked={!!data.jam_kerja_minggu_mulai}
                                    onChange={(e) => {
                                        if (e.target.checked) {
                                            setData('jam_kerja_minggu_mulai', data.jam_kerja_minggu_mulai || '07:00');
                                            setData('jam_kerja_minggu_selesai', data.jam_kerja_minggu_selesai || '12:00');
                                        } else {
                                            setData('jam_kerja_minggu_mulai', null);
                                            setData('jam_kerja_minggu_selesai', null);
                                        }
                                    }}
                                    className="accent-[#0F3D3E]" />
                                <span className="text-sm font-semibold text-primary">Minggu masuk</span>
                            </label>
                            {data.jam_kerja_minggu_mulai && (
                                <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <Field label="Jam Masuk Minggu" error={errors.jam_kerja_minggu_mulai}>
                                        <input type="time" value={data.jam_kerja_minggu_mulai}
                                            onChange={(e) => setData('jam_kerja_minggu_mulai', e.target.value)} className={inputClass} />
                                    </Field>
                                    <Field label="Jam Pulang Minggu" error={errors.jam_kerja_minggu_selesai}>
                                        <input type="time" value={data.jam_kerja_minggu_selesai}
                                            onChange={(e) => setData('jam_kerja_minggu_selesai', e.target.value)} className={inputClass} />
                                    </Field>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </SectionCard>

            <SectionCard id="unit-presensi" Icon={Settings2} title="Aturan Presensi"
                description="Beban mengajar, toleransi, sesi, dan lembur.">
                <SubHeading title="Beban mengajar" />
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Durasi 1 JP (Menit)" required error={errors.durasi_jp}
                        hint="Dipakai auto-hitung jam selesai jadwal & payroll. Default 45.">
                        <input type="number" min="1" max="255" value={data.durasi_jp}
                            onChange={(e) => setData('durasi_jp', e.target.value)} className={inputClass} />
                    </Field>
                    <Field label="Maks Jam Mengajar/Minggu" required error={errors.max_jam_minggu}
                        hint="Batas jam mengajar per guru per minggu. Default 30.">
                        <input type="number" min="1" max="168" value={data.max_jam_minggu}
                            onChange={(e) => setData('max_jam_minggu', e.target.value)} className={inputClass} />
                    </Field>
                </div>

                <div className="mt-5 border-t border-border pt-5">
                    <SubHeading title="Keterlambatan" />
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field label="Toleransi Keterlambatan Kantor (Menit)" error={errors.toleransi_menit}
                            hint="Batas absen kantor, piket, dan pulang awal. Default 0.">
                            <input type="number" min="0" max="60" value={data.toleransi_menit ?? 0}
                                onChange={(e) => setData('toleransi_menit', e.target.value)} className={inputClass} />
                        </Field>
                        <Field label="Toleransi Keterlambatan Mengajar (Menit)" error={errors.toleransi_mengajar_menit}
                            hint="Batas keterlambatan JP mengajar. Default 0.">
                            <input type="number" min="0" max="60" value={data.toleransi_mengajar_menit ?? 0}
                                onChange={(e) => setData('toleransi_mengajar_menit', e.target.value)} className={inputClass} />
                        </Field>
                    </div>
                </div>

                <div className="mt-5 border-t border-border pt-5">
                    <SubHeading title="Sesi & pulang" />
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field label="Batas Slide Jadwal (Menit)" error={errors.toleransi_slide_menit}
                            hint="Slide JP hanya bisa sampai X menit setelah sesi selesai. Default 15.">
                            <input type="number" min="0" max="60" value={data.toleransi_slide_menit ?? 15}
                                onChange={(e) => setData('toleransi_slide_menit', e.target.value)} className={inputClass} />
                        </Field>
                        <Field label="Jeda Sesi Maksimum (Menit)" error={errors.max_jeda_sesi_menit}
                            hint="Jarak maksimal antar JP dalam satu sesi, termasuk istirahat. Default 120.">
                            <input type="number" min="0" max="240" value={data.max_jeda_sesi_menit ?? 120}
                                onChange={(e) => setData('max_jeda_sesi_menit', e.target.value)} className={inputClass} />
                        </Field>
                    </div>

                    <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label className="flex cursor-pointer items-center gap-2.5 rounded-xl border border-border bg-surface p-3">
                                <input type="checkbox" checked={Boolean(data.wajib_pulang_mengajar)}
                                    onChange={(e) => setData('wajib_pulang_mengajar', e.target.checked ? 1 : 0)}
                                    className="h-4 w-4 accent-[#0F3D3E]" />
                                <span className="text-sm font-semibold text-text-primary">Wajib pulang mengajar</span>
                            </label>
                            <p className="form-hint">ON: absen keluar lewat tombol &quot;Selesai Mengajar&quot;. OFF: pulang otomatis dari jadwal.</p>
                            {errors.wajib_pulang_mengajar && <p className="form-error">{errors.wajib_pulang_mengajar}</p>}
                        </div>
                        <Field label="Tombol Pulang (Menit Sebelum Selesai)" error={errors.pulang_sebelum_menit}
                            hint="Tombol muncul N menit sebelum sesi berakhir. Default 20.">
                            <input type="number" min="1" max="120" value={data.pulang_sebelum_menit ?? 20}
                                onChange={(e) => setData('pulang_sebelum_menit', e.target.value)} className={inputClass} />
                        </Field>
                    </div>
                </div>

                <div className="mt-5 border-t border-border pt-5">
                    <SubHeading title="Lembur" />
                    <Field label="Batas Jam Lembur (Per Hari)" error={errors.max_lembur_jam}
                        hint="Maksimal durasi lembur yang boleh disetujui. Kosong = tanpa batas.">
                        <input type="number" min="0.5" max="24" step="0.5" value={data.max_lembur_jam ?? ''}
                            onChange={(e) => setData('max_lembur_jam', e.target.value)} className={inputClass} placeholder="Tanpa batas" />
                    </Field>
                </div>
            </SectionCard>

            {showTembusan && (
                <SectionCard id="unit-tembusan" Icon={Users} title="Tembusan Pengajuan Izin"
                    description="Jabatan yang otomatis menerima tembusan pengajuan izin unit ini.">
                    <div className="mb-3 flex items-center justify-between gap-3">
                        <p className="text-xs text-text-secondary">Pilih jabatan penerima tembusan.</p>
                        <p className="text-xs font-semibold text-primary">{tembusanIds.length} dari {jabatans.length} dipilih</p>
                    </div>
                    {jabatans.length === 0 ? (
                        <p className="rounded-xl border border-border bg-surface p-3 text-sm text-text-secondary">
                            Belum ada data jabatan. Tambahkan jabatan terlebih dahulu.
                        </p>
                    ) : (
                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            {jabatans.map((j) => {
                                const checked = tembusanIds.includes(String(j.id));

                                return (
                                    <label key={j.id} className="flex cursor-pointer items-center gap-2.5 rounded-xl border border-border px-3 py-2.5 text-sm hover:border-primary/50">
                                        <input
                                            type="checkbox"
                                            checked={checked}
                                            onChange={(e) => {
                                                setData('tembusan_jabatan_ids', e.target.checked
                                                    ? [...tembusanIds, String(j.id)]
                                                    : tembusanIds.filter((x) => x !== String(j.id)));
                                            }}
                                            className="h-5 w-5 rounded border-border text-primary focus:ring-primary/25"
                                        />
                                        <span className="font-medium text-primary">{j.nama}</span>
                                    </label>
                                );
                            })}
                        </div>
                    )}
                    {errors.tembusan_jabatan_ids && <p className="mt-2 text-xs font-semibold text-rose-600">{errors.tembusan_jabatan_ids}</p>}
                </SectionCard>
            )}

            <CollapsibleSection
                id="unit-bobot"
                Icon={Percent}
                title="Bobot Kehadiran"
                description="Persentase hari yang dihitung hadir di rekap & payroll."
                hasError={bobotError}
            >
                <label className="mb-4 flex cursor-pointer items-start gap-2.5 rounded-xl border border-border bg-surface p-3">
                    <input type="checkbox" checked={Boolean(data.sakit_wajib_tugas)}
                        onChange={(e) => setData('sakit_wajib_tugas', e.target.checked ? 1 : 0)}
                        className="mt-0.5 h-4 w-4 accent-[#0F3D3E]" />
                    <span className="text-sm text-text-secondary">
                        <span className="font-semibold text-text-primary">Sakit wajib tugas untuk 100%</span>
                        <span className="mt-0.5 block text-xs text-text-muted">Sakit harus punya surat dokter + tugas terverifikasi.</span>
                    </span>
                </label>
                {errors.sakit_wajib_tugas && <p className="form-error mb-2">{errors.sakit_wajib_tugas}</p>}

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Field label="Sakit Lengkap (Surat + Tugas)" error={errors.bobot_sakit_surat} hint="Default 1.0.">
                        <input type="number" min="0" max="1" step="0.25" value={data.bobot_sakit_surat ?? ''}
                            onChange={(e) => setData('bobot_sakit_surat', e.target.value)} className={inputClass} placeholder="1.0" />
                    </Field>
                    <Field label="Sakit Belum Lengkap" error={errors.bobot_sakit_tanpa_surat} hint="Default 0.5.">
                        <input type="number" min="0" max="1" step="0.25" value={data.bobot_sakit_tanpa_surat ?? ''}
                            onChange={(e) => setData('bobot_sakit_tanpa_surat', e.target.value)} className={inputClass} placeholder="0.5" />
                    </Field>
                    <Field label="Izin" error={errors.bobot_izin} hint="Default 0.5.">
                        <input type="number" min="0" max="1" step="0.25" value={data.bobot_izin ?? ''}
                            onChange={(e) => setData('bobot_izin', e.target.value)} className={inputClass} placeholder="0.5" />
                    </Field>
                    <Field label="Izin Setengah Hari" error={errors.bobot_izin_setengah} hint="Default 0.5.">
                        <input type="number" min="0" max="1" step="0.25" value={data.bobot_izin_setengah ?? ''}
                            onChange={(e) => setData('bobot_izin_setengah', e.target.value)} className={inputClass} placeholder="0.5" />
                    </Field>
                    <Field label="Cuti" error={errors.bobot_cuti} hint="Default 1.0.">
                        <input type="number" min="0" max="1" step="0.25" value={data.bobot_cuti ?? ''}
                            onChange={(e) => setData('bobot_cuti', e.target.value)} className={inputClass} placeholder="1.0" />
                    </Field>
                    <Field label="Alpa" error={errors.bobot_alpa} hint="Default 0.">
                        <input type="number" min="0" max="1" step="0.25" value={data.bobot_alpa ?? ''}
                            onChange={(e) => setData('bobot_alpa', e.target.value)} className={inputClass} placeholder="0" />
                    </Field>
                </div>
            </CollapsibleSection>

            <CollapsibleSection
                id="unit-kop"
                Icon={School}
                title="Kop Surat"
                description="Dipakai di kop laporan KCD. Opsional."
                hasError={kopError}
            >
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
            </CollapsibleSection>

            {showActions && (
                <div className="flex flex-wrap items-center justify-end gap-2 rounded-xl border border-border bg-white p-4">
                    <Link href={route('unit-sekolah.index')} className="inline-flex h-10 items-center rounded-lg border border-border bg-white px-4 text-sm font-bold text-text-secondary transition-colors hover:border-primary/30 hover:text-primary">Batal</Link>
                    <Button type="submit" disabled={processing}>
                        {processing
                            ? <><Loader2 className="h-4 w-4 animate-spin" /> Menyimpan...</>
                            : <><Save className="h-4 w-4" /> {isEdit ? 'Simpan Perubahan' : 'Tambah Unit'}</>}
                    </Button>
                </div>
            )}
        </form>
    );
}
