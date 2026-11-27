<?php

namespace App\Services;

class LaporanPemenuhanExportRows
{
    public function build(array $data): array
    {
        $summary = $data['decision_summary'];
        $rows = [['RINGKASAN TINDAKAN'],
            ['Pegawai perlu klarifikasi', $summary['pegawai_klarifikasi'], 'Temuan terbuka', $summary['kejadian']],
            ['Kewajiban kurang', $summary['kewajiban_kurang'], 'Slot kelas perlu dicek', $summary['kelas_terdampak']],
            ['Administrasi pending', $summary['administrasi_pending'], 'Alpa terkonfirmasi', $summary['alpa_terkonfirmasi']],
            ['TARGET DAN REALISASI'],
            ['Keterangan', 'Tugas luar hanya dihitung jika disetujui.'],
            ['Pegawai / unit', 'Hadir / hari wajib terhitung', 'Izin / sakit / cuti / tugas luar', 'Belum jelas / pending / alpa', 'JP tercatat / jadwal', 'Kuota mingguan']];
        foreach ($data['matrix'] as $row) {
            $weeks = collect($row['weeks'])->filter(fn ($w) => $w['target'] > 0)->map(fn ($w) => $w['from'].': '.$w['realized'].'/'.$w['target'].($w['final'] ? '' : ' (sementara)'))->implode('; ');
            $rows[] = [$row['pegawai'].' / '.$row['unit'], $row['realisasi_hari'].'/'.$row['target_hari'].' (telat '.$row['telat'].')'.($row['sementara'] ? ' (sementara)' : ''), $row['izin'].'/'.$row['sakit'].'/'.$row['cuti'].'/'.$row['tugas'],
                $row['belum_tercatat'].'/'.$row['menunggu_keputusan'].'/'.$row['alpa_terkonfirmasi'],
                $row['is_guru'] ? $row['actual_jp'].'/'.$row['scheduled_jp'] : 'Tidak berlaku', $weeks ?: 'Tidak berlaku'];
        }
        if (isset($data['comparison'])) {
            $rows[] = ['PERBANDINGAN', $data['comparison']['period']['from'].' sampai '.$data['comparison']['period']['to']];
            foreach ($data['comparison']['metrics'] as $key => $metric) {
                $rows[] = [
                    ['hadir' => 'Hadir tepat waktu', 'telat' => 'Terlambat', 'actual_jp' => 'JP tercatat'][$key],
                    $metric['current'].'/'.$metric['denominator'], $metric['previous'].'/'.$metric['previous_denominator'],
                    $metric['delta_pp'] === null ? 'Tidak berlaku' : $metric['delta_pp'].' pp'];
            }
        }
        $rows[] = ['BUKTI DAN TINDAK LANJUT'];
        $rows[] = ['Pegawai / unit', 'Tanggal / temuan', 'Bukti', 'Status / penanggung jawab', 'Catatan / hasil', 'Riwayat'];
        foreach ($data['followups'] as $finding) {
            $record = $finding['record'];
            $history = collect($record['history'] ?? [])->map(fn ($h) => $h['at'].' '.$h['actor'].': '.$h['status'].'; '.($h['catatan'] ?? ''))->implode("\n");
            $rows[] = [$finding['pegawai'].' / '.$finding['unit'], $finding['tanggal'].' '.$finding['label'], $finding['evidence'],
                ($record['status'] ?? 'belum_ditindaklanjuti').' / '.($finding['penanggung_jawab'] ?? 'Belum ditetapkan').($finding['source_changed'] ? ' (sumber berubah)' : ''),
                ($record['catatan'] ?? '').' / '.($record['hasil'] ?? ''), $history];
        }

        return $rows;
    }
}
