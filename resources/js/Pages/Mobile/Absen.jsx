import React, { useState, useEffect, useRef } from 'react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Head, useForm } from '@inertiajs/react';

export default function MobileAbsen({ auth, pegawai, jadwals, presensiHariIni }) {
    const { data, setData, post, processing, errors } = useForm({
        jadwal_id: jadwals?.[0]?.id || '',
        tipe: 'masuk',
        latitude: '',
        longitude: '',
        accuracy: null,
        speed: null,
        captured_at: '',
        mock_suspect: false,
        foto: ''
    });

    const videoRef = useRef(null);
    const canvasRef = useRef(null);
    const [cameraActive, setCameraActive] = useState(false);
    const [locationStatus, setLocationStatus] = useState('Mencari lokasi...');
    const [photoCaptured, setPhotoCaptured] = useState(false);

    const selectedPresensi = presensiHariIni?.find(p => p.jadwal_id == data.jadwal_id);
    let allowedTipe = 'masuk';
    let labelTipe = 'Masuk';
    let isCompleted = false;

    if (selectedPresensi) {
        if (selectedPresensi.jam_masuk && !selectedPresensi.jam_keluar) {
            allowedTipe = 'keluar';
            labelTipe = 'Keluar/Pulang';
        } else if (selectedPresensi.jam_masuk && selectedPresensi.jam_keluar) {
            isCompleted = true;
            labelTipe = 'Sudah Presensi Lengkap';
        }
    } else if (!data.jadwal_id) {
        labelTipe = 'Pilih Jadwal Dulu';
    }

    useEffect(() => {
        if (data.jadwal_id && data.tipe !== allowedTipe && !isCompleted) {
            setData('tipe', allowedTipe);
        }
    }, [data.jadwal_id, allowedTipe, isCompleted]);

    useEffect(() => {
        if (!navigator.geolocation) {
            setLocationStatus('Browser Anda tidak mendukung Geolocation.');
            return;
        }

        setLocationStatus('Mencari lokasi GPS...');
        const samples = [];

        const finalize = () => {
            const avgLat = samples.reduce((s, x) => s + x.lat, 0) / samples.length;
            const avgLng = samples.reduce((s, x) => s + x.lng, 0) / samples.length;
            const last = samples[samples.length - 1];
            const accuracy = last.accuracy;
            const speed = last.speed;

            // [ANTISPOOF] Mock GPS sering menghasilkan koordinat persis sama di tiap sampel.
            const variance = samples.reduce((s, x) => s + Math.abs(x.lat - avgLat) + Math.abs(x.lng - avgLng), 0);
            const mockSuspect = variance < 1e-7;

                setData(d => ({
                    ...d,
                    latitude: avgLat,
                    longitude: avgLng,
                    accuracy: accuracy ?? null,
                    speed: speed ?? null,
                    captured_at: new Date().toISOString(),
                    mock_suspect: mockSuspect,
                }));

            setLocationStatus(
                `Lat: ${avgLat.toFixed(5)}, Lng: ${avgLng.toFixed(5)} | Akurasi: ${accuracy ? accuracy.toFixed(0) + 'm' : '?'}` +
                (mockSuspect ? ' (curiga mock GPS)' : '')
            );
            startCamera();
        };

        const watchId = navigator.geolocation.watchPosition(
            (pos) => {
                samples.push({
                    lat: pos.coords.latitude,
                    lng: pos.coords.longitude,
                    accuracy: pos.coords.accuracy,
                    speed: pos.coords.speed,
                });
                if (samples.length >= 3) {
                    navigator.geolocation.clearWatch(watchId);
                    finalize();
                }
            },
            () => {
                navigator.geolocation.clearWatch(watchId);
                if (samples.length > 0) {
                    finalize();
                } else {
                    setLocationStatus('Gagal mendapatkan lokasi GPS.');
                }
            },
            { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 }
        );

        // Fallback: pakai apa yang ada setelah 4 detik
        const fallback = setTimeout(() => {
            if (samples.length > 0) {
                navigator.geolocation.clearWatch(watchId);
                finalize();
            }
        }, 4000);

        return () => {
            clearTimeout(fallback);
            navigator.geolocation.clearWatch(watchId);
            stopCamera();
        };
    }, []);

    const startCamera = async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
            if (videoRef.current) {
                videoRef.current.srcObject = stream;
                setCameraActive(true);
            }
        } catch (err) {
            console.error("Error accessing camera:", err);
            setLocationStatus('Gagal mengakses kamera. Beri izin kamera.');
        }
    };

    const stopCamera = () => {
        if (videoRef.current && videoRef.current.srcObject) {
            videoRef.current.srcObject.getTracks().forEach(track => track.stop());
        }
    };

    // Fungsi untuk mengambil foto dari video stream
    const handleCameraCapture = () => {
        if (!cameraActive || !videoRef.current || !canvasRef.current) return;
        
        const video = videoRef.current;
        const vw = video.videoWidth;
        const vh = video.videoHeight;
        
        // UI menggunakan aspect-[3/4] (Width/Height = 0.75)
        // Kita harus crop (potong) gambar asli dari webcam agar sesuai dengan preview di UI (terutama di Simulator Desktop yg kameranya landscape)
        let targetWidth = vw;
        let targetHeight = vh;
        
        if (vw / vh > 0.75) {
            // Jika video lebih lebar dari 3:4 (Misal landscape 16:9), potong sampingnya
            targetWidth = vh * 0.75;
        } else {
            // Jika video lebih tinggi dari 3:4, potong atas/bawah
            targetHeight = vw / 0.75;
        }

        const canvas = canvasRef.current;
        canvas.width = targetWidth;
        canvas.height = targetHeight;
        
        const ctx = canvas.getContext('2d');
        
        // Ambil area tengah dari video
        const startX = (vw - targetWidth) / 2;
        const startY = (vh - targetHeight) / 2;
        
        // Gambar potongan frame ke canvas
        ctx.drawImage(video, startX, startY, targetWidth, targetHeight, 0, 0, targetWidth, targetHeight);
        
        // Setup dinamis font size agar proporsional di resolusi berapapun
        const baseSize = Math.max(12, Math.floor(targetWidth * 0.035));
        const stampHeight = baseSize * 5.5;
        
        // Tambahkan background semi-transparan untuk watermark
        ctx.fillStyle = 'rgba(0, 0, 0, 0.6)';
        ctx.fillRect(0, targetHeight - stampHeight, targetWidth, stampHeight);
        
        // Set alignment text di tengah agar lebih aman dan estetik
        ctx.textAlign = 'center';
        
        // Teks Nama Pegawai
        ctx.fillStyle = 'white';
        ctx.font = `bold ${baseSize * 1.2}px sans-serif`;
        ctx.fillText(auth.user.name, targetWidth / 2, targetHeight - (baseSize * 3.5));
        
        // Teks Waktu
        ctx.font = `${baseSize}px sans-serif`;
        ctx.fillText(`Waktu: ${new Date().toLocaleString('id-ID')}`, targetWidth / 2, targetHeight - (baseSize * 2.0));
        
        // Teks Koordinat (Dibatasi 5 desimal)
        ctx.font = `${baseSize * 0.9}px sans-serif`;
        ctx.fillText(`Lokasi: ${data.latitude.toFixed(5)}, ${data.longitude.toFixed(5)}`, targetWidth / 2, targetHeight - (baseSize * 0.6));
        
        // Simpan hasil ke form
        const photoData = canvas.toDataURL('image/jpeg', 0.8);
        setData('foto', photoData);
        setPhotoCaptured(true);
        stopCamera();
    };

    const retakePhoto = () => {
        setPhotoCaptured(false);
        setData('foto', '');
        startCamera();
    };

    const submitPresensi = (e) => {
        e.preventDefault();
        post(route('mobile.storeAbsen'));
    };

    return (
        <MobileLayout user={auth.user} header="Presensi Kamera">
            <Head title="Presensi Mobile" />

            <div className="space-y-6">
                {errors.geofence && (
                    <div className="bg-red-50 text-red-700 p-4 rounded-xl border border-red-200 text-sm font-medium">
                        <span className="flex items-center"><svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> {errors.geofence}</span>
                    </div>
                )}
                {errors.conflict && (
                    <div className="bg-orange-50 text-orange-700 p-4 rounded-xl border border-orange-200 text-sm font-medium">
                        <span className="flex items-center"><svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> {errors.conflict}</span>
                    </div>
                )}
                {data.mock_suspect && (
                    <div className="bg-amber-50 text-amber-800 p-4 rounded-xl border border-amber-200 text-sm font-medium">
                        <span className="flex items-center"><svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg> Lokasi terdeteksi mencurigakan (kemungkinan mock GPS). Presensi akan direview admin.</span>
                    </div>
                )}

                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    {/* Live Camera Area */}
                    <div className="relative bg-gray-900 aspect-[3/4] flex flex-col items-center justify-center overflow-hidden">
                        
                        {!photoCaptured ? (
                            <div className="w-full h-full relative">
                                <video 
                                    ref={videoRef} 
                                    autoPlay 
                                    playsInline 
                                    className={`w-full h-full object-cover ${cameraActive ? 'opacity-100' : 'opacity-0'}`}
                                ></video>
                                
                                {!cameraActive && (
                                    <div className="absolute inset-0 flex flex-col items-center justify-center text-white/50">
                                        <svg className="w-12 h-12 mb-2 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path></svg>
                                        <span>Menyiapkan Kamera...</span>
                                    </div>
                                )}

                                {/* Map Overlay (Semi-transparent) di bagian bawah */}
                                {cameraActive && data.latitude && (
                                    <div className="absolute bottom-0 left-0 w-full h-1/2 opacity-50 pointer-events-none">
                                        <iframe 
                                            width="100%" 
                                            height="100%" 
                                            frameBorder="0" 
                                            scrolling="no" 
                                            marginHeight="0" 
                                            marginWidth="0" 
                                            src={`https://www.openstreetmap.org/export/embed.html?bbox=${data.longitude-0.002},${data.latitude-0.002},${data.longitude+0.002},${data.latitude+0.002}&layer=mapnik&marker=${data.latitude},${data.longitude}`}
                                        ></iframe>
                                        {/* Gradient fade to blend with camera */}
                                        <div className="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/40 to-transparent"></div>
                                    </div>
                                )}

                                {/* Camera Capture Button overlay */}
                                {cameraActive && (
                                    <div className="absolute bottom-6 w-full flex justify-center">
                                        <button 
                                            type="button" 
                                            onClick={handleCameraCapture} 
                                            className="w-16 h-16 rounded-full bg-indigo-600 border-4 border-white/50 flex items-center justify-center focus:outline-none active:scale-95 transition-transform shadow-2xl"
                                        >
                                            <svg className="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                        </button>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <>
                                <img src={data.foto} alt="Selfie with Stamp" className="w-full h-full object-cover" />
                                <div className="absolute top-4 right-4">
                                    <button type="button" onClick={retakePhoto} className="bg-black/50 text-white backdrop-blur-md px-4 py-2 rounded-full text-xs font-bold flex items-center shadow-lg">
                                        <svg className="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Foto Ulang
                                    </button>
                                </div>
                            </>
                        )}
                        
                        {/* Hidden canvas for image processing */}
                        <canvas ref={canvasRef} className="hidden"></canvas>

                        {/* Overlay Location Status */}
                        <div className="absolute top-4 left-4 bg-black/60 backdrop-blur-md text-white text-[10px] px-3 py-1.5 rounded-full font-medium flex items-center border border-white/10 shadow-md">
                            <span className={`w-2 h-2 rounded-full mr-2 ${data.latitude ? 'bg-emerald-400' : 'bg-amber-400 animate-pulse'}`}></span>
                            {locationStatus}
                        </div>
                    </div>

                    {/* Form Controls */}
                    <div className="p-5">
                        <form onSubmit={submitPresensi}>
                            <div className="space-y-4">
                                <div>
                                    <label className="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Pilih Jadwal / Unit</label>
                                    <select 
                                        className="w-full rounded-xl border-gray-200 bg-gray-50 text-gray-900 focus:ring-indigo-500 focus:border-indigo-500 font-medium"
                                        value={data.jadwal_id}
                                        onChange={e => setData('jadwal_id', e.target.value)}
                                        required
                                    >
                                        <option value="">-- Pilih Jadwal Hari Ini --</option>
                                        {jadwals && jadwals.map(j => (
                                            <option key={j.id} value={j.id}>{j.unit_sekolah.nama} ({j.jam_mulai.substring(0,5)} - {j.jam_selesai.substring(0,5)})</option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Tipe Absen</label>
                                    <div className="flex bg-gray-100 p-1 rounded-xl">
                                        <div className={`flex-1 py-2 text-sm font-bold text-center rounded-lg shadow-sm transition-colors ${isCompleted ? 'bg-emerald-100 text-emerald-700' : (data.jadwal_id ? 'bg-white text-indigo-600' : 'bg-gray-200 text-gray-400')}`}>
                                            {labelTipe}
                                        </div>
                                    </div>
                                </div>

                                <button 
                                    type="submit" 
                                    disabled={!photoCaptured || !data.latitude || processing || isCompleted || !data.jadwal_id}
                                    className={`w-full py-3.5 rounded-xl text-white font-bold text-lg shadow-lg transition-all flex items-center justify-center ${photoCaptured && data.latitude && !isCompleted && data.jadwal_id ? 'bg-indigo-600 hover:bg-indigo-700 active:scale-[0.98]' : 'bg-gray-300 text-gray-500 cursor-not-allowed'}`}
                                >
                                    {processing ? 'Menyimpan...' : (isCompleted ? 'Presensi Selesai' : 'Kirim Presensi')}
                                    {photoCaptured && data.latitude && !processing && !isCompleted && <svg className="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </MobileLayout>
    );
}
