<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Daftar Pegawai Belum Presensi</title>
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
    table { width: 100%; border-collapse: collapse; }
    thead th { background: #0f3d3e; color: #fff; font-size: 9.5px; text-transform: uppercase; padding: 6px 5px; text-align: left; }
    tbody td { border: 1px solid #d1d5db; padding: 4px 5px; font-size: 9.5px; vertical-align: top; }
    tbody tr:nth-child(even) { background: #f3f4f6; }
    .c-no { width: 5%; text-align: center; }
    .ttd { margin-top: 18px; text-align: right; font-size: 10px; }
    .ttd .box { display: inline-block; width: 230px; text-align: center; }
    .ttd .sp { height: 34px; }
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

    <div class="doc-title">Daftar Pegawai Belum Presensi</div>
    <div class="doc-meta">Tanggal: {{ $periodeStr }} &nbsp;|&nbsp; Unit: {{ $unitName }}</div>

    <table>
        <thead>
            <tr>
                <th class="c-no">No</th>
                <th>NIP</th>
                <th>Nama Pegawai</th>
                <th>Unit</th>
                <th>Jabatan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td class="c-no">{{ $row['no'] }}</td>
                    <td>{{ $row['nip'] }}</td>
                    <td>{{ $row['nama'] }}</td>
                    <td>{{ $row['unit'] }}</td>
                    <td>{{ $row['jabatan'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:20px;">Semua pegawai sudah melakukan presensi.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="ttd">
        <div class="box">
            <div>Mengetahui,</div>
            <div>Atasan Langsung</div>
            <div class="sp"></div>
            <div>( &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; )</div>
        </div>
    </div>

    <div class="footer">
        <span>Dicetak pada: {{ now()->translatedFormat('d/m/Y H:i') }}</span>
        <span>Laporan HRIS Yayasan</span>
    </div>
</body>
</html>
