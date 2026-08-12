export const KEPEGAWAIAN_META = {
    tetap: { label: 'Tetap', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    honorer: { label: 'Honorer', badge: 'bg-blue-50 text-blue-700 border-blue-200' },
    kontrak: { label: 'Kontrak', badge: 'bg-amber-50 text-amber-700 border-amber-200' },
};

export const kepagawaianBadge = (status) =>
    KEPEGAWAIAN_META[status] || { label: status, badge: 'bg-gray-50 text-gray-600 border-gray-200' };

export const STATUS_AKTIF_BADGE = (aktif) =>
    aktif === 'aktif'
        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
        : 'border-gray-200 bg-gray-100 text-gray-500';
