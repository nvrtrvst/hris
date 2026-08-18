/**
 * Metadata tampilan pegawai.
 * Daftar nilai (status kepegawaian & pendidikan) berasal dari
 * app/Constants/PegawaiConstants.php via props Inertia — file ini hanya
 * menyimpan label tampilan (React), bukan sumber kebenaran nilainya.
 */

export const STATUS_KEPEGAWAIAN_LABELS = {
    tetap: 'Tetap',
    kontrak: 'Kontrak',
    honorer: 'Honorer',
    gtt: 'GTT (Guru Tidak Tetap)',
};

export const statusKepegawaianLabel = (value) => STATUS_KEPEGAWAIAN_LABELS[value] ?? value;
