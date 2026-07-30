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
            header={<h2 className="page-title">Absensi Mandiri</h2>}
        >
            <Head title="Absensi Mandiri" />

            <div className="py-8 bg-surface min-h-screen">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
                    <div className="mb-4">
                        <Link href={route('presensi.index')} className="link inline-flex items-center text-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Kembali ke Riwayat Presensi
                        </Link>
                    </div>
                    <div className="page-card">
                        <div className="p-8">
                            <div className="text-center mb-8">
                                <h3 className="page-title text-2xl">Live Attendance</h3>
                                <p className="page-subtitle">Sistem akan mencatat lokasi Anda dan memvalidasi radius dengan Unit Sekolah.</p>
                            </div>

                            {errors.geofence && (
                                <div className="mb-6 bg-danger-light border-l-4 border-danger p-4 rounded-r-md">
                                    <p className="text-sm text-danger font-medium">{errors.geofence}</p>
                                </div>
                            )}
                            
                            {errors.conflict && (
                                <div className="mb-6 bg-warning-light border-l-4 border-warning p-4 rounded-r-md">
                                    <p className="text-sm text-warning font-medium">{errors.conflict}</p>
                                </div>
                            )}

                            <form onSubmit={submit} className="form-section space-y-8">
                                {/* Jadwal Selection */}
                                <div className="bg-info-light p-6 rounded-card border border-info/20">
                                    <h4 className="section-title text-info mb-4">1. Pilih Jadwal Anda Hari Ini</h4>
                                    {jadwals.length > 0 ? (
                                        <div className="form-grid grid gap-4 md:grid-cols-2">
                                            {jadwals.map(j => {
                                                const hasAbsen = presensis.find(p => p.jadwal_id === j.id);
                                                const status = hasAbsen ? (hasAbsen.jam_keluar ? 'Selesai' : 'Sedang Aktif') : 'Belum Absen';
                                                
                                                return (
                                                    <label key={j.id} className={`cursor-pointer flex p-4 rounded-card border-2 ${data.jadwal_id == j.id ? 'border-primary bg-white' : 'border-transparent bg-info-light hover:bg-blue-100'} transition-all`}>
                                                        <input 
                                                            type="radio" 
                                                            name="jadwal" 
                                                            value={j.id} 
                                                            checked={data.jadwal_id == j.id} 
                                                            onChange={e => setData('jadwal_id', e.target.value)}
                                                            className="mt-1 h-4 w-4 text-primary border-border focus:ring-primary"
                                                        />
                                                        <div className="ml-3">
                                                            <span className="block font-bold text-text-primary uppercase">{j.jenis_jadwal} di {j.unit_sekolah.nama}</span>
                                                            <span className="block text-sm text-text-muted mt-1">{j.jam_mulai.substring(0,5)} - {j.jam_selesai.substring(0,5)}</span>
                                                            <span className={`badge mt-2 ${status === 'Selesai' ? 'badge-success' : (status === 'Sedang Aktif' ? 'badge-warning' : 'badge-neutral')}`}>{status}</span>
                                                        </div>
                                                    </label>
                                                )
                                            })}
                                        </div>
                                    ) : (
                                        <p className="text-danger font-medium">Anda tidak memiliki jadwal hari ini. Hubungi Admin.</p>
                                    )}
                                    {errors.jadwal_id && <p className="form-error">{errors.jadwal_id}</p>}
                                </div>

                                {/* Absen Action */}
                                <div className="form-grid grid-cols-1 md:grid-cols-2 gap-8">
                                    {/* Camera Section */}
                                    <div>
                                        <h4 className="section-title text-text-primary mb-4">2. Verifikasi Wajah</h4>
                                        <div className="relative bg-black rounded-card overflow-hidden aspect-[3/4] flex items-center justify-center shadow-inner">
                                            {!photoData ? (
                                                <>
                                                    <video ref={videoRef} autoPlay playsInline className="w-full h-full object-cover"></video>
                                                    <button type="button" onClick={takePhoto} className="absolute bottom-6 bg-white text-primary p-4 rounded-full shadow-elevated hover:scale-110 transition-transform">
                                                        <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                                    </button>
                                                </>
                                            ) : (
                                                <>
                                                    <img src={photoData} alt="Captured" className="w-full h-full object-cover" />
                                                    <button type="button" onClick={retakePhoto} className="absolute bottom-6 btn-danger rounded-full shadow-elevated">Ulangi Foto</button>
                                                </>
                                            )}
                                            <canvas ref={canvasRef} className="hidden"></canvas>
                                        </div>
                                        {errors.foto && <p className="form-error mt-2 text-center">{errors.foto}</p>}
                                    </div>

                                    {/* Action Section */}
                                    <div className="flex flex-col justify-center">
                                        <div className="bg-surface p-6 rounded-card border border-border mb-8">
                                            <h4 className="section-title text-text-primary mb-2">Status GPS</h4>
                                            {locError ? (
                                                <p className="text-danger text-sm flex items-center"><svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> {locError}</p>
                                            ) : location ? (
                                                <div>
                                                    <p className="text-success text-sm font-medium flex items-center"><svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path></svg> Lokasi Ditemukan</p>
                                                    <p className="text-xs text-text-muted mt-1">{location.lat}, {location.lng}</p>
                                                </div>
                                            ) : (
                                                <p className="text-text-muted text-sm animate-pulse">Mencari sinyal GPS...</p>
                                            )}
                                        </div>

                                        <div className="form-section space-y-4">
                                            <label className="flex items-center space-x-3 p-4 border border-border rounded-card cursor-pointer hover:bg-surface">
                                                <input type="radio" name="tipe" value="masuk" checked={data.tipe === 'masuk'} onChange={e => setData('tipe', e.target.value)} className="h-5 w-5 text-primary" />
                                                <span className="font-bold text-lg text-text-primary">Absen MASUK</span>
                                            </label>
                                            <label className="flex items-center space-x-3 p-4 border border-border rounded-card cursor-pointer hover:bg-surface">
                                                <input type="radio" name="tipe" value="keluar" checked={data.tipe === 'keluar'} onChange={e => setData('tipe', e.target.value)} className="h-5 w-5 text-primary" />
                                                <span className="font-bold text-lg text-text-primary">Absen KELUAR</span>
                                            </label>
                                        </div>

                                        <button
                                            type="submit"
                                            disabled={processing || !photoData || !location || jadwals.length === 0}
                                            className="btn-primary btn-lg w-full mt-8"
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
