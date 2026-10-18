/**
 * Semantik realisasi "sudah pulang" (pengajuan pulang cepat & baris presensi).
 *
 * Menerima objek apa pun yang punya jam_keluar + is_pulang_awal +
 * menit_pulang_awal + pulang_awal_diizinkan - presensi mentah maupun objek
 * `realisasi` yang dilampirkan controller.
 *
 * @returns {null|{ada: boolean, tone: string, jam?: string, awal?: boolean, menit?: number, diizinkan?: boolean}}
 *          null = belum ada presensi keluar dan pengajuan belum disetujui (tak perlu ditampilkan).
 */
export function realisasiInfo(row, status) {
    if (row?.jam_keluar) {
        const awal = Boolean(row.is_pulang_awal);

        return {
            ada: true,
            jam: String(row.jam_keluar).substring(0, 5),
            awal,
            menit: row.menit_pulang_awal ?? 0,
            diizinkan: Boolean(row.pulang_awal_diizinkan),
            tone: !awal ? 'slate' : row.pulang_awal_diizinkan ? 'emerald' : 'amber',
        };
    }

    if (status === 'disetujui') {
        return { ada: false, tone: 'amber' };
    }

    return null;
}
