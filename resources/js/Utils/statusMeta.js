/**
 * Badge tampilan status kepegawaian. Key = grup flag dari tabel referensi
 * (is_tetap/is_guru), bukan kode individual — menyesuaikan 6 status GTYS/PTY.
 */
export const KEPEGAWAIAN_META = {
    guru_tetap_yayasan: { label: 'GTYS', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    pegawai_tetap_yayasan: { label: 'PTY', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    guru_tidak_tetap: { label: 'GTT', badge: 'bg-amber-50 text-amber-700 border-amber-200' },
    pegawai_tidak_tetap: { label: 'Pegawai Tidak Tetap', badge: 'bg-amber-50 text-amber-700 border-amber-200' },
    guru_pemula: { label: 'Guru Pemula', badge: 'bg-blue-50 text-blue-700 border-blue-200' },
    pegawai_pemula: { label: 'Pegawai Pemula', badge: 'bg-blue-50 text-blue-700 border-blue-200' },
};

export const kepagawaianBadge = (status) =>
    KEPEGAWAIAN_META[status] || { label: status, badge: 'bg-gray-50 text-gray-600 border-gray-200' };

export const STATUS_AKTIF_BADGE = (aktif) =>
    aktif === 'aktif'
        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
        : 'border-gray-200 bg-gray-100 text-gray-500';
