export const DAYS = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

export const JENIS_META = {
    mengajar: { badge: 'bg-blue-50 text-blue-700', label: 'Mengajar' },
    piket: { badge: 'bg-amber-50 text-amber-700', label: 'Piket' },
    ekskul: { badge: 'bg-purple-50 text-purple-700', label: 'Ekskul' },
    shift_satpam: { badge: 'bg-slate-100 text-slate-700', label: 'Shift Satpam' },
    shift_kebersihan: { badge: 'bg-emerald-50 text-emerald-700', label: 'Shift Kebersihan' },
    pengawas_ujian: { badge: 'bg-primary-50 text-primary-700', label: 'Pengawas UAS' },
    lainnya: { badge: 'bg-gray-100 text-gray-600', label: 'Lainnya' },
};

export const jenisBadge = (jenis) => JENIS_META[jenis] || JENIS_META.lainnya;

export const jenisLabel = (jenis) => (JENIS_META[jenis] || JENIS_META.lainnya).label;

export const durasiMenit = (mulai, selesai) => {
    const [h1, m1] = String(mulai || '0:0').split(':').map(Number);
    const [h2, m2] = String(selesai || '0:0').split(':').map(Number);

    return Math.max(0, (h2 * 60 + m2) - (h1 * 60 + m1));
};

export const fmtDurasi = (menit) => {
    if (!menit) return '0 menit';
    const h = Math.floor(menit / 60);
    const m = menit % 60;

    return h > 0 ? `${h}j ${m}m` : `${m} mnt`;
};

export const toMinutes = (hms) => {
    if (!hms) return 0;
    const p = String(hms).split(':');

    return (+p[0]) * 60 + (+p[1] || 0);
};
