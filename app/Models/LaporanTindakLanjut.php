<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class LaporanTindakLanjut extends Model
{
    use Auditable;

    protected $table = 'laporan_tindak_lanjut';

    protected $guarded = ['id'];

    protected $casts = ['snapshot' => 'array', 'history' => 'array', 'version' => 'integer'];

    public const STATUSES = ['belum_ditindaklanjuti', 'diproses', 'selesai'];

    public const RESULTS = ['alpa_terkonfirmasi', 'data_dikoreksi', 'ketidakhadiran_sah', 'klarifikasi_selesai'];
}
