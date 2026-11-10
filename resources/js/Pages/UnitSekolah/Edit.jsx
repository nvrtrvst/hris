import React from 'react';
import AdminShell from '@/Layouts/AdminShell';
import { Button, Card, FormField, Input, PageHeader, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/AdminUI';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { ArrowLeft, MapPin, Plus, Trash2 } from 'lucide-react';
import UnitForm from './UnitForm';
import LeafletPicker from '@/Components/LeafletPicker';
import ModePresensiSection from './Partials/ModePresensiSection';

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
    const { data, setData, post, transform, processing, errors } = useForm({
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

    return (
        <AdminShell
            user={auth.user}
            header={<h2 className="page-title">Edit Unit: {unit.nama}</h2>}
        >
            <Head title={`Edit Unit - ${unit.nama}`} />
            <div className="mx-auto max-w-5xl space-y-5">
                <Link href={route('unit-sekolah.index')} className="inline-flex items-center gap-1.5 text-sm font-semibold text-text-secondary transition-colors hover:text-primary">
                    <ArrowLeft className="h-4 w-4" /> Kembali ke Daftar Unit
                </Link>
                <PageHeader
                    eyebrow="Pengaturan"
                    title={`Edit Unit: ${unit.nama}`}
                    description="Perbarui identitas, geofence, dan pengaturan presensi unit."
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
                />

                <ModePresensiSection
                    unit={unit}
                    histories={presensi_mode_histories}
                    modeOptions={mode_options}
                    modeAktif={mode_aktif}
                    modeAktifLabel={mode_aktif_label}
                    modeAktifSejak={mode_aktif_sejak}
                    modeBerikutnya={mode_berikutnya}
                />

                <Card className="p-5 sm:p-6">
                    <h3 className="text-sm font-bold text-primary">Tembusan Pengajuan Izin</h3>
                    <p className="mt-1 text-xs leading-relaxed text-text-secondary">
                        Jabatan yang otomatis menerima tembusan notifikasi setiap ada pengajuan izin di unit ini.
                    </p>
                    <div className="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        {jabatans.map((j) => {
                            const checked = (data.tembusan_jabatan_ids || []).map(String).includes(String(j.id));

                            return (
                                <label key={j.id} className="flex cursor-pointer items-center gap-2.5 rounded-xl border border-border px-3 py-2.5 text-sm hover:border-primary/50">
                                    <input
                                        type="checkbox"
                                        checked={checked}
                                        onChange={(e) => {
                                            const cur = (data.tembusan_jabatan_ids || []).map(String);
                                            setData('tembusan_jabatan_ids', e.target.checked ? [...cur, String(j.id)] : cur.filter((x) => x !== String(j.id)));
                                        }}
                                        className="h-5 w-5 rounded border-border text-primary focus:ring-primary/25"
                                    />
                                    <span className="font-medium text-primary">{j.nama}</span>
                                </label>
                            );
                        })}
                    </div>
                    {errors.tembusan_jabatan_ids && <p className="mt-2 text-xs font-semibold text-rose-600">{errors.tembusan_jabatan_ids}</p>}
                </Card>

                <Card className="p-5 sm:p-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 className="flex items-center gap-2 text-sm font-bold text-primary">
                                <MapPin className="h-4 w-4" /> Lokasi / Kampus
                            </h3>
                            <p className="mt-1 text-xs text-text-secondary">Titik geofence tambahan untuk unit ini.</p>
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

                    {(lokalis || []).length === 0 ? (
                        <p className="mt-4 text-sm text-text-secondary">Belum ada lokasi tambahan.</p>
                    ) : (
                        <div className="mt-4 overflow-hidden rounded-xl border border-border">
                            <Table>
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead>Nama</TableHead>
                                        <TableHead>Latitude</TableHead>
                                        <TableHead>Longitude</TableHead>
                                        <TableHead>Radius</TableHead>
                                        <TableHead className="text-right">Aksi</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {lokalis.map((l) => (
                                        <TableRow key={l.id}>
                                            <TableCell className="font-medium">{l.nama}</TableCell>
                                            <TableCell className="font-mono text-xs">{l.latitude}</TableCell>
                                            <TableCell className="font-mono text-xs">{l.longitude}</TableCell>
                                            <TableCell>{l.radius_meter}m</TableCell>
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
            </div>
        </AdminShell>
    );
}
