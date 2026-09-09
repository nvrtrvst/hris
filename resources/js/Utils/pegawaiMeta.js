/**
 * Metadata tampilan pegawai.
 *
 * Status kepegawaian tidak lagi hardcode di sini — opsi & label datang dari
 * tabel referensi (status_kepegawaian) via props Inertia `statusKepegawaian`
 * (array {id, kode, label, is_tetap, is_guru}). File ini hanya helper lookup.
 */

export const statusLabelFrom = (options, value) =>
    options?.find((o) => o.kode === value)?.label ?? value;
