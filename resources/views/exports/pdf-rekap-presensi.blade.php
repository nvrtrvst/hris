<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Laporan Rekapitulasi Presensi Harian</title>
<style>
    @page { margin: 12mm 10mm; size: A4 landscape; }
    * { box-sizing: border-box; }
    body { font-family: 'Helvetica', 'Arial', sans-serif; color: #1f2937; font-size: 10px; margin: 0; }

    /* ── Kop surat ── */
    .kop { text-align: center; margin-bottom: 6px; border-bottom: 2px solid #0f3d3e; padding-bottom: 6px; }
    .kop-logo { margin-bottom: 4px; }
    .kop-logo img { height: 56px; width: auto; display: inline-block; }
    .kop-name { font-size: 15px; font-weight: 800; letter-spacing: .5px; color: #0f3d3e; text-transform: uppercase; line-height: 1.2; }
    .kop-tagline { font-size: 9px; font-style: italic; color: #0f3d3e; margin-top: 1px; }
    .kop-address { font-size: 9px; color: #374151; margin-top: 2px; }
    .kop-contact { font-size: 8.5px; color: #6b7280; margin-top: 1px; }

    /* ── Title ── */
    .doc-title { text-align: center; font-size: 13px; font-weight: 800; margin: 8px 0 2px; text-transform: uppercase; }
    .doc-meta { text-align: center; font-size: 9.5px; color: #374151; margin-bottom: 8px; }

    /* ── Summary cards ── */
    .summary { display: flex; gap: 8px; margin-bottom: 10px; justify-content: center; }
    .summary-card { border: 1px solid #d1d5db; border-radius: 6px; padding: 6px 12px; text-align: center; min-width: 120px; background: #f9fafb; }
    .summary-card .value { font-size: 18px; font-weight: 800; color: #0f3d3e; }
    .summary-card .label { font-size: 8px; color: #6b7280; text-transform: uppercase; margin-top: 2px; }

    /* ── Table ── */
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    thead th { background: #0f3d3e; color: #fff; font-size: 8.5px; text-transform: uppercase; padding: 5px 4px; text-align: left; }
    tbody td { border: 1px solid #d1d5db; padding: 3px 4px; font-size: 8.5px; vertical-align: top; }
    tbody tr:nth-child(even) { background: #f3f4f6; }
    .c-no { width: 3%; text-align: center; }
    .c-nip { width: 8%; }
    .c-nama { width: 12%; }
    .c-jam { width: 7%; text-align: center; }
    .c-status { width: 9%; }
    .c-kelas { width: 28%; }
    .c-mengajar { width: 7%; text-align: center; }
    .c-detail { width: 26%; }

    /* Status badges */
    .badge { display: inline-block; padding: 1px 5px; border-radius: 8px; font-size: 7.5px; font-weight: 700; }
    .badge-hadir { background: #d1fae5; color: #065f46; }
    .badge-telat { background: #fef3c7; color: #92400e; }
    .badge-sakit { background: #ede9fe; color: #5b21b6; }
    .badge-izin { background: #dbeafe; color: #1e40af; }
    .badge-cuti { background: #cffafe; color: #155e75; }
    .badge-alpa { background: #fee2e2; color: #991b1b; }
    .badge-belum { background: #f1f5f9; color: #475569; }

    .detail-kelas { font-size: 7.5px; line-height: 1.4; }
    .detail-kelas .row { margin-bottom: 2px; }
    .detail-kelas .jam { font-weight: 700; color: #374151; }
    .detail-kelas .status-ok { color: #065f46; }
    .detail-kelas .status-warn { color: #92400e; }
    .detail-kelas .status-err { color: #991b1b; }

    /* ── Footer ── */
    .footer { margin-top: 10px; font-size: 8px; color: #6b7280; display: flex; justify-content: space-between; }
    .ttd { margin-top: 12px; text-align: right; font-size: 9px; }
    .ttd .box { display: inline-block; width: 200px; text-align: center; }
    .ttd .sp { height: 30px; }
</style>
</head>
<body>
    <div class="kop">
        @if($logoPath)
            <div class="kop-logo">
                <img src="{{ $logoPath }}" alt="Logo" style="height:56px;width:{{ $logoWidth ?? 56 }}px">
            </div>
        @endif
        <div class="kop-name">{{ $kop['name'] }}</div>
        <div class="kop-tagline">{{ $kop['tagline'] }}</div>
        <div class="kop-address">{{ $kop['address'] }}</div>
        <div class="kop-contact">Telp: {{ $kop['phone'] }} &nbsp;|&nbsp; Email: {{ $kop['email'] }} &nbsp;|&nbsp; Web: {{ $kop['website'] }}</div>
    </div>

    <div class="doc-title">Laporan Rekapitulasi Presensi Harian & Mengajar Multi-Kelas</div>
    <div class="doc-meta">
        {{ $unitName }} &bull; Monitoring Presensi Harian & Mengajar Multi-Kelas
        <br>Tanggal: {{ $periodeStr }} &nbsp;|&nbsp; Total Staf Pengajar: {{ $stats['total_guru'] }} Guru
    </div>

    <!-- Summary Cards -->
    <div class="summary">
        <div class="summary-card">
            <div class="value">{{ $stats['hadir'] }} / {{ $stats['total_guru'] }}</div>
            <div class="label">Guru Hadir</div>
        </div>
        <div class="summary-card">
            <div class="value">{{ $stats['izin_sakit_alpa'] }} Guru</div>
            <div class="label">Izin / Sakit / Alpa</div>
        </div>
        <div class="summary-card">
            <div class="value">{{ $stats['total_kelas'] }} Kelas</div>
            <div class="label">Total Sesi Kelas</div>
        </div>
    </div>

    <!-- Tabel Presensi -->
    <table>
        <thead>
            <tr>
                <th class="c-no">No</th>
                <th class="c-nip">NIP</th>
                <th class="c-nama">Nama Guru</th>
                <th class="c-jam">Jam Masuk</th>
                <th class="c-jam">Jam Pulang</th>
                <th class="c-status">Status Presensi</th>
                <th class="c-kelas">Daftar Kelas Hari Ini</th>
                <th class="c-mengajar">Status Mengajar</th>
                <th class="c-detail">Detail Kelas</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                <tr>
                    <td class="c-no">{{ $i + 1 }}</td>
                    <td class="c-nip">{{ $row['nip'] }}</td>
                    <td class="c-nama">
                        <strong>{{ $row['nama'] }}</strong>
                        <br><span style="font-size:7.5px;color:#6b7280;">{{ $row['jabatan'] }}</span>
                    </td>
                    <td class="c-jam">{{ $row['jam_masuk'] }}</td>
                    <td class="c-jam">{{ $row['jam_pulang'] }}</td>
                    <td class="c-status">
                        @php
                            $badgeClass = match(true) {
                                str_contains($row['status_presensi'], 'Hadir') => 'badge-hadir',
                                str_contains($row['status_presensi'], 'Terlambat') => 'badge-telat',
                                $row['status_presensi'] === 'Sakit' => 'badge-sakit',
                                $row['status_presensi'] === 'Izin' => 'badge-izin',
                                $row['status_presensi'] === 'Cuti' => 'badge-cuti',
                                $row['status_presensi'] === 'Alpa' => 'badge-alpa',
                                default => 'badge-belum',
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ $row['status_presensi'] }}</span>
                    </td>
                    <td class="c-kelas">{{ $row['kelas_list'] }}</td>
                    <td class="c-mengajar">{{ $row['status_mengajar'] }}</td>
                    <td class="c-detail">
                        <div class="detail-kelas">
                            @foreach($row['detail_kelas'] as $dk)
                                <div class="row">
                                    <span class="jam">{{ $dk['jam_sesi'] }}</span>
                                    {{ $dk['kelas_ruangan'] }} &mdash; {{ $dk['mata_pelajaran'] }}
                                    @if(str_contains($dk['status'], 'Terlambat'))
                                        <span class="status-warn">{{ $dk['status'] }}</span>
                                    @elseif($dk['status'] === 'Tidak Mengajar')
                                        <span class="status-err">{{ $dk['status'] }}</span>
                                    @else
                                        <span class="status-ok">{{ $dk['status'] }}</span>
                                    @endif
                                </div>
                            @endforeach
                            @if(empty($row['detail_kelas']))
                                <span style="color:#9ca3af;">—</span>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center;padding:20px;">Tidak ada data presensi untuk periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="ttd">
        <div class="box">
            <div>Mengetahui,</div>
            <div>Kepala Sekolah / Wali Kurikulum</div>
            <div class="sp"></div>
            <div>( &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; )</div>
        </div>
    </div>

    <div class="footer">
        <span>Dicetak pada: {{ now()->translatedFormat('d/m/Y H:i') }}</span>
        <span>Laporan Presensi HRIS</span>
    </div>
</body>
</html>
