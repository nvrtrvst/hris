<?php

namespace App\Models;

use App\Constants\BobotKehadiran;
use App\Helpers\FileHelper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitSekolah extends Model
{
    protected $table = 'unit_sekolah';

    protected $fillable = ['nama', 'singkatan', 'logo', 'latitude', 'longitude', 'radius_meter', 'durasi_jp', 'max_jam_minggu', 'toleransi_menit', 'toleransi_mengajar_menit', 'toleransi_slide_menit', 'max_jeda_sesi_menit', 'wajib_pulang_mengajar', 'pulang_sebelum_menit', 'max_lembur_jam', 'jam_masuk_kantor', 'jam_pulang_kantor', 'jam_kerja_sabtu_mulai', 'jam_kerja_sabtu_selesai', 'jam_kerja_minggu_mulai', 'jam_kerja_minggu_selesai', 'web', 'telepon', 'alamat', 'bobot_sakit_surat', 'bobot_sakit_tanpa_surat', 'bobot_izin', 'bobot_izin_setengah', 'bobot_cuti', 'bobot_alpa', 'sakit_wajib_tugas'];

    protected $appends = ['logo_url'];

    /**
     * Bobot kehadiran untuk rekap/payroll: kolom unit ?? default (BobotKehadiran).
     *
     * @return array{sakit_surat: float, sakit_tanpa_surat: float, izin: float, izin_setengah: float, cuti: float, alpa: float}
     */
    public function bobotHadiran(): array
    {
        return [
            'sakit_surat' => (float) ($this->bobot_sakit_surat ?? BobotKehadiran::SAKIT_SURAT),
            'sakit_tanpa_surat' => (float) ($this->bobot_sakit_tanpa_surat ?? BobotKehadiran::SAKIT_TANPA_SURAT),
            'izin' => (float) ($this->bobot_izin ?? BobotKehadiran::IZIN),
            'izin_setengah' => (float) ($this->bobot_izin_setengah ?? BobotKehadiran::IZIN_SETENGAH),
            'cuti' => (float) ($this->bobot_cuti ?? BobotKehadiran::CUTI),
            'alpa' => (float) ($this->bobot_alpa ?? BobotKehadiran::ALPA),
        ];
    }

    public function sakitWajibTugas(): bool
    {
        return (bool) ($this->sakit_wajib_tugas ?? false);
    }

    public function toleransiMengajarMenit(): int
    {
        return (int) ($this->toleransi_mengajar_menit ?? $this->toleransi_menit ?? 0);
    }

    public function presensiModeHistories(): HasMany
    {
        return $this->hasMany(UnitPresensiModeHistory::class, 'unit_sekolah_id')
            ->orderBy('berlaku_mulai');
    }

    public function modePresensiPada(Carbon|string|null $date = null): string
    {
        $tanggal = $date ? Carbon::parse($date)->toDateString() : Carbon::today()->toDateString();

        if ($this->relationLoaded('presensiModeHistories')) {
            $history = $this->presensiModeHistories
                ->filter(fn (UnitPresensiModeHistory $row) => $row->berlaku_mulai->toDateString() <= $tanggal)
                ->sortByDesc('berlaku_mulai')
                ->first();

            return $history?->mode ?? UnitPresensiModeHistory::MODE_KANTOR;
        }

        return $this->presensiModeHistories()
            ->whereDate('berlaku_mulai', '<=', $tanggal)
            ->orderByDesc('berlaku_mulai')
            ->value('mode') ?? UnitPresensiModeHistory::MODE_KANTOR;
    }

    public function modePresensiAktif(): string
    {
        return $this->modePresensiPada(Carbon::today());
    }

    /**
     * Pecah rentang menjadi segmen mode agar perubahan di tengah bulan
     * dihitung dengan aturan yang berlaku pada tanggal masing-masing.
     *
     * @return list<array{from: Carbon, to: Carbon, mode: string}>
     */
    public function modeSegments(Carbon $start, Carbon $end): array
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();
        if ($start->gt($end)) {
            return [];
        }

        $histories = $this->relationLoaded('presensiModeHistories')
            ? $this->presensiModeHistories
            : $this->presensiModeHistories()->whereDate('berlaku_mulai', '<=', $end)->get();

        $mode = UnitPresensiModeHistory::MODE_KANTOR;
        $changes = [];
        foreach ($histories as $history) {
            $tanggal = $history->berlaku_mulai->copy()->startOfDay();
            if ($tanggal->lte($start)) {
                $mode = $history->mode;
            } elseif ($tanggal->lte($end)) {
                $changes[] = ['date' => $tanggal, 'mode' => $history->mode];
            }
        }

        usort($changes, fn (array $a, array $b) => $a['date']->timestamp <=> $b['date']->timestamp);

        $segments = [];
        $cursor = $start->copy();
        foreach ($changes as $change) {
            $segmentEnd = $change['date']->copy()->subDay();
            if ($cursor->lte($segmentEnd)) {
                $segments[] = ['from' => $cursor->copy(), 'to' => $segmentEnd, 'mode' => $mode];
            }
            $cursor = $change['date']->copy();
            $mode = $change['mode'];
        }

        if ($cursor->lte($end)) {
            $segments[] = ['from' => $cursor, 'to' => $end, 'mode' => $mode];
        }

        return $segments;
    }

    public function getLogoUrlAttribute(): ?string
    {
        return FileHelper::fotoUrl($this->logo);
    }

    /**
     * Pegawai yang ditugaskan ke unit ini (via pivot pegawai_unit).
     */
    public function pegawais(): BelongsToMany
    {
        return $this->belongsToMany(Pegawai::class, 'pegawai_unit')
            ->withPivot(['jabatan_id', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * Jadwal mengajar/reguler yang berlangsung di unit ini.
     */
    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class, 'unit_sekolah_id');
    }

    public function lokalis(): HasMany
    {
        return $this->hasMany(UnitLokasi::class, 'unit_sekolah_id');
    }
}
