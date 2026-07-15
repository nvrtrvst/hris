/**
 * Application Constants
 * Centralized configuration for frontend resources
 */

// Map Tile Configuration
export const OSM_TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
export const OSM_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';

// Geocoding Configuration
export const BDC_API_BASE = 'https://api.bigdatacloud.net/data';

// Photo Overlay Configuration
export const PHOTO_OVERLAY_CONFIG = {
    maxWidth: 1024,
    quality: 0.95,
    dateFormat: 'id-ID',
    timeFormat: 'HH:mm:ss'
};