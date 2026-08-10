<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Slip Gaji - {{ $penggajian->pegawai->nama_lengkap }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #0f3d3e; padding-bottom: 10px; }
        .header h1 { font-size: 18px; color: #0f3d3e; margin: 0; text-transform: uppercase; letter-spacing: 2px; }
        .header p { font-size: 10px; color: #64748b; margin: 4px 0 0; }
        .info { margin-bottom: 15px; }
        .info table { width: 100%; border-collapse: collapse; }
        .info td { padding: 2px 0; }
        .info td:first-child { width: 140px; font-weight: bold; color: #475569; }
        .section-title { background: #0f3d3e; color: white; padding: 6px 10px; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin: 15px 0 0; }
        table.details { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.details th { background: #f1f5f9; padding: 6px 8px; text-align: left; font-size: 10px; text-transform: uppercase; color: #475569; }
        table.details td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; }
        table.details tr:last-child td { border-bottom: none; }
        .amount { text-align: right; font-family: 'DejaVu Sans Mono', monospace; }
        .total-row td { font-weight: bold; border-top: 2px solid #0f3d3e; padding-top: 6px; }
        .footer { margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 8px; font-size: 9px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Slip Gaji</h1>
        <p>Periode {{ $penggajian->periode_bulan }}</p>
    </div>

    <div class="info">
        <table>
            <tr><td>Nama</td><td>: {{ $penggajian->pegawai->nama_lengkap }}</td></tr>
            <tr><td>Unit</td><td>: {{ $penggajian->pegawai->units->firstWhere('pivot.is_primary', true)?->nama ?? $penggajian->pegawai->units->first()?->nama ?? '-' }}</td></tr>
            <tr><td>Status</td><td>: {{ $penggajian->pegawai->status_kepegawaian }}</td></tr>
            <tr><td>Status Gaji</td><td>: {{ strtoupper($penggajian->status) }}</td></tr>
        </table>
    </div>

    <div class="section-title">Pendapatan</div>
    <table class="details">
        <thead><tr><th>Komponen</th><th class="amount">Nominal</th></tr></thead>
        <tbody>
            @foreach ($penggajian->details->where('tipe', 'pendapatan') as $d)
            <tr><td>{{ $d->nama_komponen }}</td><td class="amount">Rp {{ number_format($d->nominal, 0, ',', '.') }}</td></tr>
            @endforeach
            <tr class="total-row"><td>Total Pendapatan</td><td class="amount">Rp {{ number_format($penggajian->total_pendapatan, 0, ',', '.') }}</td></tr>
        </tbody>
    </table>

    <div class="section-title">Potongan</div>
    <table class="details">
        <thead><tr><th>Komponen</th><th class="amount">Nominal</th></tr></thead>
        <tbody>
            @foreach ($penggajian->details->where('tipe', 'potongan') as $d)
            <tr><td>{{ $d->nama_komponen }}</td><td class="amount">Rp {{ number_format($d->nominal, 0, ',', '.') }}</td></tr>
            @endforeach
            <tr class="total-row"><td>Total Potongan</td><td class="amount">Rp {{ number_format($penggajian->total_potongan, 0, ',', '.') }}</td></tr>
        </tbody>
    </table>

    <table class="details" style="margin-top: 15px;">
        <thead><tr><th style="font-size: 12px;">Gaji Bersih</th><th class="amount" style="font-size: 12px; color: #0f3d3e;">Rp {{ number_format($penggajian->gaji_bersih, 0, ',', '.') }}</th></tr></thead>
    </table>

    <div class="footer">
        Dokumen ini digenerate otomatis dari sistem HRIS Yayasan pada {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>