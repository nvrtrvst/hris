# Plan: Multi-Lokasi Geofence

## Goal
Geofence kantor/lembur cek GPS terhadap `unit_lokasi` yang di-assign ke pegawai (bukan selalu primary unit).

## Context
- SMK Nuurul Muttaqiin punya Kampus 1 (unit_sekolah) dan Kampus 2 (unit_lokasi)
- Pegawai hybrid: bisa di Kampus 1 atau Kampus 2
- Pegawai standby: hanya di Kampus 2
- Sekarang kantor/lembur selalu pakai koordinat unit_sekolah (Kampus 1) → standby Kampus 2 tidak bisa absen kantor

## Existing Infrastructure
- `unit_lokasi` tabel: ✅ ada
- `pegawai_unit_lokasi` pivot: ✅ ada
- `Pegawai::lokasis()`: ✅ ada
- `Pegawai/Edit.jsx`: ✅ sudah ada UI assign lokasi
- Admin UI `PegawaiController::update()`: ✅ sudah sync `lokasi_ids`

## Yang Perlu Diubah

### 1. Helper Method Baru: `resolveGeofenceLocation()`

**File:** `app/Http/Controllers/MobileController.php`

```php
/**
 * Resolve lokasi geofence untuk kantor/lembur.
 * Prioritas: assigned UnitLokasi → fallback primary UnitSekolah.
 * Return: ['unit' => UnitLokasi|UnitSekolah, 'unitSekolah' => UnitSekolah, 'locations' => Collection]
 */
private function resolveGeofenceLocation(Pegawai $pegawai): array
{
    $unitSekolah = $pegawai->units()->orderByPivot('is_primary', 'desc')->first();
    
    if (!$unitSekolah) {
        return ['unit' => null, 'unitSekolah' => null, 'locations' => collect()];
    }
    
    // Load assigned UnitLokasi (eager-load parent)
    $assignedLocations = $pegawai->lokasis()
        ->where('is_active', true)
        ->where('unit_sekolah_id', $unitSekolah->id)
        ->get();
    
    // Kalau ada assigned locations → pakai itu
    if ($assignedLocations->isNotEmpty()) {
        return [
            'unit' => $assignedLocations->first(), // UnitLokasi (lat/lng/radius)
            'unitSekolah' => $unitSekolah, // Untuk jam_masuk_kantor, toleransi_menit
            'locations' => $assignedLocations, // Semua lokasi untuk multi-check
        ];
    }
    
    // Fallback: pakai UnitSekolah (backward compat)
    return [
        'unit' => $unitSekolah,
        'unitSekolah' => $unitSekolah,
        'locations' => collect(),
    ];
}
```

### 2. Method Baru: `checkMultiLocationGeofence()`

**File:** `app/Http/Controllers/MobileController.php`

```php
/**
 * Cek GPS terhadap multiple locations. Return ['inside' => bool, 'distance' => float, 'matchedUnit' => ?Model].
 */
private function checkMultiLocationGeofence(float $lat, float $lng, Collection $locations, $fallbackUnit): array
{
    // Multi-location check
    if ($locations->isNotEmpty()) {
        foreach ($locations as $loc) {
            $distance = $this->calculateDistance($lat, $lng, $loc->latitude, $loc->longitude);
            if ($distance <= $loc->radius_meter) {
                return ['inside' => true, 'distance' => $distance, 'matchedUnit' => $loc];
            }
        }
        // Tidak match satupun → hitung jarak terdekat untuk pesan error
        $minDistance = $locations->map(fn($loc) => $this->calculateDistance($lat, $lng, $loc->latitude, $loc->longitude))->min();
        return ['inside' => false, 'distance' => $minDistance, 'matchedUnit' => null];
    }
    
    // Fallback: single unit check
    $distance = $this->calculateDistance($lat, $lng, $fallbackUnit->latitude, $fallbackUnit->longitude);
    return [
        'inside' => $distance <= $fallbackUnit->radius_meter,
        'distance' => $distance,
        'matchedUnit' => $distance <= $fallbackUnit->radius_meter ? $fallbackUnit : null,
    ];
}
```

### 3. Update `storeAbsen()` — Kantor/Lembur Mode

**File:** `app/Http/Controllers/MobileController.php`, baris ~583-612

**Sekarang:**
```php
} elseif ($isLembur) {
    $primaryUnit = $pegawai->units()->orderByPivot('is_primary', 'desc')->first();
    // ...
    $unit = $primaryUnit;
} elseif ($isTugasLuar) {
    $primaryUnit = $pegawai->units()->orderByPivot('is_primary', 'desc')->first();
    // ...
    $unit = $primaryUnit;
} elseif ($pegawai->wajib_kantor && ...) {
    $unit = $pegawai->units()->orderByPivot('is_primary', 'desc')->first();
    // ...
}
```

**Sesudah:**
```php
} elseif ($isLembur) {
    $geo = $this->resolveGeofenceLocation($pegawai);
    if (!$geo['unit']) {
        return response()->json([...], 422);
    }
    $unit = $geo['unit'];
    $unitSekolah = $geo['unitSekolah'];
    $assignedLocations = $geo['locations'];
    $jadwal = null;
} elseif ($isTugasLuar) {
    // sama seperti lembur
} elseif ($pegawai->wajib_kantor && ...) {
    $geo = $this->resolveGeofenceLocation($pegawai);
    if (!$geo['unit']) {
        return response()->json([...], 422);
    }
    $unit = $geo['unit'];
    $unitSekolah = $geo['unitSekolah'];
    $assignedLocations = $geo['locations'];
    $jadwal = null;
}
```

### 4. Update Geofence Check — Multi-Location

**File:** `app/Http/Controllers/MobileController.php`, baris ~629-635

**Sekarang:**
```php
$distance = $this->calculateDistance($request->latitude, $request->longitude, $unit->latitude, $unit->longitude);
if (! $isTugasLuar && $distance > $unit->radius_meter) {
    $message = sprintf(PresensiMessages::GEOFENCE_OUTSIDE, $distance, $unit->radius_meter);
    return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
}
```

**Sesudah:**
```php
$geoResult = $this->checkMultiLocationGeofence(
    $request->latitude,
    $request->longitude,
    $assignedLocations ?? collect(),
    $unit
);
$distance = $geoResult['distance'];

if (! $isTugasLuar && ! $geoResult['inside']) {
    $radiusUsed = $geoResult['matchedUnit'] ? $geoResult['matchedUnit']->radius_meter : $unit->radius_meter;
    $message = sprintf(PresensiMessages::GEOFENCE_OUTSIDE, $distance, $radiusUsed);
    return response()->json(['success' => false, 'message' => $message, 'errors' => ['geofence' => $message]], 422);
}

// Update $unit ke matched location (untuk overlay nama, accuracy check)
if ($geoResult['matchedUnit']) {
    $unit = $geoResult['matchedUnit'];
}
```

### 5. Update Time Settings — Pakai UnitSekolah

**File:** `app/Http/Controllers/MobileController.php`, baris ~859-862

**Sekarang:**
```php
$jamMulai = $tipePresensi === 'kantor'
    ? (Carbon::now()->isSaturday() && $unit->jam_kerja_sabtu_mulai ? $unit->jam_kerja_sabtu_mulai : $unit->jam_masuk_kantor)
    : $jadwal->jam_mulai;
$presensi->status = Presensi::statusAt(Carbon::now()->format('H:i:s'), $jamMulai, (int) $unit->toleransi_menit);
```

**Sesudah:**
```php
$timeUnit = $unitSekolah ?? $unit; // UnitSekolah untuk time settings
$jamMulai = $tipePresensi === 'kantor'
    ? (Carbon::now()->isSaturday() && $timeUnit->jam_kerja_sabtu_mulai ? $timeUnit->jam_kerja_sabtu_mulai : $timeUnit->jam_masuk_kantor)
    : $jadwal->jam_mulai;
$presensi->status = Presensi::statusAt(Carbon::now()->format('H:i:s'), $jamMulai, (int) $timeUnit->toleransi_menit);
```

### 6. Update Saturday Check — Pakai UnitSekolah

**File:** `app/Http/Controllers/MobileController.php`, baris ~622

```php
// Sekarang: $unit->jam_kerja_sabtu_mulai
// Sesudah: ($unitSekolah ?? $unit)->jam_kerja_sabtu_mulai
```

### 7. Update `storeAbsenTetap()` — Sama Seperti storeAbsen

**File:** `app/Http/Controllers/MobileController.php`, baris ~1056-1061

```php
// Sekarang:
$unit = $pegawai->units()->orderByPivot('is_primary', 'desc')->first();

// Sesudah:
$geo = $this->resolveGeofenceLocation($pegawai);
$unit = $geo['unit'];
$unitSekolah = $geo['unitSekolah'];
$assignedLocations = $geo['locations'];
```

Update geofence check di `storeAbsenTetap` (baris ~1059-1061) dengan pola `checkMultiLocationGeofence`.

Update call ke `storeAbsenTransaction` (baris ~1137) — tambah `$unitSekolah`:
```php
// Sekarang:
$presensi = $this->storeAbsenTransaction($request, $pegawai, null, $unit, ...);

// Sesudah:
$presensi = $this->storeAbsenTransaction($request, $pegawai, null, $unit, ..., $unitSekolah);
```

### 8. Update `storeAbsenTransaction()` — Pakai UnitSekolah untuk Time Settings + ID

**File:** `app/Http/Controllers/MobileController.php`, baris 790

Tambah parameter `$unitSekolah` ke method signature:

```php
// Sekarang:
private function storeAbsenTransaction(Request $request, $pegawai, ?Jadwal $jadwal, $unit, ...)

// Sesudah:
private function storeAbsenTransaction(Request $request, $pegawai, ?Jadwal $jadwal, $unit, ..., $unitSekolah = null)
```

Update baris 823:
```php
// Sekarang:
$presensi->unit_sekolah_id = $jadwal->unit_sekolah_id ?? $unit->id;

// Sesudah:
$presensi->unit_sekolah_id = $jadwal->unit_sekolah_id ?? ($unitSekolah?->id ?? $unit->id);
```

Update baris 859-862 (time settings):
```php
// Sekarang:
$jamMulai = $tipePresensi === 'kantor'
    ? (Carbon::now()->isSaturday() && $unit->jam_kerja_sabtu_mulai ? $unit->jam_kerja_sabtu_mulai : $unit->jam_masuk_kantor)
    : $jadwal->jam_mulai;
$presensi->status = Presensi::statusAt(Carbon::now()->format('H:i:s'), $jamMulai, (int) $unit->toleransi_menit);

// Sesudah:
$timeUnit = $unitSekolah ?? $unit;
$jamMulai = $tipePresensi === 'kantor'
    ? (Carbon::now()->isSaturday() && $timeUnit->jam_kerja_sabtu_mulai ? $timeUnit->jam_kerja_sabtu_mulai : $timeUnit->jam_masuk_kantor)
    : $jadwal->jam_mulai;
$presensi->status = Presensi::statusAt(Carbon::now()->format('H:i:s'), $jamMulai, (int) $timeUnit->toleransi_menit);
```

Update call site di `storeAbsen` (baris 723):
```php
// Sekarang:
$presensi = $this->storeAbsenTransaction($request, $pegawai, $jadwal, $unit, ...);

// Sesudah:
$presensi = $this->storeAbsenTransaction($request, $pegawai, $jadwal, $unit, ..., $unitSekolah ?? null);
```

### 9. Update `absen()` — Load Lokasi ke Frontend

**File:** `app/Http/Controllers/MobileController.php`, baris ~443

```php
// Sekarang:
$pegawai->load('units');

// Sesudah:
$pegawai->load(['units', 'lokasis' => function ($q) {
    $q->where('is_active', true);
}]);
```

### 10. Seed Kampus 1 sebagai UnitLokasi

**File:** Migration baru atau manual seed

```php
// Kampus 1 = ambil koordinat dari unit_sekolah SMK
UnitLokasi::create([
    'unit_sekolah_id' => 6, // SMK Nuurul Muttaqiin
    'nama' => 'Kampus 1',
    'latitude' => $smk->latitude,
    'longitude' => $smk->longitude,
    'radius_meter' => $smk->radius_meter,
    'is_active' => true,
]);
```

Dan update Kampus 2 koordinat dari placeholder `1.0, 1.0` ke koordinat asli.

## File yang Diubah

| File | Perubahan |
|---|---|
| `app/Http/Controllers/MobileController.php` | +2 helper methods, update storeAbsen (resolve+check), storeAbsenTetap, storeAbsenTransaction (param+time+ID), absen (eager load) |
| Migration | Seed Kampus 1 + update Kampus 2 coords |

## Tidak Diubah

- Frontend (`Absen.jsx`, `TetapPresensi`) — tidak ada perubahan
- Admin UI (`Pegawai/Edit.jsx`) — sudah ada
- `PegawaiController` — sudah sync `lokasi_ids`
- `UnitLokasi` model — sudah lengkap
- `Presensi` model — `unit_sekolah_id` tetap pakai parent unit

## Critical Detail: UnitLokasi vs UnitSekolah

`UnitLokasi` hanya punya: `id, unit_sekolah_id, nama, latitude, longitude, radius_meter, is_active`
`UnitSekolah` punya: semua field di atas + `jam_masuk_kantor, jam_pulang_kantor, toleransi_menit, jam_kerja_sabtu_mulai, dll`

Jadi:
- **Geofence check** (lat/lng/radius) → pakai `UnitLokasi`
- **Time settings** (jam_masuk, toleransi, sabtu) → pakai parent `UnitSekolah`
- **Presensi.unit_sekolah_id** → selalu pakai `UnitSekolah.id` (bukan `UnitLokasi.id`)

## Edge Cases

1. **Pegawai tanpa assigned locations** → fallback ke primary unit (backward compat)
2. **Pegawai assigned ke lokasi lintas unit** → filter by `unit_sekolah_id` (hanya lokasi dari unit yang sama)
3. **Lokasi tidak aktif** → filter `is_active = true`
4. **Concurrent absen** → tidak ada race condition (read-only check)

## Testing Scenarios

1. Pegawai hybrid (K1+K2) absen kantor di K1 → DITERIMA
2. Pegawai hybrid absen kantor di K2 → DITERIMA
3. Pegawai standby (K2) absen kantor di K1 → DITOLAK
4. Pegawai standby absen kantor di K2 → DITERIMA
5. Pegawai tanpa assign absen kantor di K1 → DITERIMA (fallback)
6. Pegawai tetap absen kantor di K2 → DITERIMA
7. Lembur di K2 → DITERIMA (sesuai assigned)
8. Tugas luar → skip geofence (existing behavior)
