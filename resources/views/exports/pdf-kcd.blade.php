<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Laporan KCD - {{ $unit['nama'] }}</title>
<style>
    @page { margin: 10mm; }
    html, body { margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #111; }
    .kop { text-align: center; }
    .kop img { height: 46px; margin-bottom: 2px; }
    .kop .nama { font-size: 13px; font-weight: bold; margin: 1px 0; text-transform: uppercase; }
    .kop .kontak { font-size: 8px; line-height: 1.35; }
    .garis { border-top: 2px solid #111; margin: 4px 0 6px; }
    .judul { text-align: center; font-weight: bold; font-size: 11px; }
    .subjudul { text-align: center; font-size: 9px; margin: 2px 0 8px; }
    table.lap { width: 100%; border-collapse: collapse; font-size: 8px; }
    table.lap th, table.lap td { border: 1px solid #444; padding: 2px 3px; text-align: center; vertical-align: middle; }
    table.lap thead th { background: #e9eef0; font-weight: bold; }
    .c-no { width: 3%; }
    .c-nama { width: 13%; text-align: left; }
    .hari { font-size: 8px; }
    .tgl { font-size: 7px; font-weight: normal; }
    .sub { font-size: 6.5px; font-weight: normal; }
    .blok { margin-bottom: 10px; }
    .ttd { margin-top: 16px; text-align: right; font-size: 9px; }
    .ttd .box { display: inline-block; width: 230px; text-align: center; }
    .ttd .sp { height: 34px; }
    .foot { text-align: right; font-size: 7px; margin-top: 6px; }
</style>
</head>
<body>

@foreach($weeks as $wi => $week)
    <div class="blok">
        <div class="kop">
            @if($unit['logo_path'])
                <img src="{{ $unit['logo_path'] }}" />
            @endif
            <div class="nama">{{ $unit['nama'] }}</div>
            <div class="kontak">
                @if($unit['web'])<div>Web: {{ $unit['web'] }}</div>@endif
                @if($unit['telepon'])<div>Telepon: {{ $unit['telepon'] }}</div>@endif
                @if($unit['alamat'])<div>{{ $unit['alamat'] }}</div>@endif
            </div>
        </div>
        <div class="garis"></div>
        <div class="judul">DAFTAR HADIR &mdash; LAPORAN KCD</div>
        <div class="subjudul">Periode: {{ $periode }} &nbsp;|&nbsp; Unit: {{ $unit['nama'] }}</div>

        <table class="lap">
            <thead>
                <tr>
                    <th class="c-no" rowspan="2">No</th>
                    <th class="c-nama" rowspan="2">Nama</th>
                    @foreach($week['days'] as $day)
                        <th class="hari" colspan="2">{{ $day['label'] }}<br><span class="tgl">{{ $day['short'] }}</span></th>
                    @endforeach
                </tr>
                <tr>
                    @foreach($week['days'] as $day)
                        <th class="sub">Jam Masuk</th>
                        <th class="sub">Jam Pulang</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($pegawai as $p)
                    <tr>
                        <td>{{ $p['no'] }}</td>
                        <td class="c-nama">{{ $p['nama'] }}</td>
                        @foreach($week['days'] as $day)
                            @php $cell = $p['days'][$day['date']]; @endphp
                            <td>{{ $cell['masuk'] }}</td>
                            <td>{{ $cell['pulang'] }}@if($cell['koordinasi']).@endif</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 2 + count($week['days']) * 2 }}" style="text-align:center;padding:8px;">
                            Tidak ada pegawai aktif pada unit ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($loop->last)
            <div class="ttd">
                <div class="box">
                    <div>Mengetahui,</div>
                    <div>Atasan Langsung</div>
                    <div class="sp"></div>
                    <div>( &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; )</div>
                    <div>NIP. &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
                </div>
            </div>
        @endif
    </div>
@endforeach

<div class="foot">
    Cetakan ke-{{ $nomor_cetak }} &nbsp;|&nbsp; {{ $download_at }} &nbsp;|&nbsp; {{ $download_by }}
</div>

</body>
</html>
