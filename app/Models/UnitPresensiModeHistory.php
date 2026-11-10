<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitPresensiModeHistory extends Model
{
    use Auditable;

    public const MODE_KANTOR = 'kantor';

    public const MODE_HANYA_JADWAL = 'hanya_jadwal';

    protected $fillable = [
        'unit_sekolah_id',
        'mode',
        'berlaku_mulai',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'berlaku_mulai' => 'date',
        ];
    }

    public static function modes(): array
    {
        return [
            self::MODE_KANTOR => 'Kantor',
            self::MODE_HANYA_JADWAL => 'Hanya Saat Ada Jadwal',
        ];
    }

    public function unitSekolah(): BelongsTo
    {
        return $this->belongsTo(UnitSekolah::class, 'unit_sekolah_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function label(): string
    {
        return self::modes()[$this->mode] ?? $this->mode;
    }
}
