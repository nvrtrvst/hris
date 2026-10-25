# Plan: Radio Button Lokasi Assignment

## Context
User bingung checkbox "centang keduanya vs kosongkan". Ingin simple: **radio button 3 opsi**.

## UI Baru

```
Lokasi Presensi
Pilih lokasi yang diizinkan untuk presensi pegawai ini.

○ Kampus 1 (Titik Utama)
○ Kampus 2
○ Keduanya
```

Label dinamis dari nama `UnitLokasi`. Opsi pertama dapat suffix "(Titik Utama)".

## Mapping

| Radio Value | `use_primary_location` | `pegawai_unit_lokasi` |
|-------------|----------------------|----------------------|
| `primary` | true | kosong |
| `secondary` | false | [kampus2_id] |
| `both` | true | [kampus2_id] |

## Changes (6 files)

### 1. Migration
**New:** `database/migrations/2026_09_23_000000_add_use_primary_location_to_pegawai_table.php`

```php
Schema::table('pegawai', function (Blueprint $table) {
    $table->boolean('use_primary_location')->default(true)->after('wajib_kantor');
});
```

### 2. Pegawai model
**File:** `app/Models/Pegawai.php`

Tambah `$fillable[]` + `$casts['use_primary_location'] = 'boolean'`.

### 3. PegawaiController
**File:** `app/Http/Controllers/PegawaiController.php`

**edit() — pass ke Inertia:**
```php
'lokasi_pilihan' => $this->resolveLokasiPilihan($pegawai),
```

**Baru — `resolveLokasiPilihan()`:**
```php
private function resolveLokasiPilihan(Pegawai $pegawai): string
{
    $hasPrimary = $pegawai->use_primary_location;
    $hasSecondary = $pegawai->lokasis->isNotEmpty();

    if ($hasPrimary && $hasSecondary) return 'both';
    if ($hasSecondary) return 'secondary';
    return 'primary';
}
```

**update() — handle radio value:**
```php
'lokasi_pilihan' => 'required|string|in:primary,secondary,both',
```

```php
$pilihan = $request->input('lokasi_pilihan', 'primary');
$usePrimary = in_array($pilihan, ['primary', 'both']);
$pegawai->update(['use_primary_location' => $usePrimary]);

if ($pilihan === 'both') {
    $pegawai->lokasis()->sync($pegawai->units->flatMap(function ($unit) {
        return UnitLokasi::where('unit_sekolah_id', $unit->id)->pluck('id');
    }));
} elseif ($pilihan === 'secondary') {
    $pegawai->lokasis()->sync(
        UnitLokasi::whereIn('unit_sekolah_id', $pegawai->units->pluck('id'))->pluck('id')
    );
} else {
    $pegawai->lokasis()->detach();
}
```

### 4. Frontend Edit.jsx
**File:** `resources/js/Pages/Pegawai/Edit.jsx`

**Props:** tambah `lokasi_pilihan`

**Form data:**
```jsx
lokasi_pilihan: lokasi_pilihan || 'primary',
```

**Radio UI (ganti checkbox section):**
```jsx
{lokalis.length > 0 && (() => {
    const primary = lokalis[0];
    const secondary = lokalis[1];
    return (
        <div className="card p-6">
            <h3>Lokasi Presensi</h3>
            <p>Pilih lokasi yang diizinkan untuk presensi pegawai ini.</p>
            <div className="space-y-2">
                <label className="flex items-center gap-2">
                    <input type="radio" name="lokasi_pilihan" value="primary"
                        checked={data.lokasi_pilihan === 'primary'}
                        onChange={e => setData('lokasi_pilihan', e.target.value)} />
                    <span>{primary?.nama} (Titik Utama)</span>
                </label>
                {secondary && (
                    <label className="flex items-center gap-2">
                        <input type="radio" name="lokasi_pilihan" value="secondary"
                            checked={data.lokasi_pilihan === 'secondary'}
                            onChange={e => setData('lokasi_pilihan', e.target.value)} />
                        <span>{secondary.nama}</span>
                    </label>
                )}
                {secondary && (
                    <label className="flex items-center gap-2">
                        <input type="radio" name="lokasi_pilihan" value="both"
                            checked={data.lokasi_pilihan === 'both'}
                            onChange={e => setData('lokasi_pilihan', e.target.value)} />
                        <span>Keduanya</span>
                    </label>
                )}
            </div>
        </div>
    );
})()}
```

### 5. Backend resolveGeofenceLocation
**File:** `app/Http/Controllers/MobileController.php`

```php
$checkLocations = collect();

if ($pegawai->use_primary_location) {
    $checkLocations->push($unitSekolah);
}

$assignedLocations = $pegawai->lokasis()
    ->where('is_active', true)
    ->where('unit_sekolah_id', $unitSekolah->id)
    ->get();
$checkLocations = $checkLocations->concat($assignedLocations);

// ... rest unchanged
```

### 6. absen() — load data
**File:** `app/Http/Controllers/MobileController.php` (sudah di-update sebelumnya)

```php
$pegawai->load(['units', 'lokasis' => fn($q) => $q->where('is_active', true)]);
```

## Verification

1. Radio "Kampus 1 (Titik Utama)" → `use_primary=1`, pivot kosong
2. Radio "Kampus 2" → `use_primary=0`, pivot=[kampus2_id]
3. Radio "Keduanya" → `use_primary=1`, pivot=[kampus2_id]
4. Mobile absen → check geofence melawan lokasi yang dipilih
5. Satu pegawai cuma bisa absen di lokasi yang dipilih
