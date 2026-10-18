import React from 'react';
import { Head, Link } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card, Empty } from '@/Components/MobileUI';
import { Receipt, ChevronRight, Download } from 'lucide-react';

const statusCls = {
    draft: 'bg-slate-100 text-slate-700',
    finalized: 'bg-sky-100 text-sky-700',
    paid: 'bg-emerald-100 text-emerald-700',
};

export default function GajiIndex({ auth, pegawai, penggajians }) {
    const totalFinalized = penggajians.filter((p) => p.status === 'paid' || p.status === 'finalized').length;
    return (
        <MobileLayout user={auth.user}>
            <Head title="Slip Gaji" />
            <div className="mb-5">
                <h1 className="text-2xl font-extrabold tracking-tight text-slate-800">Slip Gaji</h1>
                <p className="mt-0.5 text-sm text-slate-500">{totalFinalized} slip tersedia</p>
            </div>

            {penggajians.length === 0 ? (
                <Empty icon={Receipt} title="Belum ada slip gaji" subtitle="Slip gaji akan muncul setelah payroll di-finalize." />
            ) : (
                <div className="space-y-2.5">
                    {penggajians.map((p) => (
                        <Link key={p.id} href={route('presensi.gaji.show', p.id)} className="block">
                            <Card className="flex items-center justify-between py-3.5">
                                <div>
                                    <p className="font-bold text-slate-800">{p.periode_bulan}</p>
                                    <p className="mt-0.5 text-sm text-slate-500">Rp {Number(p.gaji_bersih).toLocaleString('id-ID')}</p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider ${statusCls[p.status] || statusCls.draft}`}>
                                        {p.status}
                                    </span>
                                    <ChevronRight className="h-4 w-4 text-slate-500" />
                                </div>
                            </Card>
                        </Link>
                    ))}
                </div>
            )}
        </MobileLayout>
    );
}
