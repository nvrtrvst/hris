import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { Save, Plus, Trash2, MapPin, ListChecks, Users, ShieldAlert } from 'lucide-react';

const TABS = [
    { key: 'area', label: 'Area & Kelas', Icon: MapPin },
    { key: 'pos', label: 'Pos', Icon: ShieldAlert },
    { key: 'item', label: 'Item Checklist', Icon: ListChecks },
    { key: 'penugasan', label: 'Penugasan', Icon: Users },
];

export default function PiketSettings({ auth, units = [], unit, areas = [], mapping = [], unmapped = [], pos = [], templates = [], penugasan = [], pegawai = [], sesiHariIni = null }) {
    const { flash = {} } = usePage().props;
    const [modeErr, setModeErr] = useState(null);
    const [tab, setTab] = useState('area');
    const [areaForm, setAreaForm] = useState({ id: '', nama: '', urutan: 1 });
    const [mapForm, setMapForm] = useState({ kelas_label: '', area_id: '' });
    const [posForm, setPosForm] = useState({ id: '', nama: '', area_id: '', jam_mulai: '06:30', jam_selesai: '07:15', is_wajib: false });
    const [itemForm, setItemForm] = useState({ id: '', kode: 'kelas_kosong', label: '', seksi: 'kbm', scope: 'area', wajib: true, urutan: 30 });
    const [tugasForm, setTugasForm] = useState({ pegawai_id: '', area_id: '', pos_id: '', peran: 'area' });
    const [koreksi, setKoreksi] = useState({ pos_id: '', petugas_id: '', alasan: '' });

    const post = (name, data) => router.post(route(name), data, { preserveScroll: true });
    const unitId = unit?.id;

    const simpanArea = (e) => {
        e.preventDefault();
        post('piket.settings.area', { ...areaForm, unit_sekolah_id: unitId });
        setAreaForm({ id: '', nama: '', urutan: areas.length + 1 });
    };

    const simpanKelas = (e) => {
        e.preventDefault();
        post('piket.settings.kelas', { ...mapForm, unit_sekolah_id: unitId });
        setMapForm({ kelas_label: '', area_id: '' });
    };

    const simpanPos = (e) => {
        e.preventDefault();
        post('piket.settings.pos', { ...posForm, unit_sekolah_id: unitId });
        setPosForm({ id: '', nama: '', area_id: '', jam_mulai: '06:30', jam_selesai: '07:15', is_wajib: false });
    };

    const simpanItem = (e) => {
        e.preventDefault();
        post('piket.settings.item', { ...itemForm, unit_sekolah_id: unitId });
    };

    const simpanTugas = (e) => {
        e.preventDefault();
        post('piket.settings.penugasan', { ...tugasForm, unit_sekolah_id: unitId });
        setTugasForm({ pegawai_id: '', area_id: '', pos_id: '', peran: 'area' });
    };

    return (
        <AuthenticatedLayout user={auth.user} header={<h2 className="page-title">Piket</h2>}>
            <Head title="Piket" />

            <div className="mx-auto w-full max-w-6xl space-y-4">
                {flash.error && (
                    <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                        {flash.error}
                    </div>
                )}
                {flash.message && (
                    <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                        {flash.message}
                    </div>
                )}

                {units.length === 0 ? (
                    <div className="card p-6 text-sm text-text-muted">
                        Belum ada unit dengan mode area. Aktifkan lewat seeder/migrasi terlebih dahulu.
                    </div>
                ) : (
                    <>
                        <div className="flex flex-wrap items-center gap-3">
                            <label className="text-xs font-semibold text-text-secondary">Unit</label>
                            <select
                                className="select-field w-auto"
                                value={unitId}
                                onChange={(e) => router.get(route('piket.settings.index'), { unit: e.target.value }, { preserveState: true })}
                            >
                                {units.map((u) => <option key={u.id} value={u.id}>{u.nama}</option>)}
                            </select>
                            <span className={`rounded-full px-2 py-1 text-[11px] font-bold uppercase ${unit?.piket_mode === 'area' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>
                                mode {unit?.piket_mode === 'area' ? 'area' : 'sederhana'}
                            </span>
                            <button
                                type="button"
                                onClick={() => post('piket.settings.mode', {
                                    unit_sekolah_id: unitId,
                                    mode: unit?.piket_mode === 'area' ? 'simple' : 'area',
                                })}
                                className="btn-secondary btn-sm"
                            >
                                {unit?.piket_mode === 'area' ? 'Kembali ke sederhana' : 'Aktifkan mode area'}
                            </button>
                        </div>

                        <div className="flex flex-wrap gap-1 rounded-xl border border-border bg-white p-1">
                            {TABS.map(({ key, label, Icon }) => (
                                <button
                                    key={key}
                                    type="button"
                                    onClick={() => setTab(key)}
                                    className={`inline-flex min-h-11 items-center gap-1.5 rounded-lg px-3 text-xs font-bold ${tab === key ? 'bg-primary text-white' : 'text-text-secondary'}`}
                                >
                                    <Icon className="h-4 w-4" /> {label}
                                </button>
                            ))}
                        </div>

                        {tab === 'area' && (
                            <div className="space-y-4">
                                <div className="card p-4">
                                    <h3 className="mb-3 text-sm font-extrabold uppercase tracking-wide text-primary">Area</h3>
                                    <ul className="mb-3 divide-y divide-border/60 text-sm">
                                        {areas.map((a) => <li key={a.id} className="py-2">{a.nama} <span className="text-xs text-text-muted">· urutan {a.urutan}</span></li>)}
                                    </ul>
                                    <form onSubmit={simpanArea} className="flex flex-wrap items-end gap-2">
                                        <label className="block">
                                            <span className="form-label text-xs">Nama area</span>
                                            <input className="input-field" value={areaForm.nama} onChange={(e) => setAreaForm({ ...areaForm, nama: e.target.value })} placeholder="Kampus 1" required />
                                        </label>
                                        <label className="block">
                                            <span className="form-label text-xs">Urutan</span>
                                            <input type="number" className="input-field w-24" value={areaForm.urutan} onChange={(e) => setAreaForm({ ...areaForm, urutan: e.target.value })} />
                                        </label>
                                        <button className="btn-primary inline-flex items-center gap-1.5"><Plus className="h-4 w-4" /> Tambah</button>
                                    </form>
                                </div>

                                <div className="card p-4">
                                    <h3 className="mb-3 text-sm font-extrabold uppercase tracking-wide text-primary">Pemetaan Kelas</h3>
                                    <ul className="mb-3 max-h-64 space-y-1 overflow-y-auto text-xs">
                                        {mapping.map((m) => <li key={m.id} className="flex justify-between gap-2"><span className="font-medium text-text-primary">{m.kelas_label}</span><span className="text-text-muted">{m.area}</span></li>)}
                                    </ul>
                                    <form onSubmit={simpanKelas} className="flex flex-wrap items-end gap-2">
                                        <label className="block">
                                            <span className="form-label text-xs">Kelas</span>
                                            <select className="select-field" value={mapForm.kelas_label} onChange={(e) => setMapForm({ ...mapForm, kelas_label: e.target.value })} required>
                                                <option value="">Pilih kelas</option>
                                                {unmapped.map((l) => <option key={l} value={l}>{l}</option>)}
                                                {mapping.map((m) => <option key={m.id} value={m.kelas_label}>{m.kelas_label} (pindah)</option>)}
                                            </select>
                                        </label>
                                        <label className="block">
                                            <span className="form-label text-xs">Area</span>
                                            <select className="select-field" value={mapForm.area_id} onChange={(e) => setMapForm({ ...mapForm, area_id: e.target.value })} required>
                                                <option value="">Pilih area</option>
                                                {areas.map((a) => <option key={a.id} value={a.id}>{a.nama}</option>)}
                                            </select>
                                        </label>
                                        <button className="btn-primary inline-flex items-center gap-1.5"><Save className="h-4 w-4" /> Simpan</button>
                                    </form>
                                    {unmapped.length > 0 && <p className="mt-2 text-xs text-amber-700">{unmapped.length} kelas belum dipetakan.</p>}
                                </div>
                            </div>
                        )}

                        {tab === 'pos' && (
                            <div className="space-y-4">
                                <div className="card p-4">
                                    <h3 className="mb-3 text-sm font-extrabold uppercase tracking-wide text-primary">Daftar Pos</h3>
                                    <div className="overflow-x-auto">
                                        <table className="table-base">
                                            <thead><tr><th>Nama</th><th>Area</th><th>Jendela</th><th>Wajib</th><th>Aktif</th></tr></thead>
                                            <tbody>
                                                {pos.map((p) => (
                                                    <tr key={p.id}>
                                                        <td>{p.nama}</td>
                                                        <td>{areas.find((a) => a.id === p.area_id)?.nama || '—'}</td>
                                                        <td className="font-mono text-xs">{p.jam_mulai}–{p.jam_selesai}</td>
                                                        <td>{p.is_wajib ? 'Ya' : 'Tidak'}</td>
                                                        <td>{p.aktif ? 'Ya' : 'Tidak'}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div className="card p-4">
                                    <h3 className="mb-3 text-sm font-extrabold uppercase tracking-wide text-primary">Tambah Pos</h3>
                                    <form onSubmit={simpanPos} className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                        <label className="block"><span className="form-label text-xs">Nama</span><input className="input-field" value={posForm.nama} onChange={(e) => setPosForm({ ...posForm, nama: e.target.value })} required /></label>
                                        <label className="block"><span className="form-label text-xs">Area</span>
                                            <select className="select-field" value={posForm.area_id} onChange={(e) => setPosForm({ ...posForm, area_id: e.target.value })}>
                                                <option value="">Semua area</option>
                                                {areas.map((a) => <option key={a.id} value={a.id}>{a.nama}</option>)}
                                            </select>
                                        </label>
                                        <label className="block"><span className="form-label text-xs">Mulai</span><input type="time" className="input-field" value={posForm.jam_mulai} onChange={(e) => setPosForm({ ...posForm, jam_mulai: e.target.value })} /></label>
                                        <label className="block"><span className="form-label text-xs">Selesai</span><input type="time" className="input-field" value={posForm.jam_selesai} onChange={(e) => setPosForm({ ...posForm, jam_selesai: e.target.value })} /></label>
                                        <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={posForm.is_wajib} onChange={(e) => setPosForm({ ...posForm, is_wajib: e.target.checked })} /> Wajib</label>
                                        <button className="btn-primary inline-flex items-center gap-1.5 self-end"><Plus className="h-4 w-4" /> Tambah</button>
                                    </form>
                                </div>
                            </div>
                        )}

                        {tab === 'item' && (
                            <div className="card p-4">
                                <h3 className="mb-3 text-sm font-extrabold uppercase tracking-wide text-primary">Item Checklist</h3>
                                <div className="overflow-x-auto">
                                    <table className="table-base">
                                        <thead><tr><th>Label</th><th>Kode</th><th>Seksi</th><th>Scope</th><th>Wajib</th><th>Aktif</th></tr></thead>
                                        <tbody>
                                            {templates.map((t) => (
                                                <tr key={t.id}>
                                                    <td>{t.label}</td>
                                                    <td className="font-mono text-xs">{t.kode}</td>
                                                    <td>{t.seksi}</td>
                                                    <td>{t.scope}</td>
                                                    <td>{t.wajib ? 'Ya' : 'Tidak'}</td>
                                                    <td>{t.aktif ? 'Ya' : 'Tidak'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        {tab === 'penugasan' && (
                            <div className="space-y-4">
                                <div className="card p-4">
                                    <h3 className="mb-3 text-sm font-extrabold uppercase tracking-wide text-primary">Petugas</h3>
                                    <ul className="divide-y divide-border/60 text-sm">
                                        {penugasan.map((p) => (
                                            <li key={p.id} className="flex items-center justify-between gap-2 py-2">
                                                <span>{p.pegawai?.nama_lengkap} · {p.area?.nama || '—'} {p.pos ? `· ${p.pos.nama}` : ''} <span className="text-xs text-text-muted">({p.peran})</span></span>
                                                <button type="button" onClick={() => router.delete(route('piket.settings.penugasan.hapus', p.id), { preserveScroll: true })} className="rounded-lg p-2 text-rose-600 hover:bg-rose-50" aria-label="Hapus penugasan"><Trash2 className="h-4 w-4" /></button>
                                            </li>
                                        ))}
                                        {penugasan.length === 0 && <li className="py-2 text-text-muted">Belum ada penugasan.</li>}
                                    </ul>
                                </div>
                                <div className="card p-4">
                                    <h3 className="mb-3 text-sm font-extrabold uppercase tracking-wide text-primary">Tambah Penugasan</h3>
                                    <form onSubmit={simpanTugas} className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <label className="block"><span className="form-label text-xs">Pegawai</span>
                                            <select className="select-field" value={tugasForm.pegawai_id} onChange={(e) => setTugasForm({ ...tugasForm, pegawai_id: e.target.value })} required>
                                                <option value="">Pilih pegawai</option>
                                                {pegawai.map((p) => <option key={p.id} value={p.id}>{p.nama_lengkap}</option>)}
                                            </select>
                                        </label>
                                        <label className="block"><span className="form-label text-xs">Peran</span>
                                            <select className="select-field" value={tugasForm.peran} onChange={(e) => setTugasForm({ ...tugasForm, peran: e.target.value })}>
                                                <option value="area">Area</option>
                                                <option value="gerbang">Gerbang</option>
                                                <option value="backup">Backup</option>
                                            </select>
                                        </label>
                                        <label className="block"><span className="form-label text-xs">Area</span>
                                            <select className="select-field" value={tugasForm.area_id} onChange={(e) => setTugasForm({ ...tugasForm, area_id: e.target.value })}>
                                                <option value="">Tanpa area</option>
                                                {areas.map((a) => <option key={a.id} value={a.id}>{a.nama}</option>)}
                                            </select>
                                        </label>
                                        <label className="block"><span className="form-label text-xs">Pos</span>
                                            <select className="select-field" value={tugasForm.pos_id} onChange={(e) => setTugasForm({ ...tugasForm, pos_id: e.target.value })}>
                                                <option value="">Tanpa pos</option>
                                                {pos.map((p) => <option key={p.id} value={p.id}>{p.nama}</option>)}
                                            </select>
                                        </label>
                                        <button className="btn-primary inline-flex items-center gap-1.5 self-end"><Save className="h-4 w-4" /> Simpan</button>
                                    </form>
                                </div>
                            </div>
                        )}

                        {sesiHariIni && (
                            <div className="card p-4">
                                <h3 className="mb-2 text-sm font-extrabold uppercase tracking-wide text-primary">Sesi hari ini · {sesiHariIni.jam}</h3>
                                <p className="text-xs text-text-muted">
                                    Petugas: {sesiHariIni.petugas.map((p) => `${p.nama} (${p.peran})`).join(', ') || '—'} · status {sesiHariIni.status}
                                </p>
                                {sesiHariIni.pos_belum.length > 0 ? (
                                    <div className="mt-3 space-y-3">
                                        <p className="text-sm font-semibold text-rose-700">Belum terlayani: {sesiHariIni.pos_belum.join(', ')}</p>
                                        <form onSubmit={(e) => { e.preventDefault(); router.post(route('piket.sesi.pos.layani', [sesiHariIni.id, koreksi.pos_id]), { ...koreksi }, { preserveScroll: true }); }} className="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                            <select className="select-field" value={koreksi.pos_id} onChange={(e) => setKoreksi({ ...koreksi, pos_id: e.target.value })} required>
                                                <option value="">Pilih pos</option>
                                                {sesiHariIni.pos.filter((p) => p.is_wajib).map((p) => <option key={p.id} value={p.id}>{p.nama}</option>)}
                                            </select>
                                            <select className="select-field" value={koreksi.petugas_id} onChange={(e) => setKoreksi({ ...koreksi, petugas_id: e.target.value })}>
                                                <option value="">Petugas pengganti (opsional)</option>
                                                {pegawai.map((p) => <option key={p.id} value={p.id}>{p.nama_lengkap}</option>)}
                                            </select>
                                            <input className="input-field" placeholder="Alasan" value={koreksi.alasan} onChange={(e) => setKoreksi({ ...koreksi, alasan: e.target.value })} required />
                                            <button className="btn-primary">Tandai terlayani</button>
                                        </form>
                                    </div>
                                ) : (
                                    <p className="mt-2 text-sm font-semibold text-emerald-700">Semua pos wajib terlayani.</p>
                                )}
                            </div>
                        )}
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
