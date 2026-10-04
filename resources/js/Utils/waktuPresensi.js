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

// Menit sejak 00:00 dari 'HH:MM[:SS]'.
export const jamKeMenit = (jam) => {
    const [h = 0, m = 0] = String(jam ?? '').split(':').map(Number);
    return (h || 0) * 60 + (m || 0);
};

// Menit → 'HH:MM'.
export const menitKeJam = (menit) => {
    const t = ((Math.round(menit) % 1440) + 1440) % 1440;
    return `${String(Math.floor(t / 60)).padStart(2, '0')}:${String(t % 60).padStart(2, '0')}`;
};

// Tampil JP: bulat → '7', pecahan → '6.2' (mirror PHP fmtJp).
export const fmtJp = (value) => String(Math.round((Number(value) || 0) * 100) / 100);

// Bobot JP 1 baris = durasi jadwal ÷ durasi_jp unit (mirror jpWeight server).
export function hitungJp(item, durasiJp = 45) {
    const d = Number(durasiJp) > 0 ? Number(durasiJp) : 45;
    const jadwal = item?.jadwal ?? item;
    const total = jamKeMenit(jadwal?.jam_selesai) - jamKeMenit(jadwal?.jam_mulai);
    return total > 0 ? total / d : 0;
}

// Pecah jadwal jadi slot per JP: [{mulai:'HH:MM', selesai:'HH:MM'}].
// Bukan kelipatan durasi_jp → dibulatkan, minimal 1 slot (tampilan saja).
export function splitJadwalKeJp(item, durasiJp = 45) {
    const d = Number(durasiJp) > 0 ? Number(durasiJp) : 45;
    const jadwal = item?.jadwal ?? item;
    const mulai = jamKeMenit(jadwal?.jam_mulai);
    const selesai = jamKeMenit(jadwal?.jam_selesai);
    const n = Math.max(1, Math.round((selesai - mulai) / d));
    return Array.from({ length: n }, (_, k) => ({
        mulai: menitKeJam(mulai + k * d),
        selesai: menitKeJam(mulai + (k + 1) * d),
    }));
}
