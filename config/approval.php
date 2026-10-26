<?php

return [
    // Jabatan puncak organisasi: pengajuannya tidak butuh persetujuan.
    'yayasan_head_jabatan_ids' => [24],

    // Pemetaan jenis pengajuan ke kunci policy.
    'policy_keys' => [
        'izin' => 'izin',
        'sakit' => 'izin',
        'cuti' => 'cuti',
        'koreksi' => 'koreksi',
        'pulang_cepat' => 'pulang_cepat',
        'lembur' => 'lembur',
        'tugas_luar' => 'tugas_luar',
    ],
];
