<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
    @page { margin: 12mm 10mm; }
    * { box-sizing: border-box; }
    body { font-family: 'Helvetica', 'Arial', sans-serif; color: #1f2937; font-size: 9px; margin: 0; }
    .kop { text-align: center; margin-bottom: 8px; }
    .kop-logo { margin-bottom: 4px; }
    .kop-logo img { height: 56px; width: auto; display: inline-block; }
    .kop-name { font-size: 15px; font-weight: 800; letter-spacing: .5px; color: #0f3d3e; text-transform: uppercase; line-height: 1.2; }
    .kop-address { font-size: 9px; color: #374151; margin-top: 3px; }
    .kop-contact { font-size: 8.5px; color: #6b7280; margin-top: 2px; }
    .doc-title { text-align: center; font-size: 13px; font-weight: 800; margin: 8px 0 2px; text-transform: uppercase; }
    .doc-meta { text-align: center; font-size: 9px; color: #4b5563; margin-bottom: 8px; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    thead th { background: #0f3d3e; color: #fff; padding: 4px 3px; font-size: 8px; text-align: left; text-transform: uppercase; }
    tbody td { border: 1px solid #d1d5db; padding: 3px; font-size: 8px; vertical-align: top; word-wrap: break-word; }
    tbody tr:nth-child(even) { background: #f3f4f6; }
    .empty { text-align: center; padding: 20px; color: #6b7280; }
    .footer { margin-top: 8px; font-size: 8px; color: #6b7280; display: flex; justify-content: space-between; }
</style>
</head>
<body>
    <div class="kop">
        @if($logoPath)
            <div class="kop-logo"><img src="{{ $logoPath }}" alt="Logo"></div>
        @endif
        <div class="kop-name">{{ $kop['name'] }}</div>
        <div class="kop-address">{{ $kop['address'] }}</div>
        <div class="kop-contact">Telp: {{ $kop['phone'] }} &nbsp;|&nbsp; Email: {{ $kop['email'] }} &nbsp;|&nbsp; Web: {{ $kop['website'] }}</div>
    </div>

    <div class="doc-title">{{ $title }}</div>
    <div class="doc-meta">Cakupan: {{ $scopeLabel }} &nbsp;|&nbsp; {{ $totalRows }} pegawai &nbsp;|&nbsp; Dicetak: {{ $generatedAt }}</div>

    <table>
        <thead>
            <tr>
                @foreach($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($row as $cell)
                        <td>{!! nl2br(e((string) $cell)) !!}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headings) }}" class="empty">Tidak ada data untuk cakupan yang dipilih.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <span>Dicetak pada: {{ $generatedAt }}</span>
        <span>HRIS Yayasan</span>
    </div>
</body>
</html>
