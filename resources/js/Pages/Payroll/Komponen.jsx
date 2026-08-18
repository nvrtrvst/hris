import React, { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router, Link } from '@inertiajs/react';
import { Coins, Grid3X3, Loader2, Pencil, Plus, Save, Search, Trash2, Users as UsersIcon } from 'lucide-react';
import { formatRupiah } from '@/Utils/format';
import { avatarTone, initials } from '@/Utils/avatar';

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

// Label & deskripsi jenis perhitungan — satu sumber, dipakai form + tabel.
const JENIS_META = {
    fixed: { label: 'Fixed', desc: 'Nominal pasti per bulan', tone: 'border-slate-200 bg-slate-50 text-slate-700' },
    persentase: { label: 'Persentase', desc: '% dari gaji pokok', tone: 'border-violet-200 bg-violet-50 text-violet-700' },
    dinamis_kehadiran: { label: 'Dinamis Kehadiran', desc: 'Berubah sesuai absensi', tone: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
    dinamis_jam_mengajar: { label: 'Honor Jam Mengajar', desc: 'Dihitung dari JTM', tone: 'border-sky-200 bg-sky-50 text-sky-700' },
    dinamis_masa_bakti: { label: 'Masa Bakti', desc: 'Otomatis dari skala', tone: 'border-amber-200 bg-amber-50 text-amber-700' },
    dinamis_lembur: { label: 'Lembur', desc: 'Dihitung dari jam lembur', tone: 'border-orange-200 bg-orange-50 text-orange-700' },
};

const StatPill = ({ label, value, cls }) => (
    <div className="card flex items-center gap-3 p-4">
        <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-extrabold ${cls.bg}`}>
            <cls.Icon className={`h-5 w-5 ${cls.fg}`} />
        </span>
        <div className="min-w-0">
            <p className="text-xl font-extrabold leading-none text-text-primary tabular-nums">{value}</p>
            <p className="mt-1 truncate text-[11px] font-semibold uppercase tracking-wide text-text-secondary">{label}</p>
        </div>
    </div>
);

export default function Komponen({ auth, komponens, units }) {
    const [isEditing, setIsEditing] = useState(false);
    const [editId, setEditId] = useState(null);
    const [search, setSearch] = useState('');
    const [tipeFilter, setTipeFilter] = useState('');

    const { data, setData, post, put, reset, processing, errors, clearErrors } = useForm({
        nama: '',
        kode: '',
        tipe: 'pendapatan',
        jenis: 'fixed',
        applies_to_status_kepegawaian: '',
        syarat_bayar_jam_mengajar: '',
        nilai_default: '',
        unit_sekolah_id: '',
        is_taxable: true,
        is_active: true,
        urutan: 99,
        tampil_di_matrix: true,
    });

    const handleEdit = (k) => {
        setIsEditing(true);
        setEditId(k.id);
        setData({
            nama: k.nama,
            kode: k.kode || '',
            tipe: k.tipe,
            jenis: k.jenis,
            applies_to_status_kepegawaian: k.applies_to_status_kepegawaian || '',
            syarat_bayar_jam_mengajar: k.syarat_bayar_jam_mengajar || '',
            nilai_default: k.nilai_default || '',
            unit_sekolah_id: k.unit_sekolah_id || '',
            is_taxable: k.is_taxable == 1,
            is_active: k.is_active == 1,
            urutan: k.urutan || 99,
            tampil_di_matrix: k.tampil_di_matrix == 1,
        });
        clearErrors();
    };

    const handleCancel = () => {
        setIsEditing(false);
        setEditId(null);
        reset();
        clearErrors();
    };

    const submit = (e) => {
        e.preventDefault();
        if (isEditing) {
            put(route('komponen-gaji.update', editId), {
                onSuccess: () => handleCancel()
            });
        } else {
            post(route('komponen-gaji.store'), {
                onSuccess: () => handleCancel()
            });
        }
    };

    // Filter instan (client-side) — data master kecil, tanpa round-trip server.
    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();

        return komponens.filter((k) => {
            if (tipeFilter && k.tipe !== tipeFilter) return false;
            if (!q) return true;

            return k.nama.toLowerCase().includes(q)
                || (k.kode || '').toLowerCase().includes(q);
        });
    }, [komponens, search, tipeFilter]);

    const stats = {
        total: komponens.length,
        pendapatan: komponens.filter((k) => k.tipe === 'pendapatan').length,
        potongan: komponens.filter((k) => k.tipe === 'potongan').length,
        aktif: komponens.filter((k) => k.is_active == 1).length,
    };

    const tipeCounts = {
        '': komponens.length,
        pendapatan: stats.pendapatan,
        potongan: stats.potongan,
    };

    const scrollToForm = () => {
        document.getElementById('komponen-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Pengaturan Komponen Gaji</h2>}
        >
            <Head title="Komponen Gaji" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    {/* Header */}
                    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h3 className="text-xl font-extrabold text-text-primary">Komponen Gaji</h3>
                            <p className="mt-0.5 text-sm text-text-muted">Kelola master komponen pendapatan & potongan yang dipakai perhitungan payroll.</p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('komponen-gaji.matrix')} className="btn-secondary inline-flex items-center gap-1.5">
                                <Grid3X3 className="h-4 w-4" /> Buka Matrix Gaji
                            </Link>
                            <button type="button" onClick={() => { handleCancel(); scrollToForm(); }} className="btn-primary inline-flex items-center gap-1.5 lg:hidden">
                                <Plus className="h-4 w-4" /> Tambah Komponen
                            </button>
                        </div>
                    </div>

                    {/* Stats ringkas */}
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <StatPill label="Total Komponen" value={stats.total} cls={{ Icon: Coins, bg: 'bg-primary/10', fg: 'text-primary' }} />
                        <StatPill label="Pendapatan" value={stats.pendapatan} cls={{ Icon: Plus, bg: 'bg-emerald-100', fg: 'text-emerald-600' }} />
                        <StatPill label="Potongan" value={stats.potongan} cls={{ Icon: Trash2, bg: 'bg-rose-100', fg: 'text-rose-600' }} />
                        <StatPill label="Aktif" value={stats.aktif} cls={{ Icon: Save, bg: 'bg-sky-100', fg: 'text-sky-600' }} />
                    </div>

                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start">
                        {/* Form */}
                        <div id="komponen-form" className="w-full scroll-mt-6 lg:w-1/3 lg:sticky lg:top-6">
                            <div className="card overflow-hidden">
                                <div className="flex items-center justify-between border-b border-border bg-surface px-5 py-3.5">
                                    <h3 className="flex items-center gap-2 text-sm font-extrabold uppercase tracking-wide text-primary">
                                        <Coins className="h-4 w-4" />
                                        {isEditing ? 'Edit Komponen' : 'Tambah Komponen'}
                                    </h3>
                                    {isEditing && (
                                        <button type="button" onClick={handleCancel} className="btn-secondary btn-sm">Batal</button>
                                    )}
                                </div>
                                <form onSubmit={submit} className="space-y-4 p-5">
                                    <Field label="Nama Komponen" required error={errors.nama}>
                                        <input type="text" value={data.nama} onChange={(e) => setData('nama', e.target.value)}
                                            placeholder="Contoh: Gaji Pokok / PPh21" className={inputClass} />
                                    </Field>
                                    <Field label="Kode (stabil untuk logika)" error={errors.kode}
                                        hint="Isi agar payroll tidak bergantung pada nama. Kosong = pakai pencocokan nama (legacy).">
                                        <input type="text" value={data.kode} onChange={(e) => setData('kode', e.target.value)}
                                            placeholder="cth: gaji_pokok, kehadiran_telat, tunjangan_kehadiran" className={inputClass} />
                                    </Field>
                                    <Field label="Tipe" required error={errors.tipe}>
                                        <select value={data.tipe} onChange={(e) => setData('tipe', e.target.value)} className={selectClass}>
                                            <option value="pendapatan">Pendapatan (+) — menambah gaji</option>
                                            <option value="potongan">Potongan (−) — mengurangi gaji</option>
                                        </select>
                                    </Field>
                                    <Field label="Jenis Perhitungan" required error={errors.jenis}>
                                        <select value={data.jenis} onChange={(e) => setData('jenis', e.target.value)} className={selectClass}>
                                            <option value="fixed">Fixed (Nominal Pasti)</option>
                                            <option value="persentase">Persentase (dari Gaji Pokok)</option>
                                            <option value="dinamis_kehadiran">Dinamis Kehadiran (Uang Makan / Telat)</option>
                                            <option value="dinamis_jam_mengajar">Dinamis Jam Mengajar (Honor JTM)</option>
                                            <option value="dinamis_masa_bakti">Dinamis Masa Bakti (Otomatis dari Skala)</option>
                                            <option value="dinamis_lembur">Dinamis Lembur (Dihitung dari Jam Lembur)</option>
                                        </select>
                                        {JENIS_META[data.jenis] && (
                                            <p className="mt-1.5 rounded-lg border border-border bg-surface px-2.5 py-1.5 text-[11px] font-medium text-text-secondary">
                                                {JENIS_META[data.jenis].desc}
                                            </p>
                                        )}
                                    </Field>
                                    <Field label="Nilai Default (Rp / %)" error={errors.nilai_default}
                                        hint="Biarkan kosong jika nilai diatur spesifik per pegawai. Untuk persentase isi angka saja (cth: 5 = 5%).">
                                        <input type="number" step="0.01" value={data.nilai_default} onChange={(e) => setData('nilai_default', e.target.value)}
                                            placeholder="Contoh: 5000000 atau 5 untuk 5%" className={inputClass} />
                                    </Field>
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field label="Status Kepegawaian" error={errors.applies_to_status_kepegawaian}>
                                            <select value={data.applies_to_status_kepegawaian} onChange={(e) => setData('applies_to_status_kepegawaian', e.target.value)} className={selectClass}>
                                                <option value="">Semua</option>
                                                <option value="tetap">Tetap / GTYS</option>
                                                <option value="honorer">Honorer</option>
                                            </select>
                                        </Field>
                                        <Field label="Urutan Matrix" error={errors.urutan}>
                                            <input type="number" value={data.urutan} onChange={(e) => setData('urutan', e.target.value)} className={inputClass} />
                                        </Field>
                                    </div>
                                    <Field label="Unit (khusus Honor Jam Mengajar)" error={errors.unit_sekolah_id}
                                        hint="Isi bila komponen hanya berlaku untuk unit tertentu (mis. Honor Mengajar unit TK).">
                                        <select value={data.unit_sekolah_id} onChange={(e) => setData('unit_sekolah_id', e.target.value)} className={selectClass}>
                                            <option value="">Semua Unit</option>
                                            {units && units.map((u) => (
                                                <option key={u.id} value={u.id}>{u.nama}</option>
                                            ))}
                                        </select>
                                    </Field>
                                    {data.jenis === 'dinamis_jam_mengajar' && (
                                        <Field label="Syarat Bayar Jam Mengajar" error={errors.syarat_bayar_jam_mengajar}
                                            hint="'Hanya Hadir' = jam dihitung dari jadwal yg ada presensi hadir/telat. 'Semua Jadwal' = semua jam jadwal dibayar.">
                                            <select value={data.syarat_bayar_jam_mengajar} onChange={(e) => setData('syarat_bayar_jam_mengajar', e.target.value)} className={selectClass}>
                                                <option value="hanya_hadir">Hanya Jadwal dengan Presensi Hadir/Telat</option>
                                                <option value="semua_jadwal">Semua Jadwal (tanpa cek presensi)</option>
                                            </select>
                                        </Field>
                                    )}

                                    <div className="space-y-2 rounded-xl border border-border bg-surface p-4">
                                        <label className="flex cursor-pointer items-center justify-between text-sm font-medium text-text-primary">
                                            <span>Tampil di Matrix</span>
                                            <input type="checkbox" checked={data.tampil_di_matrix} onChange={(e) => setData('tampil_di_matrix', e.target.checked)}
                                                className="h-4 w-4 rounded border-border text-primary focus:ring-primary" />
                                        </label>
                                        <label className="flex cursor-pointer items-center justify-between text-sm font-medium text-text-primary">
                                            <span>Taxable (Kena PPh21)</span>
                                            <input type="checkbox" checked={data.is_taxable} onChange={(e) => setData('is_taxable', e.target.checked)}
                                                className="h-4 w-4 rounded border-border text-primary focus:ring-primary" />
                                        </label>
                                        <label className="flex cursor-pointer items-center justify-between text-sm font-medium text-text-primary">
                                            <span>Aktif</span>
                                            <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)}
                                                className="h-4 w-4 rounded border-border text-primary focus:ring-primary" />
                                        </label>
                                    </div>

                                    <div className="flex justify-end gap-2 border-t border-border pt-4">
                                        {isEditing && (
                                            <button type="button" onClick={handleCancel} className="btn-secondary">Batal</button>
                                        )}
                                        <button type="submit" disabled={processing} className="btn-primary flex items-center gap-2">
                                            {processing ? <><Loader2 className="h-4 w-4 animate-spin" /> Menyimpan…</> : <><Save className="h-4 w-4" /> {isEditing ? 'Update Komponen' : 'Simpan Komponen'}</>}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        {/* Tabel */}
                        <div className="w-full lg:w-2/3">
                            <div className="card overflow-hidden">
                                <div className="flex flex-col gap-3 border-b border-border bg-surface px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                                    <h3 className="text-base font-extrabold text-text-primary">Daftar Komponen</h3>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {/* Segmented filter tipe */}
                                        <div className="flex rounded-lg border border-border bg-white p-0.5">
                                            {[{ k: '', label: `Semua (${tipeCounts['']})` }, { k: 'pendapatan', label: `+ Pendapatan (${tipeCounts.pendapatan})` }, { k: 'potongan', label: `− Potongan (${tipeCounts.potongan})` }].map((t) => (
                                                <button
                                                    key={t.k}
                                                    type="button"
                                                    onClick={() => setTipeFilter(t.k)}
                                                    className={`rounded-md px-2.5 py-1 text-[11px] font-bold transition-colors ${tipeFilter === t.k ? 'bg-primary text-white' : 'text-text-secondary hover:text-primary'}`}
                                                >
                                                    {t.label}
                                                </button>
                                            ))}
                                        </div>
                                        <div className="relative sm:w-56">
                                            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                            <input
                                                type="text"
                                                value={search}
                                                onChange={(e) => setSearch(e.target.value)}
                                                placeholder="Cari nama / kode…"
                                                className="input-field h-9 pl-9 text-xs"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-border">
                                        <thead className="bg-surface">
                                            <tr>
                                                <th className="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Nama</th>
                                                <th className="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Kategori</th>
                                                <th className="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Nilai Default</th>
                                                <th className="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-text-muted">Status</th>
                                                <th className="px-6 py-3.5 text-right text-xs font-bold uppercase tracking-wider text-text-muted">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border bg-white">
                                            {filtered.length > 0 ? filtered.map((k) => (
                                                <tr key={k.id} className="transition-colors hover:bg-surface">
                                                    <td className="whitespace-nowrap px-6 py-4">
                                                        <div className="flex items-center gap-3">
                                                            <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-extrabold ${avatarTone(k.nama)}`}>
                                                                {initials(k.nama)}
                                                            </span>
                                                            <div className="min-w-0">
                                                                <p className="truncate text-sm font-bold text-text-primary">{k.nama}</p>
                                                                <div className="mt-0.5 flex flex-wrap gap-1">
                                                                    {k.kode && <span className="rounded-full bg-surface px-2 py-0.5 text-[10px] font-semibold text-text-muted">{k.kode}</span>}
                                                                    {k.applies_to_status_kepegawaian && <span className="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700">{k.applies_to_status_kepegawaian}</span>}
                                                                    {k.syarat_bayar_jam_mengajar && <span className="rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-semibold text-sky-700">{k.syarat_bayar_jam_mengajar === 'hanya_hadir' ? 'Bayar Hadir' : 'Bayar Semua'}</span>}
                                                                    {k.unit_sekolah_id && <span className="rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-semibold text-sky-700">Unit Tertentu</span>}
                                                                    {k.is_taxable == 1 && <span className="rounded-full bg-surface px-2 py-0.5 text-[10px] font-semibold text-text-muted">Taxable</span>}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="whitespace-nowrap px-6 py-4">
                                                        <span className={`inline-flex rounded-full border px-2.5 py-1 text-[11px] font-bold ${k.tipe === 'pendapatan' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700'}`}>
                                                            {k.tipe === 'pendapatan' ? '+' : '−'} {k.tipe}
                                                        </span>
                                                        <div className="mt-1 flex items-center gap-1.5">
                                                            <span className={`inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ${JENIS_META[k.jenis]?.tone || 'border-gray-200 bg-gray-50 text-gray-600'}`}>
                                                                {JENIS_META[k.jenis]?.label || k.jenis}
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-text-secondary tabular-nums">
                                                        {k.nilai_default !== null && k.nilai_default !== '' && k.nilai_default != 0
                                                            ? (k.jenis === 'persentase' ? `${k.nilai_default}%` : formatRupiah(k.nilai_default))
                                                            : <span className="text-xs italic text-text-muted">Default / per pegawai</span>}
                                                    </td>
                                                    <td className="whitespace-nowrap px-6 py-4">
                                                        <div className="flex flex-col gap-1">
                                                            <span className={`inline-flex w-max rounded-full border px-2.5 py-0.5 text-[11px] font-bold ${k.is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-gray-200 bg-gray-100 text-gray-600'}`}>
                                                                {k.is_active ? 'Aktif' : 'Nonaktif'}
                                                            </span>
                                                            <span className={`inline-flex w-max rounded-full border px-2.5 py-0.5 text-[11px] font-bold ${k.tampil_di_matrix ? 'border-sky-200 bg-sky-50 text-sky-700' : 'border-amber-200 bg-amber-50 text-amber-700'}`}>
                                                                {k.tampil_di_matrix ? `Matrix (Urutan ${k.urutan})` : 'Sembunyi dari Matrix'}
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td className="whitespace-nowrap px-6 py-4 text-right">
                                                        <div className="inline-flex items-center gap-2">
                                                            <Link href={route('komponen-gaji.pegawai.index', k.id)} title="Atur nominal khusus per pegawai"
                                                                className="btn-secondary btn-sm inline-flex items-center gap-1.5">
                                                                <UsersIcon className="h-3.5 w-3.5" /> Pegawai
                                                            </Link>
                                                            <button onClick={() => handleEdit(k)} className="btn-secondary btn-sm inline-flex items-center gap-1.5">
                                                                <Pencil className="h-3.5 w-3.5" /> Edit
                                                            </button>
                                                            <button
                                                                onClick={() => {
                                                                    if (confirm('Hapus komponen ini?')) {
                                                                        router.delete(route('komponen-gaji.destroy', k.id));
                                                                    }
                                                                }}
                                                                className="btn-danger btn-sm inline-flex items-center gap-1.5"
                                                            >
                                                                <Trash2 className="h-3.5 w-3.5" /> Hapus
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            )) : (
                                                <tr>
                                                    <td colSpan="5" className="px-6 py-12 text-center">
                                                        <Search className="mx-auto h-7 w-7 text-border" />
                                                        <p className="mt-2 text-sm font-semibold text-text-primary">
                                                            {komponens.length === 0 ? 'Belum ada komponen gaji' : 'Tidak ada komponen yang cocok'}
                                                        </p>
                                                        <p className="text-xs text-text-muted">
                                                            {komponens.length === 0
                                                                ? 'Konfigurasi komponen pertama untuk memulai perhitungan payroll.'
                                                                : 'Coba ubah kata kunci atau filter tipe.'}
                                                        </p>
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
