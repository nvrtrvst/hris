<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Referensi status kepegawaian (Tier B — ref table + kode).
 *
 * `pegawais.status_kepegawaian` menyimpan `kode`. Label & flag (is_tetap,
 * is_guru, is_active) hidup di tabel ini — ubah label/kelompok cukup via DB,
 * tanpa deploy. Menambah status baru = insert row dengan kode unik.
 */
class StatusKepegawaian extends Model
{
    protected $table = 'status_kepegawaian';

    protected $fillable = [
        'kode',
        'label',
        'is_tetap',
        'is_guru',
        'urutan',
        'is_active',
    ];

    protected $casts = [
        'is_tetap' => 'boolean',
        'is_guru' => 'boolean',
        'urutan' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Daftar opsi aktif (kode, label) untuk dropdown & validasi.
     *
     * @return Collection<int, array{id: int, kode: string, label: string, is_tetap: bool, is_guru: bool}>
     */
    public static function activeOptions()
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('urutan')
            ->get(['id', 'kode', 'label', 'is_tetap', 'is_guru']);
    }

    /**
     * Kode status aktif — dipakai validasi `in:`.
     *
     * @return list<string>
     */
    public static function activeKodes(): array
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('urutan')
            ->pluck('kode')
            ->all();
    }

    /**
     * Kode status aktif ber-grup tetap (gate presensi pagi/sore + slide).
     *
     * @return list<string>
     */
    public static function activeTetapKodes(): array
    {
        return static::query()
            ->where('is_active', true)
            ->where('is_tetap', true)
            ->orderBy('urutan')
            ->pluck('kode')
            ->all();
    }
}
