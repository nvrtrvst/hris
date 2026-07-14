import React, { useRef, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card, Badge } from '@/Components/MobileUI';
import { ArrowLeft, Upload, Camera, Info } from 'lucide-react';

export default function Create({ pegawai }) {
    const { data, setData, post, processing, errors } = useForm({
        jenis_izin: 'sakit',
        tanggal_mulai: '',
        tanggal_selesai: '',
        alasan: '',
        bukti_foto: null,
    });

    const [previewUrl, setPreviewUrl] = useState(null);
    const fileInputRef = useRef(null);

    const handleFileChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onloadend = () => {
                setData('bukti_foto', reader.result);
                setPreviewUrl(reader.result);
            };
            reader.readAsDataURL(file);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('mobile.izin.store'));
    };

    const { auth } = usePage().props;

    const options = [
        { value: 'sakit', label: 'Sakit' },
        { value: 'izin', label: 'Izin' },
        { value: 'cuti', label: 'Cuti' },
    ];

    return (
        <MobileLayout user={auth.user}>
            <Head title="Buat Pengajuan" />

            <div className="mb-5 px-1">
                <Link href={route('mobile.izin.index')} className="mb-2 inline-flex items-center text-sm font-semibold text-primary transition-colors active:scale-95">
                    <ArrowLeft className="mr-1 h-4 w-4" />
                    Kembali
                </Link>
                <h1 className="text-2xl font-extrabold tracking-tight text-slate-800">Buat Pengajuan</h1>
                <p className="mt-0.5 text-sm text-slate-500">Isi form di bawah ini untuk mengajukan</p>
            </div>

            <form onSubmit={submit} className="space-y-4">
                {/* Jenis selector */}
                <div className="grid grid-cols-3 gap-2">
                    {options.map((o) => {
                        const active = data.jenis_izin === o.value;
                        return (
                            <button
                                key={o.value}
                                type="button"
                                onClick={() => setData('jenis_izin', o.value)}
                                className={`rounded-2xl py-3 text-sm font-bold transition-all active:scale-95 ${
                                    active ? 'bg-emerald-500 text-white shadow-[0_8px_20px_-6px_rgba(79,70,229,0.6)]' : 'bg-white text-slate-500 ring-1 ring-slate-200'
                                }`}
                            >
                                {o.label}
                            </button>
                        );
                    })}
                </div>

                {data.jenis_izin === 'cuti' && (
                    <div className="flex items-start gap-3 rounded-2xl border border-emerald-100 bg-emerald-50 p-3">
                        <Info className="mt-0.5 h-5 w-5 flex-shrink-0 text-primary" />
                        <div>
                            <p className="text-sm font-bold text-primary">Sisa Cuti Tahunan: <span>{pegawai.sisa_cuti} Hari</span></p>
                            <p className="mt-0.5 text-xs text-primary">Pastikan rentang hari tidak melebihi sisa cuti Anda.</p>
                        </div>
                    </div>
                )}

                <Card className="space-y-4 py-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="mb-1 block text-sm font-semibold text-slate-700">Tgl Mulai</label>
                            <input
                                type="date"
                                value={data.tanggal_mulai}
                                onChange={(e) => setData('tanggal_mulai', e.target.value)}
                                className="block w-full rounded-2xl border-slate-200 bg-slate-50 px-3 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            />
                            {errors.tanggal_mulai && <p className="mt-1 text-xs font-medium text-rose-600">{errors.tanggal_mulai}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-semibold text-slate-700">Tgl Selesai</label>
                            <input
                                type="date"
                                value={data.tanggal_selesai}
                                onChange={(e) => setData('tanggal_selesai', e.target.value)}
                                className="block w-full rounded-2xl border-slate-200 bg-slate-50 px-3 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            />
                            {errors.tanggal_selesai && <p className="mt-1 text-xs font-medium text-rose-600">{errors.tanggal_selesai}</p>}
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-semibold text-slate-700">Alasan Lengkap</label>
                        <textarea
                            rows="3"
                            value={data.alasan}
                            onChange={(e) => setData('alasan', e.target.value)}
                            className="block w-full rounded-2xl border-slate-200 bg-slate-50 px-3 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            placeholder="Tuliskan alasan pengajuan Anda secara detail..."
                        ></textarea>
                        {errors.alasan && <p className="mt-1 text-xs font-medium text-rose-600">{errors.alasan}</p>}
                    </div>
                </Card>

                {/* Bukti */}
                <div>
                    <label className="mb-1 block text-sm font-semibold text-slate-700">
                        Bukti / Surat Dokter {data.jenis_izin === 'sakit' && <span className="text-rose-500">*wajib</span>}
                    </label>
                    <div
                        onClick={() => fileInputRef.current.click()}
                        className={`flex cursor-pointer flex-col items-center justify-center rounded-3xl border-2 border-dashed p-6 text-center transition-all active:scale-[0.98] ${
                            previewUrl ? 'border-emerald-400 bg-emerald-50/50' : 'border-slate-300 bg-white hover:border-emerald-400'
                        }`}
                    >
                        {previewUrl ? (
                            <div className="space-y-2">
                                <img src={previewUrl} alt="Preview" className="mx-auto max-h-44 rounded-2xl object-contain shadow-md" />
                                <p className="text-xs font-semibold text-primary">Klik untuk mengganti foto</p>
                            </div>
                        ) : (
                            <div className="flex flex-col items-center space-y-2">
                                <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                    <Camera className="h-6 w-6" />
                                </div>
                                <p className="text-sm text-slate-500">Ambil foto atau pilih dari galeri</p>
                            </div>
                        )}
                    </div>
                    <input type="file" ref={fileInputRef} className="hidden" accept="image/*" onChange={handleFileChange} />
                    {errors.bukti_foto && <p className="mt-1 text-xs font-medium text-rose-600">{errors.bukti_foto}</p>}
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="mt-2 w-full rounded-2xl bg-gradient-to-br from-emerald-500 to-primary py-4 font-extrabold text-white shadow-[0_10px_24px_-6px_rgba(79,70,229,0.6)] transition-transform active:scale-[0.98] disabled:opacity-60"
                >
                    {processing ? 'Mengirim…' : 'Kirim Pengajuan'}
                </button>
            </form>
        </MobileLayout>
    );
}
