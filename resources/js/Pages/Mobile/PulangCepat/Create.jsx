import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card } from '@/Components/MobileUI';
import { ArrowLeft, Clock3, Info } from 'lucide-react';

export default function Create({ auth }) {
    const today = new Date().toISOString().slice(0, 10);

    const { data, setData, post, processing, errors } = useForm({
        tanggal: today,
        jam_diminta: '',
        alasan: 'keperluan_pribadi',
        alasan_detail: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('presensi.pulang-cepat.store'));
    };

    const options = [
        { value: 'sakit', label: 'Sakit' },
        { value: 'urusan_keluarga', label: 'Urusan Keluarga' },
        { value: 'keperluan_pribadi', label: 'Keperluan Pribadi' },
        { value: 'dinas_luar', label: 'Dinas Luar' },
        { value: 'lainnya', label: 'Lainnya' },
    ];

    return (
        <MobileLayout user={auth.user}>
            <Head title="Ajukan Izin Pulang Cepat" />

            <div className="mb-5 px-1">
                <Link href={route('presensi.pulang-cepat.index')} className="mb-2 inline-flex items-center text-sm font-semibold text-primary transition-colors active:scale-95">
                    <ArrowLeft className="mr-1 h-4 w-4" />
                    Kembali
                </Link>
                <h1 className="text-2xl font-extrabold tracking-tight text-slate-800">Ajukan Pulang Cepat</h1>
                <p className="mt-0.5 text-sm text-slate-500">Izin pulang lebih awal dari jam kerja/jam mengajar</p>
            </div>

            <form onSubmit={submit} className="space-y-4">
                <Card className="space-y-3 py-4">
                    <div>
                        <label className="mb-1 block text-sm font-semibold text-slate-700">Tanggal</label>
                        <input
                            type="date"
                            value={data.tanggal}
                            min={today}
                            onChange={(e) => setData('tanggal', e.target.value)}
                            className="block w-full rounded-2xl border-slate-200 bg-slate-50 px-3 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            required
                        />
                        {errors.tanggal && <p className="mt-1 text-xs font-medium text-rose-600">{errors.tanggal}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-semibold text-slate-700">Jam Diminta Pulang</label>
                        <input
                            type="time"
                            value={data.jam_diminta}
                            onChange={(e) => setData('jam_diminta', e.target.value)}
                            className="block w-full rounded-2xl border-slate-200 bg-slate-50 px-3 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            required
                        />
                        {errors.jam_diminta && <p className="mt-1 text-xs font-medium text-rose-600">{errors.jam_diminta}</p>}
                    </div>
                </Card>

                <div className="flex items-start gap-3 rounded-2xl border border-emerald-100 bg-emerald-50 p-3">
                    <Info className="mt-0.5 h-5 w-5 flex-shrink-0 text-primary" />
                    <p className="text-xs text-slate-500">
                        Setelah disetujui, tombol presensi keluar terbuka lebih awal pada tanggal tersebut dan presensi tetap
                        tercatat sebagai pulang awal yang <strong>diizinkan</strong>.
                    </p>
                </div>

                <div>
                    <label className="mb-1 block text-sm font-semibold text-slate-700">Alasan</label>
                    <div className="grid grid-cols-3 gap-2">
                        {options.map((o) => {
                            const active = data.alasan === o.value;
                            return (
                                <button
                                    key={o.value}
                                    type="button"
                                    onClick={() => setData('alasan', o.value)}
                                    className={`rounded-2xl py-2.5 text-xs font-bold transition-all active:scale-95 ${
                                        active ? 'bg-primary text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200'
                                    }`}
                                >
                                    {o.label}
                                </button>
                            );
                        })}
                    </div>
                    {errors.alasan && <p className="mt-1 text-xs font-medium text-rose-600">{errors.alasan}</p>}
                </div>

                <div>
                    <label className="mb-1 block text-sm font-semibold text-slate-700">Detail Alasan <span className="font-normal text-slate-600">(opsional)</span></label>
                    <textarea
                        rows="2"
                        value={data.alasan_detail}
                        onChange={(e) => setData('alasan_detail', e.target.value)}
                        className="block w-full rounded-2xl border-slate-200 bg-slate-50 px-3 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                        placeholder="Keterangan tambahan bila perlu..."
                        maxLength={500}
                    ></textarea>
                    {errors.alasan_detail && <p className="mt-1 text-xs font-medium text-rose-600">{errors.alasan_detail}</p>}
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="mt-2 min-h-14 w-full rounded-xl bg-primary px-4 py-4 font-bold text-white transition-transform active:scale-[0.99] disabled:opacity-60"
                >
                    {processing ? 'Mengirim…' : 'Kirim Pengajuan'}
                </button>
            </form>
        </MobileLayout>
    );
}
