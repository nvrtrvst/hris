<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Jadwal Pelajaran - {{ $unitName }}</title>
<style>
    @page { size: A4 landscape; margin: 8mm 8mm; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7px; color: #1f2937; }
    .page-break { page-break-before: always; }

    /* Kop */
    .kop { text-align: center; margin-bottom: 4px; }
    .kop img { height: 40px; }
    .kop .nama { font-size: 13px; font-weight: 800; color: #0f3d3e; line-height: 1.2; }
    .kop .tagline { font-size: 8px; font-style: italic; color: #0f3d3e; margin-top: 1px; }
    .kop .info { font-size: 7.5px; color: #6b7280; margin-top: 2px; }
    .garis { border-top: 2px solid #0f3d3e; margin: 3px 0 6px; }

    .day-title { text-align: center; font-size: 11px; font-weight: 800; margin: 2px 0; color: #0f3d3e; }
    .day-sub { text-align: center; font-size: 7.5px; color: #6b7280; margin-bottom: 5px; }

    /* Papan jadwal: kolom = kelas, baris = JP */
    table.schedule { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 6.5px; }
    table.schedule th { background: #0f3d3e; color: #fff; padding: 3px 2px; text-align: center; font-weight: 700; border: 1px solid #0f3d3e; }
    table.schedule th.group { font-size: 6.5px; }
    table.schedule th.kelas-col { font-size: 6px; line-height: 1.15; }
    table.schedule td { border: 1px solid #d1d5db; padding: 2px; vertical-align: top; text-align: center; }
    .jp-col { width: 3%; }
    .time-col { width: 8%; }
    .jp-num { font-weight: 700; color: #0f3d3e; background: #f9fafb; }
    .jp-time { color: #6b7280; font-size: 5.5px; background: #f9fafb; }
    .mapel { font-weight: 700; color: #0f3d3e; font-size: 6px; line-height: 1.15; }
    .guru { color: #374151; font-size: 5.5px; line-height: 1.1; }
    tr.istirahat td { background: #f3f4f6; color: #6b7280; font-weight: 700; font-size: 6px; text-align: center; }

    /* Tabel sederhana (piket & daftar guru) */
    table.simple { width: 100%; border-collapse: collapse; font-size: 7px; }
    table.simple th { background: #0f3d3e; color: #fff; padding: 4px 3px; text-align: left; font-weight: 700; }
    table.simple td { border: 1px solid #d1d5db; padding: 3px 4px; vertical-align: top; }
    table.simple tbody tr:nth-child(even) { background: #f9fafb; }

    .section-title { font-size: 11px; font-weight: 800; margin: 8px 0 4px; color: #0f3d3e; }
    .footer { margin-top: 8px; font-size: 6.5px; color: #9ca3af; text-align: right; border-top: 1px solid #e5e7eb; padding-top: 3px; }
    .empty-msg { text-align: center; padding: 12px; color: #9ca3af; font-size: 8px; }

    /* Tanda tangan kepala sekolah */
    .ttd { width: 100%; margin-top: 16px; page-break-inside: avoid; }
    .ttd .ttd-slot { width: 40%; }
    .ttd .ttd-kolom { text-align: center; font-size: 8px; line-height: 1.5; }
    .ttd .ttd-nama { font-weight: 700; text-decoration: underline; margin-top: 40px; }
    .ttd .ttd-nip { font-size: 7px; color: #374151; }
</style>
</head>
<body>

@php $dayCount = count($DAYS); @endphp

{{-- SATU HALAMAN PER HARI --}}
@foreach($DAYS as $day)
    @if(! $loop->first)
        <div class="page-break"></div>
    @endif

    @if($loop->first)
        <div class="kop">
            @if($logoPath)
                <img src="{{ $logoPath }}" />
            @endif
            <div class="nama">{{ config('yayasan.name') }}</div>
            <div class="tagline">{{ config('yayasan.tagline') }}</div>
            <div class="info">{{ config('yayasan.address') }} | Telp: {{ config('yayasan.phone') }} | Email: {{ config('yayasan.email') }}</div>
        </div>
        <div class="garis"></div>
    @endif

    <div class="day-title">JADWAL PELAJARAN &mdash; {{ strtoupper($day) }}</div>
    <div class="day-sub">Unit: {{ $unitName }} &mdash; T.A. {{ $tahunAjaran }} Semester {{ $semester }}</div>

    <table class="schedule">
        <thead>
            <tr>
                <th rowspan="2" class="jp-col">JP</th>
                <th rowspan="2" class="time-col">WAKTU</th>
                @foreach($kelasGroups as $program => $kelasGroup)
                    <th colspan="{{ count($kelasGroup) }}" class="group">{{ $program }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach($kelasList as $kelas)
                    <th class="kelas-col">{{ $kelas }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rowsTimeline as $row)
                @if($row['type'] === 'istirahat')
                    <tr class="istirahat">
                        <td colspan="{{ count($kelasList) + 2 }}">ISTIRAHAT &mdash; {{ $row['mulai'] }}-{{ $row['selesai'] }}</td>
                    </tr>
                @else
                    <tr>
                        <td class="jp-num">{{ $row['jp'] }}</td>
                        <td class="jp-time">{{ $row['mulai'] }}-{{ $row['selesai'] }}</td>
                        @foreach($kelasList as $kelas)
                            <td>
                                @foreach(($matrix[$day][$kelas][$row['mulai']] ?? []) as $cell)
                                    <div class="mapel">{{ $cell['mapel'] }}</div>
                                    <div class="guru">{{ $cell['guru'] }}</div>
                                @endforeach
                            </td>
                        @endforeach
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="{{ count($kelasList) + 2 }}" class="empty-msg">Tidak ada jadwal pada hari {{ $day }}.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Halaman {{ $loop->iteration }}/{{ $dayCount }} &mdash; {{ config('yayasan.name') }} &mdash; Dicetak: {{ now()->format('d/m/Y H:i') }}</div>
@endforeach

{{-- JADWAL PIKET --}}
<div class="page-break"></div>
<div class="section-title">JADWAL PIKET</div>
<div class="day-sub">Unit: {{ $unitName }} &mdash; T.A. {{ $tahunAjaran }} Semester {{ $semester }}</div>

@if(empty($piketPerHari))
    <div class="empty-msg">Belum ada jadwal piket di unit ini.</div>
@else
    <table class="simple">
        <thead>
            <tr>
                <th style="width:12%;">Hari</th>
                <th style="width:36%;">Nama</th>
                <th style="width:36%;">Tugas/Area</th>
                <th style="width:16%;">Jam</th>
            </tr>
        </thead>
        <tbody>
            @php $piketDays = array_unique(array_merge($DAYS, array_keys($piketPerHari))); @endphp
            @foreach($piketDays as $day)
                @php $rows = $piketPerHari[$day] ?? []; @endphp
                @foreach($rows as $idx => $row)
                    <tr>
                        @if($idx === 0)
                            <td rowspan="{{ count($rows) }}" style="font-weight:700;">{{ $day }}</td>
                        @endif
                        <td>{{ $row['nama'] }}</td>
                        <td>{{ $row['tugas'] }}</td>
                        <td>{{ $row['jam'] }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <div class="footer">{{ config('yayasan.name') }} &mdash; Dicetak: {{ now()->format('d/m/Y H:i') }}</div>
@endif

{{-- DAFTAR GURU & MAPEL --}}
<div class="page-break"></div>
<div class="section-title">DAFTAR GURU &amp; MAPEL YANG DIAJARKAN</div>
<div class="day-sub">Unit: {{ $unitName }} &mdash; T.A. {{ $tahunAjaran }} Semester {{ $semester }}</div>

@if(empty($daftarGuru))
    <div class="empty-msg">Belum ada data guru dan mapel di unit ini.</div>
@else
    @foreach(array_chunk($daftarGuru, 25) as $chunkIdx => $chunk)
        @if($chunkIdx > 0)
            <div class="page-break"></div>
        @endif

        <table class="simple">
            <thead>
                <tr>
                    <th style="width:5%;">No</th>
                    <th style="width:40%;">Nama Guru</th>
                    <th style="width:55%;">Mapel yang Diajarkan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($chunk as $idx => $guru)
                    <tr>
                        <td>{{ $chunkIdx * 25 + $idx + 1 }}</td>
                        <td style="font-weight:700;">{{ $guru['nama'] }}</td>
                        <td>{{ $guru['mapel'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer">{{ config('yayasan.name') }} &mdash; Dicetak: {{ now()->format('d/m/Y H:i') }}</div>
    @endforeach
@endif

{{-- Tanda tangan kepala sekolah unit --}}
@if($kepalaSekolah ?? null)
    <table class="ttd">
        <tr>
            <td class="ttd-slot"></td>
            <td class="ttd-slot">
                <div class="ttd-kolom">
                    <div>{{ $unitName }}, {{ now()->format('d/m/Y') }}</div>
                    <div>Kepala Sekolah</div>
                    <div class="ttd-nama">{{ $kepalaSekolah->nama_lengkap }}</div>
                    @if($kepalaSekolah->nuptk)
                        <div class="ttd-nip">NIP. {{ $kepalaSekolah->nuptk }}</div>
                    @endif
                </div>
            </td>
            <td></td>
        </tr>
    </table>
@endif

</body>
</html>
