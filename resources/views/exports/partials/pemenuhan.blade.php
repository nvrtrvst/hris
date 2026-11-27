<h2>Kehadiran dan Pemenuhan Tugas</h2>
<p>Hari/jadwal berjalan bersifat sementara. Tidak ada catatan presensi belum berarti pelanggaran. Penyelesaian klarifikasi tidak mengubah payroll.</p>
<table>
@foreach(app(\App\Services\LaporanPemenuhanExportRows::class)->build(get_defined_vars()) as $line)
<tr>
@foreach(array_pad($line, 6, '') as $cell)
<td style="word-wrap:break-word; white-space:pre-line">{{ $cell }}</td>
@endforeach
</tr>
@endforeach
</table>
