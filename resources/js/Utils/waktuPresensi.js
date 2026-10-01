// Helper tampil waktu presensi (akual, bukan jadwal).
// Kebijakan status telat = detik ketat (Presensi::statusAt), jadi tampilan
// harus memuat detik agar 07:15 telat tidak dianggap bug.

export const fmtDetik = (value, fallback = '—') =>
    value ? String(value).substring(0, 8) : fallback;

// 'HH:MM[:SS]' + menit → 'HH:MM:00' (dibulatkan, wrap 24 jam).
export function tambahMenit(jam, menit) {
    if (!jam) return null;
    const [h = 0, m = 0] = String(jam).split(':').map(Number);
    const total = (((h || 0) * 60 + (m || 0) + (Number(menit) || 0)) % 1440 + 1440) % 1440;
    return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}:00`;
}

// Batas hadir = jam_mulai + toleransi_menit (mirror statusAt di server).
export const batasHadir = (jamMulai, toleransi) =>
    jamMulai ? tambahMenit(jamMulai, toleransi ?? 0) : null;
