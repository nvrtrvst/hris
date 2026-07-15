/**
 * Calculate distance between two geographic points using Haversine formula
 * @param {number} lat1 - Latitude of first point
 * @param {number} lon1 - Longitude of first point
 * @param {number} lat2 - Latitude of second point
 * @param {number} lon2 - Longitude of second point
 * @returns {number} Distance in meters
 */
export const calculateDistance = (lat1, lon1, lat2, lon2) => {
    const R = 6371000; // Earth radius in meters
    const toRad = (d) => (d * Math.PI) / 180;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
};

/**
 * Check if a point is within geofence radius
 * @param {number} userLat - User latitude
 * @param {number} userLon - User longitude
 * @param {number} centerLat - Center point latitude
 * @param {number} centerLon - Center point longitude
 * @param {number} radiusMeters - Radius in meters
 * @returns {{ inside: boolean, distance: number }}
 */
export const checkGeofence = (userLat, userLon, centerLat, centerLon, radiusMeters) => {
    const distance = calculateDistance(userLat, userLon, centerLat, centerLon);
    return {
        inside: distance <= radiusMeters,
        distance
    };
};
