<?php

use Illuminate\Support\Facades\Schedule;

// Presensi: kirim reminder push ke pegawai belum absen → tiap hari jam 06:30
Schedule::command('presensi:reminder')->dailyAt('06:30')->timezone('Asia/Jakarta');

// Presensi: tandai pegawai tidak hadir sebagai alpa → tiap hari jam 23:00
Schedule::command('presensi:finalize-alpa')->dailyAt('23:00')->timezone('Asia/Jakarta');

// Presensi: sync status presensi dari izin yang sudah disetujui → tiap hari jam 01:00
Schedule::command('presensi:sync-status-from-izin')->dailyAt('01:00')->timezone('Asia/Jakarta');

// Presensi: cleanup foto presensi >3 bulan → tiap minggu jam 02:00 (Minggu)
Schedule::command('presensi:cleanup-foto')->weeklyOn(0, '02:00')->timezone('Asia/Jakarta');
