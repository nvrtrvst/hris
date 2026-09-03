<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Jadwal Pelajaran - {{ $unitName }}</title>
<style>
    @page { size: A4 landscape; margin: 8mm 10mm; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7px; color: #1f2937; }
    .page-break { page-break-before: always; }

    /* Kop */
    .kop { text-align: center; margin-bottom: 4px; }
    .kop img { height: 40px; }
    .kop .nama { font-size: 13px; font-weight: 800; text-transform: uppercase; color: #0f3d3e; line-height: 1.2; }
    .kop .tagline { font-size: 8px; font-style: italic; color: #0f3d3e; margin-top: 1px; }
    .kop .info { font-size: 7.5px; color: #6b7280; margin-top: 2px; }
    .garis { border-top: 2px solid #0f3d3e; margin: 3px 0 6px; }

    /* Day header */
    .day-title { text-align: center; font-size: 11px; font-weight: 800; text-transform: uppercase; margin: 2px 0; color: #0f3d3e; }
    .day-sub { text-align: center; font-size: 7.5px; color: #6b7280; margin-bottom: 5px; }

    /* Matrix table */
    table.matrix { width: 100%; border-collapse: collapse; font-size: 6.5px; }
    table.matrix th { background: #0f3d3e; color: #fff; padding: 3px 2px; text-align: center; font-weight: 700; border: 1px solid #0f3d3e; }
    table.matrix th.kelas-col { text-align: left; width: 13%; padding-left: 4px; }
    table.matrix td { border: 1px solid #d1d5db; padding: 2px 2px; vertical-align: top; text-align: center; min-height: 14px; }
    table.matrix td.kelas-name { text-align: left; font-weight: 700; background: #f9fafb; padding-left: 4px; font-size: 7px; }
    table.matrix td .guru-name { font-weight: 600; color: #0f3d3e; font-size: 6px; line-height: 1.2; }
    table.matrix td .mapel-name { color: #374151; font-size: 5.5px; line-height: 1.1; }
    table.matrix tbody tr:nth-child(even) { background: #f9fafb; }
    table.matrix tbody tr:nth-child(odd) { background: #fff; }

    /* Per-guru table */
    table.guru { width: 100%; border-collapse: collapse; font-size: 7px; margin-top: 6px; }
    table.guru th { background: #0f3d3e; color: #fff; padding: 4px 3px; text-align: left; font-weight: 700; font-size: 7px; }
    table.guru td { border: 1px solid #d1d5db; padding: 3px 4px; vertical-align: top; }
    table.guru tbody tr:nth-child(even) { background: #f9fafb; }
    .guru-jadwal-item { margin-bottom: 1px; }
    .guru-jadwal-item .jam { font-weight: 600; color: #0f3d3e; }
    .guru-jadwal-item .detail { font-size: 6px; color: #6b7280; }

    .section-title { font-size: 11px; font-weight: 800; margin: 8px 0 4px; text-transform: uppercase; color: #0f3d3e; }
    .footer { margin-top: 8px; font-size: 6.5px; color: #9ca3af; text-align: right; border-top: 1px solid #e5e7eb; padding-top: 3px; }
    .empty-msg { text-align: center; padding: 12px; color: #9ca3af; font-size: 8px; }
</style>
</head>
<body>

{{-- KOP --}}
<div class="kop">
    @if($logoPath)
        <img src="{{ $logoPath }}" />
    @endif
    <div class="nama">{{ config('yayasan.name') }}</div>
    <div class="tagline">{{ config('yayasan.tagline') }}</div>
    <div class="info">{{ config('yayasan.address') }} | Telp: {{ config('yayasan.phone') }} | Email: {{ config('yayasan.email') }}</div>
</div>
<div class="garis"></div>

{{-- MATRIX PER HARI --}}
@foreach($DAYS as $day)
    @if(! $loop->first)
        <div class="page-break"></div>
    @endif

    <div class="day-title">Jadwal Pelajaran &mdash; {{ $day }}</div>
    <div class="day-sub">Unit: {{ $unitName }} &mdash; T.A. {{ $tahunAjaran }} Semester {{ $semester }}</div>

    <table class="matrix">
        <thead>
            <tr>
                <th class="kelas-col">Kelas</th>
                @foreach($jamSlotLabels as $num => $time)
                    <th>{{ $num }}<br><span style="font-weight:normal;font-size:5.5px;">{{ $time }}</span></th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($kelasList as $kelas)
                <tr>
                    <td class="kelas-name">{{ $kelas }}</td>
                    @foreach($jamSlotLabels as $num => $time)
                        <td>
                            @if(isset($matrix[$kelas][$day][$num]))
                                <div class="guru-name">{{ $matrix[$kelas][$day][$num]['guru'] }}</div>
                                <div class="mapel-name">{{ $matrix[$kelas][$day][$num]['mapel'] }}</div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="empty-msg">Tidak ada data jadwal untuk hari {{ $day }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Halaman {{ $loop->iteration }}/{{ count($DAYS) }} &mdash; {{ config('yayasan.name') }} &mdash; Dicetak: {{ now()->format('d/m/Y H:i') }}</div>
@endforeach

{{-- PER-GURU --}}
<div class="page-break"></div>
<div class="section-title">Daftar Jadwal Per Guru</div>
<div class="day-sub">Unit: {{ $unitName }} &mdash; T.A. {{ $tahunAjaran }} Semester {{ $semester }}</div>

@php $guruSorted = collect($perGuru)->sortBy('nama')->values()->all(); @endphp

@if(empty($guruSorted))
    <div class="empty-msg">Tidak ada data jadwal guru.</div>
@else
    @foreach(array_chunk($guruSorted, 15) as $chunkIdx => $chunk)
        @if($chunkIdx > 0)
            <div class="page-break"></div>
        @endif

        <table class="guru">
            <thead>
                <tr>
                    <th style="width:3%;">No</th>
                    <th style="width:16%;">Nama Guru</th>
                    <th style="width:13%;">Mapel</th>
                    @foreach($DAYS as $day)
                        <th style="width:13.6%;">{{ substr($day, 0, 3) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($chunk as $guruIdx => $guru)
                    @php $jadwalsByDay = collect($guru['jadwals'])->groupBy('hari'); @endphp
                    <tr>
                        <td>{{ $chunkIdx * 15 + $guruIdx + 1 }}</td>
                        <td style="font-weight:700;">{{ $guru['nama'] }}</td>
                        <td style="font-size:6px;">
                            @php
                                $mapels = collect($guru['jadwals'])->pluck('mapel')->unique()->implode(', ');
                            @endphp
                            {{ Str::limit($mapels, 40) }}
                        </td>
                        @foreach($DAYS as $day)
                            <td>
                                @if(isset($jadwalsByDay[$day]))
                                    @foreach($jadwalsByDay[$day] as $j)
                                        <div class="guru-jadwal-item">
                                            <span class="jam">{{ $j['jam_mulai'] }}-{{ $j['jam_selesai'] }}</span>
                                            <span class="detail"> {{ $j['kelas'] }}</span>
                                        </div>
                                    @endforeach
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer">{{ config('yayasan.name') }} &mdash; Dicetak: {{ now()->format('d/m/Y H:i') }}</div>
    @endforeach
@endif

</body>
</html>
