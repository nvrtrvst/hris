<?php

namespace App\Services;

use App\Exceptions\PdfTooLargeException;
use App\Exports\LaporanDetailHarianExport;
use App\Exports\LaporanLemburanExport;
use App\Exports\LaporanMengajarHarianExport;
use App\Exports\LaporanPenggajianExport;
use App\Exports\LaporanPresensiExport;
use App\Exports\LaporanRekapKehadiranExport;
use App\Exports\LaporanRekapKehadiranHarianExport;
use App\Exports\LaporanRekapMengajarExport;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Render PDF laporan (DomPDF). Satu-satunya sumber logika render PDF laporan
 * (dipakai LaporanController::exportPdf).
 */
class LaporanPdfService
{
    /** Di atas jumlah baris ini DomPDF rawan OOM/timeout — tolak, arahkan ke Excel. */
    public const MAX_ROWS = 2000;

    /**
     * @param  array<string, mixed>  $validated  output LaporanGenerateRequest::validated()
     * @return array{filename: string, content: string}
     *
     * @throws PdfTooLargeException saat jumlah baris melebihi MAX_ROWS
     */
    public function generate(array $validated, ?int $unitId): array
    {
        $type = $validated['type'];

        // HTTP sync dibatasi nginx fastcgi_read_timeout (120s) — memory/time
        // limit tetap dinaikkan agar render sedikit di bawah batas tetap lolos.
        set_time_limit(900);
        ini_set('memory_limit', '1G');

        $export = match ($type) {
            'presensi' => new LaporanPresensiExport($validated['start_date'], $validated['end_date'], $unitId, $validated['jenis_filter'] ?? null, $validated['tipe_filter'] ?? null, $validated['search'] ?? null, $validated['status_filter'] ?? null, $validated['jadwal_filter'] ?? null),
            'penggajian' => new LaporanPenggajianExport($validated['start_date'], $validated['end_date'], $unitId, $validated['jenis_filter'] ?? null, $validated['search'] ?? null),
            'lemburan' => new LaporanLemburanExport($validated['start_date'], $validated['end_date'], $unitId, $validated['jenis_filter'] ?? null, $validated['search'] ?? null, $validated['lembur_status'] ?? 'semua'),
            'rekap_mengajar' => new LaporanRekapMengajarExport($validated['start_date'], $validated['end_date'], $unitId, $validated['jenis_filter'] ?? null, $validated['search'] ?? null),
            'rekap_kehadiran' => new LaporanRekapKehadiranExport($validated['start_date'], $validated['end_date'], $unitId, $validated['jenis_filter'] ?? null, $validated['search'] ?? null, $validated['status_filter'] ?? null),
            'rekap_kehadiran_harian' => new LaporanRekapKehadiranHarianExport($validated['start_date'], $validated['end_date'], $unitId, $validated['jenis_filter'] ?? null, $validated['search'] ?? null, $validated['status_filter'] ?? null),
            'detail_harian' => new LaporanDetailHarianExport($validated['start_date'], $validated['end_date'], $unitId, $validated['jenis_filter'] ?? null, $validated['search'] ?? null),
            'mengajar_harian' => new LaporanMengajarHarianExport($validated['start_date'], $validated['end_date'], $unitId, $validated['jenis_filter'] ?? null, $validated['search'] ?? null),
        };

        // Hitung dulu, map belakangan — hindari build array besar sebelum guard.
        $total = $export->collection()->count();
        if ($total > self::MAX_ROWS) {
            throw new PdfTooLargeException(
                'Data terlalu banyak untuk PDF ('.$total.' baris, maksimal '.self::MAX_ROWS.'). Gunakan filter yang lebih spesifik atau export ke Excel.'
            );
        }

        $rows = $export->collection()->map(fn ($item) => $export->map($item))->all();
        $headings = $export->headings();

        $title = match ($type) {
            'presensi' => 'LAPORAN REKAPITULASI PRESENSI PEGAWAI',
            'penggajian' => 'LAPORAN REKAPITULASI PENGGAJIAN PEGAWAI',
            'lemburan' => 'LAPORAN LEMBUR PEGAWAI',
            'rekap_mengajar' => 'LAPORAN REKAPITULASI PRESENSI MENGAJAR',
            'rekap_kehadiran' => 'LAPORAN REKAPITULASI KEHADIRAN',
            'rekap_kehadiran_harian' => 'LAPORAN REKAPITULASI KEHADIRAN HARIAN',
            'detail_harian' => 'DETAIL PRESENSI HARIAN PEGAWAI',
            'mengajar_harian' => 'DETAIL MENGAJAR HARIAN PEGAWAI (MENGAJAR & PIKET)',
        };

        $filename = match ($type) {
            'presensi' => 'Laporan_Presensi',
            'penggajian' => 'Laporan_Rekap_Gaji',
            'lemburan' => 'Laporan_Lemburan',
            'rekap_mengajar' => 'Laporan_Rekap_Mengajar',
            'rekap_kehadiran' => 'Laporan_Rekap_Kehadiran',
            'rekap_kehadiran_harian' => 'Laporan_Rekap_Kehadiran_Harian',
            'detail_harian' => 'Laporan_Detail_Presensi_Harian',
            'mengajar_harian' => 'Laporan_Detail_Mengajar_Harian',
        };

        return $this->renderDocument($validated, $unitId, $headings, $rows, $title, $filename, $type);
    }

    public function generatePresensiPribadi(array $validated, Pegawai $pegawai, array $summary): array
    {
        set_time_limit(900);
        ini_set('memory_limit', '1G');

        $export = new LaporanPresensiExport(
            $validated['start_date'],
            $validated['end_date'],
            null,
            null,
            null,
            null,
            $validated['status'] ?? null,
            null,
            $pegawai->id,
        );

        $total = $export->collection()->count();
        if ($total > self::MAX_ROWS) {
            throw new PdfTooLargeException(
                'Data terlalu banyak untuk PDF ('.$total.' baris, maksimal '.self::MAX_ROWS.'). Gunakan rentang lebih pendek atau unduh Excel.'
            );
        }

        $pegawai->loadMissing('units');
        $unit = $pegawai->units->firstWhere('pivot.is_primary', true) ?? $pegawai->units->first();

        return $this->renderDocument(
            $validated,
            $unit?->id,
            $export->headings(),
            $export->collection()->map(fn ($item) => $export->map($item))->all(),
            'LAPORAN PRESENSI PRIBADI - '.strtoupper($pegawai->nama_lengkap),
            'Laporan_Presensi_Pribadi',
            'presensi_pribadi',
            $summary,
        );
    }

    public function generateAbsenSiswa(array $validated, ?int $unitId, array $headings, array $rows): array
    {
        if (count($rows) > self::MAX_ROWS) {
            throw new PdfTooLargeException(
                'Data terlalu banyak untuk PDF ('.count($rows).' baris, maksimal '.self::MAX_ROWS.'). Gunakan filter yang lebih spesifik atau export ke Excel.'
            );
        }

        return $this->renderDocument(
            $validated,
            $unitId,
            $headings,
            $rows,
            'LAPORAN ABSEN SISWA',
            'Laporan_Absen_Siswa',
            'absen_siswa',
        );
    }

    public function generateKeterlambatanSiswa(array $validated, ?int $unitId, array $headings, array $rows): array
    {
        if (count($rows) > self::MAX_ROWS) {
            throw new PdfTooLargeException(
                'Data terlalu banyak untuk PDF ('.count($rows).' baris, maksimal '.self::MAX_ROWS.'). Gunakan filter yang lebih spesifik atau export ke Excel.'
            );
        }

        return $this->renderDocument(
            $validated,
            $unitId,
            $headings,
            $rows,
            'LAPORAN KETERLAMBATAN SISWA',
            'Laporan_Keterlambatan_Siswa',
            'keterlambatan_siswa',
        );
    }

    private function renderDocument(array $validated, ?int $unitId, array $headings, array $rows, string $title, string $filename, string $type, ?array $summary = null): array
    {
        // Kop surat: unit terpilih (atau auto-resolved dari scope user) -> data unit itu;
        // semua unit (superadmin) -> unit induk "Yayasan"; fallback terakhir -> config env.
        $kopUnit = $unitId ? UnitSekolah::find($unitId) : null;
        if (! $kopUnit) {
            $kopUnit = UnitSekolah::where('nama', 'like', 'Yayasan%')->first();
        }
        $unitName = $kopUnit?->nama ?? 'Semua Unit Sekolah';

        $periodeStr = Carbon::parse($validated['start_date'])->format('d/m/Y')
            .' s/d '.Carbon::parse($validated['end_date'])->format('d/m/Y');

        $logoPath = $this->resolveUnitLogoPath($kopUnit) ?? $this->resolveYayasanLogoPath();
        $logoWidth = null;
        if ($logoPath && file_exists($logoPath)) {
            $sz = @getimagesize($logoPath);
            if ($sz) {
                $logoWidth = (int) round(64 * $sz[0] / $sz[1]);
            }
        }

        $kop = [
            'name' => $kopUnit?->nama ? strtoupper($kopUnit->nama) : config('yayasan.name'),
            'tagline' => config('yayasan.tagline'),
            'address' => $kopUnit?->alamat ?: config('yayasan.address'),
            'phone' => $kopUnit?->telepon ?: config('yayasan.phone'),
            'email' => config('yayasan.email'),
            'website' => $kopUnit?->web ?: config('yayasan.website'),
        ];

        $unitSlug = $this->resolveUnitSlug($unitId);

        $pdf = Pdf::loadView('exports.pdf-laporan', compact('headings', 'rows', 'title', 'periodeStr', 'unitName', 'logoPath', 'logoWidth', 'kop', 'type', 'summary'))
            ->setPaper('A4', 'landscape');

        return [
            'filename' => $filename.'_'.$unitSlug.'_'.$validated['start_date'].'_to_'.$validated['end_date'].'.pdf',
            'content' => $pdf->output(),
        ];
    }

    /**
     * Resolve logo unit ke path file lokal untuk DOMPDF. Null bila unit
     * tidak punya logo — caller fallback ke kop config.
     */
    public function resolveUnitLogoPath(?UnitSekolah $unit): ?string
    {
        if (! $unit?->logo) {
            return null;
        }

        $disk = config('filesystems.image_disk', 'public');
        $root = config("filesystems.disks.$disk.root");
        $path = rtrim($root, '/').'/'.ltrim($unit->logo, '/');

        return file_exists($path) ? $path : null;
    }

    /**
     * Resolve logo yayasan ke path file lokal untuk DOMPDF.
     */
    public function resolveYayasanLogoPath(): ?string
    {
        $rel = config('kcd.yayasan_logo');
        if (! $rel) {
            return null;
        }

        $public = public_path($rel);
        if (file_exists($public)) {
            return $public;
        }

        $disk = config('filesystems.image_disk', 'public');
        $root = config("filesystems.disks.$disk.root");
        $path = rtrim($root, '/').'/'.ltrim($rel, '/');

        return file_exists($path) ? $path : null;
    }

    public function resolveUnitSlug(?int $unitId): string
    {
        if (! $unitId) {
            return 'semua_unit';
        }

        $unit = UnitSekolah::find($unitId);

        return Str::slug($unit?->nama ?? 'unit_lain', '_');
    }
}
