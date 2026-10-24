<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaporanPiket extends Model
{
    protected $table = 'laporan_piket';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const SEKSI_PAGI = 'pagi';

    public const SEKSI_KBM = 'kbm';

    public const SEKSI_SORE = 'sore';

    public const ITEM_GERBANG = 'gerbang';

    public const ITEM_CEK_ABSEN = 'cek_absen';

    public const ITEM_KELAS_KOSONG = 'kelas_kosong';

    public const ITEM_BUKU = 'buku';

    public const ITEM_INSIDEN = 'insiden';

    public const ITEM_ABSEN_SISWA = 'absen_siswa';

    /** Label item untuk pesan error. */
    public const ITEM_LABEL = [
        self::ITEM_GERBANG => 'Jaga gerbang',
        self::ITEM_CEK_ABSEN => 'Cek guru absen',
        self::ITEM_KELAS_KOSONG => 'Kelas kosong',
        self::ITEM_BUKU => 'Buku piket',
        self::ITEM_INSIDEN => 'Insiden',
        self::ITEM_ABSEN_SISWA => 'Absen siswa',
    ];

    /** Kode item yang wajib selesai sebelum laporan bisa dikirim. */
    public const ITEM_WAJIB = [
        self::ITEM_GERBANG,
        self::ITEM_CEK_ABSEN,
        self::ITEM_KELAS_KOSONG,
        self::ITEM_BUKU,
        self::ITEM_INSIDEN,
        self::ITEM_ABSEN_SISWA,
    ];

    /** Seksi pemilik tiap item (cek_absen boleh tambah entry sepanjang hari). */
    public const ITEM_SEKSI = [
        self::ITEM_GERBANG => self::SEKSI_PAGI,
        self::ITEM_CEK_ABSEN => self::SEKSI_PAGI,
        self::ITEM_KELAS_KOSONG => self::SEKSI_KBM,
        self::ITEM_BUKU => self::SEKSI_KBM,
        self::ITEM_INSIDEN => self::SEKSI_KBM,
        self::ITEM_ABSEN_SISWA => self::SEKSI_PAGI,
    ];

    protected $fillable = [
        'pegawai_id',
        'jadwal_id',
        'unit_sekolah_id',
        'tanggal',
        'items',
        'status',
        'submitted_at',
        'unlocked_at',
        'unlocked_by',
    ];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'items' => 'array',
        'submitted_at' => 'datetime',
        'unlocked_at' => 'datetime',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class);
    }

    public function unitSekolah(): BelongsTo
    {
        return $this->belongsTo(UnitSekolah::class);
    }

    public function item(string $kode): ?array
    {
        return ($this->items ?? [])[$kode] ?? null;
    }

    public function isItemDone(string $kode): bool
    {
        return (bool) ($this->item($kode)['done_at'] ?? null);
    }

    /** Jumlah item wajib yang selesai. */
    public function doneCount(): int
    {
        $done = 0;
        foreach (self::ITEM_WAJIB as $kode) {
            if ($this->isItemDone($kode)) {
                $done++;
            }
        }

        return $done;
    }

    public function isLengkap(): bool
    {
        return $this->doneCount() === count(self::ITEM_WAJIB);
    }

    /** Kode item wajib yang belum selesai (untuk pesan error submit). */
    public function kurangItems(): array
    {
        return array_values(array_filter(
            self::ITEM_WAJIB,
            fn ($kode) => ! $this->isItemDone($kode)
        ));
    }

    /** Entry kelas_kosong yang butuh verifikasi admin. */
    public function perluCekCount(): int
    {
        $count = 0;
        foreach (($this->item(self::ITEM_KELAS_KOSONG)['entries'] ?? []) as $entry) {
            if (($entry['validasi'] ?? null) === 'perlu_cek' && empty($entry['verified_at'])) {
                $count++;
            }
        }

        return $count;
    }
}
