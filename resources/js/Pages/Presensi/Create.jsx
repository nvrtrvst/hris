import React, { useState, useEffect, useRef } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Create({ auth, jadwals, presensis, pegawai }) {
    const [location, setLocation] = useState(null);
    const [locError, setLocError] = useState('');
    const [photoData, setPhotoData] = useState(null);
    const videoRef = useRef(null);
    const canvasRef = useRef(null);
    const [stream, setStream] = useState(null);

    const { data, setData, post, processing, errors } = useForm({
        pegawai_id: pegawai.id,
        jadwal_id: jadwals.length > 0 ? jadwals[0].id : '',
        tipe: 'masuk',
        latitude: '',
        longitude: '',
        foto: '',
    });

    useEffect(() => {
        // Get Geolocation
        if ("geolocation" in navigator) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    setLocation({
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    });
                    setData(data => ({
                        ...data,
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude
                    }));
                },
                (error) => {
                    setLocError("Tidak dapat mengakses lokasi. Pastikan GPS aktif dan izin diberikan.");
                },
                { enableHighAccuracy: true }
            );
        } else {
            setLocError("Browser Anda tidak mendukung Geolocation.");
        }

        // Setup Camera
        const startCamera = async () => {
            try {
                const mediaStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
                setStream(mediaStream);
                if (videoRef.current) {
                    videoRef.current.srcObject = mediaStream;
                }
            } catch (err) {
                console.error("Camera error:", err);
            }
        };

        startCamera();

        return () => {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
        };
    }, []);

    const takePhoto = () => {
        if (videoRef.current && canvasRef.current) {
            const context = canvasRef.current.getContext('2d');
            canvasRef.current.width = videoRef.current.videoWidth;
            canvasRef.current.height = videoRef.current.videoHeight;
            context.drawImage(videoRef.current, 0, 0, canvasRef.current.width, canvasRef.current.height);
            const dataUrl = canvasRef.current.toDataURL('image/jpeg');
            setPhotoData(dataUrl);
            setData('foto', dataUrl);
        }
    };

    const retakePhoto = () => {
        setPhotoData(null);
        setData('foto', '');
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('presensi.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-2xl text-gray-800 leading-tight">Absensi Mandiri</h2>}
        >
            <Head title="Absensi Mandiri" />

            <div className="py-12 bg-gray-50 min-h-screen">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-xl sm:rounded-2xl border border-gray-100">
                        <div className="p-8">
                            <div className="text-center mb-8">
                                <h3 className="text-2xl font-bold text-gray-900">Live Attendance</h3>
                                <p className="text-gray-500 mt-2">Sistem akan mencatat lokasi Anda dan memvalidasi radius dengan Unit Sekolah.</p>
                            </div>

                            {errors.geofence && (
                                <div className="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-md">
                                    <p className="text-sm text-red-700 font-medium">{errors.geofence}</p>
                                </div>
                            )}
                            
                            {errors.conflict && (
                                <div className="mb-6 bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-md">
                                    <p className="text-sm text-amber-700 font-medium">{errors.conflict}</p>
                                </div>
                            )}

                            <form onSubmit={submit} className="space-y-8">
                                {/* Jadwal Selection */}
                                <div className="bg-blue-50 p-6 rounded-xl border border-blue-100">
                                    <h4 className="font-bold text-blue-900 mb-4">1. Pilih Jadwal Anda Hari Ini</h4>
                                    {jadwals.length > 0 ? (
                                        <div className="grid gap-4 md:grid-cols-2">
                                            {jadwals.map(j => {
                                                const hasAbsen = presensis.find(p => p.jadwal_id === j.id);
                                                const status = hasAbsen ? (hasAbsen.jam_keluar ? 'Selesai' : 'Sedang Aktif') : 'Belum Absen';
                                                
                                                return (
                                                    <label key={j.id} className={`cursor-pointer flex p-4 rounded-lg border-2 ${data.jadwal_id == j.id ? 'border-indigo-600 bg-white' : 'border-transparent bg-blue-100 hover:bg-blue-200'} transition-all`}>
                                                        <input 
                                                            type="radio" 
                                                            name="jadwal" 
                                                            value={j.id} 
                                                            checked={data.jadwal_id == j.id} 
                                                            onChange={e => setData('jadwal_id', e.target.value)}
                                                            className="mt-1 h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500"
                                                        />
                                                        <div className="ml-3">
                                                            <span className="block font-bold text-gray-900 uppercase">{j.jenis_jadwal} di {j.unit_sekolah.nama}</span>
                                                            <span className="block text-sm text-gray-500 mt-1">{j.jam_mulai.substring(0,5)} - {j.jam_selesai.substring(0,5)}</span>
                                                            <span className={`inline-block mt-2 px-2 py-1 text-xs font-semibold rounded ${status === 'Selesai' ? 'bg-green-100 text-green-800' : (status === 'Sedang Aktif' ? 'bg-amber-100 text-amber-800' : 'bg-gray-200 text-gray-800')}`}>{status}</span>
                                                        </div>
                                                    </label>
                                                )
                                            })}
                                        </div>
                                    ) : (
                                        <p className="text-red-500 font-medium">Anda tidak memiliki jadwal hari ini. Hubungi Admin.</p>
                                    )}
                                    {errors.jadwal_id && <p className="mt-2 text-sm text-red-600">{errors.jadwal_id}</p>}
                                </div>

                                {/* Absen Action */}
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    {/* Camera Section */}
                                    <div>
                                        <h4 className="font-bold text-gray-900 mb-4">2. Verifikasi Wajah</h4>
                                        <div className="relative bg-black rounded-xl overflow-hidden aspect-[3/4] flex items-center justify-center shadow-inner">
                                            {!photoData ? (
                                                <>
                                                    <video ref={videoRef} autoPlay playsInline className="w-full h-full object-cover"></video>
                                                    <button type="button" onClick={takePhoto} className="absolute bottom-6 bg-white text-indigo-600 p-4 rounded-full shadow-lg hover:scale-110 transition-transform">
                                                        <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                                    </button>
                                                </>
                                            ) : (
                                                <>
                                                    <img src={photoData} alt="Captured" className="w-full h-full object-cover" />
                                                    <button type="button" onClick={retakePhoto} className="absolute bottom-6 bg-red-600 text-white px-4 py-2 rounded-full shadow-lg font-medium hover:bg-red-700">Ulangi Foto</button>
                                                </>
                                            )}
                                            <canvas ref={canvasRef} className="hidden"></canvas>
                                        </div>
                                        {errors.foto && <p className="mt-2 text-sm text-red-600 text-center">{errors.foto}</p>}
                                    </div>

                                    {/* Action Section */}
                                    <div className="flex flex-col justify-center">
                                        <div className="bg-gray-50 p-6 rounded-xl border border-gray-200 mb-8">
                                            <h4 className="font-bold text-gray-900 mb-2">Status GPS</h4>
                                            {locError ? (
                                                <p className="text-red-500 text-sm flex items-center"><svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> {locError}</p>
                                            ) : location ? (
                                                <div>
                                                    <p className="text-green-600 text-sm font-medium flex items-center"><svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path></svg> Lokasi Ditemukan</p>
                                                    <p className="text-xs text-gray-500 mt-1">{location.lat}, {location.lng}</p>
                                                </div>
                                            ) : (
                                                <p className="text-gray-500 text-sm animate-pulse">Mencari sinyal GPS...</p>
                                            )}
                                        </div>

                                        <div className="space-y-4">
                                            <label className="flex items-center space-x-3 p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                                                <input type="radio" name="tipe" value="masuk" checked={data.tipe === 'masuk'} onChange={e => setData('tipe', e.target.value)} className="h-5 w-5 text-indigo-600" />
                                                <span className="font-bold text-lg text-gray-900">Absen MASUK</span>
                                            </label>
                                            <label className="flex items-center space-x-3 p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                                                <input type="radio" name="tipe" value="keluar" checked={data.tipe === 'keluar'} onChange={e => setData('tipe', e.target.value)} className="h-5 w-5 text-indigo-600" />
                                                <span className="font-bold text-lg text-gray-900">Absen KELUAR</span>
                                            </label>
                                        </div>

                                        <button
                                            type="submit"
                                            disabled={processing || !photoData || !location || jadwals.length === 0}
                                            className="mt-8 w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 px-8 rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 disabled:opacity-50 text-lg"
                                        >
                                            {processing ? 'Memproses...' : 'Kirim Absensi'}
                                        </button>
                                    </div>
                                </div>
                            </form>

                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
