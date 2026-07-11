export const formatRupiah = (angka, withDecimal = false) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: withDecimal ? 2 : 0,
        maximumFractionDigits: withDecimal ? 2 : 0,
    }).format(Number(angka) || 0);
};
