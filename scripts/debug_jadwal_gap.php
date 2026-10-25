<?php

$hari = $argv[1] ?? 'Rabu';
$rows = DB::table('jadwal')
    ->where('jenis_jadwal', 'mengajar')
    ->where('hari', $hari)
    ->orderBy('pegawai_id')
    ->orderBy('jam_mulai')
    ->get(['pegawai_id', 'jam_mulai', 'jam_selesai', 'kelas_label', 'pegawai_mapel_id']);
$lastPegawai = null;
$lastSelesai = null;
$lastKelas = null;
$lastPmid = null;
$gaps = 0;
$nogap = 0;
foreach ($rows as $r) {
    if ($r->pegawai_id !== $lastPegawai) {
        $lastPegawai = $r->pegawai_id;
        $lastSelesai = null;
    }
    if ($lastSelesai !== null && $r->kelas_label === $lastKelas && $r->pegawai_mapel_id === $lastPmid) {
        if ($r->jam_mulai === $lastSelesai) {
            $nogap++;
        } else {
            $gaps++;
            echo 'GAP pegawai='.$r->pegawai_id.': '.$lastSelesai.' -> '.$r->jam_mulai.' kelas='.$r->kelas_label.' pmid='.$r->pegawai_mapel_id."\n";
        }
    }
    $lastSelesai = $r->jam_selesai;
    $lastKelas = $r->kelas_label;
    $lastPmid = $r->pegawai_mapel_id;
}
echo 'consecutive-no-gap='.$nogap.' with-gap='.$gaps."\n";
