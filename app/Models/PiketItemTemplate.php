<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PiketItemTemplate extends Model
{
    protected $table = 'piket_item_templates';
    protected $fillable = ['unit_sekolah_id', 'kode', 'label', 'seksi', 'scope', 'tipe', 'wajib', 'butuh_foto', 'urutan', 'aktif'];
    protected $casts = ['wajib' => 'boolean', 'butuh_foto' => 'boolean', 'aktif' => 'boolean', 'urutan' => 'int'];
}
