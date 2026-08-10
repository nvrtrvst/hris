import React from 'react';
import { Head, Link } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card } from '@/Components/MobileUI';
import { ArrowLeft, Building2, User, Hash, BadgeCheck } from 'lucide-react';

export default function GajiShow({ auth, penggajian }) {
    const p = penggajian;
    const pegawai = p.pegawai;
    const primaryUnit = pegawai?.units?.find((u) => u.pivot?.is_primary) ?? pegawai?.units?.[0];
    const pendapatan = p.details.filter((d) => d.tipe === 'pendapatan');
    const potongan = p.details.filter((d) => d.tipe === 'potongan');

    return (
        <MobileLayout user={auth.user}>
            <Head title={`Slip Gaji ${p.periode_bulan}`} />

            <Link href={route('presensi.gaji.index')} className="mb-4 inline-flex items-center gap-1.5 text-sm font-bold text-primary">
                <ArrowLeft className="h-4 w-4" /> Kembali
            </Link>

            <Card className="mb-4 border-0 bg-primary text-white p-5">
                <h1 className="text-lg font-bold uppercase tracking-widest">Slip Gaji</h1>
                <p className="mt-1 text-sm text-white/80">{p.periode_bulan}</p>
                <div className="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div className="flex items-center gap-2"><User className="h-4 w-4 text-white/60" /> {pegawai?.nama_lengkap || '-'}</div>
                    <div className="flex items-center gap-2"><Building2 className="h-4 w-4 text-white/60" /> {primaryUnit?.nama || '-'}</div>
                    <div className="flex items-center gap-2"><Hash className="h-4 w-4 text-white/60" /> {pegawai?.status_kepegawaian || '-'}</div>
                    <div className="flex items-center gap-2"><BadgeCheck className="h-4 w-4 text-white/60" /> {p.status}</div>
                </div>
            </Card>

            <Card className="mb-4 p-4">
                <h2 className="mb-3 text-xs font-bold uppercase tracking-wider text-slate-500">Pendapatan</h2>
                <div className="divide-y divide-slate-100">
                    {pendapatan.map((d, i) => (
                        <div key={i} className="flex items-center justify-between py-2">
                            <span className="text-sm text-slate-700">{d.nama_komponen}</span>
                            <span className="text-sm font-bold text-slate-900">Rp {Number(d.nominal).toLocaleString('id-ID')}</span>
                        </div>
                    ))}
                </div>
                <div className="mt-2 flex items-center justify-between border-t-2 border-primary pt-3">
                    <span className="text-sm font-bold text-slate-800">Total Pendapatan</span>
                    <span className="text-sm font-bold text-primary">Rp {Number(p.total_pendapatan).toLocaleString('id-ID')}</span>
                </div>
            </Card>

            <Card className="mb-4 p-4">
                <h2 className="mb-3 text-xs font-bold uppercase tracking-wider text-slate-500">Potongan</h2>
                <div className="divide-y divide-slate-100">
                    {potongan.map((d, i) => (
                        <div key={i} className="flex items-center justify-between py-2">
                            <span className="text-sm text-slate-700">{d.nama_komponen}</span>
                            <span className="text-sm font-bold text-slate-900">Rp {Number(d.nominal).toLocaleString('id-ID')}</span>
                        </div>
                    ))}
                </div>
                <div className="mt-2 flex items-center justify-between border-t-2 border-rose-500 pt-3">
                    <span className="text-sm font-bold text-slate-800">Total Potongan</span>
                    <span className="text-sm font-bold text-rose-600">Rp {Number(p.total_potongan).toLocaleString('id-ID')}</span>
                </div>
            </Card>

            <Card className="border-2 border-primary p-5">
                <div className="flex items-center justify-between">
                    <span className="text-base font-bold text-slate-900">Gaji Bersih</span>
                    <span className="text-xl font-extrabold text-primary">Rp {Number(p.gaji_bersih).toLocaleString('id-ID')}</span>
                </div>
            </Card>
        </MobileLayout>
    );
}
