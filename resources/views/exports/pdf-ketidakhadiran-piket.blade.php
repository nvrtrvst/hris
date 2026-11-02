<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Ketidakhadiran Piket</title>
<style>
    @page { margin: 12mm 10mm; }
    * { box-sizing: border-box; }
    body { font-family: 'Helvetica', 'Arial', sans-serif; color: #1f2937; font-size: 9px; margin: 0; }
    .kop { text-align: center; margin-bottom: 6px; }
    .kop img { height: 48px; width: auto; }
    .kop .nama { font-size: 15px; font-weight: 800; color: #0f3d3e; line-height: 1.2; }
    .kop .tagline { font-size: 8.5px; font-style: italic; color: #0f3d3e; margin-top: 1px; }
    .kop .info { font-size: 8px; color: #6b7280; margin-top: 3px; }
    .garis { border-top: 2px solid #0f3d3e; margin: 4px 0 8px; }
    .title { text-align: center; font-size: 12px; font-weight: 800; margin: 2px 0; }
    .meta { text-align: center; font-size: 8.5px; color: #374151; margin-bottom: 8px; }
    .summary { margin: 0 0 8px; padding: 5px 8px; border: 1px solid #d1d5db; background: #f9fafb; font-size: 8.5px; color: #374151; }
    .summary strong { color: #0f3d3e; }
    table { width: 100%; border-collapse: collapse; }
    thead th { background: #0f3d3e; color: #fff; padding: 4px 4px; text-align: left; font-size: 8.5px; }
    tbody td { border: 1px solid #d1d5db; padding: 4px; vertical-align: top; font-size: 8.5px; }
    .empty { text-align: center; padding: 16px; color: #6b7280; font-size: 9px; }
    .footer { margin-top: 10px; font-size: 8px; color: #6b7280; text-align: right; }
    .ttd { width: 100%; margin-top: 18px; page-break-inside: avoid; }
    .ttd .slot { width: 40%; }
    .ttd .kolom { text-align: center; font-size: 9px; line-height: 1.5; }
    .ttd .nama { font-weight: 700; text-decoration: underline; margin-top: 40px; }
    .ttd .nip { font-size: 8px; color: #374151; }
</style>
</head>
<body>

<div class="kop">
    @if($logoPath)
        <img src="{{ $logoPath }}" style="height:48px;width:{{ $logoWidth ?? 48 }}px" alt="Logo">
    @endif
    <div class="nama">{{ $kop['name'] }}</div>
    <div class="tagline">{{ $kop['tagline'] }}</div>
    <div class="info">{{ $kop['address'] }} | Telp: {{ $kop['phone'] }} | Email: {{ $kop['email'] }}</div>
</div>
<div class="garis"></div>

<div class="title">LAPORAN KETIDAKHADIRAN PIKET</div>
<div class="meta">
    Unit: {{ $meta['unit'] }} &nbsp;|&nbsp; Periode: {{ \Carbon\Carbon::parse($meta['start_date'])->format('d/m/Y') }} &ndash; {{ \Carbon\Carbon::parse($meta['end_date'])->format('d/m/Y') }}
</div>

<div class="summary">
    Alpa piket: <strong>{{ $meta['alpa'] }}</strong> &nbsp;|&nbsp;
    Terlambat lapor: <strong>{{ $meta['terlambat'] }}</strong>
    <span style="color:#6b7280;">&nbsp;|&nbsp; Alpa = presensi hadir/telat tanpa laporan terkirim setelah jam piket selesai. Terlambat lapor = laporan terkirim setelah jam piket selesai (informasi).</span>
</div>

@if(empty($rows))
    <div class="empty">Tidak ada catatan ketidakhadiran piket pada periode dan unit ini.</div>
@else
    <table>
        <thead>
            <tr>
                <th style="width:9%;">Tanggal</th>
                <th style="width:22%;">Pegawai</th>
                <th style="width:15%;">Unit</th>
                <th style="width:11%;">Jam Piket</th>
                <th style="width:12%;">Area</th>
                <th style="width:12%;">Presensi</th>
                <th style="width:19%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($row['tanggal'])->format('d/m/Y') }}</td>
                    <td>{{ $row['pegawai']['nama_lengkap'] ?? '-' }}</td>
                    <td>{{ $row['unit'] ?? '-' }}</td>
                    <td>{{ $row['jam'] }}</td>
                    <td>{{ $row['area'] ?? '-' }}</td>
                    <td>{{ ucfirst($row['presensi_status']) }} {{ $row['presensi_jam'] }}</td>
                    <td>
                        @if($row['kategori'] === 'terlambat_lapor')
                            Terlambat lapor &mdash; kirim {{ $row['submitted_at'] ?? '-' }}
                        @else
                            Alpa piket &mdash; {{ $row['status_laporan'] === 'draft' ? 'laporan belum dikirim' : 'tidak ada laporan' }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="footer">Dicetak: {{ now()->format('d/m/Y H:i') }}</div>

@if($kepalaSekolah)
    <table class="ttd">
        <tr>
            <td class="slot"></td>
            <td class="slot">
                <div class="kolom">
                    <div>{{ $meta['unit'] }}, {{ now()->format('d/m/Y') }}</div>
                    <div>Kepala Sekolah</div>
                    <div class="nama">{{ $kepalaSekolah->nama_lengkap }}</div>
                    @if($kepalaSekolah->nuptk)
                        <div class="nip">NIP. {{ $kepalaSekolah->nuptk }}</div>
                    @endif
                </div>
            </td>
            <td class="slot"></td>
        </tr>
    </table>
@endif

</body>
</html>
