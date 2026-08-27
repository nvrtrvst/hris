// Validasi ukuran & tipe file di sisi klien sebelum upload.
// Tujuannya: feedback langsung (seperti notif WhatsApp) dan pesan jelas,
// bukan error 422/413 yang membingungkan.

export function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));
    const val = bytes / Math.pow(1024, i);
    return `${val.toFixed(val >= 10 || i === 0 ? 0 : 1)} ${units[i]}`;
}

/**
 * Validasi file upload (ukuran + tipe).
 * @param {File} file
 * @param {{ maxBytes: number, accept: string[], label?: string }} opts
 *   accept: array MIME, mis. ['image/jpeg','image/png','image/webp']
 * @returns {string|null} pesan error (Indonesia) atau null bila valid.
 */
export function validateUpload(file, { maxBytes, accept, label = 'File' }) {
    if (!file) return null;

    if (file.size > maxBytes) {
        return `${label} terlalu besar. Maksimal ${formatBytes(maxBytes)}.`;
    }

    if (accept && accept.length) {
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        const ok = accept.some((a) => {
            if (a.includes('/')) return a === file.type; // MIME, mis. image/jpeg
            return a.toLowerCase() === ext; // ekstensi, mis. docx
        });
        if (!ok) {
            const labels = accept.map((a) => (a.includes('/') ? (a.split('/')[1] || a) : a).toUpperCase()).join(', ');
            return `Format ${label} tidak didukung. Gunakan: ${labels}.`;
        }
    }

    return null;
}
