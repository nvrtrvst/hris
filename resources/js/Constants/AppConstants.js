/**
 * Application Constants
 * Centralized configuration for frontend resources
 */

// Map Tile Configuration
export const MAP_TILE_URL = 'https://mt1.google.com/vt/lyrs=y&hl=id&x={x}&y={y}&z={z}';
export const MAP_ATTRIBUTION = '&copy; Google Maps';

// Geocoding Configuration
export const BDC_API_BASE = 'https://api.bigdatacloud.net/data';

// Photo Overlay Configuration
export const PHOTO_OVERLAY_CONFIG = {
    maxWidth: 1024,
    quality: 0.95,
    dateFormat: 'id-ID',
    timeFormat: 'HH:mm:ss'
};