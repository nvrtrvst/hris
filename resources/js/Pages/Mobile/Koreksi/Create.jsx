import React from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id } from 'date-fns/locale';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card } from '@/Components/MobileUI';
import { ArrowLeft, Clock, Info, AlertTriangle } from 'lucide-react';

export default function Create({ auth, presensi, koreksi_count, kuota }) {
    const { data, setData, post, processing, errors } = useForm({
        presensi_id: presensi.id,
        nilai_baru: '',
        alasan: 'lupa_presensi',
        alasan_detail: '',
        penjelasan_khusus: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('presensi.koreksi.store'));
    };

    const fmtJam = (j) => (j ? j.substring(0, 5) : '—');
    const lewatKuota = koreksi_count >= kuota;

    const options = [
        { value: 'lupa_presensi', label: 'Lupa Presensi' },
        { value: 'hp_rusak', label: 'HP Rusak' },
        { value: 'dinas_luar', label: 'Dinas Luar' },
        { value: 'rapat', label: 'Rapat' },
        { value: 'lainnya', label: 'Lainnya' },
    ];

    return (
        <MobileLayout user={auth.user}>
            <Head title="Ajukan Koreksi Presensi" />

            <div className="mb-5 px-1">
                <Link href={route('presensi.koreksi.index')} className="mb-2 inline-flex items-center text-sm font-semibold text-primary transition-colors active:scale-95">
                    <ArrowLeft className="mr-1 h-4 w-4" />
                    Kembali
                </Link>
                <h1 className="text-2xl font-extrabold tracking-tight text-slate-800">Ajukan Koreksi</h1>
                <p className="mt-0.5 text-sm text-slate-500">Koreksi jam keluar presensi Anda</p>
            </div>

            <form onSubmit={submit} className="space-y-4">
                <Card className="space-y-1.5 py-4">
                    <p className="text-xs font-bold uppercase tracking-wide text-slate-400">Presensi Asal</p>
                    <p className="text-sm font-bold text-slate-800">
                        {format(parseISO(presensi.tanggal), 'EEEE, d MMMM yyyy', { locale: id })}
                    </p>
                    <p className="flex items-center gap-2 text-sm text-slate-500">
                        <Clock className="h-4 w-4 text-emerald-400" />
                        Masuk {fmtJam(presensi.jam_masuk)} · Keluar {fmtJam(presensi.jam_keluar)}
                    </p>
                    <p className="text-xs text-slate-400">{presensi.unit_sekolah?.nama || '—'}</p>
                </Card>

                <div className={`flex items-start gap-3 rounded-2xl border p-3 ${lewatKuota ? 'border-amber-200 bg-amber-50' : 'border-emerald-100 bg-emerald-50'}`}>
                    {lewatKuota ? (
                        <AlertTriangle className="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600" />
                    ) : (
                        <Info className="mt-0.5 h-5 w-5 flex-shrink-0 text-primary" />
                    )}
                    <div>
                        <p className="text-sm font-bold text-slate-800">Kuota bulan ini: {koreksi_count}/{kuota} disetujui</p>
                        <p className="mt-0.5 text-xs text-slate-500">
                            {lewatKuota
                                ? 'Kuota terlampaui — penjelasan khusus wajib diisi, pengajuan tetap dapat diproses.'
                                : 'Kuota koreksi yang disetujui per bulan.'}
                        </p>
                    </div>
                </div>

                <div>
                    <label className="mb-1 block text-sm font-semibold text-slate-700">Jam Keluar Baru</label>
                    <input
                        type="time"
                        value={data.nilai_baru}
                        onChange={(e) => setData('nilai_baru', e.target.value)}
                        className="block w-full rounded-2xl border-slate-200 bg-slate-50 px-3 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                        required
                    />
                    <p className="mt-1 text-xs text-slate-400">Wajib setelah jam masuk {fmtJam(presensi.jam_masuk)}.</p>
                    {errors.nilai_baru && <p className="mt-1 text-xs font-medium text-rose-600">{errors.nilai_baru}</p>}
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
                    <label className="mb-1 block text-sm font-semibold text-slate-700">Detail Alasan <span className="font-normal text-slate-400">(opsional)</span></label>
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

                {lewatKuota && (
                    <div>
                        <label className="mb-1 block text-sm font-semibold text-amber-700">
                            Penjelasan Khusus <span className="text-rose-500">*</span>
                        </label>
                        <textarea
                            rows="3"
                            value={data.penjelasan_khusus}
                            onChange={(e) => setData('penjelasan_khusus', e.target.value)}
                            className="block w-full rounded-2xl border-amber-200 bg-amber-50 px-3 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                            placeholder="Wajib: pengajuan melebihi kuota 3 koreksi/bulan, jelaskan kondisinya..."
                            maxLength={500}
                            required
                        ></textarea>
                        {errors.penjelasan_khusus && <p className="mt-1 text-xs font-medium text-rose-600">{errors.penjelasan_khusus}</p>}
                    </div>
                )}

                {errors.presensi_id && (
                    <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                        {errors.presensi_id}
                    </div>
                )}

                <button
                    type="submit"
                    disabled={processing}
                    className="mt-2 min-h-14 w-full rounded-xl bg-primary px-4 py-4 font-bold text-white transition-transform active:scale-[0.99] disabled:opacity-60"
                >
                    {processing ? 'Mengirim…' : 'Kirim Pengajuan Koreksi'}
                </button>
            </form>
        </MobileLayout>
    );
}
