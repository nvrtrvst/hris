/**
 * Metadata tampilan pegawai.
 * Daftar nilai (status kepegawaian & pendidikan) berasal dari
 * app/Constants/PegawaiConstants.php via props Inertia — file ini hanya
 * menyimpan label tampilan (React), bukan sumber kebenaran nilainya.
 */

export const STATUS_KEPEGAWAIAN_LABELS = {
    guru_tetap_yayasan: 'GTYS (Guru Tetap Yayasan)',
    guru_tidak_tetap: 'GTT (Guru Tidak Tetap)',
    guru_pemula: 'Guru Pemula',
    ptt: 'PTT (Pegawai Tidak Tetap)',
    pegawai_tidak_tetap: 'Pegawai Tidak Tetap',
    pegawai_pemula: 'Pegawai Pemula',
};

export const statusKepegawaianLabel = (value) => STATUS_KEPEGAWAIAN_LABELS[value] ?? value;
