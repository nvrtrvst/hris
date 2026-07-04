import React, { useRef, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { ArrowLeft, Upload, Camera } from 'lucide-react';

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

    return (
        <MobileLayout user={auth.user}>
            <Head title="Buat Pengajuan" />

            <div className="mb-6">
                <Link href={route('mobile.izin.index')} className="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-800 mb-2 transition-colors">
                    <ArrowLeft className="w-4 h-4 mr-1" />
                    Kembali
                </Link>
                <h1 className="text-xl font-extrabold text-gray-900">Buat Pengajuan</h1>
                <p className="text-sm text-gray-500">Isi form di bawah ini untuk mengajukan izin</p>
            </div>

            <div className="relative z-20">
                <form onSubmit={submit} className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 space-y-5">
                    
                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1">Jenis Pengajuan</label>
                        <select
                            value={data.jenis_izin}
                            onChange={(e) => setData('jenis_izin', e.target.value)}
                            className="block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5"
                        >
                            <option value="sakit">Sakit</option>
                            <option value="izin">Izin</option>
                            <option value="cuti">Cuti</option>
                        </select>
                        {errors.jenis_izin && <p className="mt-1.5 text-sm text-red-600 font-medium">{errors.jenis_izin}</p>}
                    </div>

                    {data.jenis_izin === 'cuti' && (
                        <div className="bg-indigo-50 border border-indigo-100 rounded-xl p-3 flex items-start space-x-3">
                            <div className="flex-shrink-0 mt-0.5">
                                <svg className="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <div>
                                <p className="text-sm text-indigo-800 font-medium">Sisa Cuti Tahunan: <span className="font-bold">{pegawai.sisa_cuti} Hari</span></p>
                                <p className="text-xs text-indigo-600 mt-0.5">Pastikan rentang hari yang diajukan tidak melebihi sisa cuti Anda.</p>
                            </div>
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-semibold text-gray-700 mb-1">Tgl Mulai</label>
                            <input
                                type="date"
                                value={data.tanggal_mulai}
                                onChange={(e) => setData('tanggal_mulai', e.target.value)}
                                className="block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 px-2"
                            />
                            {errors.tanggal_mulai && <p className="mt-1 text-xs text-red-600">{errors.tanggal_mulai}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-semibold text-gray-700 mb-1">Tgl Selesai</label>
                            <input
                                type="date"
                                value={data.tanggal_selesai}
                                onChange={(e) => setData('tanggal_selesai', e.target.value)}
                                className="block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 px-2"
                            />
                            {errors.tanggal_selesai && <p className="mt-1 text-xs text-red-600">{errors.tanggal_selesai}</p>}
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1">Alasan Lengkap</label>
                        <textarea
                            rows="3"
                            value={data.alasan}
                            onChange={(e) => setData('alasan', e.target.value)}
                            className="block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5"
                            placeholder="Tuliskan alasan pengajuan Anda secara detail..."
                        ></textarea>
                        {errors.alasan && <p className="mt-1 text-xs text-red-600">{errors.alasan}</p>}
                    </div>

                    {/* Jika sakit, wajib foto surat dokter */}
                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1">
                            Bukti / Surat Dokter {data.jenis_izin === 'sakit' && <span className="text-red-500">*wajib</span>}
                        </label>
                        
                        <div 
                            onClick={() => fileInputRef.current.click()}
                            className={`border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-colors ${previewUrl ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 hover:border-indigo-400 bg-gray-50'}`}
                        >
                            {previewUrl ? (
                                <div className="space-y-2">
                                    <img src={previewUrl} alt="Preview" className="mx-auto max-h-40 rounded-lg object-contain shadow-sm" />
                                    <p className="text-xs text-indigo-600 font-medium">Klik untuk mengganti foto</p>
                                </div>
                            ) : (
                                <div className="space-y-2 flex flex-col items-center">
                                    <div className="bg-white p-3 rounded-full shadow-sm">
                                        <Camera className="w-6 h-6 text-gray-400" />
                                    </div>
                                    <p className="text-sm text-gray-500">Ambil foto atau pilih dari galeri</p>
                                </div>
                            )}
                        </div>
                        <input
                            type="file"
                            ref={fileInputRef}
                            className="hidden"
                            accept="image/*"
                            onChange={handleFileChange}
                        />
                        {errors.bukti_foto && <p className="mt-1 text-xs text-red-600">{errors.bukti_foto}</p>}
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full bg-indigo-600 text-white font-bold py-3.5 rounded-xl shadow-md hover:bg-indigo-700 transition-colors mt-6 disabled:opacity-50"
                    >
                        {processing ? 'Mengirim...' : 'Kirim Pengajuan'}
                    </button>
                </form>
            </div>
        </MobileLayout>
    );
}
