import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router, Link } from '@inertiajs/react';
import { ArrowLeft, Coins, Grid3X3, Loader2, Pencil, Save, Trash2, Users as UsersIcon } from 'lucide-react';
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

export default function Komponen({ auth, komponens, units }) {
    const [isEditing, setIsEditing] = useState(false);
    const [editId, setEditId] = useState(null);

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

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Pengaturan Komponen Gaji</h2>}
        >
            <Head title="Komponen Gaji" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 flex flex-col lg:flex-row gap-4 items-start">
                    {/* Form */}
                    <div className="w-full lg:w-1/3 space-y-4 lg:sticky lg:top-6">
                        <Link href={route('komponen-gaji.index')} className="inline-flex items-center gap-1.5 text-sm font-semibold text-text-secondary transition-colors hover:text-primary lg:hidden">
                            <ArrowLeft className="h-4 w-4" /> Kembali ke Payroll
                        </Link>
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
                                <SectionCardIconless>
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
                                            <option value="pendapatan">Pendapatan (+)</option>
                                            <option value="potongan">Potongan (-)</option>
                                        </select>
                                    </Field>
                                    <Field label="Jenis Perhitungan" required error={errors.jenis}>
                                        <select value={data.jenis} onChange={(e) => setData('jenis', e.target.value)} className={selectClass}>
                                            <option value="fixed">Fixed (Nominal Pasti)</option>
                                            <option value="persentase">Persentase (dari Gaji Pokok)</option>
                                            <option value="dinamis_kehadiran">Dinamis Kehadiran (Uang Makan / Telat)</option>
                                            <option value="dinamis_jam_mengajar">Dinamis Jam Mengajar (Honor JTM)</option>
                                            <option value="dinamis_masa_bakti">Dinamis Masa Bakti (Otomatis dari Skala)</option>
                                        </select>
                                    </Field>
                                    <Field label="Nilai Default (Rp / %)" error={errors.nilai_default}
                                        hint="Biarkan kosong jika nilai diatur spesifik per pegawai.">
                                        <input type="number" step="0.01" value={data.nilai_default} onChange={(e) => setData('nilai_default', e.target.value)}
                                            placeholder="Contoh: 5000000 atau 5 untuk 5%" className={inputClass} />
                                    </Field>
                                    <Field label="Status Kepegawaian" error={errors.applies_to_status_kepegawaian}>
                                        <select value={data.applies_to_status_kepegawaian} onChange={(e) => setData('applies_to_status_kepegawaian', e.target.value)} className={selectClass}>
                                            <option value="">Semua Status</option>
                                            <option value="tetap">Tetap / GTYS</option>
                                            <option value="honorer">Honorer</option>
                                        </select>
                                    </Field>
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
                                    <Field label="Urutan Matrix" hint="Gaji Pokok biasanya urutan 1">
                                        <input type="number" value={data.urutan} onChange={(e) => setData('urutan', e.target.value)} className={inputClass} />
                                    </Field>
                                </SectionCardIconless>

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

                                <div className="flex justify-end border-t border-border pt-4">
                                    <button type="submit" disabled={processing} className="btn-primary flex items-center gap-2">
                                        {processing ? <><Loader2 className="h-4 w-4 animate-spin" /> Menyimpan…</> : <><Save className="h-4 w-4" /> {isEditing ? 'Update Komponen' : 'Simpan Komponen'}</>}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {/* Table */}
                    <div className="w-full lg:w-2/3">
                        <div className="card overflow-hidden">
                            <div className="flex flex-col gap-3 border-b border-border bg-surface px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <h3 className="text-base font-extrabold text-text-primary">Daftar Komponen (Master Data)</h3>
                                <Link href={route('komponen-gaji.matrix')} className="btn-secondary btn-sm inline-flex items-center gap-1.5">
                                    <Grid3X3 className="h-4 w-4" /> Buka Matrix Gaji
                                </Link>
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
                                        {komponens.length > 0 ? komponens.map((k) => (
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
                                                                {k.unit_sekolah_id && <span className="rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-semibold text-sky-700">Unit</span>}
                                                                {k.is_taxable == 1 && <span className="rounded-full bg-surface px-2 py-0.5 text-[10px] font-semibold text-text-muted">Taxable</span>}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4">
                                                    <span className={`inline-flex rounded-full border px-2.5 py-1 text-[11px] font-bold ${k.tipe === 'pendapatan' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700'}`}>
                                                        {k.tipe === 'pendapatan' ? '+' : '-'} {k.tipe}
                                                    </span>
                                                    <div className="mt-1 text-[11px] font-medium uppercase tracking-wide text-text-muted">{k.jenis.replace('_', ' ')}</div>
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-text-secondary tabular-nums">
                                                    {k.nilai_default ? (k.jenis === 'persentase' ? `${k.nilai_default}%` : formatRupiah(k.nilai_default)) : '-'}
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
                                                    <Coins className="mx-auto h-7 w-7 text-border" />
                                                    <p className="mt-2 text-sm font-semibold text-text-primary">Belum ada komponen gaji</p>
                                                    <p className="text-xs text-text-muted">Konfigurasi komponen pertama untuk memulai perhitungan payroll.</p>
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
        </AuthenticatedLayout>
    );
}

/** Wrapper 1 kolom untuk form komponen (panel sempit). */
function SectionCardIconless({ children }) {
    return <div className="grid grid-cols-1 gap-4">{children}</div>;
}
