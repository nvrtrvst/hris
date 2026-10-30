<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Pengawasan Kehadiran &amp; Mengajar</title>
<style>
    * { box-sizing: border-box; }
    body { margin: 0; color: #0f172a; font-family: Arial, Helvetica, sans-serif; font-size: 9px; }
    .header { border-bottom: 2px solid #0f172a; padding-bottom: 6px; margin-bottom: 10px; }
    .header-table { width: 100%; border-collapse: collapse; }
    .header-table td { border: 0; padding: 0; vertical-align: middle; }
    .logo { width: 42px; height: 42px; border: 2px solid #0f3d3e; border-radius: 50%; color: #0f3d3e; text-align: center; line-height: 38px; font-weight: 800; font-size: 9px; }
    .org { font-size: 14px; font-weight: 800; }
    .unit { font-size: 12px; font-weight: 800; }
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
    .footer { margin-top: 10px; color: #64748b; font-size: 7.5px; }
    .page-break { page-break-before: always; }
    tr { page-break-inside: avoid; }
    thead { display: table-header-group; }
</style>
</head>
<body>
@php
    $unitLabel = $unit['nama'] ?? 'Semua Unit';
    $unitShort = $unit['singkatan'] ?? 'Semua Unit';
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
@endphp

<div class="header">
    <table class="header-table">
        <tr>
            <td style="width:54px"><div class="logo">NM</div></td>
            <td>
                <div class="org">YAYASAN NUURUL MUTTAQIN</div>
                <div class="unit">{{ strtoupper($unitLabel) }}</div>
                <div class="muted">Laporan konsolidasi kehadiran dan mengajar</div>
            </td>
        </tr>
    </table>
</div>

<div class="title">Laporan Pengawasan Kehadiran &amp; Mengajar</div>
<div class="subtitle">
    {{ $period['label'] }} &nbsp;|&nbsp; {{ $period['tahun_ajaran'] }} · Semester {{ $period['semester'] }} &nbsp;|&nbsp; Dicetak {{ \Carbon\Carbon::parse($generated_at)->format('d/m/Y H:i') }}
</div>

<table class="summary">
    <tr>
        <td><div class="label">Pegawai</div><div class="value">{{ number_format($summary['employees'], 0, ',', '.') }}</div></td>
        <td><div class="label">Hari Pegawai</div><div class="value">{{ number_format($summary['employee_days'], 0, ',', '.') }}</div></td>
        <td><div class="label">Hadir</div><div class="value success">{{ number_format($summary['hadir'], 0, ',', '.') }}</div></td>
        <td><div class="label">Telat</div><div class="value warning">{{ number_format($summary['telat'], 0, ',', '.') }}</div></td>
        <td><div class="label">Izin / Sakit / Cuti</div><div class="value">{{ number_format($summary['izin_sakit_cuti'], 0, ',', '.') }}</div></td>
        <td><div class="label">Alpa</div><div class="value danger">{{ number_format($summary['alpa'], 0, ',', '.') }}</div></td>
    </tr>
</table>

<table style="margin-top:4px">
    <tr>
        <td><strong>Hadir tanpa JP:</strong> {{ $summary['hadir_tanpa_jp'] }}</td>
        <td><strong>JP kurang:</strong> {{ $summary['jp_kurang'] }}</td>
        <td><strong>Izin tetapi mengajar:</strong> {{ $summary['izin_mengajar'] }}</td>
        <td><strong>Kelas belum terpantau:</strong> {{ $summary['kelas_belum'] }}</td>
        <td><strong>JP:</strong> {{ $summary['actual_jp'] }} / {{ $summary['scheduled_jp'] }} ({{ $summary['jp_percent'] ?? '-' }}%)</td>
    </tr>
</table>

<h2>Matriks Pegawai</h2>
<table>
    <thead>
        <tr>
            <th style="width:22%">Pegawai / Unit</th>
            <th style="width:8%" class="center">Hadir</th>
            <th style="width:7%" class="center">Telat</th>
            <th style="width:8%" class="center">Izin</th>
            <th style="width:7%" class="center">Sakit</th>
            <th style="width:7%" class="center">Cuti</th>
            <th style="width:7%" class="center">Alpa</th>
            <th style="width:9%" class="center">JP</th>
            <th style="width:25%">Temuan</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($matrix as $row)
            <tr>
                <td><strong>{{ $row['pegawai'] }}</strong><br><span class="muted">{{ $row['unit'] }}</span></td>
                <td class="center">{{ $row['hadir'] }}</td>
                <td class="center">{{ $row['telat'] }}</td>
                <td class="center">{{ $row['izin'] }}</td>
                <td class="center">{{ $row['sakit'] }}</td>
                <td class="center">{{ $row['cuti'] }}</td>
                <td class="center">{{ $row['alpa'] }}</td>
                <td class="center">{{ $row['is_guru'] ? $row['actual_jp'].' / '.$row['scheduled_jp'] : '-' }}</td>
                <td>{{ $row['temuan'] }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="center">Tidak ada pegawai pada filter ini.</td></tr>
        @endforelse
    </tbody>
</table>

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

<h2>Detail Temuan</h2>
<table>
    <thead>
        <tr>
            <th style="width:16%">Jenis</th>
            <th style="width:20%">Pegawai</th>
            <th style="width:14%">Unit</th>
            <th style="width:12%">Tanggal</th>
            <th style="width:38%">Keterangan</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($details['alpa'] as $row)
            <tr>
                <td>Alpa</td>
                <td>{{ $row['pegawai'] }}</td>
                <td>{{ $row['unit'] }}</td>
                <td>{{ \Carbon\Carbon::parse($row['tanggal'])->format('d/m/Y') }}</td>
                <td>{{ $row['sumber'] }}</td>
            </tr>
        @empty
        @endforelse
        @foreach ($details['hadir_tanpa_jp'] as $row)
            <tr>
                <td>Hadir tanpa JP</td>
                <td>{{ $row['pegawai'] }}</td>
                <td>{{ $row['unit'] }}</td>
                <td>{{ \Carbon\Carbon::parse($row['tanggal'])->format('d/m/Y') }}</td>
                <td>JP terjadwal {{ $row['scheduled_jp'] }}, JP aktual {{ $row['actual_jp'] }}</td>
            </tr>
        @endforeach
        @foreach ($details['jp_kurang'] as $row)
            <tr>
                <td>JP kurang</td>
                <td>{{ $row['pegawai'] }}</td>
                <td>{{ $row['unit'] }}</td>
                <td>{{ \Carbon\Carbon::parse($row['tanggal'])->format('d/m/Y') }}</td>
                <td>JP terjadwal {{ $row['scheduled_jp'] }}, JP aktual {{ $row['actual_jp'] }}, kurang {{ $row['kurang'] }}</td>
            </tr>
        @endforeach
        @foreach ($details['izin_mengajar'] as $row)
            <tr>
                <td>Izin tetapi mengajar</td>
                <td>{{ $row['pegawai'] }}</td>
                <td>{{ $row['unit'] }}</td>
                <td>{{ \Carbon\Carbon::parse($row['tanggal'])->format('d/m/Y') }}</td>
                <td>Izin {{ $row['izin'] }}, realisasi {{ $row['realisasi'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="footer">
    Sumber: Jadwal mengajar, Presensi per JP, Pengajuan Izin disetujui, dan Laporan Piket submitted. Alpa dihitung dari hari kerja tanpa presensi dan tanpa izin.
</div>
</body>
</html>
