<?php

$dir = __DIR__ . '/../app/Http/Controllers';
$files = glob($dir . '/*.php');

foreach ($files as $file) {
    $content = file_get_contents($file);
    $original = $content;

    // Pattern: $user && $user->role === 'admin_unit'
    // Pattern: $user->role === 'admin_unit'
    // Replacement: $user && $user->unit_sekolah_id && !$user->can('view_all_units')
    
    // Replace specific $user->role === 'admin_unit'
    $content = preg_replace("/\\\$user\s*&&\s*\\\$user->role\s*===\s*'admin_unit'/", "\$user && \$user->unit_sekolah_id && !\$user->can('view_all_units')", $content);
    $content = preg_replace("/\\\$user->role\s*===\s*'admin_unit'/", "\$user && \$user->unit_sekolah_id && !\$user->can('view_all_units')", $content);
    $content = preg_replace("/auth\(\)->user\(\)->role\s*===\s*'admin_unit'/", "auth()->user() && auth()->user()->unit_sekolah_id && !auth()->user()->can('view_all_units')", $content);

    // Now for the in_array superadmin, admin_unit checks:
    // We replace it with specific module permissions based on the filename
    $permission = 'view_dashboard';
    if (strpos($file, 'Pegawai') !== false) $permission = 'view_pegawai';
    if (strpos($file, 'Presensi') !== false) $permission = 'view_presensi';
    if (strpos($file, 'Jadwal') !== false) $permission = 'view_jadwal';
    if (strpos($file, 'Izin') !== false) $permission = 'view_izin';
    if (strpos($file, 'Penggajian') !== false || strpos($file, 'Komponen') !== false) $permission = 'view_payroll';

    $content = preg_replace("/in_array\(\\\$user->role,\s*\['superadmin',\s*'admin_unit'\]\)/", "\$user->can('$permission')", $content);
    $content = preg_replace("/in_array\(auth\(\)->user\(\)->role,\s*\['superadmin',\s*'admin_unit'\]\)/", "auth()->user()->can('$permission')", $content);

    if ($original !== $content) {
        file_put_contents($file, $content);
        echo "Updated " . basename($file) . "\n";
    }
}

echo "Refactoring completed.\n";
