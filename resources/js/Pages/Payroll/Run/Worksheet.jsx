import React, { useState, useEffect, useMemo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import axios from 'axios';
import { formatRupiah } from '@/Utils/format';
import {
    ArrowLeft,
    ArrowRight,
    CalendarDays,
    CheckCircle2,
    ClipboardList,
    FileText,
    Loader2,
    Lock,
    Minus,
    Plus,
    Save,
    Search,
    Sparkles,
    TrendingUp,
    Users,
    Wallet,
    X,
} from 'lucide-react';

const STEPS = [
    { label: 'Pilih Periode', Icon: CalendarDays, done: true },
    { label: 'Review Worksheet', Icon: ClipboardList, active: true },
    { label: 'Finalisasi', Icon: Lock },
];

/** Identitas unik kolom = tipe + nama (ad-hoc dgn nama sama + tipe sama = 1 kolom). */
const keyOf = (d) => `${d.tipe}::${d.nama_komponen}`;

/** Format pendek status pegawai (chip kecil di baris). */
const pegawaiInitials = (nama = '') =>
    nama.split(' ').map((w) => w[0]).filter(Boolean).slice(0, 2).join('').toUpperCase();

export default function RunPayrollWorksheet({ auth, month, year, periode }) {
    const [rows, setRows] = useState([]);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState('');
    const [search, setSearch] = useState('');

    // Status simpan per baris: undefined | 'saving' | 'saved' | 'error'
    const [saveState, setSaveState] = useState({});
    const [dirtyIds, setDirtyIds] = useState(() => new Set());

    // Modal tambah komponen khusus (ad-hoc)
    const [adHocTipe, setAdHocTipe] = useState(null); // null | 'pendapatan' | 'potongan'
    const [adHocName, setAdHocName] = useState('');
    const [adHocError, setAdHocError] = useState('');

    useEffect(() => {
        axios.get(route('penggajian.run.worksheet_data', { month, year }))
            .then((res) => {
                setRows(res.data);
                setLoading(false);
            })
            .catch(() => {
                setLoadError('Gagal memuat data draft. Periksa koneksi lalu muat ulang halaman.');
                setLoading(false);
            });
    }, [month, year]);

    // ── Kolom: dedupe (tipe, nama) urutan stabil dari data ──
    const columns = useMemo(() => {
        const seen = new Set();
        const cols = [];
        rows.forEach((r) => r.details.forEach((d) => {
            const key = keyOf(d);
            if (!seen.has(key)) {
                seen.add(key);
                cols.push({ ...d, key, isAdHoc: !d.komponen_gaji_id });
            }
        }));

        return cols;
    }, [rows]);

    // ── Index O(1): penggajianId → (kolomKey → index detail) ──
    const detailIndexByRow = useMemo(() => {
        const map = new Map();
        rows.forEach((r) => {
            const m = new Map();
            r.details.forEach((d, i) => m.set(keyOf(d), i));
            map.set(r.id, m);
        });

        return map;
    }, [rows]);

    // ── Gaji bersih per baris (1 pass) ──
    const bersihByRow = useMemo(() => {
        const map = new Map();
        rows.forEach((r) => {
            let pd = 0;
            let pt = 0;
            r.details.forEach((d) => {
                const v = Number(d.nominal) || 0;
                if (d.tipe === 'pendapatan') pd += v;
                else pt += v;
            });
            map.set(r.id, pd - pt);
        });

        return map;
    }, [rows]);

    const filteredRows = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) return rows;

        return rows.filter((r) => {
            const nama = (r.pegawai?.nama_lengkap || '').toLowerCase();
            const nip = (r.pegawai?.nip || '').toLowerCase();

            return nama.includes(q) || nip.includes(q);
        });
    }, [rows, search]);

    const summary = useMemo(() => {
        let pendapatan = 0;
        let potongan = 0;
        rows.forEach((r) => r.details.forEach((d) => {
            const v = Number(d.nominal) || 0;
            if (d.tipe === 'pendapatan') pendapatan += v;
            else potongan += v;
        }));

        return { pegawai: rows.length, pendapatan, potongan, bersih: pendapatan - potongan };
    }, [rows]);

    const dirtyCount = dirtyIds.size;

    // ── Mutasi sel ──
    const updateCell = (rowId, col, value) => {
        const idx = detailIndexByRow.get(rowId)?.get(col.key);
        if (idx === undefined) return;

        setRows((prev) => prev.map((r) => {
            if (r.id !== rowId) return r;
            const details = [...r.details];
            details[idx] = { ...details[idx], nominal: value === '' ? 0 : Number(value) };

            return { ...r, details };
        }));
        setDirtyIds((prev) => new Set(prev).add(rowId));
        setSaveState((prev) => {
            const next = { ...prev };
            delete next[rowId];

            return next;
        });
    };

    // ── Simpan per baris (hanya jika dirty) ──
    const saveRow = (row) => {
        if (!dirtyIds.has(row.id)) return;

        setSaveState((prev) => ({ ...prev, [row.id]: 'saving' }));
        axios.post(route('penggajian.run.worksheet_save', { month, year }), {
            penggajian_id: row.id,
            details: row.details,
        }).then(() => {
            setDirtyIds((prev) => {
                const next = new Set(prev);
                next.delete(row.id);

                return next;
            });
            setSaveState((prev) => ({ ...prev, [row.id]: 'saved' }));
            setTimeout(() => {
                setSaveState((prev) => {
                    const next = { ...prev };
                    delete next[row.id];

                    return next;
                });
            }, 2500);
        }).catch(() => setSaveState((prev) => ({ ...prev, [row.id]: 'error' })));
    };

    /**
     * Simpan SEMUA baris dirty sekaligus. Return true jika semua sukses,
     * false jika ada yang gagal (baris gagal ditandai 'error' + tetap dirty).
     * Baris yang sedang 'saving' dilewati (hindari double-POST dgn saveRow blur).
     */
    const saveAllDirty = async () => {
        const pending = rows.filter((r) => dirtyIds.has(r.id) && saveState[r.id] !== 'saving');
        if (pending.length === 0) return true;

        pending.forEach((r) => setSaveState((prev) => ({ ...prev, [r.id]: 'saving' })));

        const results = await Promise.all(pending.map(async (r) => {
            try {
                await axios.post(
                    route('penggajian.run.worksheet_save', { month, year }),
                    { penggajian_id: r.id, details: r.details },
                );
                setDirtyIds((prev) => {
                    const next = new Set(prev);
                    next.delete(r.id);

                    return next;
                });
                setSaveState((prev) => ({ ...prev, [r.id]: 'saved' }));
                setTimeout(() => {
                    setSaveState((prev) => {
                        const next = { ...prev };
                        delete next[r.id];

                        return next;
                    });
                }, 2500);

                return true;
            } catch {
                setSaveState((prev) => ({ ...prev, [r.id]: 'error' }));

                return false;
            }
        }));

        return results.every(Boolean);
    };

    const finalize = async () => {
        if (dirtyCount > 0) {
            if (!confirm(`Masih ada ${dirtyCount} baris belum disimpan. Simpan dulu sebelum mengunci payroll?`)) return;
            const allSaved = await saveAllDirty();
            if (!allSaved) {
                alert('Gagal menyimpan sebagian perubahan. Baris yang error ditandai merah — perbaiki lalu coba lagi.');

                return;
            }
        }
        if (!confirm('Kunci Penggajian? Setelah dikunci, data tidak bisa diubah dan slip gaji siap didownload.')) return;

        router.post(route('penggajian.run.worksheet_finalize', { month, year }));
    };

    // ── Komponen khusus (ad-hoc) ──
    const openAdHoc = (tipe) => {
        setAdHocTipe(tipe);
        setAdHocName('');
        setAdHocError('');
    };

    const submitAdHoc = (e) => {
        e.preventDefault();
        const nama = adHocName.trim();
        if (!nama) {
            setAdHocError('Nama komponen wajib diisi.');

            return;
        }
        if (columns.some((c) => c.tipe === adHocTipe && c.nama_komponen === nama)) {
            setAdHocError('Komponen dengan nama & tipe yang sama sudah ada di tabel.');

            return;
        }

        setRows((prev) => prev.map((r) => ({
            ...r,
            details: [...r.details, { komponen_gaji_id: null, nama_komponen: nama, tipe: adHocTipe, nominal: 0 }],
        })));
        setDirtyIds((prev) => new Set([...prev, ...rows.map((r) => r.id)]));
        setAdHocTipe(null);
        setAdHocName('');
        setAdHocError('');
    };

    const removeAdHocColumn = (col) => {
        if (!confirm(`Hapus kolom "${col.nama_komponen}" dari semua pegawai?`)) return;

        setRows((prev) => prev.map((r) => ({
            ...r,
            details: r.details.filter((d) => keyOf(d) !== col.key),
        })));
        setDirtyIds((prev) => new Set([...prev, ...rows.map((r) => r.id)]));
    };

    const SaveStateChip = ({ row }) => {
        const state = saveState[row.id];
        if (state === 'saving') {
            return <span className="mt-1 inline-flex items-center gap-1 text-[9px] font-bold text-info"><Loader2 className="h-2.5 w-2.5 animate-spin" /> Menyimpan…</span>;
        }
        if (state === 'saved') {
            return <span className="mt-1 inline-flex items-center gap-1 text-[9px] font-bold text-emerald-600"><CheckCircle2 className="h-2.5 w-2.5" /> Tersimpan</span>;
        }
        if (state === 'error') {
            return <button type="button" onClick={() => saveRow(row)} className="mt-1 inline-flex items-center gap-1 text-[9px] font-bold text-danger underline-offset-2 hover:underline">Gagal simpan — coba lagi</button>;
        }
        if (dirtyIds.has(row.id)) {
            return <span className="mt-1 inline-flex items-center gap-1 text-[9px] font-bold text-amber-600">Belum disimpan</span>;
        }

        return null;
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Draft Worksheet" />

            <div className="bg-surface pt-6 pb-6 px-4 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-7xl space-y-4">
                    {/* Wizard steps */}
                    <div className="flex items-center justify-center gap-2 sm:gap-3">
                        {STEPS.map((s, i) => (
                            <React.Fragment key={s.label}>
                                <div className={`flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold ${
                                    s.done ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                        : s.active ? 'bg-primary text-white shadow-card'
                                            : 'bg-white border border-border text-text-secondary'
                                }`}>
                                    {s.done ? <CheckCircle2 className="h-3.5 w-3.5" /> : <s.Icon className="h-3.5 w-3.5" />}
                                    <span className="hidden sm:inline">{s.label}</span>
                                    <span className="sm:hidden">{i + 1}</span>
                                </div>
                                {i < STEPS.length - 1 && <ArrowRight className="h-4 w-4 text-border" />}
                            </React.Fragment>
                        ))}
                    </div>

                    {/* Toolbar */}
                    <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                        <div>
                            <h2 className="flex items-center gap-2 text-xl font-extrabold text-primary">
                                <ClipboardList className="h-5 w-5" />
                                Worksheet Penggajian
                            </h2>
                            <p className="mt-1 flex flex-wrap items-center gap-2 text-xs text-text-secondary">
                                <span className="inline-flex items-center gap-1"><CalendarDays className="h-3.5 w-3.5" /> Periode {periode}</span>
                                <span className="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-700">Draft</span>
                                <span className="text-[11px] text-text-muted">Edit sel → tersimpan otomatis saat sel ditinggalkan.</span>
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <button type="button" onClick={() => openAdHoc('pendapatan')} className="btn-secondary btn-sm flex items-center gap-1.5 border-emerald-200 text-emerald-700 hover:bg-emerald-50">
                                <Plus className="h-3.5 w-3.5" /> Pendapatan Khusus
                            </button>
                            <button type="button" onClick={() => openAdHoc('potongan')} className="btn-secondary btn-sm flex items-center gap-1.5 border-rose-200 text-rose-600 hover:bg-rose-50">
                                <Minus className="h-3.5 w-3.5" /> Potongan Khusus
                            </button>
                            {dirtyCount > 0 && (
                                <button type="button" onClick={() => saveAllDirty()} className="btn-secondary btn-sm flex items-center gap-1.5">
                                    <Save className="h-3.5 w-3.5" /> Simpan Semua ({dirtyCount})
                                </button>
                            )}
                            <button type="button" onClick={finalize} className="btn-primary btn-sm flex items-center gap-1.5">
                                <Lock className="h-3.5 w-3.5" /> Finalisasi (Kunci)
                            </button>
                        </div>
                    </div>

                    {/* Table */}
                    <div className="card overflow-hidden">
                        {loading ? (
                            <div className="flex flex-col items-center justify-center py-24 text-center">
                                <Loader2 className="mb-3 h-8 w-8 animate-spin text-primary" />
                                <p className="text-sm font-semibold text-text-secondary">Memuat draft worksheet…</p>
                                <p className="mt-1 text-xs text-text-muted">Menarik data penggajian periode {periode}.</p>
                            </div>
                        ) : loadError ? (
                            <div className="flex flex-col items-center py-24 text-center">
                                <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-rose-50">
                                    <X className="h-8 w-8 text-danger" />
                                </div>
                                <p className="mt-4 text-base font-bold text-primary">Gagal memuat draft</p>
                                <p className="mt-1 max-w-sm text-sm text-text-secondary">{loadError}</p>
                                <button type="button" onClick={() => window.location.reload()} className="btn-secondary btn-sm mt-4">Muat Ulang</button>
                            </div>
                        ) : rows.length === 0 ? (
                            <div className="flex flex-col items-center py-24 text-center">
                                <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-surface">
                                    <FileText className="h-8 w-8 text-border" />
                                </div>
                                <p className="mt-4 text-base font-bold text-primary">Draft belum tersedia</p>
                                <p className="mt-1 max-w-sm text-sm text-text-secondary">
                                    Jalankan <b>Run Payroll</b> (Step 1) untuk periode {periode} terlebih dahulu.
                                </p>
                                <Link href={route('penggajian.run')} className="btn-primary btn-sm mt-4 flex items-center gap-1.5">
                                    <Sparkles className="h-3.5 w-3.5" /> Buka Run Payroll
                                </Link>
                            </div>
                        ) : (
                            <>
                                <div className="flex flex-col gap-3 border-b border-border bg-surface px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                    <p className="text-xs text-text-secondary">
                                        <b className="text-primary">{rows.length}</b> pegawai · <b className="text-emerald-600">{columns.filter((c) => c.tipe === 'pendapatan').length}</b> kolom pendapatan · <b className="text-rose-600">{columns.filter((c) => c.tipe === 'potongan').length}</b> kolom potongan
                                        <span className="ml-2 hidden text-[11px] text-text-muted md:inline">Sel kosong = tidak dihitung. Set 0 = pakai nilai default.</span>
                                    </p>
                                    <div className="relative sm:w-64">
                                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
                                        <input
                                            type="text"
                                            value={search}
                                            onChange={(e) => setSearch(e.target.value)}
                                            placeholder="Cari nama / NIP…"
                                            className="input-field h-9 pl-9 text-xs"
                                        />
                                    </div>
                                </div>

                                <div className="overflow-auto max-h-[60vh]">
                                    <table className="min-w-full divide-y divide-border">
                                        <thead className="bg-surface sticky top-0 z-30 shadow-card">
                                            <tr>
                                                <th className="sticky left-0 top-0 bg-surface z-40 px-3 py-2.5 text-left text-[10px] font-bold text-text-secondary uppercase tracking-wider border-b border-border border-r">
                                                    Pegawai
                                                </th>
                                                {columns.map((col) => (
                                                    <th key={col.key} className={`sticky top-0 bg-surface z-30 px-3 py-2.5 text-right text-[10px] font-bold uppercase tracking-wider border-b border-border border-r min-w-[110px] ${col.tipe === 'pendapatan' ? 'text-emerald-600' : 'text-rose-600'}`}>
                                                        <span className="inline-flex items-center gap-1">
                                                            {col.tipe === 'pendapatan' ? '+' : '−'} {col.nama_komponen}
                                                            {col.isAdHoc && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => removeAdHocColumn(col)}
                                                                    className="rounded p-0.5 text-text-muted transition-colors hover:bg-rose-50 hover:text-danger"
                                                                    title={`Hapus kolom ${col.nama_komponen}`}
                                                                >
                                                                    <X className="h-3 w-3" />
                                                                </button>
                                                            )}
                                                        </span>
                                                    </th>
                                                ))}
                                                <th className="sticky right-0 top-0 bg-primary-50/80 z-40 px-3 py-2.5 text-right text-[10px] font-black text-primary uppercase tracking-wider border-b border-primary/20 border-l">
                                                    Total Gaji Bersih
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-border/50">
                                            {filteredRows.map((p, pIdx) => (
                                                <tr key={p.id} className={`group transition-colors ${pIdx % 2 !== 0 ? 'bg-surface/50' : 'bg-white'} hover:bg-primary-50/30`}>
                                                    <td className={`sticky left-0 z-10 px-3 py-2 whitespace-nowrap border-r border-border ${pIdx % 2 !== 0 ? 'bg-surface/50' : 'bg-white'} group-hover:bg-primary-50/30`}>
                                                        <div className="flex items-center gap-2">
                                                            <span className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[10px] font-extrabold ${pIdx % 3 === 0 ? 'bg-emerald-100 text-emerald-700' : pIdx % 3 === 1 ? 'bg-sky-100 text-sky-700' : 'bg-purple-100 text-purple-700'}`}>
                                                                {pegawaiInitials(p.pegawai?.nama_lengkap)}
                                                            </span>
                                                            <div className="min-w-0">
                                                                <div className="truncate text-[11px] font-bold text-text-primary max-w-[150px]">{p.pegawai?.nama_lengkap || 'Unknown'}</div>
                                                                <div className="text-[9px] text-text-muted">
                                                                    {p.pegawai?.units?.[0]?.singkatan || p.pegawai?.units?.[0]?.nama || p.pegawai?.nip || '-'}
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <SaveStateChip row={p} />
                                                    </td>
                                                    {columns.map((col) => {
                                                        const idx = detailIndexByRow.get(p.id)?.get(col.key);

                                                        if (idx !== undefined) {
                                                            const detail = p.details[idx];

                                                            return (
                                                                <td key={col.key} className="relative px-2 py-2 whitespace-nowrap border-r border-border focus-within:bg-primary-50/30 transition-colors">
                                                                    <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2">
                                                                        <span className="text-[9px] text-text-muted">Rp</span>
                                                                    </div>
                                                                    <input
                                                                        type="number"
                                                                        min="0"
                                                                        step="0.01"
                                                                        className="input-field w-full rounded-none border-0 border-b border-transparent bg-transparent py-1 pl-5 pr-2 text-right text-[11px] shadow-none hover:border-border focus:border-primary focus:ring-0"
                                                                        value={detail.nominal}
                                                                        onChange={(e) => updateCell(p.id, col, e.target.value)}
                                                                        onBlur={() => saveRow(p)}
                                                                        onKeyDown={(e) => { if (e.key === 'Enter') e.target.blur(); }}
                                                                    />
                                                                </td>
                                                            );
                                                        }

                                                        return <td key={col.key} className="border-r border-border px-2 py-2 text-center text-[9px] text-text-muted">-</td>;
                                                    })}
                                                    <td className={`sticky right-0 z-10 whitespace-nowrap border-l border-primary/20 px-3 py-2 text-right text-[11px] font-black text-primary ${pIdx % 2 !== 0 ? 'bg-primary-100/40' : 'bg-primary-50/40'} group-hover:bg-primary-100/60`}>
                                                        {formatRupiah(bersihByRow.get(p.id))}
                                                    </td>
                                                </tr>
                                            ))}
                                            {filteredRows.length === 0 && (
                                                <tr>
                                                    <td colSpan={columns.length + 2} className="px-6 py-12 text-center text-sm text-text-muted">
                                                        Tidak ada pegawai yang cocok dengan pencarian.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </>
                        )}
                    </div>

                    {/* Summary footer */}
                    {!loading && !loadError && rows.length > 0 && (
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <div className="stat-card">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                                    <Users className="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <p className="text-xl font-extrabold leading-none text-primary tabular-nums">{summary.pegawai}</p>
                                    <p className="mt-1 text-[10px] font-semibold uppercase tracking-wide text-text-secondary">Pegawai</p>
                                </div>
                            </div>
                            <div className="stat-card">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100">
                                    <TrendingUp className="h-5 w-5 text-emerald-600" />
                                </div>
                                <div>
                                    <p className="text-sm font-extrabold leading-none text-primary tabular-nums">{formatRupiah(summary.pendapatan)}</p>
                                    <p className="mt-1 text-[10px] font-semibold uppercase tracking-wide text-text-secondary">Total Pendapatan</p>
                                </div>
                            </div>
                            <div className="stat-card">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-100">
                                    <Minus className="h-5 w-5 text-rose-600" />
                                </div>
                                <div>
                                    <p className="text-sm font-extrabold leading-none text-primary tabular-nums">{formatRupiah(summary.potongan)}</p>
                                    <p className="mt-1 text-[10px] font-semibold uppercase tracking-wide text-text-secondary">Total Potongan</p>
                                </div>
                            </div>
                            <div className="stat-card bg-primary text-white border-primary">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10">
                                    <Wallet className="h-5 w-5 text-accent" />
                                </div>
                                <div>
                                    <p className="text-sm font-extrabold leading-none text-white tabular-nums">{formatRupiah(summary.bersih)}</p>
                                    <p className="mt-1 text-[10px] font-semibold uppercase tracking-wide text-white/70">Total Gaji Bersih</p>
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="flex justify-center">
                        <Link href={route('penggajian.index')} className="inline-flex items-center gap-1.5 text-sm font-semibold text-text-secondary transition-colors hover:text-primary">
                            <ArrowLeft className="h-4 w-4" /> Kembali ke Riwayat Penggajian
                        </Link>
                    </div>
                </div>
            </div>

            {/* ─── MODAL: Tambah Komponen Khusus (ad-hoc) ─── */}
            <Modal show={adHocTipe !== null} onClose={() => setAdHocTipe(null)} maxWidth="sm">
                <form onSubmit={submitAdHoc} className="px-6 py-5">
                    <div className="flex items-center gap-3 mb-4">
                        <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${adHocTipe === 'pendapatan' ? 'bg-emerald-100' : 'bg-rose-100'}`}>
                            {adHocTipe === 'pendapatan'
                                ? <Plus className="h-5 w-5 text-emerald-600" />
                                : <Minus className="h-5 w-5 text-rose-600" />}
                        </div>
                        <div>
                            <h3 className="text-lg font-bold text-primary">Tambah {adHocTipe === 'pendapatan' ? 'Pendapatan' : 'Potongan'} Khusus</h3>
                            <p className="text-xs text-text-secondary">Kolom baru akan ditambahkan ke SEMUA pegawai di worksheet.</p>
                        </div>
                    </div>

                    <div>
                        <label className="form-label mb-1">Nama Komponen <span className="text-danger">*</span></label>
                        <input
                            type="text"
                            autoFocus
                            value={adHocName}
                            onChange={(e) => setAdHocName(e.target.value)}
                            placeholder={adHocTipe === 'pendapatan' ? 'Contoh: Bonus THR, Uang Transport' : 'Contoh: Kasbon, Potongan Seragam'}
                            className="input-field"
                        />
                        {adHocError && <p className="form-error mt-1">{adHocError}</p>}
                    </div>

                    <div className="mt-5 flex justify-end gap-3 border-t border-border pt-4">
                        <button type="button" onClick={() => setAdHocTipe(null)} className="btn-secondary">Batal</button>
                        <button type="submit" className="btn-primary">Tambah Kolom</button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
