<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Formulir Pengajuan Koreksi Presensi</title>
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
    thead th { background: #0f3d3e; color: #fff; font-size: 9.5px; text-transform: uppercase; padding: 6px 5px; text-align: left; }
    tbody td { border: 1px solid #d1d5db; padding: 4px 5px; font-size: 9.5px; vertical-align: top; }
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

    <div class="doc-title">Formulir Pengajuan Koreksi Presensi</div>
    <div class="doc-meta">No. {{ $koreksi->nomor }}</div>

    <p>Dengan ini saya mengajukan koreksi presensi sebagaimana yang tertera di bawah ini, dan menyatakan bahwa data yang saya sampaikan adalah benar.</p>

    <table class="kv">
        <tbody>
            <tr><td class="k">Nama Pegawai</td><td>{{ $koreksi->pegawai?->nama_lengkap ?? '-' }}</td></tr>
            <tr><td class="k">NIP</td><td>{{ $koreksi->pegawai?->nip ?? '-' }}</td></tr>
            <tr><td class="k">Unit</td><td>{{ $koreksi->presensi?->unitSekolah?->nama ?? ($koreksi->pegawai?->units?->pluck('nama')->implode(', ') ?: '-') }}</td></tr>
            <tr><td class="k">Tanggal Pengajuan</td><td>{{ $koreksi->created_at?->translatedFormat('d F Y') }}</td></tr>
        </tbody>
    </table>

    <table>
        <thead>
            <tr>
                <th>Tanggal Presensi</th>
                <th>Jam Masuk</th>
                <th>Jam Keluar Sebelumnya</th>
                <th>Jam Keluar Diusulkan</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $koreksi->tanggal?->translatedFormat('d F Y') }}</td>
                <td>{{ $koreksi->presensi?->jam_masuk ?? '-' }}</td>
                <td>{{ $koreksi->presensi?->jam_keluar ?: 'Belum tercatat' }}</td>
                <td>{{ $koreksi->nilai_baru }}</td>
            </tr>
        </tbody>
    </table>

    <p>
        @switch($koreksi->alasan)
            @case('lupa_presensi')
                Saya lupa melakukan presensi keluar pada tanggal tersebut.
                @break
            @case('hp_rusak')
                Ponsel yang saya gunakan untuk presensi mengalami kendala/rusak pada saat waktu presensi keluar.
                @break
            @case('dinas_luar')
                Saya sedang melaksanakan tugas di luar unit pada saat waktu presensi keluar.
                @break
            @case('rapat')
                Saya mengikuti rapat yang berlangsung hingga melewati waktu presensi keluar.
                @break
            @default
                {{ $koreksi->alasan_detail ?: 'Dengan alasan lainnya.' }}
        @endswitch
        @if($koreksi->alasan !== 'lainnya' && $koreksi->alasan_detail)
            <br>Detail: {{ $koreksi->alasan_detail }}
        @endif
    </p>

    @if($koreksi->penjelasan_khusus)
        <div class="status-box">
            Pengajuan ini <strong>melebihi kuota 3 koreksi/bulan</strong> dan menyertakan penjelasan khusus:<br>
            {{ $koreksi->penjelasan_khusus }}
        </div>
    @endif

    <div class="status-box">
        @if($koreksi->status === 'pending')
            <span class="badge badge-pending">Menunggu Persetujuan</span><br>
            Pengajuan ini sedang diproses oleh atasan/HR.
        @elseif($koreksi->status === 'disetujui')
            <span class="badge badge-disetujui">Disetujui</span><br>
            Disetujui oleh {{ $koreksi->approver?->name ?? '-' }} pada {{ $koreksi->approved_at?->translatedFormat('d F Y H:i') }}.
            @if($koreksi->catatan_approval)<br>Catatan: {{ $koreksi->catatan_approval }}@endif
        @else
            <span class="badge badge-ditolak">Ditolak</span><br>
            Ditolak oleh {{ $koreksi->rejectedByUser?->name ?? '-' }} pada {{ $koreksi->rejected_at?->translatedFormat('d F Y H:i') }}.
            @if($koreksi->alasan_penolakan)<br>Alasan penolakan: {{ $koreksi->alasan_penolakan }}@endif
        @endif
    </div>

    <div class="ttd">
        <div class="ttd-row">
            <div class="box">
                <div>Pengaju,</div>
                <div>{{ $koreksi->pegawai?->nama_lengkap ?? '-' }}</div>
                <div class="sp"></div>
                <div>( {{ $koreksi->pegawai?->nip ?? '....................' }} )</div>
            </div>
            <div class="box">
                <div>Mengetahui,</div>
                <div>Atasan Langsung / Kepala Unit</div>
                <div class="sp"></div>
                <div>( {{ $koreksi->status === 'pending' ? '....................' : ($koreksi->approver?->name ?? '....................') }} )</div>
            </div>
        </div>
    </div>

    <div class="footer">
        <span>Dicetak pada: {{ now()->translatedFormat('d/m/Y H:i') }} &nbsp;·&nbsp; No. {{ $koreksi->nomor }}</span>
        <span>HRIS Yayasan</span>
    </div>
</body>
</html>
