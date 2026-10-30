<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Presensi Guru - {{ $pegawai['nama'] }}</title>
<style>
    @page { size: A4 landscape; margin: 8mm; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #111827; font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; }
    .kop { text-align: center; border-bottom: 2px solid #374151; padding-bottom: 5px; }
    .kop-brand { display: inline-flex; align-items: center; justify-content: center; gap: 10px; text-align: left; }
    .kop-logo { width: 52px; height: 52px; object-fit: contain; }
    .kop-name { font-size: 13px; font-weight: 800; line-height: 1.2; }
    .kop-unit { font-size: 11px; font-weight: 800; line-height: 1.2; }
    .kop-contact { margin-top: 3px; color: #6b7280; font-size: 7.5px; }
    .title { margin: 8px 0 1px; text-align: center; font-size: 13px; font-weight: 800; text-transform: uppercase; }
    .meta { text-align: center; font-size: 9px; font-weight: 700; }
    .person { margin: 8px 0 5px; font-size: 9px; font-weight: 700; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th, td { border: 1px solid #374151; padding: 3px 2px; text-align: center; vertical-align: middle; }
    th { background: #e5e7eb; font-size: 7px; font-weight: 800; line-height: 1.15; }
    td { height: 22px; font-size: 7.5px; }
    .no { width: 22px; }
    .date { width: 55px; }
    .day { width: 52px; }
    .time { width: 48px; }
    .jp { width: 20px; }
    .total { width: 40px; }
    .status { width: 24px; }
    .percent { width: 34px; }
    .mark { font-size: 11px; font-weight: 800; }
    .muted { color: #6b7280; }
    .summary-row td { background: #f3f4f6; font-weight: 800; }
    .b-col { border: 0; border-right: 1px solid #374151; border-bottom: 1px solid #374151; font-weight: 800; vertical-align: middle; }
    .b-head { border: 0; border-top: 1px solid #374151; border-right: 1px solid #374151; }
    .signature { display: flex; justify-content: space-between; margin-top: 18px; padding: 0 30px; font-size: 8px; }
    .signature .box { width: 21%; text-align: center; }
    .signature .role { font-weight: 800; text-transform: uppercase; }
    .signature .space { height: 30px; }
    .signature .name { border-top: 1px solid #374151; padding-top: 2px; margin-top: 30px; }
    .note { margin-top: 6px; color: #6b7280; font-size: 7px; }
</style>
</head>
<body>
    <div class="kop">
        <div class="kop-brand">
            @if(!empty($unit['logo_path']))
                <img class="kop-logo" src="{{ $unit['logo_path'] }}" alt="Logo">
            @endif
            <div>
                <div class="kop-name">YAYASAN NUURUL MUTTAQIN</div>
                <div class="kop-unit">{{ strtoupper($unit['nama']) }}</div>
            </div>
        </div>
        <div class="kop-contact">
            @if($unit['alamat']){{ $unit['alamat'] }}@endif
            @if($unit['telepon']) &nbsp;|&nbsp; Telp: {{ $unit['telepon'] }}@endif
            @if($unit['web']) &nbsp;|&nbsp; Web: {{ $unit['web'] }}@endif
        </div>
    </div>

    <div class="title">Presensi Guru</div>
    <div class="meta">
        Tahun Pelajaran {{ $tahun_ajaran }} &nbsp;|&nbsp;
        Semester {{ $semester }} &nbsp;|&nbsp;
        Periode {{ $periode_label }}
    </div>

    <div class="person">NAMA : {{ $pegawai['nama'] }}</div>

    <table>
        <thead>
            <tr>
                <th class="no" rowspan="2">NO</th>
                <th class="date" rowspan="2">TANGGAL</th>
                <th class="day" rowspan="2">HARI</th>
                <th class="time" rowspan="2">JAM DATANG</th>
                <th colspan="{{ $slot_count }}">MENGAJAR JAM KE (AKTUAL)</th>
                <th class="total" rowspan="2">JUMLAH JAM PERHARI</th>
                <th colspan="4">KETERANGAN</th>
                <th class="time" rowspan="2">JAM PULANG</th>
                <th colspan="3">PERSENTASE</th>
            </tr>
            <tr>
                @for($jp = 1; $jp <= $slot_count; $jp++)
                    <th class="jp">{{ $jp }}</th>
                @endfor
                <th class="status">S</th>
                <th class="status">I</th>
                <th class="status">TK</th>
                <th class="status">TUGAS</th>
                <th class="percent">PH</th>
                <th class="percent">M</th>
                <th class="percent b-head">B</th>
            </tr>
        </thead>
        <tbody>
            @php
                $no = 1;
                $firstRow = true;
                $monthlyRows = collect($weeks)->sum(fn ($week) => count($week['rows'])) + count($weeks);
            @endphp

            @forelse($weeks as $week)
                @foreach($week['rows'] as $row)
                    <tr>
                        <td>{{ $no++ }}</td>
                        <td>{{ $row['tanggal'] }}</td>
                        <td>{{ $row['hari'] }}</td>
                        <td>{{ $row['jam_datang'] ?? '-' }}</td>
                        @for($jp = 1; $jp <= $slot_count; $jp++)
                            <td class="mark">{{ isset($row['slots'][$jp]) ? '✓' : '' }}</td>
                        @endfor
                        <td>{{ $row['jumlah_jp'] }}</td>
                        <td>{{ $row['s'] ? '✓' : '' }}</td>
                        <td>{{ $row['i'] ? '✓' : '' }}</td>
                        <td>{{ $row['tk'] ? '✓' : '' }}</td>
                        <td>{{ $row['tugas'] ? 'TUGAS' : '' }}</td>
                        <td>{{ $row['jam_pulang'] ?? '-' }}</td>
                        <td>{{ $row['ph'] ?? '-' }}</td>
                        <td></td>
                        @if($firstRow)
                            <td class="b-col" rowspan="{{ $monthlyRows }}">{{ $summary['b'] ?? '-' }}</td>
                            @php $firstRow = false; @endphp
                        @endif
                    </tr>
                @endforeach

                <tr class="summary-row">
                    <td colspan="4">KEHADIRAN / MINGGU</td>
                    <td colspan="{{ $slot_count }}">{{ $week['actual_jp'] }} / {{ $week['scheduled_jp'] }} JP</td>
                    <td>{{ $week['actual_jp'] }}</td>
                    <td colspan="4"></td>
                    <td></td>
                    <td></td>
                    <td>{{ $week['m'] !== null ? $week['m'].'%' : '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $slot_count + 13 }}" class="muted">Tidak ada hari kerja pada periode ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="note">
        PH = JP aktual / JP terjadwal per hari. M = agregat JP aktual / JP terjadwal per minggu.
        B = agregat JP aktual / JP terjadwal per bulan. TK = tidak ada keterangan.
    </div>

    <div class="signature">
        <div class="box">
            <div class="role">KEPALA SEKOLAH</div>
            <div class="space"></div>
            <div class="name">{{ $officials['kepala'] ?? '' }}</div>
        </div>
        <div class="box">
            <div class="role">KEPALA TU</div>
            <div class="space"></div>
            <div class="name">{{ $officials['kepala_tu'] ?? '' }}</div>
        </div>
        <div class="box">
            <div class="role">KEPEGAWAIAN</div>
            <div class="space"></div>
            <div class="name">{{ $officials['kepegawaian'] ?? '' }}</div>
        </div>
        <div class="box">
            <div class="role">GURU YANG BERSANGKUTAN</div>
            <div class="space"></div>
            <div class="name">{{ $officials['guru'] }}</div>
        </div>
    </div>
</body>
</html>
