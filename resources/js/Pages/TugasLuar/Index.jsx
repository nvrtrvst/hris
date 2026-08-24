import React from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import { CalendarDays, MapPin, Trash2, Plus } from 'lucide-react';

export default function Index({ auth, tugasLuar, units, pegawais, filters = {} }) {
    const { flash = {} } = usePage().props;
    const [pegawaiId, setPegawaiId] = React.useState('');
    const [unitId, setUnitId] = React.useState(filters.unit_sekolah_id || '');
    const [tanggal, setTanggal] = React.useState('');
    const [jamMulai, setJamMulai] = React.useState('');
    const [jamSelesai, setJamSelesai] = React.useState('');
    const [tujuan, setTujuan] = React.useState('');
    const [keterangan, setKeterangan] = React.useState('');
    const [processing, setProcessing] = React.useState(false);
    const [error, setError] = React.useState(null);

    const submit = (e) => {
        e.preventDefault();
        setError(null);
        setProcessing(true);
        router.post(route('tugas-luar.store'), {
            pegawai_id: pegawaiId,
            unit_sekolah_id: unitId || null,
            tanggal,
            jam_mulai: jamMulai || null,
            jam_selesai: jamSelesai || null,
            tujuan,
            keterangan: keterangan || null,
        }, {
            preserveScroll: true,
            onError: (err) => setError(err.tujuan || err.pegawai_id || err.tanggal || 'Data tidak valid.'),
            onFinish: () => {
                setProcessing(false);
                setTujuan('');
                setKeterangan('');
                setJamMulai('');
                setJamSelesai('');
            },
        });
    };

    const hapus = (id) => {
        if (!confirm('Hapus jadwal tugas luar ini?')) return;
        router.delete(route('tugas-luar.destroy', id), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="page-title">Kelola Tugas Luar</h2>}>
            <Head title="Tugas Luar" />
            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    {flash?.message && <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.message}</div>}

                    <div className="card p-5">
                        <h3 className="text-sm font-bold text-primary mb-3 flex items-center gap-2"><Plus className="h-4 w-4" /> Tambah Jadwal Tugas Luar</h3>
                        <form onSubmit={submit} className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <select className="input-field text-xs h-9" value={pegawaiId} onChange={(e) => setPegawaiId(e.target.value)} required>
                                <option value="">Pilih Pegawai</option>
                                {pegawais.map((pg) => <option key={pg.id} value={pg.id}>{pg.nama_lengkap}</option>)}
                            </select>
                            <select className="input-field text-xs h-9" value={unitId} onChange={(e) => setUnitId(e.target.value)}>
                                <option value="">Unit (otomatis primer)</option>
                                {units.map((u) => <option key={u.id} value={u.id}>{u.nama}</option>)}
                            </select>
                            <input type="date" className="input-field text-xs h-9" value={tanggal} onChange={(e) => setTanggal(e.target.value)} required />
                            <input type="time" className="input-field text-xs h-9" value={jamMulai} onChange={(e) => setJamMulai(e.target.value)} placeholder="Jam mulai" />
                            <input type="time" className="input-field text-xs h-9" value={jamSelesai} onChange={(e) => setJamSelesai(e.target.value)} placeholder="Jam selesai" />
                            <input type="text" className="input-field text-xs h-9" value={tujuan} onChange={(e) => setTujuan(e.target.value)} placeholder="Tujuan" required />
                            <textarea className="input-field text-xs h-9 py-2 sm:col-span-2 lg:col-span-3" value={keterangan} onChange={(e) => setKeterangan(e.target.value)} placeholder="Keterangan (opsional)" />
                            {error && <p className="text-xs font-semibold text-rose-600 sm:col-span-3">{error}</p>}
                            <button type="submit" disabled={processing} className="btn-primary btn-sm flex items-center gap-1.5 sm:col-span-3 w-fit">
                                {processing ? 'Menyimpan...' : <><Plus className="h-3.5 w-3.5" /> Simpan Jadwal</>}
                            </button>
                        </form>
                    </div>

                    <div className="card p-0 overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-border">
                                <thead className="bg-surface/80">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Pegawai</th>
                                        <th className="px-4 py-3 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Tanggal</th>
                                        <th className="px-4 py-3 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Jam</th>
                                        <th className="px-4 py-3 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Tujuan</th>
                                        <th className="px-4 py-3 text-left text-[11px] font-bold text-text-secondary uppercase tracking-wider">Dibuat Oleh</th>
                                        <th className="px-4 py-3 text-right text-[11px] font-bold text-text-secondary uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/50">
                                    {tugasLuar.data.map((t) => (
                                        <tr key={t.id} className="hover:bg-surface/70">
                                            <td className="px-4 py-3">
                                                <div className="text-sm font-bold text-primary">{t.pegawai?.nama_lengkap || '-'}</div>
                                                <div className="text-[11px] text-text-secondary">{t.unit_sekolah?.nama || '—'}</div>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm font-semibold text-primary">{t.tanggal}</td>
                                            <td className="px-4 py-3 whitespace-nowrap text-xs text-text-secondary">
                                                {t.jam_mulai ? `${t.jam_mulai.substring(0, 5)}` : '—'}{t.jam_selesai ? `–${t.jam_selesai.substring(0, 5)}` : ''}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-start gap-1.5 text-xs text-primary max-w-[260px]">
                                                    <MapPin className="h-3.5 w-3.5 mt-0.5 shrink-0 text-sky-500" />
                                                    <div>
                                                        <div className="font-semibold">{t.tujuan}</div>
                                                        {t.keterangan && <div className="text-text-secondary">{t.keterangan}</div>}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-xs text-text-secondary">{t.created_by ? (t.created_by.name || t.createdBy?.name || '-') : '—'}</td>
                                            <td className="px-4 py-3 whitespace-nowrap text-right">
                                                <button onClick={() => hapus(t.id)} className="rounded-lg p-1.5 text-rose-600 transition-colors hover:bg-rose-50" title="Hapus"><Trash2 className="h-4 w-4" /></button>
                                            </td>
                                        </tr>
                                    ))}
                                    {tugasLuar.data.length === 0 && (
                                        <tr>
                                            <td colSpan="6" className="px-6 py-16 text-center">
                                                <div className="flex flex-col items-center">
                                                    <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-surface">
                                                        <CalendarDays className="h-8 w-8 text-border" />
                                                    </div>
                                                    <p className="mt-4 text-base font-bold text-primary">Belum ada jadwal tugas luar</p>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <div className="p-4">
                            <Pagination links={tugasLuar.links} />
                        </div>
                    </div>

                    <div>
                        <Link href={route('presensi.index')} className="btn-secondary btn-sm">Kembali ke Presensi</Link>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
