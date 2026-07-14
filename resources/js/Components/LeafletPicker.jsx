import React, { useEffect, useRef } from 'react';

const LEAFLET_CSS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
const LEAFLET_JS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
const DEFAULT_CENTER = [-6.2, 106.816]; // Indonesia (Jakarta) sebagai fallback awal

function loadLeaflet() {
    return new Promise((resolve) => {
        if (window.L) return resolve(window.L);
        if (!document.querySelector(`link[href="${LEAFLET_CSS}"]`)) {
            const l = document.createElement('link');
            l.rel = 'stylesheet';
            l.href = LEAFLET_CSS;
            document.head.appendChild(l);
        }
        if (document.querySelector(`script[src="${LEAFLET_JS}"]`)) {
            const t = setInterval(() => {
                if (window.L) {
                    clearInterval(t);
                    resolve(window.L);
                }
            }, 50);
            return;
        }
        const s = document.createElement('script');
        s.src = LEAFLET_JS;
        s.onload = () => resolve(window.L);
        s.onerror = () => resolve(null);
        document.head.appendChild(s);
    });
}

export default function LeafletPicker({ lat, lng, radius, onChange }) {
    const containerRef = useRef(null);
    const mapRef = useRef(null);
    const markerRef = useRef(null);
    const circleRef = useRef(null);
    const onChangeRef = useRef(onChange);
    onChangeRef.current = onChange;

    const numLat = parseFloat(lat);
    const numLng = parseFloat(lng);
    const hasPoint = !isNaN(numLat) && !isNaN(numLng);
    const center = hasPoint ? [numLat, numLng] : DEFAULT_CENTER;
    const r = parseFloat(radius) || 50;

    // Inisialisasi peta sekali saja
    useEffect(() => {
        let cancelled = false;
        loadLeaflet().then((L) => {
            if (cancelled || !containerRef.current || !L || mapRef.current) return;
            const map = L.map(containerRef.current).setView(center, hasPoint ? 16 : 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap',
            }).addTo(map);
            mapRef.current = map;

            const place = (p) => {
                if (!markerRef.current) {
                    markerRef.current = L.marker(p, { draggable: true }).addTo(map);
                    markerRef.current.on('dragend', (e) => {
                        const q = e.target.getLatLng();
                        onChangeRef.current(q.lat.toFixed(6), q.lng.toFixed(6));
                    });
                } else {
                    markerRef.current.setLatLng(p);
                }
                if (!circleRef.current) {
                    circleRef.current = L.circle(p, {
                        radius: r,
                        color: '#0F3D3E',
                        fillColor: '#0F3D3E',
                        fillOpacity: 0.12,
                    }).addTo(map);
                } else {
                    circleRef.current.setLatLng(p);
                }
            };

            if (hasPoint) place(center);

            map.on('click', (e) => {
                place(e.latlng);
                onChangeRef.current(e.latlng.lat.toFixed(6), e.latlng.lng.toFixed(6));
            });
        });
        return () => {
            cancelled = true;
            if (mapRef.current) {
                mapRef.current.remove();
                mapRef.current = null;
                markerRef.current = null;
                circleRef.current = null;
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Sinkron perubahan dari input (ketik) ke peta
    useEffect(() => {
        if (!mapRef.current) return;
        const p = [parseFloat(lat), parseFloat(lng)];
        if (!isNaN(p[0]) && !isNaN(p[1])) {
            if (markerRef.current) markerRef.current.setLatLng(p);
            else if (window.L) {
                markerRef.current = window.L.marker(p, { draggable: true }).addTo(mapRef.current);
                markerRef.current.on('dragend', (e) => {
                    const q = e.target.getLatLng();
                    onChangeRef.current(q.lat.toFixed(6), q.lng.toFixed(6));
                });
            }
            mapRef.current.setView(p, Math.max(mapRef.current.getZoom(), 16));
            if (circleRef.current) {
                circleRef.current.setLatLng(p);
                circleRef.current.setRadius(r);
            } else if (window.L) {
                circleRef.current = window.L.circle(p, { radius: r, color: '#0F3D3E', fillColor: '#0F3D3E', fillOpacity: 0.12 }).addTo(mapRef.current);
            }
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [lat, lng, radius]);

    return (
        <div>
            <div ref={containerRef} className="h-72 w-full rounded-xl overflow-hidden border border-gray-200 z-0" />
            <p className="mt-2 text-xs text-gray-500">
                Klik pada peta atau geser penanda untuk menentukan titik pusat sekolah. Lingkaran
                menunjukkan radius toleransi absen.
            </p>
        </div>
    );
}
