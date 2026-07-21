import { useState, useRef, useEffect, useMemo } from 'react';
import { checkGeofence } from '@/Utils/geo';
import { MAP_TILE_URL, MAP_ATTRIBUTION } from '@/Constants/AppConstants';
import { Head, usePage } from '@inertiajs/react';
import MobileLayout from '@/Layouts/MobileLayout';
import { Card, Toggle } from '@/Components/MobileUI';
import { Camera, RefreshCw, MapPin, CheckCircle, AlertCircle, Loader2, LocateFixed, ShieldCheck } from 'lucide-react';

export default function Absen({ auth, pegawai, jadwals, presensiHariIni, officeAttendance = false }) {
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [loadingLocation, setLoadingLocation] = useState(false);
    const [locationError, setLocationError] = useState(null);
    const [showLive, setShowLive] = useState(false);
    const [error, setError] = useState(null);
    const [successMessage, setSuccessMessage] = useState(null);
    const [capturedPhoto, setCapturedPhoto] = useState(null);
    const [isLembur, setIsLembur] = useState(false);
    const [jadwalId, setJadwalId] = useState(null);
    const [currentTime, setCurrentTime] = useState('');
    const [geoStatus, setGeoStatus] = useState('idle');
    const [currentPosition, setCurrentPosition] = useState(null);
    const [geoInfo, setGeoInfo] = useState(null);
    const [geoInfoLoading, setGeoInfoLoading] = useState(false);

    const videoRef = useRef(null);
    const canvasRef = useRef(null);
    const streamRef = useRef(null);
    const photoInputRef = useRef(null);
    const geoControllerRef = useRef(null);
    const watchIdRef = useRef(null);
    const geocodedRef = useRef(false);

    const { flash = {} } = usePage().props;

    useEffect(() => {
        startCamera();
        setCurrentTime(new Date().toLocaleTimeString('id-ID', { hour12: false }));
        const clock = setInterval(() => {
            const now = new Date();
            setCurrentTime(now.toLocaleTimeString('id-ID', { hour12: false }));
        }, 1000);
        getCurrentPosition();
        return () => {
            clearInterval(clock);
            if (streamRef.current) {
                streamRef.current.getTracks().forEach((t) => t.stop());
            }
            if (watchIdRef.current !== null) navigator.geolocation.clearWatch(watchIdRef.current);
            if (geoControllerRef.current) {
                geoControllerRef.current.abort();
            }
        };
    }, []);

    useEffect(() => {
        if (showLive && videoRef.current && streamRef.current) {
            videoRef.current.srcObject = streamRef.current;
            videoRef.current.play().catch(() => {});
        }
    }, [showLive]);

    const startCamera = async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 720 }, height: { ideal: 960 } },
                audio: false,
            });
            streamRef.current = stream;
            setShowLive(true);
        } catch (err) {
            setShowLive(false);
            if (!locationError) setLocationError('Kamera tidak dapat diakses. Gunakan tombol di bawah untuk unggah foto.');
        }
    };

    const stopCamera = () => {
        if (streamRef.current) {
            streamRef.current.getTracks().forEach((t) => t.stop());
            streamRef.current = null;
        }
    };

    const pad = (n) => String(n).padStart(2, '0');

    const evidenceDetails = () => {
        const now = new Date();
        const time = now.toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
            timeZone: 'Asia/Jakarta',
        });
        const date = now.toLocaleDateString('id-ID', {
            weekday: 'long',
            day: '2-digit',
            month: 'long',
            year: 'numeric',
            timeZone: 'Asia/Jakarta',
        });
        const addressParts = [];
        if (geoInfo?.streetName) addressParts.push(`Jl. ${geoInfo.streetName}${geoInfo.streetNumber ? ` No. ${geoInfo.streetNumber}` : ''}`);
        if (geoInfo?.locality) addressParts.push(geoInfo.locality);
        if (geoInfo?.city && geoInfo.city !== geoInfo.locality) addressParts.push(geoInfo.city);
        if (geoInfo?.principalSubdivision) addressParts.push(geoInfo.principalSubdivision);
        if (geoInfo?.postcode) addressParts.push(geoInfo.postcode);

        return {
            label: isLembur ? 'BUKTI PRESENSI LEMBUR' : 'BUKTI PRESENSI',
            time: `${time} WIB`,
            date,
            unit: targetUnit?.nama || targetUnit?.nama_unit || targetUnit?.singkatan || 'Unit belum dipilih',
            address: addressParts.length ? addressParts.join(', ') : 'Alamat belum tersedia',
            coordinates: currentPosition
                ? `${currentPosition.latitude.toFixed(6)}, ${currentPosition.longitude.toFixed(6)}`
                : 'Koordinat belum tersedia',
            accuracy: currentPosition?.accuracy ? `Akurasi GPS +/- ${Math.round(currentPosition.accuracy)} meter` : 'Akurasi GPS tidak tersedia',
        };
    };

    const wrapCanvasText = (ctx, text, x, y, maxWidth, lineHeight, maxLines = 2) => {
        const words = String(text).split(' ');
        const lines = [];
        let line = '';

        words.forEach((word) => {
            const candidate = line ? `${line} ${word}` : word;
            if (ctx.measureText(candidate).width > maxWidth && line) {
                lines.push(line);
                line = word;
            } else {
                line = candidate;
            }
        });
        if (line) lines.push(line);

        lines.slice(0, maxLines).forEach((value, index) => {
            const isLastVisible = index === maxLines - 1 && lines.length > maxLines;
            const displayText = isLastVisible ? `${value.replace(/[,.]$/, '')}...` : value;
            ctx.strokeText(displayText, x, y + index * lineHeight);
            ctx.fillText(displayText, x, y + index * lineHeight);
        });

        return Math.min(lines.length, maxLines) * lineHeight;
    };

    const createEvidencePhoto = (source, sourceWidth, sourceHeight) => {
        return new Promise((resolve) => {
            const MAX = 1024;
            
            // The preview UI is aspect-[3/4] object-cover. We must crop the raw video to match this 3:4 ratio!
            const targetRatio = 3 / 4;
            let cropWidth = sourceWidth;
            let cropHeight = sourceHeight;
            let cropX = 0;
            let cropY = 0;

            if (sourceWidth / sourceHeight > targetRatio) {
                cropWidth = sourceHeight * targetRatio;
                cropX = (sourceWidth - cropWidth) / 2;
            } else {
                cropHeight = sourceWidth / targetRatio;
                cropY = (sourceHeight - cropHeight) / 2;
            }

            const scale = Math.min(1, MAX / Math.max(cropWidth, cropHeight));
            const width = Math.round(cropWidth * scale);
            const videoHeight = Math.round(cropHeight * scale);
            
            const mapHeight = Math.round(videoHeight * 0.33); 
            const height = videoHeight + mapHeight;
            
            const canvas = canvasRef.current;
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            
            // Draw video (cropped to 3:4)
            ctx.drawImage(source, cropX, cropY, cropWidth, cropHeight, 0, 0, width, videoHeight);
            
            ctx.fillStyle = '#f1f5f9';
            ctx.fillRect(0, videoHeight, width, mapHeight);

            const drawOverlays = () => {
                const details = evidenceDetails();
                const padding = Math.max(16, Math.round(width * 0.035));
                const panelHeight = Math.min(Math.round(videoHeight * 0.45), Math.round(width * 0.75));
                const panelTop = videoHeight - panelHeight;
                const titleSize = Math.max(15, Math.round(width * 0.032));
                const timeSize = Math.max(23, Math.round(width * 0.054));
                const bodySize = Math.max(12, Math.round(width * 0.027));
                const bodyLine = Math.round(bodySize * 1.35);

                let y = panelTop + padding;
                ctx.textBaseline = 'top';
                ctx.shadowColor = 'rgba(0, 0, 0, 0.7)';
                ctx.shadowBlur = 6;
                ctx.lineJoin = 'round';
                ctx.strokeStyle = 'rgba(0, 0, 0, 0.5)';
                ctx.lineWidth = 3;

                ctx.fillStyle = isLembur ? '#FCD34D' : '#6EE7B7';
                ctx.font = `700 ${titleSize}px sans-serif`;
                ctx.strokeText(details.label, padding, y);
                ctx.fillText(details.label, padding, y);

                y += titleSize + 8;
                ctx.fillStyle = '#FFFFFF';
                ctx.font = `700 ${timeSize}px ui-monospace, monospace`;
                ctx.strokeText(details.time, padding, y);
                ctx.fillText(details.time, padding, y);

                y += timeSize + 6;
                ctx.font = `600 ${bodySize}px sans-serif`;
                ctx.fillStyle = '#E2E8F0';
                ctx.lineWidth = 2;
                ctx.strokeText(details.date, padding, y);
                ctx.fillText(details.date, padding, y);

                y += bodyLine + 3;
                ctx.fillStyle = '#FFFFFF';
                ctx.font = `700 ${bodySize}px sans-serif`;
                ctx.strokeText(details.unit, padding, y);
                ctx.fillText(details.unit, padding, y);

                y += bodyLine + 3;
                ctx.fillStyle = '#CBD5E1';
                ctx.font = `500 ${bodySize}px sans-serif`;
                y += wrapCanvasText(ctx, details.address, padding, y, width - padding * 2, bodyLine, 2);

                y += 2;
                ctx.fillStyle = '#FFFFFF';
                ctx.font = `600 ${bodySize}px ui-monospace, monospace`;
                ctx.strokeText(details.coordinates, padding, y);
                ctx.fillText(details.coordinates, padding, y);

                y += bodyLine + 2;
                ctx.fillStyle = '#94A3B8';
                ctx.font = `500 ${Math.max(10, bodySize - 1)}px sans-serif`;
                ctx.strokeText(details.accuracy, padding, y);
                ctx.fillText(details.accuracy, padding, y);

                ctx.shadowBlur = 0;
                ctx.shadowColor = 'transparent';
                ctx.strokeStyle = 'transparent';
                ctx.lineWidth = 0;
            };

            const finish = () => {
                drawOverlays();
                resolve(canvas.toDataURL('image/jpeg', 0.84));
            };

            const mapUrl = currentPosition ? (() => {
                const zoom = 18;
                const tiles = 2 ** zoom;
                const x = Math.floor(((currentPosition.longitude + 180) / 360) * tiles);
                const lat = (currentPosition.latitude * Math.PI) / 180;
                const tileY = Math.floor(((1 - Math.log(Math.tan(lat) + 1 / Math.cos(lat)) / Math.PI) / 2) * tiles);
                return MAP_TILE_URL.replace('{z}', String(zoom)).replace('{x}', String(x)).replace('{y}', String(tileY));
            })() : null;

            if (mapUrl) {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.onload = () => {
                    const imgRatio = img.width / img.height;
                    const destRatio = width / mapHeight;
                    let sWidth = img.width;
                    let sHeight = img.height;
                    let sx = 0;
                    let sy = 0;
                    
                    if (imgRatio > destRatio) {
                        sWidth = img.height * destRatio;
                        sx = (img.width - sWidth) / 2;
                    } else {
                        sHeight = img.width / destRatio;
                        sy = (img.height - sHeight) / 2;
                    }
                    
                    ctx.drawImage(img, sx, sy, sWidth, sHeight, 0, videoHeight, width, mapHeight);
                    
                    ctx.fillStyle = 'rgba(255, 255, 255, 0.3)';
                    ctx.fillRect(0, videoHeight, width, mapHeight);
                    
                    const details = evidenceDetails();
                    const panelPad = Math.max(16, Math.round(width * 0.035));
                    const s = width / 512;
                    const boxHeight = 70 * s;
                    const boxY = height - boxHeight - panelPad;
                    const boxWidth = width - panelPad * 2;
                    
                    ctx.fillStyle = 'rgba(255, 255, 255, 0.9)';
                    ctx.beginPath();
                    if (ctx.roundRect) {
                        ctx.roundRect(panelPad, boxY, boxWidth, boxHeight, 8 * s);
                    } else {
                        ctx.rect(panelPad, boxY, boxWidth, boxHeight);
                    }
                    ctx.fill();
                    
                    ctx.textBaseline = 'top';
                    ctx.textAlign = 'left';
                    const titleF = 12 * s;
                    const bodyF = 14 * s;
                    const monoF = 11 * s;
                    
                    ctx.font = `bold ${titleF}px sans-serif`;
                    ctx.fillStyle = '#10b981'; 
                    ctx.fillText('LOKASI PRESENSI', panelPad + 12*s, boxY + 12*s);
                    
                    ctx.font = `bold ${bodyF}px sans-serif`;
                    ctx.fillStyle = '#0f172a';
                    ctx.fillText(details.unit, panelPad + 12*s, boxY + 30*s);
                    
                    ctx.font = `${monoF}px monospace`;
                    ctx.fillStyle = '#64748b'; 
                    ctx.fillText(details.coordinates, panelPad + 12*s, boxY + 50*s);
                    
                    const statusText = geofence?.inside ? 'Dalam radius' : 'Di luar radius';
                    const badgeColor = geofence?.inside ? '#34d399' : '#fb7185';
                    ctx.font = `bold ${titleF}px sans-serif`;
                    const badgeWidth = ctx.measureText(statusText).width + 24*s;
                    const badgeHeight = 28*s;
                    const badgeX = panelPad + boxWidth - badgeWidth - 12*s;
                    const badgeY = boxY + (boxHeight - badgeHeight)/2;
                    
                    ctx.fillStyle = badgeColor;
                    ctx.beginPath();
                    if (ctx.roundRect) {
                        ctx.roundRect(badgeX, badgeY, badgeWidth, badgeHeight, 6*s);
                    } else {
                        ctx.rect(badgeX, badgeY, badgeWidth, badgeHeight);
                    }
                    ctx.fill();
                    
                    ctx.fillStyle = geofence?.inside ? '#064e3b' : '#4c0519';
                    ctx.fillText(statusText, badgeX + 12*s, badgeY + 8*s);
                    
                    const centerX = width / 2;
                    const centerY = videoHeight + (mapHeight / 2) - 20*s;
                    
                    ctx.beginPath();
                    ctx.arc(centerX, centerY, 32*s, 0, 2 * Math.PI);
                    ctx.fillStyle = 'rgba(52, 211, 153, 0.3)';
                    ctx.fill();
                    
                    ctx.beginPath();
                    ctx.arc(centerX, centerY - 6*s, 14*s, 0, 2 * Math.PI);
                    ctx.fillStyle = '#0f766e';
                    ctx.fill();
                    
                    ctx.beginPath();
                    ctx.moveTo(centerX - 12*s, centerY - 2*s);
                    ctx.lineTo(centerX + 12*s, centerY - 2*s);
                    ctx.lineTo(centerX, centerY + 18*s);
                    ctx.fill();
                    
                    ctx.beginPath();
                    ctx.arc(centerX, centerY - 6*s, 5*s, 0, 2 * Math.PI);
                    ctx.fillStyle = '#FFFFFF';
                    ctx.fill();
                    
                    finish();
                };
                img.onerror = () => {
                    finish();
                };
                img.src = mapUrl;
            } else {
                finish();
            }
        });
    };

    const capturePhoto = async () => {
        if (geoBlocked || !currentPosition) return;
        if (!videoRef.current || !canvasRef.current) return;
        const video = videoRef.current;
        if (!video.videoWidth || !video.videoHeight) return;
        
        setIsSubmitting(true);
        const dataUrl = await createEvidencePhoto(video, video.videoWidth, video.videoHeight);
        setCapturedPhoto(dataUrl);
        stopCamera();
        setShowLive(false);
        setIsSubmitting(false);
    };

    const retakePhoto = () => {
        setCapturedPhoto(null);
        startCamera();
    };

    const toggleLembur = () => setIsLembur((prev) => !prev);

    const getCurrentPosition = () => {
        setLoadingLocation(true);
        setGeoStatus('loading');
        if (!navigator.geolocation) {
            setGeoStatus('error');
            setLocationError('Geolocation tidak didukung di perangkat ini.');
            setLoadingLocation(false);
            return;
        }
        const id = navigator.geolocation.watchPosition(
            (pos) => {
                setCurrentPosition({
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude,
                    accuracy: pos.coords.accuracy,
                });
                setGeoStatus('ready');
                setLoadingLocation(false);
                if (geocodedRef.current) return;
                geocodedRef.current = true;
                setGeoInfoLoading(true);
                geoControllerRef.current?.abort();
                geoControllerRef.current = new AbortController();
                fetch(
                    `https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${pos.coords.latitude}&longitude=${pos.coords.longitude}&localityLanguage=id`,
                    { signal: geoControllerRef.current.signal }
                )
                    .then((r) => {
                        if (!r.ok) throw new Error('Geocoding failed');
                        return r.json();
                    })
                    .then((d) =>
                        setGeoInfo({
                            locality: d.locality,
                            city: d.city,
                            principalSubdivision: d.principalSubdivision,
                            postcode: d.postcode,
                            countryName: d.countryName,
                            streetName: d.streetName,
                            streetNumber: d.streetNumber,
                        })
                    )
                    .catch((err) => {
                        if (err.name !== 'AbortError') {
                            setGeoInfo(null);
                        }
                    })
                    .finally(() => setGeoInfoLoading(false));
            },
            (err) => {
                setGeoStatus('error');
                setLocationError(err.message || 'Tidak dapat mengambil lokasi.');
                setLoadingLocation(false);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
        watchIdRef.current = id;
    };

    const handleFileFallback = (e) => {
        if (geoBlocked || !currentPosition) return;
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onloadend = () => {
            const image = new Image();
            image.onload = async () => {
                setIsSubmitting(true);
                const dataUrl = await createEvidencePhoto(image, image.naturalWidth, image.naturalHeight);
                setCapturedPhoto(dataUrl);
                setIsSubmitting(false);
            };
            image.onerror = () => setError('Foto tidak dapat diproses. Pilih file gambar lain.');
            image.src = reader.result;
        };
        reader.readAsDataURL(file);
    };

    const selectJadwal = (id) => setJadwalId(id);

    // Unit geofence target: lembur -> unit primer; reguler -> unit jadwal terpilih (atau primer).
    const lemburUnit =
        pegawai?.units?.find((u) => u.pivot?.is_primary) ?? pegawai?.units?.[0] ?? null;
    const selectedJadwal = jadwals.find((j) => j.id === jadwalId);
    const targetUnit = isLembur
        ? lemburUnit
        : selectedJadwal?.unit_sekolah ?? lemburUnit;

    const geofence = useMemo(() => {
        if (!currentPosition || !targetUnit) return null;
        const lat = parseFloat(targetUnit.latitude);
        const lon = parseFloat(targetUnit.longitude);
        if (isNaN(lat) || isNaN(lon)) return null;
        const radius = targetUnit.radius_meter ?? 50;
        const { inside, distance } = checkGeofence(
            currentPosition.latitude,
            currentPosition.longitude,
            lat,
            lon,
            radius
        );
        return {
            name: targetUnit.nama_unit || targetUnit.nama || 'Unit Sekolah',
            distance,
            radius,
            inside,
        };
    }, [currentPosition, targetUnit]);

    const geoBlocked = geofence && !geofence.inside;
    const geoReady = geofence !== null;

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError(null);
        setSuccessMessage(null);

        if (!capturedPhoto) {
            setError('Silakan ambil foto presensi terlebih dahulu.');
            return;
        }
        if (!isLembur && !officeAttendance && jadwals.length > 0 && !jadwalId) {
            setError('Silakan pilih jadwal presensi Anda.');
            return;
        }
        if (!currentPosition && !isLembur) {
            setError('Lokasi belum tersedia. Pastikan GPS aktif.');
            return;
        }
        if (geoBlocked) {
            setError(
                `Anda di luar radius ${geofence.name} (${Math.round(geofence.distance)}m / batas ${geofence.radius}m). Tidak dapat presensi.`
            );
            return;
        }

        setIsSubmitting(true);
        const openPresensi = presensiHariIni?.find((p) => p.jam_masuk && !p.jam_keluar);
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const payload = {
            _token: token,
            foto: capturedPhoto,
            is_lembur: isLembur,
            jadwal_id: isLembur ? null : jadwalId,
            tipe: openPresensi ? 'keluar' : 'masuk',
            latitude: currentPosition?.latitude ?? null,
            longitude: currentPosition?.longitude ?? null,
            accuracy: currentPosition?.accuracy ?? null,
            mock_suspect: currentPosition?.accuracy === 0,
            captured_at: new Date().toISOString(),
        };
        try {
            const res = await fetch(route('presensi.absen.store'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (res.ok && data.success) {
                setSuccessMessage(data.message || 'Presensi berhasil dikirim.');
                setCapturedPhoto(null);
                setJadwalId(null);
                setTimeout(() => {
                    if (typeof window !== 'undefined') window.location.reload();
                }, 1500);
            } else {
                setError(data.message || 'Gagal mengirim presensi.');
            }
        } catch (err) {
            setError('Terjadi kesalahan jaringan. Coba lagi.');
        } finally {
            setIsSubmitting(false);
        }
    };

    const now = new Date();
    const timeString = `${pad(now.getHours())}.${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
    const dateString = now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

    const placeParts = [];
    if (geoInfo?.streetName) {
        placeParts.push(`Jl. ${geoInfo.streetName}${geoInfo.streetNumber ? ' No. ' + geoInfo.streetNumber : ''}`);
    }
    if (geoInfo?.locality) placeParts.push(geoInfo.locality);
    if (geoInfo?.city && geoInfo.city !== geoInfo.locality) placeParts.push(geoInfo.city);
    if (geoInfo?.principalSubdivision) placeParts.push(geoInfo.principalSubdivision);
    if (geoInfo?.postcode) placeParts.push(geoInfo.postcode);
    const placeString = geoInfoLoading
        ? 'Mendeteksi alamat\u2026'
        : placeParts.length
          ? placeParts.join(', ')
          : 'Lokasi belum tersedia';

    const mapTileUrl = currentPosition
        ? (() => {
              const zoom = 16;
              const tiles = 2 ** zoom;
              const x = Math.floor(((currentPosition.longitude + 180) / 360) * tiles);
              const latitude = (currentPosition.latitude * Math.PI) / 180;
              const y = Math.floor(((1 - Math.log(Math.tan(latitude) + 1 / Math.cos(latitude)) / Math.PI) / 2) * tiles);
              return MAP_TILE_URL.replace('{z}', String(zoom)).replace('{x}', String(x)).replace('{y}', String(y));
          })()
        : null;

    return (
        <MobileLayout user={auth.user}>
            <Head title="Presensi" />

            <div className="mb-5">
                <p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Presensi harian</p>
                <h1 className="mt-1 text-2xl font-bold tracking-tight text-slate-950">Verifikasi kehadiran</h1>
                <p className="mt-1 text-sm leading-relaxed text-slate-600">Pilih jadwal, pastikan GPS valid, lalu ambil foto.</p>
            </div>

            {flash.message && (
                <div role="status" className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.message}</div>
            )}
            {flash.error && (
                <div role="alert" className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{flash.error}</div>
            )}

            {successMessage && (
                <div role="status" className="mb-4 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                    <CheckCircle className="h-5 w-5" /> {successMessage}
                </div>
            )}
            {error && (
                <div role="alert" className="mb-4 flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">
                    <AlertCircle className="h-5 w-5" /> {error}
                </div>
            )}

            <div className="mb-4 grid grid-cols-2 gap-2" aria-label="Status verifikasi">
                <div className={`flex min-h-14 items-center gap-2.5 rounded-xl border px-3 py-2.5 ${geoReady && geofence.inside ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : geoBlocked ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-slate-200 bg-white text-slate-600'}`}>
                    {loadingLocation ? <Loader2 className="h-5 w-5 shrink-0 animate-spin" /> : <LocateFixed className="h-5 w-5 shrink-0" />}
                    <div className="min-w-0">
                        <p className="text-[10px] font-bold uppercase tracking-wider">GPS</p>
                        <p className="truncate text-xs font-semibold">{geoReady ? `${Math.round(geofence.distance)} m dari unit` : geoStatus === 'error' ? 'Tidak tersedia' : 'Mendeteksi...'}</p>
                    </div>
                </div>
                <div className={`flex min-h-14 items-center gap-2.5 rounded-xl border px-3 py-2.5 ${capturedPhoto ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-white text-slate-600'}`}>
                    <ShieldCheck className="h-5 w-5 shrink-0" />
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-wider">Bukti foto</p>
                        <p className="text-xs font-semibold">{capturedPhoto ? 'Siap dikirim' : 'Belum diambil'}</p>
                    </div>
                </div>
            </div>

            {!isLembur && jadwals.length > 0 && (
                <section className="mb-4" aria-labelledby="jadwal-heading">
                    <div className="mb-2 flex items-center justify-between">
                        <h2 id="jadwal-heading" className="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Jadwal hari ini</h2>
                        <span className="text-xs text-slate-500">{jadwals.length} jadwal</span>
                    </div>
                    <div className="flex gap-2 overflow-x-auto pb-1">
                        {jadwals.map((j) => (
                            <button
                                key={j.id}
                                type="button"
                                onClick={() => selectJadwal(j.id)}
                                aria-pressed={jadwalId === j.id}
                                className={`min-h-[76px] min-w-[172px] shrink-0 rounded-xl border p-3 text-left transition-transform active:scale-[0.98] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/15 ${jadwalId === j.id ? 'border-primary bg-emerald-50 ring-1 ring-primary' : 'border-slate-200 bg-white'}`}
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <p className="line-clamp-1 text-sm font-bold text-slate-900">{j.mata_pelajaran?.nama || 'Jadwal'}</p>
                                    {jadwalId === j.id && <CheckCircle className="h-4 w-4 shrink-0 text-primary" />}
                                </div>
                                <p className="mt-1 text-xs text-slate-500">{j.kelas ? `Kelas ${j.kelas.tingkat} ${j.kelas.nama}` : (j.hari || '')}</p>
                                <p className="mt-1 font-mono text-xs font-bold tabular-nums text-primary">{j.jam_mulai} - {j.jam_selesai}</p>
                            </button>
                        ))}
                    </div>
                </section>
            )}

            {!isLembur && officeAttendance && (
                <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                    <p className="text-sm font-bold text-emerald-900">Kehadiran kantor</p>
                    <p className="mt-0.5 text-xs leading-relaxed text-emerald-800">Tidak ada jadwal mengajar hari ini. Presensi memakai unit primer dan jam masuk kantor.</p>
                </div>
            )}

            {/* Camera */}
            <Card press={false} className="overflow-hidden border-slate-300 p-0">
                <div className={`relative w-full overflow-hidden ${capturedPhoto && !showLive ? 'bg-transparent' : 'bg-slate-950'} ${showLive ? 'aspect-[3/4]' : capturedPhoto ? '' : 'aspect-[4/5]'}`}>
                    {showLive ? (
                        <>
                            <video ref={videoRef} autoPlay playsInline muted className="absolute inset-0 h-full w-full object-cover" />
                            <div className="pointer-events-none absolute inset-8 rounded-[42%] border border-white/35" />
                        </>
                    ) : capturedPhoto ? (
                        <img src={capturedPhoto} alt="Pratinjau foto presensi" className="block w-full" />
                    ) : (
                                <button
                                    type="button"
                                    onClick={() => photoInputRef.current?.click()}
                                    disabled={geoBlocked || !currentPosition}
                                    className="flex h-full w-full flex-col items-center justify-center gap-2 bg-slate-950 px-6 text-center text-slate-300 transition-colors active:bg-slate-900 disabled:opacity-40"
                                >
                            <div className="flex h-14 w-14 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-slate-200">
                                <Camera className="h-6 w-6" />
                            </div>
                            <p className="text-sm font-medium">{locationError || 'Kamera tidak dapat diakses'}</p>
                            <p className="text-xs text-slate-400">Ketuk untuk mengunggah foto dari galeri</p>
                        </button>
                    )}

                    {!capturedPhoto && (
                        <span className="absolute left-3 top-3 rounded-lg bg-black/65 px-2.5 py-1.5 font-mono text-xs font-bold tabular-nums text-white">{currentTime}</span>
                    )}

                    {isLembur && !capturedPhoto && (
                        <span className="absolute right-3 top-3 rounded-lg bg-amber-500 px-2.5 py-1.5 text-xs font-bold text-amber-950">LEMBUR</span>
                    )}

                    {currentPosition && !capturedPhoto && (
                        <span className={`absolute right-3 ${isLembur ? 'top-12' : 'top-3'} rounded-lg bg-black/65 px-2.5 py-1.5 text-[10px] font-mono font-semibold text-white`}>
                            {currentPosition.latitude.toFixed(5)}, {currentPosition.longitude.toFixed(5)}
                        </span>
                    )}

                    {!capturedPhoto && (
                        <div className="absolute inset-x-0 bottom-0 flex h-[50%] flex-col justify-end bg-gradient-to-t from-black/90 via-black/40 to-transparent">
                            {showLive && (
                                <div className="absolute bottom-4 left-4 right-1/2 pr-3">
                                    <p className="font-mono text-lg font-bold leading-tight tabular-nums text-white">{timeString}</p>
                                    <p className="text-[11px] font-semibold text-white/90">{dateString}</p>
                                    <p className="mt-1 flex items-start gap-1 text-[10px] leading-tight text-white/80">
                                        <MapPin className="mt-0.5 h-3 w-3 flex-shrink-0 text-emerald-300" />
                                        <span className="line-clamp-2">{placeString}</span>
                                    </p>
                                </div>
                            )}
                            <div className="mb-4 flex justify-center">
                                <button
                                    type="button"
                                    onClick={capturePhoto}
                                    disabled={geoBlocked || !currentPosition}
                                    aria-label="Ambil foto presensi"
                                    className="flex h-[72px] w-[72px] items-center justify-center rounded-full border-4 border-white bg-transparent transition-transform active:scale-95 disabled:opacity-40 disabled:active:scale-100"
                                >
                                    <span className={`h-14 w-14 rounded-full ${isLembur ? 'bg-amber-500' : 'bg-emerald-500'}`} />
                                </button>
                            </div>
                            {showLive && !currentPosition && (
                                <p className="mb-3 text-center text-[10px] font-semibold text-white/80">Menunggu koordinat GPS sebelum foto dapat diambil...</p>
                            )}
                        </div>
                    )}
                </div>

                {showLive && currentPosition && (
                    <div className="relative h-48 overflow-hidden border-t border-slate-200 bg-slate-100">
                        {mapTileUrl && (
                            <img
                                src={mapTileUrl}
                                alt="Peta lokasi presensi"
                                width="512"
                                height="256"
                                loading="eager"
                                onError={(event) => { event.currentTarget.style.display = 'none'; }}
                                className="absolute inset-0 h-full w-full object-cover opacity-50"
                            />
                        )}
                        <div className="absolute inset-0 bg-white/30" />
                        <div className="absolute left-1/2 top-1/2 flex -translate-x-1/2 -translate-y-1/2 items-center justify-center">
                            <span className="absolute h-12 w-12 rounded-full bg-emerald-400/25" />
                            <MapPin className="relative h-8 w-8 fill-primary text-white drop-shadow-lg" />
                        </div>
                        <div className="absolute inset-x-3 bottom-3 flex items-end justify-between gap-3">
                            <div className="rounded-lg bg-white/90 px-3 py-2 text-slate-900 shadow-sm backdrop-blur-sm">
                                <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-primary">Lokasi presensi</p>
                                <p className="mt-1 text-xs font-semibold">{targetUnit?.nama || targetUnit?.nama_unit || 'Unit sekolah'}</p>
                                <p className="mt-1 font-mono text-[10px] tabular-nums text-slate-500">{currentPosition.latitude.toFixed(6)}, {currentPosition.longitude.toFixed(6)}</p>
                            </div>
                            <div className={`rounded-lg px-2.5 py-2 text-[10px] font-bold ${geoReady && geofence.inside ? 'bg-emerald-400 text-emerald-950' : 'bg-rose-400 text-rose-950'}`}>
                                {geoReady && geofence.inside ? 'Dalam radius' : 'Di luar radius'}
                            </div>
                        </div>
                        <span className="absolute right-2 top-2 rounded bg-white/70 px-1.5 py-1 text-[8px] font-semibold text-slate-500" dangerouslySetInnerHTML={{ __html: MAP_ATTRIBUTION }}></span>
                    </div>
                )}
            </Card>

            {capturedPhoto && (
                <div className="mt-3 flex justify-center">
                    <button
                        type="button"
                        onClick={retakePhoto}
                        className="flex min-h-12 items-center gap-2 rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-900 shadow-sm transition-transform active:scale-95"
                    >
                        <RefreshCw className="h-4 w-4" /> Ambil Ulang
                    </button>
                </div>
            )}

            <input type="file" accept="image/*" capture="environment" ref={photoInputRef} className="hidden" onChange={handleFileFallback} />

            <Card press={false} className="mt-3 flex items-center justify-between py-3.5">
                <div>
                    <p className="text-sm font-bold text-slate-900">Mode lembur</p>
                    <p className="mt-0.5 text-xs text-slate-500">Tanpa jadwal, perlu persetujuan admin</p>
                </div>
                <Toggle checked={isLembur} onChange={toggleLembur} tone="amber" />
            </Card>

            <button
                type="button"
                onClick={handleSubmit}
                disabled={isSubmitting || !capturedPhoto || !currentPosition || geoBlocked || (!isLembur && !officeAttendance && (!jadwals.length || !jadwalId))}
                className={`mt-4 flex min-h-14 w-full items-center justify-center rounded-xl px-5 py-4 text-sm font-bold transition-transform active:scale-[0.99] disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500 ${isLembur ? 'bg-amber-500 text-amber-950' : 'bg-primary text-white'}`}
            >
                {isSubmitting ? <><Loader2 className="mr-2 h-5 w-5 animate-spin" />Memproses...</> : isLembur ? 'Kirim bukti lembur' : presensiHariIni?.some((p) => p.jam_masuk && !p.jam_keluar) ? 'Konfirmasi presensi keluar' : 'Konfirmasi presensi masuk'}
            </button>
            <p className="mt-2 text-center text-[11px] leading-relaxed text-slate-500">Foto, waktu, dan koordinat dikirim sebagai bukti presensi.</p>

            <canvas ref={canvasRef} className="hidden" />
        </MobileLayout>
    );
}
