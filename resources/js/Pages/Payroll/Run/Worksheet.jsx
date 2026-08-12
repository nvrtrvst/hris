import React, { useState, useEffect, useMemo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
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
    Sparkles,
    TrendingUp,
    Users,
    Wallet,
} from 'lucide-react';

const STEPS = [
    { label: 'Pilih Periode', Icon: CalendarDays, done: true },
    { label: 'Review Worksheet', Icon: ClipboardList, active: true },
    { label: 'Finalisasi', Icon: Lock },
];

export default function RunPayrollWorksheet({ auth, month, year, periode }) {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);
    const [savingId, setSavingId] = useState(null);

    useEffect(() => {
        axios.get(route('penggajian.run.worksheet_data', { month, year }))
            .then((res) => {
                setData(res.data);
                setLoading(false);
            })
            .catch(() => {
                alert('Gagal memuat data draft');
                setLoading(false);
            });
    }, [month, year]);

    const addAdHoc = (tipe) => {
        const nama = prompt(`Masukkan nama ${tipe === 'pendapatan' ? 'Pendapatan' : 'Potongan'} baru (Misal: Kasbon, Bonus):`);
        if (!nama) return;

        const newData = data.map((p) => ({
            ...p,
            details: [...p.details, { komponen_gaji_id: null, nama_komponen: nama, tipe, nominal: 0 }],
        }));
        setData(newData);
    };

    const handleCellChange = (penggajianIdx, detailIdx, newNominal) => {
        const newData = [...data];
        newData[penggajianIdx].details[detailIdx].nominal = newNominal === '' ? 0 : parseFloat(newNominal);
        setData(newData);
    };

    const saveRow = (penggajian) => {
        setSavingId(penggajian.id);
        axios.post(route('penggajian.run.worksheet_save', { month, year }), {
            penggajian_id: penggajian.id,
            details: penggajian.details,
        }).then(() => {
            setSavingId(null);
        }).catch(() => {
            alert('Gagal menyimpan baris ini!');
            setSavingId(null);
        });
    };

    const allDetailNames = useMemo(() => {
        const names = [];
        data.forEach((p) => {
            p.details.forEach((d) => {
                if (!names.find((n) => n.nama === d.nama_komponen && n.tipe === d.tipe)) {
                    names.push({ nama: d.nama_komponen, tipe: d.tipe });
                }
            });
        });
        return names;
    }, [data]);

    // Hitung live dari detail (ikuti edit sel) — gaji_bersih = pendapatan − potongan
    const rowBersih = (p) => {
        let pd = 0;
        let pt = 0;
        p.details.forEach((d) => {
            const v = Number(d.nominal) || 0;
            if (d.tipe === 'pendapatan') pd += v;
            else pt += v;
        });

        return pd - pt;
    };

    const summary = useMemo(() => {
        let pendapatan = 0;
        let potongan = 0;
        data.forEach((p) => {
            p.details.forEach((d) => {
                const v = Number(d.nominal) || 0;
                if (d.tipe === 'pendapatan') pendapatan += v;
                else potongan += v;
            });
        });

        return {
            pegawai: data.length,
            pendapatan,
            potongan,
            bersih: pendapatan - potongan,
        };
    }, [data]);

    const finalize = () => {
        if (confirm('Kunci Penggajian? Setelah dikunci, data tidak bisa diubah dan slip gaji siap didownload.')) {
            router.post(route('penggajian.run.worksheet_finalize', { month, year }));
        }
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
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 className="flex items-center gap-2 text-xl font-extrabold text-primary">
                                <ClipboardList className="h-5 w-5" />
                                Worksheet Penggajian
                            </h2>
                            <p className="mt-1 flex flex-wrap items-center gap-2 text-xs text-text-secondary">
                                <span className="inline-flex items-center gap-1"><CalendarDays className="h-3.5 w-3.5" /> Periode {periode}</span>
                                <span className="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-700">
                                    Draft
                                </span>
                                <span className="text-[11px] text-text-muted">Edit langsung di tabel — tersimpan otomatis saat sel ditinggalkan.</span>
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <button type="button" onClick={() => addAdHoc('pendapatan')} className="btn-secondary btn-sm flex items-center gap-1.5 border-emerald-200 text-emerald-700 hover:bg-emerald-50">
                                <Plus className="h-3.5 w-3.5" /> Pendapatan Khusus
                            </button>
                            <button type="button" onClick={() => addAdHoc('potongan')} className="btn-secondary btn-sm flex items-center gap-1.5 border-rose-200 text-rose-600 hover:bg-rose-50">
                                <Minus className="h-3.5 w-3.5" /> Potongan Khusus
                            </button>
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
                        ) : data.length === 0 ? (
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
                            <div className="overflow-auto max-h-[65vh]">
                                <table className="min-w-full divide-y divide-border">
                                    <thead className="bg-surface sticky top-0 z-30 shadow-card">
                                        <tr>
                                            <th className="sticky left-0 top-0 bg-surface z-40 px-3 py-2.5 text-left text-[10px] font-bold text-text-secondary uppercase tracking-wider border-b border-border border-r">
                                                Pegawai
                                            </th>
                                            {allDetailNames.map((col, i) => (
                                                <th key={i} className={`sticky top-0 bg-surface z-30 px-3 py-2.5 text-right text-[10px] font-bold uppercase tracking-wider border-b border-border border-r min-w-[110px] ${col.tipe === 'pendapatan' ? 'text-emerald-600' : 'text-rose-600'}`}>
                                                    {col.tipe === 'pendapatan' ? '+' : '−'} {col.nama}
                                                </th>
                                            ))}
                                            <th className="sticky right-0 top-0 bg-primary-50/80 z-40 px-3 py-2.5 text-right text-[10px] font-black text-primary uppercase tracking-wider border-b border-primary/20 border-l">
                                                Total Gaji Bersih
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-border/50">
                                        {data.map((p, pIdx) => (
                                            <tr key={p.id} className={`group transition-colors ${pIdx % 2 !== 0 ? 'bg-surface/50' : 'bg-white'} hover:bg-primary-50/30`}>
                                                <td className={`sticky left-0 z-10 px-3 py-2 whitespace-nowrap border-r border-border ${pIdx % 2 !== 0 ? 'bg-surface/50' : 'bg-white'} group-hover:bg-primary-50/30`}>
                                                    <div className="flex items-center gap-2">
                                                        <span className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[10px] font-extrabold ${pIdx % 3 === 0 ? 'bg-emerald-100 text-emerald-700' : pIdx % 3 === 1 ? 'bg-sky-100 text-sky-700' : 'bg-purple-100 text-purple-700'}`}>
                                                            {(p.pegawai?.nama_lengkap || '?').split(' ').map((w) => w[0]).filter(Boolean).slice(0, 2).join('').toUpperCase()}
                                                        </span>
                                                        <div>
                                                            <div className="text-[11px] font-bold text-text-primary">{p.pegawai?.nama_lengkap || 'Unknown'}</div>
                                                            <div className="text-[9px] text-text-muted">{p.pegawai?.nip || p.pegawai?.nik || '-'}</div>
                                                        </div>
                                                    </div>
                                                    {savingId === p.id && (
                                                        <span className="mt-1 inline-flex items-center gap-1 text-[9px] font-bold text-info animate-pulse">
                                                            <Loader2 className="h-2.5 w-2.5 animate-spin" /> Menyimpan…
                                                        </span>
                                                    )}
                                                </td>
                                                {allDetailNames.map((col, cIdx) => {
                                                    const detailIdx = p.details.findIndex((d) => d.nama_komponen === col.nama && d.tipe === col.tipe);

                                                    if (detailIdx !== -1) {
                                                        const detail = p.details[detailIdx];
                                                        return (
                                                            <td key={cIdx} className="relative px-2 py-2 whitespace-nowrap border-r border-border focus-within:bg-primary-50/30 transition-colors">
                                                                <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2">
                                                                    <span className="text-[9px] text-text-muted">Rp</span>
                                                                </div>
                                                                <input
                                                                    type="number"
                                                                    className="input-field w-full rounded-none border-0 border-b border-transparent bg-transparent py-1 pl-5 pr-2 text-right text-[11px] shadow-none hover:border-border focus:border-primary focus:ring-0"
                                                                    value={detail.nominal}
                                                                    onChange={(e) => handleCellChange(pIdx, detailIdx, e.target.value)}
                                                                    onBlur={() => saveRow(p)}
                                                                />
                                                            </td>
                                                        );
                                                    }
                                                    return <td key={cIdx} className="border-r border-border px-2 py-2 text-center text-[9px] text-text-muted">-</td>;
                                                })}
                                                <td className={`sticky right-0 z-10 whitespace-nowrap border-l border-primary/20 px-3 py-2 text-right text-[11px] font-black text-primary ${pIdx % 2 !== 0 ? 'bg-primary-100/40' : 'bg-primary-50/40'} group-hover:bg-primary-100/60`}>
                                                    {formatRupiah(rowBersih(p))}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    {/* Summary footer */}
                    {!loading && data.length > 0 && (
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
        </AuthenticatedLayout>
    );
}
