<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Kehadiran dan Pemenuhan Tugas</title>
<style>
    @page { margin: 10mm; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #0f172a; font-family: Arial, Helvetica, sans-serif; font-size: 9px; }
    .kop { text-align: center; border-bottom: 2px solid #0f3d3e; padding-bottom: 6px; margin-bottom: 8px; }
    .kop-logo { margin-bottom: 2px; }
    .kop-logo img { height: 64px; width: auto; }
    .kop-name { font-size: 15px; font-weight: 800; letter-spacing: .5px; color: #0f3d3e; text-transform: uppercase; line-height: 1.2; }
    .kop-tagline { font-size: 8.5px; font-style: italic; color: #0f3d3e; margin-top: 1px; }
    .kop-address { font-size: 8.5px; color: #374151; margin-top: 2px; }
    .kop-contact { font-size: 8px; color: #64748b; margin-top: 1px; }
    .muted { color: #64748b; }
    .title { margin: 8px 0 2px; text-align: center; font-size: 14px; font-weight: 800; text-transform: uppercase; }
    .subtitle { margin-bottom: 8px; text-align: center; color: #64748b; font-size: 9px; }
    h2 { margin: 12px 0 4px; font-size: 10px; text-transform: uppercase; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th, td { border: 1px solid #d7dee8; padding: 3px 4px; vertical-align: top; }
    th { background: #eef2f6; color: #334155; font-size: 8px; font-weight: 800; text-transform: uppercase; }
    td { font-size: 8.5px; }
    .center { text-align: center; }
    .summary td { width: 16.66%; }
    .summary .label { color: #64748b; font-size: 7.5px; text-transform: uppercase; }
    .summary .value { font-size: 14px; font-weight: 800; margin-top: 2px; }
    .danger { color: #be123c; }
    .success { color: #047857; }
    .warning { color: #b45309; }
    .badge { display: inline-block; padding: 1px 4px; border-radius: 8px; font-size: 7px; font-weight: 800; }
    .badge-danger { color: #9f1239; background: #ffe4e6; }
    .badge-warning { color: #92400e; background: #fef3c7; }
    .badge-success { color: #065f46; background: #d1fae5; }
    .badge-info { color: #075985; background: #e0f2fe; }
    .finding { line-height: 1.35; }
    .finding + .finding { margin-top: 3px; }
    .finding-label { font-size: 8.5px; font-weight: 800; }
    .finding-count { color: #64748b; font-weight: 400; }
    .chips { margin-top: 1px; }
    .chip { display: inline-block; border: 1px solid #d7dee8; border-radius: 3px; padding: 0 3px; margin: 1px 2px 0 0; font-size: 7.5px; color: #334155; white-space: nowrap; }
    .footer { margin-top: 10px; color: #64748b; font-size: 7.5px; }
    .page-break { page-break-before: always; }
    tr { page-break-inside: avoid; }
    thead { display: table-header-group; }
</style>
</head>
<body>
@php
    $statusLabels = [
        'belum_terpantau' => 'Belum terpantau',
        'kelas_kosong' => 'Kelas kosong',
        'perlu_cek' => 'Perlu cek',
        'digantikan' => 'Digantikan',
    ];
    $statusClass = [
        'belum_terpantau' => 'badge-danger',
        'kelas_kosong' => 'badge-warning',
        'perlu_cek' => 'badge-info',
        'digantikan' => 'badge-info',
    ];
    $fmtJp = function ($value) {
        $value = round((float) $value, 2);

        return $value == floor($value) ? (string) (int) $value : number_format($value, 2, ',', '.');
    };
    $itemText = function (array $finding, array $item) use ($fmtJp) {
        $date = \Carbon\Carbon::parse($item['tanggal'])->format('d/m/Y');

        return match ($finding['key']) {
            'jp_kurang' => $date.' ('.$fmtJp($item['actual_jp']).'/'.$fmtJp($item['scheduled_jp']).' JP)',
            'hadir_tanpa_jp' => $date.' (0/'.$fmtJp($item['scheduled_jp']).' JP)',
            'izin_mengajar' => $date.' ('.$item['izin'].', '.$item['realisasi'].')',
            default => $date,
        };
    };
@endphp

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

<div class="title">Laporan Kehadiran dan Pemenuhan Tugas</div>
<div class="subtitle">
    {{ $period['label'] }} &nbsp;|&nbsp; {{ $period['tahun_ajaran'] }} · Semester {{ $period['semester'] }} &nbsp;|&nbsp; Dicetak {{ \Carbon\Carbon::parse($generated_at)->format('d/m/Y H:i') }}
</div>

@include('exports.partials.pemenuhan')

<h2>Kelas &amp; Mengajar</h2>
<table>
    <thead>
        <tr>
            <th style="width:12%">Tanggal</th>
            <th style="width:12%">Jam</th>
            <th style="width:16%">Kelas</th>
            <th style="width:14%">Mapel</th>
            <th style="width:16%">Guru</th>
            <th style="width:16%">Status</th>
            <th style="width:14%">Sumber</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($class_issues as $issue)
            <tr>
                <td>{{ \Carbon\Carbon::parse($issue['tanggal'])->format('d/m/Y') }}</td>
                <td>{{ $issue['jam'] }}</td>
                <td>{{ $issue['kelas'] ?? '-' }}</td>
                <td>{{ $issue['mapel'] ?? '-' }}</td>
                <td>{{ $issue['guru'] ?? '-' }}</td>
                <td><span class="badge {{ $statusClass[$issue['status']] ?? 'badge-info' }}">{{ $statusLabels[$issue['status']] ?? $issue['status'] }}</span></td>
                <td>{{ $issue['sumber'] }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="center">Tidak ada kelas bermasalah pada filter ini.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="page-break"></div>

<div class="footer">
    Sumber: Jadwal mengajar, Presensi per JP, Pengajuan Izin disetujui, dan Laporan Piket submitted. Belum tercatat dihitung dari hari kerja tanpa presensi dan tanpa izin. JP = jam pelajaran; durasi mengikuti durasi JP unit.
</div>
</body>
</html>
