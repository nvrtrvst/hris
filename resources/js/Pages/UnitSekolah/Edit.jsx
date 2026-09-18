import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2, MapPin } from 'lucide-react';
import UnitForm from './UnitForm';

export default function Edit({ auth, unit, lokalis }) {
    const { data, setData, put, processing, errors } = useForm({
        nama: unit.nama,
        singkatan: unit.singkatan,
        latitude: String(unit.latitude),
        longitude: String(unit.longitude),
        radius_meter: unit.radius_meter,
        durasi_jp: unit.durasi_jp || 45,
        toleransi_menit: unit.toleransi_menit ?? 0,
        toleransi_slide_menit: unit.toleransi_slide_menit ?? 15,
        max_jam_minggu: unit.max_jam_minggu ?? 30,
        jam_masuk_kantor: unit.jam_masuk_kantor?.slice(0, 5) || '07:30',
        jam_pulang_kantor: unit.jam_pulang_kantor?.slice(0, 5) || '15:00',
        jam_kerja_sabtu_mulai: unit.jam_kerja_sabtu_mulai?.slice(0, 5) || null,
        jam_kerja_sabtu_selesai: unit.jam_kerja_sabtu_selesai?.slice(0, 5) || null,
        web: unit.web ?? '',
        telepon: unit.telepon ?? '',
        alamat: unit.alamat ?? '',
    });

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
        put(route('unit-sekolah.update', unit.id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Edit Unit: {unit.nama}</h2>}
        >
            <Head title={`Edit Unit - ${unit.nama}`} />
            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    <Link href={route('unit-sekolah.index')} className="inline-flex items-center gap-1.5 text-sm font-semibold text-text-secondary transition-colors hover:text-primary">
                        <ArrowLeft className="h-4 w-4" /> Kembali ke Daftar Unit
                    </Link>
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

                    {/* Lokasi / Kampus Section */}
                    <div className="page-card">
                        <div className="p-6">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="section-title flex items-center gap-2">
                                    <MapPin className="h-5 w-5 text-primary" />
                                    Lokasi / Kampus
                                </h3>
                                <button
                                    type="button"
                                    onClick={() => { setShowLokasiForm(!showLokasiForm); setEditLokasi(null); lokasiForm.reset(); }}
                                    className="btn-primary text-sm"
                                >
                                    <Plus className="h-4 w-4 mr-1 inline" /> Tambah Lokasi
                                </button>
                            </div>

                            {showLokasiForm && (
                                <form onSubmit={handleSubmitLokasi} className="bg-primary-50 p-4 rounded-card border border-primary/10 mb-4 space-y-3">
                                    <h4 className="text-sm font-semibold text-primary">{editLokasi ? 'Edit Lokasi' : 'Tambah Lokasi Baru'}</h4>
                                    <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
                                        <div>
                                            <label className="form-label">Nama Lokasi</label>
                                            <input type="text" value={lokasiForm.data.nama} onChange={e => lokasiForm.setData('nama', e.target.value)} className="input-field" placeholder="Wilayah B / Kampus 2" required />
                                            {lokasiForm.errors.nama && <p className="form-error">{lokasiForm.errors.nama}</p>}
                                        </div>
                                        <div>
                                            <label className="form-label">Latitude</label>
                                            <input type="number" step="any" value={lokasiForm.data.latitude} onChange={e => lokasiForm.setData('latitude', e.target.value)} className="input-field" required />
                                        </div>
                                        <div>
                                            <label className="form-label">Longitude</label>
                                            <input type="number" step="any" value={lokasiForm.data.longitude} onChange={e => lokasiForm.setData('longitude', e.target.value)} className="input-field" required />
                                        </div>
                                        <div>
                                            <label className="form-label">Radius (meter)</label>
                                            <input type="number" value={lokasiForm.data.radius_meter} onChange={e => lokasiForm.setData('radius_meter', Number(e.target.value))} className="input-field" min="10" max="100000" required />
                                        </div>
                                    </div>
                                    <div className="flex gap-2">
                                        <button type="submit" disabled={lokasiForm.processing} className="btn-primary text-sm">{lokasiForm.processing ? 'Menyimpan...' : 'Simpan'}</button>
                                        <button type="button" onClick={() => { setShowLokasiForm(false); setEditLokasi(null); lokasiForm.reset(); }} className="btn-secondary text-sm">Batal</button>
                                    </div>
                                </form>
                            )}

                            {(lokalis || []).length === 0 ? (
                                <p className="text-sm text-text-secondary italic">Belum ada lokasi tambahan. Klik "Tambah Lokasi" untuk menambah wilayah/kampus kedua.</p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="table-clean text-sm">
                                        <thead>
                                            <tr>
                                                <th className="text-left py-2 px-3">Nama</th>
                                                <th className="text-left py-2 px-3">Latitude</th>
                                                <th className="text-left py-2 px-3">Longitude</th>
                                                <th className="text-left py-2 px-3">Radius</th>
                                                <th className="text-right py-2 px-3">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {lokalis.map(l => (
                                                <tr key={l.id} className="border-t border-border">
                                                    <td className="py-2 px-3 font-medium">{l.nama}</td>
                                                    <td className="py-2 px-3 font-mono text-xs">{l.latitude}</td>
                                                    <td className="py-2 px-3 font-mono text-xs">{l.longitude}</td>
                                                    <td className="py-2 px-3">{l.radius_meter}m</td>
                                                    <td className="py-2 px-3 text-right">
                                                        <button type="button" onClick={() => startEditLokasi(l)} className="text-primary hover:underline text-xs mr-2">Edit</button>
                                                        <button type="button" onClick={() => handleDeleteLokasi(l.id)} className="text-danger hover:underline text-xs">Hapus</button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
