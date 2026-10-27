import React, { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router, Link } from '@inertiajs/react';
import { AlertTriangle, Coins, Grid3X3, Loader2, Pencil, Plus, Save, Search, Trash2, Users as UsersIcon } from 'lucide-react';
import Modal from '@/Components/Modal';
import { formatRupiah } from '@/Utils/format';

const inputClass = 'input-field';
const selectClass = 'select-field';
const aksiClass = 'btn-secondary btn-sm inline-flex h-11 w-11 items-center justify-center';
const aksiHapusClass = 'btn-danger btn-sm inline-flex h-11 w-11 items-center justify-center';
const CONTROL_TAGS = ['input', 'select', 'textarea'];

// Field mengasosiasikan label ke kontrol pertama di dalamnya (htmlFor + aria-describedby)
// supaya pesan error dan hint terbaca pembaca layar.
const Field = ({ id, label, required, error, hint, children, className = '' }) => {
    const describedBy = error ? `${id}-error` : hint ? `${id}-hint` : undefined;
    let attached = false;
    const content = React.Children.map(children, (child) => {
        if (attached || !React.isValidElement(child) || !CONTROL_TAGS.includes(child.type)) return child;
        attached = true;

        return React.cloneElement(child, {
            id,
            'aria-describedby': describedBy,
            'aria-invalid': error ? true : undefined,
        });
    });

    return (
        <div className={className}>
            <label htmlFor={attached ? id : undefined} className="form-label text-xs">
                {label} {required && <span className="text-danger" aria-hidden="true">*</span>}
            </label>
            {content}
            {error && <p id={`${id}-error`} role="alert" className="form-error">{error}</p>}
            {hint && !error && <p id={`${id}-hint`} className="form-hint">{hint}</p>}
        </div>
    );
};

// Label & deskripsi jenis perhitungan, satu sumber, dipakai form + tabel.
// Satu tone netral untuk semua jenis (audit 001 #9): pembeda cukup dari label teks,
// bukan warna; jadi palet halaman tetap dalam 3 peran warna.
const JENIS_META = {
    fixed: { label: 'Fixed', desc: 'Nominal pasti per bulan' },
    persentase: { label: 'Persentase', desc: '% dari gaji pokok' },
    dinamis_kehadiran: { label: 'Dinamis Kehadiran', desc: 'Berubah sesuai absensi' },
    dinamis_jam_mengajar: { label: 'Honor Jam Mengajar', desc: 'Dihitung dari JTM' },
    dinamis_masa_bakti: { label: 'Masa Bakti', desc: 'Otomatis dari skala' },
    dinamis_lembur: { label: 'Lembur', desc: 'Dihitung dari jam lembur' },
    dinamis_jabatan: { label: 'Tunjangan Jabatan', desc: 'Jumlah nominal semua jabatan pegawai' },
};

export default function Komponen({ auth, komponens, units, jabatans = [], jabatanMappings = {} }) {
    const [isEditing, setIsEditing] = useState(false);
    const [editId, setEditId] = useState(null);
    const [search, setSearch] = useState('');
    const [tipeFilter, setTipeFilter] = useState('');
    const [konfirmasiHapus, setKonfirmasiHapus] = useState(null);
    const [menghapus, setMenghapus] = useState(false);

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
        tampil_di_matrix: true,
        urutan: 99,
        jabatan_nominals: [],
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
            jabatan_nominals: (jabatanMappings[k.id] || []).map((m) => ({ jabatan_id: String(m.jabatan_id), nominal: m.nominal })),
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

    const hapusKomponen = () => {
        if (!konfirmasiHapus) return;

        setMenghapus(true);
        router.delete(route('komponen-gaji.destroy', konfirmasiHapus.id), {
            preserveScroll: true,
            onFinish: () => {
                setMenghapus(false);
                setKonfirmasiHapus(null);
            },
        });
    };

    // Filter instan (client-side), data master kecil, tanpa round-trip server.
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
        const kurangiGerak = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;

        document.getElementById('komponen-form')?.scrollIntoView({
            behavior: kurangiGerak ? 'auto' : 'smooth',
            block: 'start',
        });
    };

    const isLoading = processing || menghapus;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="page-title">Komponen Gaji</h2>}
        >
            <Head title="Komponen Gaji" />

            <div className="bg-surface min-h-screen">
                <div className="mx-auto w-full space-y-4">
                    {/* Deskripsi + aksi */}
                    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <p className="text-sm text-text-secondary">Kelola master komponen pendapatan & potongan yang dipakai perhitungan payroll.</p>
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('komponen-gaji.matrix')} className="btn-secondary inline-flex min-h-[44px] items-center gap-1.5">
                                <Grid3X3 className="h-4 w-4" /> Buka Matrix Gaji
                            </Link>
                            <button type="button" onClick={() => { handleCancel(); scrollToForm(); }} className="btn-primary inline-flex min-h-[44px] items-center gap-1.5 lg:hidden">
                                <Plus className="h-4 w-4" /> Tambah Komponen
                            </button>
                        </div>
                    </div>

                    {/* Ringkasan satu baris (audit 001 #4): angka sudah terlihat di tabel,
                        kartu metrik hanya menfonduang tempat tanpa memandu keputusan. */}
                    <p className="text-xs font-semibold text-text-secondary">
                        {stats.total} komponen · {stats.pendapatan} pendapatan · {stats.potongan} potongan · {stats.aktif} aktif
                    </p>

                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start">
                        {/* Form */}
                        <div id="komponen-form" className="w-full scroll-mt-6 lg:w-1/3 lg:sticky lg:top-6">
                            <div className="card overflow-hidden">
                                <div className="flex items-center justify-between border-b border-border bg-surface px-5 py-3.5">
                                    <h3 className="flex items-center gap-2 text-sm font-extrabold text-primary">
                                        <Coins className="h-4 w-4" aria-hidden="true" />
                                        {isEditing ? 'Edit Komponen' : 'Tambah Komponen'}
                                    </h3>
                                    {isEditing && (
                                        <button type="button" onClick={handleCancel} className="btn-secondary btn-sm">Batal</button>
                                    )}
                                </div>
                                <form onSubmit={submit} className="space-y-4 p-5">
                                    <Field id="komponen-nama" label="Nama Komponen" required error={errors.nama}>
                                        <input type="text" value={data.nama} onChange={(e) => setData('nama', e.target.value)}
                                            placeholder="Contoh: Gaji Pokok / PPh21" className={inputClass} />
                                    </Field>
                                    <Field id="komponen-kode" label="Kode (stabil untuk logika)" error={errors.kode}
                                        hint="Isi agar payroll tidak bergantung pada nama. Kosong = pakai pencocokan nama (legacy).">
                                        <input type="text" value={data.kode} onChange={(e) => setData('kode', e.target.value)}
                                            placeholder="cth: gaji_pokok, kehadiran_telat, tunjangan_kehadiran" className={inputClass} />
                                    </Field>
                                    <Field id="komponen-tipe" label="Tipe" required error={errors.tipe}>
                                        <select value={data.tipe} onChange={(e) => setData('tipe', e.target.value)} className={selectClass}>
                                            <option value="pendapatan">Pendapatan (+), menambah gaji</option>
                                            <option value="potongan">Potongan (-), mengurangi gaji</option>
                                        </select>
                                    </Field>
                                    <Field id="komponen-jenis" label="Jenis Perhitungan" required error={errors.jenis}>
                                        <select value={data.jenis} onChange={(e) => setData('jenis', e.target.value)} className={selectClass}>
                                            <option value="fixed">Fixed (Nominal Pasti)</option>
                                            <option value="persentase">Persentase (dari Gaji Pokok)</option>
                                            <option value="dinamis_kehadiran">Dinamis Kehadiran (Uang Makan / Telat)</option>
                                            <option value="dinamis_jam_mengajar">Dinamis Jam Mengajar (Honor JTM)</option>
                                            <option value="dinamis_masa_bakti">Dinamis Masa Bakti (Otomatis dari Skala)</option>
                                            <option value="dinamis_lembur">Dinamis Lembur (Dihitung dari Jam Lembur)</option>
                                            <option value="dinamis_jabatan">Dinamis Jabatan (Tunjangan per Jabatan)</option>
                                        </select>
                                        {JENIS_META[data.jenis] && (
                                            <p className="mt-1.5 rounded-lg border border-border bg-surface px-2.5 py-1.5 text-[11px] font-medium text-text-secondary">
                                                {JENIS_META[data.jenis].desc}
                                            </p>
                                        )}
                                    </Field>
                                    <Field id="komponen-nilai-default" label="Nilai Default (Rp / %)" error={errors.nilai_default}
                                        hint="Biarkan kosong jika nilai diatur spesifik per pegawai. Untuk persentase isi angka saja (cth: 5 = 5%).">
                                        <input type="number" step="0.01" value={data.nilai_default} onChange={(e) => setData('nilai_default', e.target.value)}
                                            placeholder="Contoh: 5000000 atau 5 untuk 5%" className={inputClass} />
                                    </Field>
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field id="komponen-status-kepegawaian" label="Status Kepegawaian" error={errors.applies_to_status_kepegawaian}>
                                            <select value={data.applies_to_status_kepegawaian} onChange={(e) => setData('applies_to_status_kepegawaian', e.target.value)} className={selectClass}>
                                                <option value="">Semua</option>
                                                <option value="tetap">Tetap (GTYS / PTY)</option>
                                                <option value="honorer">Non-Tetap</option>
                                            </select>
                                        </Field>
                                        <Field id="komponen-urutan" label="Urutan Matrix" error={errors.urutan}>
                                            <input type="number" value={data.urutan} onChange={(e) => setData('urutan', e.target.value)} className={inputClass} />
                                        </Field>
                                    </div>
                                    <Field id="komponen-unit" label="Unit (khusus Honor Jam Mengajar)" error={errors.unit_sekolah_id}
                                        hint="Isi bila komponen hanya berlaku untuk unit tertentu (mis. Honor Mengajar unit TK).">
                                        <select value={data.unit_sekolah_id} onChange={(e) => setData('unit_sekolah_id', e.target.value)} className={selectClass}>
                                            <option value="">Semua Unit</option>
                                            {units && units.map((u) => (
                                                <option key={u.id} value={u.id}>{u.nama}</option>
                                            ))}
                                        </select>
                                    </Field>
                                    {data.jenis === 'dinamis_jabatan' && (
                                        <Field id="komponen-jabatan-nominals" label="Nominal per Jabatan" error={errors.jabatan_nominals}
                                            hint="Rangkap jabatan dijumlah semua (full sebulan). Override manual per pegawai tetap menang.">
                                            <div className="space-y-2">
                                                {(data.jabatan_nominals || []).map((row, i) => (
                                                    <div key={i} className="flex items-center gap-2">
                                                        <select
                                                            value={row.jabatan_id}
                                                            onChange={(e) => {
                                                                const next = [...(data.jabatan_nominals || [])];
                                                                next[i] = { ...next[i], jabatan_id: e.target.value };
                                                                setData('jabatan_nominals', next);
                                                            }}
                                                            className={`${selectClass} flex-1`}
                                                            aria-label="Jabatan"
                                                        >
                                                            <option value="">Pilih jabatan</option>
                                                            {jabatans.map((j) => (
                                                                <option key={j.id} value={j.id} disabled={(data.jabatan_nominals || []).some((r, ri) => ri !== i && String(r.jabatan_id) === String(j.id))}>
                                                                    {j.nama}
                                                                </option>
                                                            ))}
                                                        </select>
                                                        <input
                                                            type="number" min="0" step="0.01"
                                                            value={row.nominal}
                                                            onChange={(e) => {
                                                                const next = [...(data.jabatan_nominals || [])];
                                                                next[i] = { ...next[i], nominal: e.target.value };
                                                                setData('jabatan_nominals', next);
                                                            }}
                                                            placeholder="Rp"
                                                            className={`${inputClass} w-36`}
                                                            aria-label="Nominal"
                                                        />
                                                        <button
                                                            type="button"
                                                            onClick={() => setData('jabatan_nominals', (data.jabatan_nominals || []).filter((_, ri) => ri !== i))}
                                                            className="inline-flex min-h-[44px] shrink-0 items-center rounded-button px-3 text-xs font-bold text-danger transition-colors hover:bg-danger-light"
                                                            title={`Hapus nominal jabatan baris ${i + 1}`}
                                                        >
                                                            Hapus
                                                        </button>
                                                    </div>
                                                ))}
                                                <button
                                                    type="button"
                                                    onClick={() => setData('jabatan_nominals', [...(data.jabatan_nominals || []), { jabatan_id: '', nominal: '' }])}
                                                    className="text-xs font-bold text-primary hover:underline"
                                                >
                                                    + Tambah jabatan
                                                </button>
                                            </div>
                                        </Field>
                                    )}
                                    {data.jenis === 'dinamis_jam_mengajar' && (
                                        <Field id="komponen-syarat-jam-mengajar" label="Syarat Bayar Jam Mengajar" error={errors.syarat_bayar_jam_mengajar}
                                            hint="Hanya Hadir = potong per JP. Hadir Harian = semua JP dibayar jika ada presensi hadir/telat. Sebelum JP Pertama = presensi harus sebelum JP pertama, memakai toleransi unit.">
                                            <select value={data.syarat_bayar_jam_mengajar} onChange={(e) => setData('syarat_bayar_jam_mengajar', e.target.value)} className={selectClass}>
                                                <option value="hanya_hadir">Hanya Jadwal dengan Presensi Hadir/Telat</option>
                                                <option value="semua_jadwal">Semua Jadwal (tanpa cek presensi)</option>
                                                <option value="hadir_harian">Semua Jadwal Jika Ada Presensi Harian</option>
                                                <option value="hadir_harian_pagi">Semua Jadwal Jika Presensi Sebelum JP Pertama</option>
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
                                <div className="flex flex-col gap-3 border-b border-border bg-surface px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                    <h3 className="flex flex-wrap items-baseline gap-2 text-base font-extrabold text-text-primary">
                                        Daftar Komponen
                                        <span className="text-xs font-semibold text-text-muted">{filtered.length} dari {komponens.length}</span>
                                    </h3>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {/* Segmented filter tipe */}
                                        <div className="flex rounded-lg border border-border bg-surface p-0.5" role="group" aria-label="Filter tipe komponen">
                                            {[{ k: '', label: `Semua (${tipeCounts['']})` }, { k: 'pendapatan', label: `+ Pendapatan (${tipeCounts.pendapatan})` }, { k: 'potongan', label: `- Potongan (${tipeCounts.potongan})` }].map((t) => (
                                                <button
                                                    key={t.k}
                                                    type="button"
                                                    onClick={() => setTipeFilter(t.k)}
                                                    aria-pressed={tipeFilter === t.k}
                                                    className={`inline-flex min-h-[44px] items-center rounded-md px-3 text-xs font-bold transition-colors ${tipeFilter === t.k ? 'bg-primary text-white' : 'text-text-secondary hover:bg-surface hover:text-primary'}`}
                                                >
                                                    {t.label}
                                                </button>
                                            ))}
                                        </div>
                                        <div className="relative sm:w-56">
                                            <label htmlFor="komponen-cari" className="sr-only">Cari komponen</label>
                                            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" aria-hidden="true" />
                                            <input
                                                id="komponen-cari"
                                                type="text"
                                                value={search}
                                                onChange={(e) => setSearch(e.target.value)}
                                                placeholder="Cari nama / kode…"
                                                className="input-field h-11 pl-9 text-xs"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div className="overflow-x-auto lg:max-h-[72vh] lg:overflow-y-auto">
                                    <table className="min-w-full divide-y divide-border" aria-busy={isLoading}>
                                        <caption className="sr-only">
                                            Daftar komponen gaji, menampilkan {filtered.length} dari {komponens.length} komponen
                                        </caption>
                                        <thead className="sticky top-0 z-10 bg-surface">
                                            <tr>
                                                <th scope="col" className="px-4 py-3.5 text-left text-xs font-semibold text-text-secondary sm:px-6">Nama</th>
                                                <th scope="col" className="px-4 py-3.5 text-left text-xs font-semibold text-text-secondary sm:px-6">Kategori</th>
                                                <th scope="col" className="px-4 py-3.5 text-left text-xs font-semibold text-text-secondary sm:px-6">Nilai Default</th>
                                                <th scope="col" className="px-4 py-3.5 text-left text-xs font-semibold text-text-secondary sm:px-6">Status</th>
                                                <th scope="col" className="px-4 py-3.5 text-right text-xs font-semibold text-text-secondary sm:px-6">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border bg-white">
                                            {isLoading ? (
                                                <tr>
                                                    <td colSpan="5" className="px-4 py-4 sm:px-6">
                                                        <span className="sr-only">Memuat data komponen gaji</span>
                                                        <div className="space-y-3">
                                                            {[0, 1, 2, 3].map((i) => (
                                                                <div key={i} className="flex items-center gap-3">
                                                                    <span className="h-10 w-10 shrink-0 rounded-xl bg-border/60" />
                                                                    <span className="h-3.5 w-44 rounded-full bg-border/60" />
                                                                    <span className="ml-auto h-3.5 w-24 rounded-full bg-border/60" />
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ) : filtered.length > 0 ? filtered.map((k) => (
                                                <tr key={k.id} className="transition-colors hover:bg-surface">
                                                    <td className="whitespace-nowrap px-4 py-3.5 sm:px-6">
                                                        <p className="text-sm font-bold text-text-primary">{k.nama}</p>
                                                        {k.kode && <p className="mt-0.5 text-[11px] text-text-secondary">{k.kode}</p>}
                                                    </td>
                                                    <td className="px-4 py-3.5 sm:px-6">
                                                        {/* Satu badge berwarna hanya untuk tipe (audit 001 #1):
                                                            pendapatan = emerald, potongan = netral dengan marker minus. */}
                                                        <span className={`inline-flex rounded-full border px-2.5 py-1 text-[11px] font-bold ${k.tipe === 'pendapatan' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-border bg-surface text-text-secondary'}`}>
                                                            {k.tipe === 'pendapatan' ? '+ Pendapatan' : '- Potongan'}
                                                        </span>
                                                        <p className="mt-1 text-xs font-medium text-text-primary">{JENIS_META[k.jenis]?.label || k.jenis}</p>
                                                        {(() => {
                                                            // Atribut opsional jadi satu baris teks kecil (audit 001 #2),
                                                            // hanya yang di-set: bukan lima pill berwarna.
                                                            const attr = [
                                                                k.applies_to_status_kepegawaian === 'tetap' ? 'Tetap (GTYS/PTY)' : k.applies_to_status_kepegawaian ? 'Non-Tetap' : null,
                                                                k.syarat_bayar_jam_mengajar === 'hanya_hadir' ? 'Potong per JP' : k.syarat_bayar_jam_mengajar === 'hadir_harian' ? 'Hadir Harian' : k.syarat_bayar_jam_mengajar === 'hadir_harian_pagi' ? 'Sebelum JP Pertama' : k.syarat_bayar_jam_mengajar ? 'Bayar Semua' : null,
                                                                k.unit_sekolah_id ? 'Unit khusus' : null,
                                                                k.is_taxable == 1 ? 'Taxable' : null,
                                                            ].filter(Boolean);
                                                            return attr.length > 0 && <p className="text-[11px] text-text-secondary">{attr.join(' · ')}</p>;
                                                        })()}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3.5 text-sm font-medium text-text-secondary tabular-nums sm:px-6">
                                                        {k.nilai_default !== null && k.nilai_default !== '' && k.nilai_default != 0
                                                            ? (k.jenis === 'persentase' ? `${k.nilai_default}%` : formatRupiah(k.nilai_default))
                                                            : <span className="text-xs italic text-text-muted">Default / per pegawai</span>}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3.5 sm:px-6">
                                                        {/* Titik status (audit 001 #8): aktif/nonaktif cukup dot + teks,
                                                            info matrix sekunder satu baris teks, du dua tidak bertumpuk. */}
                                                        <span className={`inline-flex items-center gap-1.5 text-xs font-semibold ${k.is_active ? 'text-emerald-700' : 'text-text-secondary'}`}>
                                                            <span className={`h-2 w-2 rounded-full ${k.is_active ? 'bg-emerald-500' : 'bg-slate-300'}`} aria-hidden="true" />
                                                            {k.is_active ? 'Aktif' : 'Nonaktif'}
                                                        </span>
                                                        <p className="text-[11px] text-text-secondary">
                                                            {k.tampil_di_matrix ? `Matrix · urutan ${k.urutan}` : 'Di luar Matrix'}
                                                        </p>
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3.5 text-right sm:px-6">
                                                        <div className="inline-flex items-center gap-2">
                                                            <Link href={route('komponen-gaji.pegawai.index', k.id)} title="Atur nominal khusus per pegawai" className={aksiClass}>
                                                                <UsersIcon className="h-4 w-4" aria-hidden="true" />
                                                                <span className="sr-only">Pegawai: atur nominal khusus {k.nama}</span>
                                                            </Link>
                                                            <button type="button" onClick={() => handleEdit(k)} className={aksiClass} title="Edit komponen ini">
                                                                <Pencil className="h-4 w-4" aria-hidden="true" />
                                                                <span className="sr-only">Edit {k.nama}</span>
                                                            </button>
                                                            <button type="button" onClick={() => setKonfirmasiHapus(k)} className={aksiHapusClass} title="Hapus komponen ini">
                                                                <Trash2 className="h-4 w-4" aria-hidden="true" />
                                                                <span className="sr-only">Hapus {k.nama}</span>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            )) : (
                                                <tr>
                                                    <td colSpan="5" className="px-4 py-12 text-center sm:px-6">
                                                        <Search className="mx-auto h-7 w-7 text-text-muted" aria-hidden="true" />
                                                        <p className="mt-2 text-sm font-semibold text-text-primary">
                                                            {komponens.length === 0 ? 'Belum ada komponen gaji' : 'Tidak ada komponen yang cocok'}
                                                        </p>
                                                        <p className="text-xs text-text-secondary">
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

            {/* Konfirmasi hapus, menggantikan confirm() bawaan browser */}
            <Modal show={!!konfirmasiHapus} maxWidth="sm" onClose={() => { if (!menghapus) setKonfirmasiHapus(null); }}>
                <div className="p-6">
                    <div className="flex items-start gap-3">
                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-danger-light text-danger" aria-hidden="true">
                            <AlertTriangle className="h-5 w-5" />
                        </span>
                        <div className="min-w-0">
                            <h3 className="text-base font-extrabold text-text-primary">Hapus komponen gaji?</h3>
                            <p className="mt-1 text-sm text-text-secondary">
                                {konfirmasiHapus?.nama} akan dihapus permanen dari master komponen. Perhitungan payroll berikutnya tidak lagi memakai komponen ini.
                            </p>
                        </div>
                    </div>
                    <div className="mt-5 flex justify-end gap-2">
                        <button type="button" className="btn-secondary" onClick={() => setKonfirmasiHapus(null)} disabled={menghapus}>
                            Batal
                        </button>
                        <button type="button" className="btn-danger inline-flex items-center gap-2" onClick={hapusKomponen} disabled={menghapus}>
                            {menghapus ? <><Loader2 className="h-4 w-4 animate-spin" /> Menghapus</> : <><Trash2 className="h-4 w-4" /> Hapus</>}
                        </button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
