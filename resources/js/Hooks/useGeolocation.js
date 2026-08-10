import { useCallback, useRef, useState } from 'react';

/**
 * Hook geolocation bersama utk halaman presensi mobile.
 *
 * Mengelola: watchPosition GPS (high accuracy), status loading/error,
 * reverse-geocoding via BigDataCloud (sekali saja per sesi).
 *
 * @returns {object} state + getCurrentPosition() + clearGeolocation()
 */
export function useGeolocation() {
    const [loadingLocation, setLoadingLocation] = useState(false);
    const [geoStatus, setGeoStatus] = useState('idle');
    const [currentPosition, setCurrentPosition] = useState(null);
    const [geoInfo, setGeoInfo] = useState(null);
    const [geoInfoLoading, setGeoInfoLoading] = useState(false);
    const [locationError, setLocationError] = useState(null);

    const geoControllerRef = useRef(null);
    const watchIdRef = useRef(null);
    const geocodedRef = useRef(false);

    const getCurrentPosition = useCallback(() => {
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
                    .then((d) => setGeoInfo({
                        locality: d.locality,
                        city: d.city,
                        principalSubdivision: d.principalSubdivision,
                        postcode: d.postcode,
                        countryName: d.countryName,
                        streetName: d.streetName,
                        streetNumber: d.streetNumber,
                    }))
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
    }, []);

    const clearGeolocation = useCallback(() => {
        if (watchIdRef.current !== null) navigator.geolocation.clearWatch(watchIdRef.current);
        if (geoControllerRef.current) {
            geoControllerRef.current.abort();
        }
    }, []);

    return {
        loadingLocation,
        geoStatus,
        currentPosition,
        geoInfo,
        geoInfoLoading,
        locationError,
        getCurrentPosition,
        clearGeolocation,
    };
}
