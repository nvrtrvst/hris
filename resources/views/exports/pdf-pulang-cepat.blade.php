<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Formulir Izin Pulang Cepat</title>
<style>
    @page { margin: 16mm 12mm; }
    * { box-sizing: border-box; }
    body { font-family: 'Helvetica', 'Arial', sans-serif; color: #1f2937; font-size: 11px; margin: 0; }
    .kop { text-align: center; margin-bottom: 6px; }
    .kop-logo { margin-bottom: 4px; }
    .kop-logo img { height: 64px; width: auto; display: inline-block; }
    .kop-name { font-size: 17px; font-weight: 800; letter-spacing: .5px; color: #0f3d3e; text-transform: uppercase; line-height: 1.2; }
    .kop-tagline { font-size: 9.5px; font-style: italic; color: #0f3d3e; margin-top: 2px; }
    .kop-address { font-size: 9.5px; color: #374151; margin-top: 4px; }
    .kop-contact { font-size: 9px; color: #6b7280; margin-top: 2px; }
    .doc-title { text-align: center; font-size: 14px; font-weight: 800; margin: 10px 0 2px; text-transform: uppercase; }
    .doc-meta { text-align: center; font-size: 10px; color: #374151; margin-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .kv td { border: 1px solid #d1d5db; padding: 5px 6px; font-size: 10px; }
    .kv .k { width: 32%; background: #f3f4f6; font-weight: 700; }
    p { font-size: 10.5px; line-height: 1.55; margin: 8px 0; }
    .status-box { border: 1px solid #d1d5db; background: #f9fafb; padding: 8px; font-size: 10px; margin-bottom: 6px; }
    .badge { display: inline-block; padding: 2px 7px; border-radius: 3px; font-size: 9px; font-weight: 700; text-transform: uppercase; }
    .badge-pending { background: #fef3c7; color: #92400e; }
    .badge-disetujui { background: #d1fae5; color: #065f46; }
    .badge-ditolak { background: #fee2e2; color: #991b1b; }
    .ttd { margin-top: 22px; text-align: center; font-size: 10px; }
    .ttd-row { display: flex; justify-content: space-between; }
    .ttd .box { display: inline-block; width: 45%; text-align: center; }
    .ttd .sp { height: 44px; }
    .footer { margin-top: 14px; font-size: 9px; color: #6b7280; display: flex; justify-content: space-between; }
</style>
</head>
<body>
    <div class="kop">
        @if($logoPath)
            <div class="kop-logo">
                <img src="{{ $logoPath }}" alt="Logo" style="height:64px;width:{{ $logoWidth ?? 64 }}px">
            </div>
        @endif
        <div class="kop-name">{{ $kop['name'] }}</div>
        <div class="kop-tagline">{{ $kop['tagline'] }}</div>
        <div class="kop-address">{{ $kop['address'] }}</div>
        <div class="kop-contact">Telp: {{ $kop['phone'] }} &nbsp;|&nbsp; Email: {{ $kop['email'] }} &nbsp;|&nbsp; Web: {{ $kop['website'] }}</div>
    </div>

    <div class="doc-title">Formulir Izin Pulang Cepat</div>
    <div class="doc-meta">No. {{ $pengajuan->nomor }}</div>

    <p>Dengan ini saya mengajukan izin untuk pulang lebih awal dari jam kerja/jam mengajar sebagaimana tertera di bawah ini, dan menyatakan bahwa keterangan yang saya sampaikan adalah benar.</p>

    <table class="kv">
        <tbody>
            <tr><td class="k">Nama Pegawai</td><td>{{ $pengajuan->pegawai?->nama_lengkap ?? '-' }}</td></tr>
            <tr><td class="k">NIP</td><td>{{ $pengajuan->pegawai?->nip ?? '-' }}</td></tr>
            <tr><td class="k">Unit</td><td>{{ $pengajuan->pegawai?->units?->pluck('nama')->implode(', ') ?: '-' }}</td></tr>
            <tr><td class="k">Tanggal</td><td>{{ $pengajuan->tanggal?->translatedFormat('d F Y') }}</td></tr>
            <tr><td class="k">Jam Diminta Pulang</td><td><strong>{{ substr((string) $pengajuan->jam_diminta, 0, 5) }}</strong></td></tr>
            <tr><td class="k">Alasan</td><td>{{ ucwords(str_replace('_', ' ', $pengajuan->alasan)) }}</td></tr>
            @if($pengajuan->alasan_detail)
                <tr><td class="k">Detail Alasan</td><td>{{ $pengajuan->alasan_detail }}</td></tr>
            @endif
        </tbody>
    </table>

    <div class="status-box">
        @if($pengajuan->status === 'pending')
            <span class="badge badge-pending">Menunggu Persetujuan</span><br>
            Pengajuan ini sedang diproses oleh atasan/HR.
        @elseif($pengajuan->status === 'disetujui')
            <span class="badge badge-disetujui">Disetujui</span><br>
            Disetujui oleh {{ $pengajuan->approver?->name ?? '-' }} pada {{ $pengajuan->approved_at?->translatedFormat('d F Y H:i') }}.
            @if($pengajuan->catatan_approval)<br>Catatan: {{ $pengajuan->catatan_approval }}@endif
        @else
            <span class="badge badge-ditolak">Ditolak</span><br>
            Ditolak pada {{ $pengajuan->rejected_at?->translatedFormat('d F Y H:i') }}.
            @if($pengajuan->alasan_penolakan)<br>Alasan penolakan: {{ $pengajuan->alasan_penolakan }}@endif
        @endif
    </div>

    <div class="ttd">
        <div class="ttd-row">
            <div class="box">
                <div>Pengaju,</div>
                <div>{{ $pengajuan->pegawai?->nama_lengkap ?? '-' }}</div>
                <div class="sp"></div>
                <div>( {{ $pengajuan->pegawai?->nip ?? '....................' }} )</div>
            </div>
            <div class="box">
                <div>Mengetahui,</div>
                <div>Atasan Langsung / Kepala Unit</div>
                <div class="sp"></div>
                <div>( {{ $pengajuan->status === 'pending' ? '....................' : ($pengajuan->approver?->name ?? '....................') }} )</div>
            </div>
        </div>
    </div>

    <div class="footer">
        <span>Dicetak pada: {{ now()->translatedFormat('d/m/Y H:i') }} &nbsp;·&nbsp; No. {{ $pengajuan->nomor }}</span>
        <span>HRIS Yayasan</span>
    </div>
</body>
</html>
