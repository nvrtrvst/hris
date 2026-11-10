import React from 'react';
import AdminShell from '@/Layouts/AdminShell';
import { Button, Card, FormField, Input, PageHeader, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/AdminUI';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { ArrowLeft, Loader2, MapPin, Plus, Save, Trash2 } from 'lucide-react';
import UnitForm from './UnitForm';
import LeafletPicker from '@/Components/LeafletPicker';
import ModePresensiSection from './Partials/ModePresensiSection';
import SettingsNav from './Partials/SettingsNav';
import UnitSummaryCard from './Partials/UnitSummaryCard';

export default function Edit({
    auth,
    unit,
    lokalis,
    jabatans = [],
    tembusan = [],
    presensi_mode_histories = [],
    mode_options = [],
    mode_aktif = 'kantor',
    mode_aktif_label = 'Kantor',
    mode_aktif_sejak = null,
    mode_berikutnya = null,
}) {
    const { data, setData, post, transform, processing, errors, isDirty } = useForm({
        nama: unit.nama,
        singkatan: unit.singkatan,
        latitude: String(unit.latitude),
        longitude: String(unit.longitude),
        radius_meter: unit.radius_meter,
        durasi_jp: unit.durasi_jp || 45,
        toleransi_menit: unit.toleransi_menit ?? 0,
        toleransi_mengajar_menit: unit.toleransi_mengajar_menit ?? unit.toleransi_menit ?? 0,
        toleransi_slide_menit: unit.toleransi_slide_menit ?? 15,
        max_jeda_sesi_menit: unit.max_jeda_sesi_menit ?? 120,
        wajib_pulang_mengajar: unit.wajib_pulang_mengajar ? 1 : 0,
        pulang_sebelum_menit: unit.pulang_sebelum_menit ?? 20,
        max_lembur_jam: unit.max_lembur_jam ?? '',
        max_jam_minggu: unit.max_jam_minggu ?? 30,
        jam_masuk_kantor: unit.jam_masuk_kantor?.slice(0, 5) || '07:30',
        jam_pulang_kantor: unit.jam_pulang_kantor?.slice(0, 5) || '15:00',
        jam_kerja_sabtu_mulai: unit.jam_kerja_sabtu_mulai?.slice(0, 5) || null,
        jam_kerja_sabtu_selesai: unit.jam_kerja_sabtu_selesai?.slice(0, 5) || null,
        jam_kerja_minggu_mulai: unit.jam_kerja_minggu_mulai?.slice(0, 5) || null,
        jam_kerja_minggu_selesai: unit.jam_kerja_minggu_selesai?.slice(0, 5) || null,
        web: unit.web ?? '',
        telepon: unit.telepon ?? '',
        alamat: unit.alamat ?? '',
        tembusan_jabatan_ids: (tembusan || []).map(String),
        sakit_wajib_tugas: unit.sakit_wajib_tugas ? 1 : 0,
        bobot_sakit_surat: unit.bobot_sakit_surat ?? '',
        bobot_sakit_tanpa_surat: unit.bobot_sakit_tanpa_surat ?? '',
        bobot_izin: unit.bobot_izin ?? '',
        bobot_izin_setengah: unit.bobot_izin_setengah ?? '',
        bobot_cuti: unit.bobot_cuti ?? '',
        bobot_alpa: unit.bobot_alpa ?? '',
    });

    // PHP tidak parse multipart body pada HTTP PUT, jadi upload logo lewat PUT
    // membuat semua field kosong. POST + _method=PUT agar multipart terbaca.
    transform((request) => ({ ...request, _method: 'PUT' }));

    const [showLokasiForm, setShowLokasiForm] = React.useState(false);
    const [editLokasi, setEditLokasi] = React.useState(null);
    const lokasiForm = useForm({
        nama: '',
        latitude: '',
        longitude: '',
        radius_meter: 50,
    });

    const handleSubmitLokasi = (e) => {
        e.preventDefault();
        if (editLokasi) {
            lokasiForm.put(route('unit-sekolah.update-lokasi', [unit.id, editLokasi.id]), {
                onSuccess: () => { setShowLokasiForm(false); setEditLokasi(null); lokasiForm.reset(); },
            });
        } else {
            lokasiForm.post(route('unit-sekolah.store-lokasi', unit.id), {
                onSuccess: () => { setShowLokasiForm(false); lokasiForm.reset(); },
            });
        }
    };

    const handleDeleteLokasi = (lokasiId) => {
        if (!confirm('Hapus lokasi ini?')) return;
        router.delete(route('unit-sekolah.destroy-lokasi', [unit.id, lokasiId]));
    };

    const startEditLokasi = (l) => {
        setEditLokasi(l);
        lokasiForm.setData({ nama: l.nama, latitude: String(l.latitude), longitude: String(l.longitude), radius_meter: l.radius_meter });
        setShowLokasiForm(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('unit-sekolah.update', unit.id), { forceFormData: true });
    };

    const hasErrors = Object.keys(errors).length > 0;
    const lokasiCount = (lokalis || []).length;
    const tembusanCount = (tembusan || []).length;

    return (
        <AdminShell
            user={auth.user}
            header={<h2 className="page-title">Pengaturan Unit</h2>}
        >
            <Head title={`Edit Unit - ${unit.nama}`} />
            <div className="mx-auto max-w-7xl space-y-5">
                <PageHeader
                    eyebrow="Pengaturan"
                    title={unit.nama}
                    description="Kelola identitas, operasional, dan aturan presensi unit."
                    actions={(
                        <Link href={route('unit-sekolah.index')} className="inline-flex h-10 items-center gap-1.5 rounded-lg border border-border bg-white px-4 text-sm font-bold text-text-secondary transition-colors hover:border-primary/30 hover:text-primary">
                            <ArrowLeft className="h-4 w-4" /> Kembali
                        </Link>
                    )}
                />

                <div className="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
                    <aside className="order-first space-y-4 xl:sticky xl:top-24 xl:order-2 xl:self-start">
                        <UnitSummaryCard unit={unit} lokasiCount={lokasiCount} tembusanCount={tembusanCount} />
                        <ModePresensiSection
                            unit={unit}
                            histories={presensi_mode_histories}
                            modeOptions={mode_options}
                            modeAktif={mode_aktif}
                            modeAktifLabel={mode_aktif_label}
                            modeAktifSejak={mode_aktif_sejak}
                            modeBerikutnya={mode_berikutnya}
                        />
                    </aside>

                    <div className="order-2 space-y-5 xl:order-1">
                        <SettingsNav
                            items={[
                                { href: '#unit-identitas', label: 'Identitas' },
                                { href: '#unit-geofence', label: 'Geofence' },
                                { href: '#unit-jam', label: 'Jam Kerja' },
                                { href: '#unit-presensi', label: 'Presensi' },
                                { href: '#unit-tembusan', label: 'Tembusan', count: tembusanCount },
                                { href: '#unit-bobot', label: 'Bobot' },
                                { href: '#unit-kop', label: 'Kop Surat' },
                                { href: '#unit-lokasi', label: 'Lokasi', count: lokasiCount },
                                { href: '#unit-mode-presensi', label: 'Mode' },
                            ]}
                        />

                        <UnitForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing}
                            onSubmit={handleSubmit}
                            isEdit={true}
                            unitName={unit.nama}
                            unitLogoUrl={unit.logo_url}
                            jabatans={jabatans}
                            showTembusan={true}
                            showActions={false}
                            formId="unit-form"
                        />

                        <Card id="unit-lokasi" className="scroll-mt-32 p-5 sm:p-6">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h3 className="flex items-center gap-2 text-sm font-bold text-primary">
                                        <MapPin className="h-4 w-4" /> Lokasi Tambahan
                                        <span className="text-xs font-semibold text-text-muted">{lokasiCount}</span>
                                    </h3>
                                    <p className="mt-1 text-xs text-text-secondary">Titik geofence tambahan untuk kampus atau lokasi lain.</p>
                                </div>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => { setShowLokasiForm(!showLokasiForm); setEditLokasi(null); lokasiForm.reset(); }}
                                >
                                    <Plus className="h-4 w-4" /> Tambah Lokasi
                                </Button>
                            </div>

                            {showLokasiForm && (
                                <form onSubmit={handleSubmitLokasi} className="mt-4 space-y-4 rounded-xl border border-primary/10 bg-primary/5 p-4">
                                    <h4 className="text-sm font-bold text-primary">{editLokasi ? 'Edit Lokasi' : 'Tambah Lokasi Baru'}</h4>
                                    <FormField label="Nama Lokasi" htmlFor="lokasi-nama" error={lokasiForm.errors.nama}>
                                        <Input
                                            id="lokasi-nama"
                                            type="text"
                                            value={lokasiForm.data.nama}
                                            onChange={(e) => lokasiForm.setData('nama', e.target.value)}
                                            placeholder="Kampus 2 / Wilayah B"
                                            required
                                        />
                                    </FormField>
                                    <LeafletPicker
                                        lat={lokasiForm.data.latitude}
                                        lng={lokasiForm.data.longitude}
                                        radius={lokasiForm.data.radius_meter}
                                        onChange={(la, ln) => {
                                            lokasiForm.setData('latitude', la);
                                            lokasiForm.setData('longitude', ln);
                                        }}
                                    />
                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                        <FormField label="Latitude" htmlFor="lokasi-latitude">
                                            <Input id="lokasi-latitude" type="number" step="any" value={lokasiForm.data.latitude} onChange={(e) => lokasiForm.setData('latitude', e.target.value)} className="font-mono" required />
                                        </FormField>
                                        <FormField label="Longitude" htmlFor="lokasi-longitude">
                                            <Input id="lokasi-longitude" type="number" step="any" value={lokasiForm.data.longitude} onChange={(e) => lokasiForm.setData('longitude', e.target.value)} className="font-mono" required />
                                        </FormField>
                                    </div>
                                    <div>
                                        <label className="mb-1.5 block text-[11px] font-bold text-text-secondary">Radius Toleransi Absen (Meter)</label>
                                        <div className="flex items-center gap-3">
                                            <input type="range" min="10" max="10000" value={lokasiForm.data.radius_meter} onChange={(e) => lokasiForm.setData('radius_meter', Number(e.target.value))} className="flex-1 accent-primary" />
                                            <Input type="number" min="10" max="100000" value={lokasiForm.data.radius_meter} onChange={(e) => lokasiForm.setData('radius_meter', Number(e.target.value))} className="w-28" />
                                        </div>
                                        <p className="mt-1 text-[10px] text-text-muted">Pegawai hanya bisa absen jika jarak GPS mereka di dalam radius ini.</p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Button type="submit" disabled={lokasiForm.processing}>{lokasiForm.processing ? 'Menyimpan...' : 'Simpan'}</Button>
                                        <Button type="button" variant="secondary" onClick={() => { setShowLokasiForm(false); setEditLokasi(null); lokasiForm.reset(); }}>Batal</Button>
                                    </div>
                                </form>
                            )}

                            {lokasiCount === 0 ? (
                                <div className="mt-4 rounded-xl border border-border bg-surface p-4">
                                    <p className="text-sm font-semibold text-text-primary">Belum ada lokasi tambahan.</p>
                                    <p className="mt-1 text-xs text-text-secondary">Tambah lokasi jika unit punya kampus atau titik absen lain.</p>
                                </div>
                            ) : (
                                <div className="mt-4 overflow-x-auto rounded-xl border border-border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow className="hover:bg-transparent">
                                                <TableHead>Nama</TableHead>
                                                <TableHead>Koordinat</TableHead>
                                                <TableHead>Radius</TableHead>
                                                <TableHead className="text-right">Aksi</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {lokalis.map((l) => (
                                                <TableRow key={l.id}>
                                                    <TableCell className="font-medium">{l.nama}</TableCell>
                                                    <TableCell className="font-mono text-xs">{l.latitude}, {l.longitude}</TableCell>
                                                    <TableCell>{l.radius_meter} m</TableCell>
                                                    <TableCell className="text-right">
                                                        <div className="inline-flex items-center gap-2">
                                                            <Button type="button" variant="secondary" size="sm" onClick={() => startEditLokasi(l)}>Edit</Button>
                                                            <Button type="button" variant="secondary" size="sm" onClick={() => handleDeleteLokasi(l.id)} className="border-rose-200 text-rose-700 hover:bg-rose-50 hover:text-rose-800">
                                                                <Trash2 className="h-3.5 w-3.5" /> Hapus
                                                            </Button>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            )}
                        </Card>

                        <Card className="sticky bottom-20 z-20 p-3 shadow-sm lg:bottom-4">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <p className={`text-xs ${hasErrors ? 'font-semibold text-rose-600' : 'text-text-secondary'}`}>
                                    {hasErrors
                                        ? 'Ada input yang perlu diperiksa.'
                                        : isDirty
                                            ? 'Ada perubahan belum disimpan.'
                                            : 'Perubahan utama sudah tersimpan.'}
                                </p>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Link href={route('unit-sekolah.index')} className="inline-flex h-10 items-center rounded-lg border border-border bg-white px-4 text-sm font-bold text-text-secondary transition-colors hover:border-primary/30 hover:text-primary">
                                        Batal
                                    </Link>
                                    <Button type="submit" form="unit-form" disabled={processing}>
                                        {processing
                                            ? <><Loader2 className="h-4 w-4 animate-spin" /> Menyimpan...</>
                                            : <><Save className="h-4 w-4" /> Simpan Perubahan</>}
                                    </Button>
                                </div>
                            </div>
                            <p className="mt-2 text-[10px] text-text-muted">
                                Mode presensi dan lokasi tambahan disimpan dari kartunya masing-masing.
                            </p>
                        </Card>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
