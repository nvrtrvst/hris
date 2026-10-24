<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px; }
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 10px; }
        h1 { margin: 0; color: #0f3d3e; font-size: 18px; }
        .meta { margin: 5px 0 16px; color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #dbe2ea; padding: 6px; text-align: left; }
        th { background: #0f3d3e; color: white; font-size: 9px; }
        tr:nth-child(even) { background: #f8fafc; }
        .num { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
    <h1>Laporan KPI Pegawai</h1>
    <p class="meta">{{ ucfirst($periode) }}: {{ $start }} s/d {{ $end }} · Unit: {{ $unitNama }}</p>
    <table>
        <thead><tr>
            <th>Pegawai</th><th>Jabatan</th><th>Unit</th><th>Kehadiran</th><th>Tepat waktu</th><th>Hadir/Hari Kerja</th><th>JP Mengajar</th><th>Nilai KPI</th><th>Status</th>
        </tr></thead>
        <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['pegawai'] }}</td>
                <td>{{ $row['jabatan'] }}</td>
                <td>{{ $row['unit'] }}</td>
                <td class="num">{{ $row['attendance_rate'] === null ? '—' : number_format((float) $row['attendance_rate'], 1).'%' }}</td>
                <td class="num">{{ $row['punctuality_rate'] === null ? '—' : number_format((float) $row['punctuality_rate'], 1).'%' }}</td>
                <td class="num">{{ $row['hadir_hari'] }}/{{ $row['hari_kerja'] }}</td>
                <td class="num">{{ $row['teaching_jp_terjadwal'] > 0 ? number_format((float) $row['teaching_jp_rate'], 1).'%' : '—' }}</td>
                <td class="num">{{ $row['score'] === null ? '—' : number_format((float) $row['score'], 1).'%' }}</td>
                <td>{{ $row['evaluation_status'] ?? 'belum dinilai' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
