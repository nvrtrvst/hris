import React, { useMemo, useRef, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, BadgeCheck, Camera, Loader2, Save, Trash2, User, X as XIcon } from 'lucide-react';
import { MapelSection } from '@/Pages/Pegawai/Partials/MapelSection';
import { UnitAssignmentSection } from '@/Pages/Pegawai/Partials/UnitAssignmentSection';
import { statusKepegawaianLabel } from '@/Utils/pegawaiMeta';

const inputClass = 'input-field';
const selectClass = 'select-field';

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
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                {children}
            </div>
        </div>
    );
}

export default function Edit({ auth, pegawai, unitSekolahs, jabatans, mapels, statusKepegawaian, pendidikanTerakhir }) {
    const canViewSensitive = auth.permissions?.includes('view_sensitive_data');
    const { data, setData, post, processing, errors } = useForm({
        _method: 'put',
        nik: canViewSensitive ? pegawai.nik_plain ?? pegawai.nik : '',
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

    // Opsi pendidikan dari PegawaiConstants. Nilai lama yang tidak ada di daftar
    // tetap disertakan agar pegawai dengan data legacy bisa diedit tanpa dipaksa ganti.
    const pendidikanOptions = useMemo(() => {
        const current = pegawai.pendidikan_terakhir;
        if (current && !(pendidikanTerakhir || []).includes(current)) {
            return [current, ...(pendidikanTerakhir || [])];
        }
        return pendidikanTerakhir || [];
    }, [pendidikanTerakhir, pegawai.pendidikan_terakhir]);

    const submit = (e) => {
        e.preventDefault();
        post(route('pegawai.update', pegawai.id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Edit Pegawai: {pegawai.nama_lengkap}</h2>}
        >
            <Head title={`Edit Pegawai - ${pegawai.nama_lengkap}`} />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    <Link href={route('pegawai.index')} className="inline-flex items-center gap-1.5 text-sm font-semibold text-text-secondary transition-colors hover:text-primary">
                        <ArrowLeft className="h-4 w-4" /> Kembali ke Daftar Pegawai
                    </Link>

                    <form onSubmit={submit} className="space-y-4">
                        {/* Foto */}
                        <SectionCard Icon={Camera} title="Foto Pegawai">
                            <div className="sm:col-span-2">
                                <div className="flex flex-col sm:flex-row gap-6 items-start">
                                    {(hasFoto || fotoPreview) && (
                                        <div className="relative shrink-0">
                                            <p className="mb-2 text-xs font-medium text-text-muted">{hasFoto ? 'Foto Saat Ini' : 'Foto Baru'}</p>
                                            <div className="relative">
                                                <img
                                                    src={fotoPreview || pegawai.foto_url}
                                                    alt="Foto Pegawai"
                                                    className="w-40 h-48 object-cover rounded-xl border border-border shadow-card"
                                                />
                                                {fotoPreview && (
                                                    <button type="button" onClick={clearFoto}
                                                        className="absolute -top-2 -right-2 bg-white rounded-full p-1 shadow border border-border text-text-muted hover:text-danger transition-colors">
                                                        <XIcon className="w-4 h-4" />
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                    <div className="flex-1 min-w-0">
                                        <Field label="Upload Foto Baru" error={errors.foto} hint="JPEG/PNG, maks 2MB. Kosongkan jika tidak ingin mengubah.">
                                            <div className="flex items-center gap-3">
                                                <label className="cursor-pointer inline-flex items-center gap-2 btn-secondary btn-sm">
                                                    <Camera className="w-4 h-4" /> Pilih File
                                                    <input ref={fileInputRef} type="file" onChange={handleFotoChange}
                                                        accept="image/jpeg, image/png, image/jpg" className="hidden" />
                                                </label>
                                                {pegawai.foto_url && !fotoPreview && (
                                                    <button type="button" onClick={clearFoto}
                                                        className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-danger hover:bg-danger-light rounded-lg transition-colors">
                                                        <Trash2 className="w-4 h-4" /> Hapus Foto
                                                    </button>
                                                )}
                                            </div>
                                        </Field>
                                    </div>
                                </div>
                            </div>
                        </SectionCard>

                        {/* Informasi Dasar */}
                        <SectionCard Icon={User} title="Informasi Dasar">
                            <Field label="NIK" required={canViewSensitive} error={errors.nik}
                                hint={!canViewSensitive ? 'NIK disembunyikan. Gunakan menu Lihat Detail > NIK untuk akses.' : undefined}>
                                <input type="text" value={canViewSensitive ? data.nik : '(Tersembunyi)'}
                                    onChange={(e) => setData('nik', e.target.value)}
                                    className={inputClass} disabled={!canViewSensitive} readOnly={!canViewSensitive} />
                            </Field>
                            <Field label="NIP / No Induk Guru" error={errors.nip}>
                                <input type="text" value={data.nip} onChange={(e) => setData('nip', e.target.value)}
                                    className={inputClass} placeholder="NIP / No Induk (Opsional)" />
                            </Field>
                            <Field label="Nama Lengkap" required error={errors.nama_lengkap}>
                                <input type="text" value={data.nama_lengkap} onChange={(e) => setData('nama_lengkap', e.target.value)} className={inputClass} />
                            </Field>
                            <Field label="Email Login Mobile" required error={errors.email} hint="Email ini dipakai untuk login ke portal mobile.">
                                <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)}
                                    autoComplete="email" className={inputClass} />
                            </Field>
                            <Field label="Tempat Lahir" required error={errors.tempat_lahir}>
                                <input type="text" value={data.tempat_lahir} onChange={(e) => setData('tempat_lahir', e.target.value)} className={inputClass} />
                            </Field>
                            <Field label="Tanggal Lahir" required error={errors.tanggal_lahir}>
                                <input type="date" value={data.tanggal_lahir} onChange={(e) => setData('tanggal_lahir', e.target.value)} className={inputClass} />
                            </Field>
                            <Field label="Jenis Kelamin" required error={errors.jenis_kelamin}>
                                <select value={data.jenis_kelamin} onChange={(e) => setData('jenis_kelamin', e.target.value)} className={selectClass}>
                                    <option value="">Pilih Jenis Kelamin</option>
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </Field>
                            <Field label="Agama" required error={errors.agama}>
                                <select value={data.agama} onChange={(e) => setData('agama', e.target.value)} className={selectClass}>
                                    <option value="">Pilih Agama</option>
                                    <option value="Islam">Islam</option>
                                    <option value="Kristen">Kristen</option>
                                    <option value="Katolik">Katolik</option>
                                    <option value="Hindu">Hindu</option>
                                    <option value="Buddha">Buddha</option>
                                    <option value="Konghucu">Konghucu</option>
                                </select>
                            </Field>
                            <Field label="Status Pernikahan" required error={errors.status_pernikahan}>
                                <select value={data.status_pernikahan} onChange={(e) => setData('status_pernikahan', e.target.value)} className={selectClass}>
                                    <option value="">Pilih Status</option>
                                    <option value="Belum Menikah">Belum Menikah</option>
                                    <option value="Menikah">Menikah</option>
                                    <option value="Cerai Hidup">Cerai Hidup</option>
                                    <option value="Cerai Mati">Cerai Mati</option>
                                </select>
                            </Field>
                            <Field label="No. HP" required error={errors.no_hp}>
                                <input type="text" value={data.no_hp} onChange={(e) => setData('no_hp', e.target.value)} className={inputClass} />
                            </Field>
                            <Field label="Alamat KTP" required error={errors.alamat_ktp} className="sm:col-span-2">
                                <textarea value={data.alamat_ktp} onChange={(e) => setData('alamat_ktp', e.target.value)}
                                    rows={3} className={inputClass} />
                            </Field>
                        </SectionCard>

                        {/* Status Kepegawaian */}
                        <SectionCard Icon={BadgeCheck} title="Status Kepegawaian">
                            <Field label="Status Kepegawaian" error={errors.status_kepegawaian}>
                                <select value={data.status_kepegawaian} onChange={(e) => setData('status_kepegawaian', e.target.value)} className={selectClass}>
                                    {(statusKepegawaian || []).map((s) => (
                                        <option key={s} value={s}>{statusKepegawaianLabel(s)}</option>
                                    ))}
                                </select>
                            </Field>
                            <Field label="Wajib Masuk Kantor" error={errors.wajib_kantor}>
                                <label className="mt-1.5 inline-flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" checked={data.wajib_kantor}
                                        onChange={(e) => setData('wajib_kantor', e.target.checked)}
                                        className="w-4 h-4 rounded border-border text-primary focus:ring-primary" />
                                    <div>
                                        <span className="text-sm font-medium text-text-primary">Aktifkan</span>
                                        <p className="form-hint">Jika tidak ada jadwal mengajar, tetap harus absen kantor.</p>
                                    </div>
                                </label>
                            </Field>
                            <Field label="Jatah Cuti Tahunan (Hari)" error={errors.jatah_cuti_tahunan}>
                                <input type="number" min="0" value={data.jatah_cuti_tahunan}
                                    onChange={(e) => setData('jatah_cuti_tahunan', e.target.value)} className={inputClass} />
                            </Field>
                            <Field label="Status Aktif" error={errors.status_aktif}>
                                <select value={data.status_aktif} onChange={(e) => setData('status_aktif', e.target.value)} className={selectClass}>
                                    <option value="aktif">Aktif</option>
                                    <option value="cuti">Cuti</option>
                                    <option value="nonaktif">Nonaktif</option>
                                    <option value="resign">Resign</option>
                                </select>
                            </Field>
                            <Field label="Tanggal Mulai Kerja" required error={errors.tanggal_mulai_kerja}>
                                <input type="date" value={data.tanggal_mulai_kerja}
                                    onChange={(e) => setData('tanggal_mulai_kerja', e.target.value)} className={inputClass} />
                            </Field>
                            <Field label="Pendidikan Terakhir" required error={errors.pendidikan_terakhir}>
                                <select value={data.pendidikan_terakhir}
                                    onChange={(e) => setData('pendidikan_terakhir', e.target.value)}
                                    className={selectClass}>
                                    {pendidikanOptions.map((p) => (
                                        <option key={p} value={p}>{p}</option>
                                    ))}
                                </select>
                            </Field>
                        </SectionCard>

                        {/* Penugasan Unit & Jabatan */}
                        <UnitAssignmentSection
                            units={data.units}
                            onUpdate={updateUnit}
                            onAdd={addUnit}
                            onRemove={removeUnit}
                            unitSekolahs={unitSekolahs}
                            jabatans={jabatans}
                        />

                        {/* Mata Pelajaran (Guru) */}
                        <MapelSection
                            mapels={data.mapels}
                            onUpdate={updateMapel}
                            onAdd={addMapel}
                            onRemove={removeMapel}
                            mapelList={mapels}
                            unitSekolahs={unitSekolahs}
                        />

                        {/* Actions */}
                        <div className="card flex items-center justify-end gap-3 p-5">
                            <Link href={route('pegawai.show', pegawai.id)} className="btn-secondary">Batal</Link>
                            <button type="submit" disabled={processing} className="btn-primary flex items-center gap-2">
                                {processing ? <><Loader2 className="h-4 w-4 animate-spin" /> Menyimpan…</> : <><Save className="h-4 w-4" /> Perbarui Pegawai</>}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
