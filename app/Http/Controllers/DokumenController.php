<?php

namespace App\Http\Controllers;

use App\Constants\DokumenKategori;
use App\Helpers\DownloadFilename;
use App\Models\Dokumen;
use App\Models\UnitSekolah;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DokumenController extends Controller
{
    private const FILE_MIMES = 'pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx';

    private const FILE_MIMETYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        $base = $this->visibleQuery($user);
        $today = now()->startOfDay();
        $expiringUntil = now()->addDays(30)->endOfDay();

        $stats = [
            'expired' => (clone $base)
                ->whereNotNull('tanggal_kedaluwarsa')
                ->whereDate('tanggal_kedaluwarsa', '<', $today)
                ->count(),
            'expiring' => (clone $base)
                ->whereNotNull('tanggal_kedaluwarsa')
                ->whereDate('tanggal_kedaluwarsa', '>=', $today)
                ->whereDate('tanggal_kedaluwarsa', '<=', $expiringUntil)
                ->count(),
        ];

        $query = (clone $base)->with('unitSekolah:id,nama,singkatan');

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function (Builder $query) use ($q) {
                $query->where('judul', 'like', "%{$q}%")
                    ->orWhere('nomor', 'like', "%{$q}%")
                    ->orWhere('penerbit', 'like', "%{$q}%");
            });
        }

        if (in_array($request->input('kategori'), array_keys(DokumenKategori::LABELS), true)) {
            $query->where('kategori', $request->input('kategori'));
        }

        if ($request->filled('unit_sekolah_id')) {
            $unit = (string) $request->input('unit_sekolah_id');
            if ($unit === 'yayasan') {
                $query->whereNull('unit_sekolah_id');
            } elseif (ctype_digit($unit)) {
                $query->where('unit_sekolah_id', (int) $unit);
            }
        }

        $this->applyStatusFilter($query, (string) $request->input('status'));

        $dokumens = $query
            ->orderByRaw('CASE WHEN tanggal_kedaluwarsa IS NULL THEN 1 ELSE 0 END')
            ->orderBy('tanggal_kedaluwarsa')
            ->orderBy('judul')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Dokumen $dokumen) => $this->serialize($dokumen, $user));

        return Inertia::render('Dokumen/Index', [
            'dokumens' => $dokumens,
            'units' => $this->unitOptions($user),
            'kategoris' => DokumenKategori::options(),
            'stats' => $stats,
            'filters' => [
                'q' => $request->input('q'),
                'kategori' => $request->input('kategori'),
                'unit_sekolah_id' => $request->input('unit_sekolah_id'),
                'status' => $request->input('status'),
            ],
            'canManageYayasan' => $user->can('view_all_units'),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $this->validatePayload($request, true);
        $unitId = $this->normalizeUnitId($validated['unit_sekolah_id'] ?? null);
        $this->authorizeManageUnit($user, $unitId);

        $file = $request->file('file');
        $path = $this->storeFile($file, $unitId);

        try {
            Dokumen::create([
                ...$this->payload($validated, $unitId),
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
            ]);
        } catch (\Throwable $e) {
            Storage::disk($this->disk())->delete($path);
            throw $e;
        }

        return redirect()->back()->with('message', 'Dokumen berhasil diunggah.');
    }

    public function update(Request $request, Dokumen $dokumen)
    {
        $user = $request->user();
        $this->authorizeManageDokumen($user, $dokumen);

        $validated = $this->validatePayload($request, false);
        $unitId = array_key_exists('unit_sekolah_id', $validated)
            ? $this->normalizeUnitId($validated['unit_sekolah_id'])
            : $dokumen->unit_sekolah_id;
        $this->authorizeManageUnit($user, $unitId);

        $oldPath = $dokumen->file_path;
        $newPath = null;
        $attributes = $this->payload($validated, $unitId);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $newPath = $this->storeFile($file, $unitId);
            $attributes['file_path'] = $newPath;
            $attributes['file_name'] = $file->getClientOriginalName();
        }

        try {
            $dokumen->update($attributes);
        } catch (\Throwable $e) {
            if ($newPath) {
                Storage::disk($this->disk())->delete($newPath);
            }
            throw $e;
        }

        if ($newPath && $oldPath) {
            Storage::disk($this->disk())->delete($oldPath);
        }

        return redirect()->back()->with('message', 'Dokumen berhasil diperbarui.');
    }

    public function download(Request $request, Dokumen $dokumen)
    {
        $this->authorizeViewDokumen($request->user(), $dokumen);

        $disk = Storage::disk($this->disk());
        abort_unless($disk->exists($dokumen->file_path), 404, 'File dokumen tidak ditemukan.');

        $extension = $dokumen->file_extension ?: 'bin';
        $name = DownloadFilename::safe($dokumen->judul, 'Dokumen').'.'.$extension;

        return $disk->download($dokumen->file_path, $name);
    }

    public function destroy(Request $request, Dokumen $dokumen)
    {
        $this->authorizeManageDokumen($request->user(), $dokumen);

        Storage::disk($this->disk())->delete($dokumen->file_path);
        $dokumen->delete();

        return redirect()->back()->with('message', 'Dokumen berhasil dihapus.');
    }

    private function validatePayload(Request $request, bool $fileRequired): array
    {
        $expiryRules = ['nullable', 'date'];
        if ($request->filled('tanggal_dokumen')) {
            $expiryRules[] = 'after_or_equal:tanggal_dokumen';
        }

        return $request->validate([
            'judul' => ['required', 'string', 'max:255'],
            'kategori' => ['required', Rule::in(array_keys(DokumenKategori::LABELS))],
            'unit_sekolah_id' => ['nullable', 'exists:unit_sekolah,id'],
            'nomor' => ['nullable', 'string', 'max:100'],
            'penerbit' => ['nullable', 'string', 'max:191'],
            'tanggal_dokumen' => ['nullable', 'date'],
            'tanggal_kedaluwarsa' => $expiryRules,
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'file' => [
                $fileRequired ? 'required' : 'nullable',
                'file',
                'mimes:'.self::FILE_MIMES,
                'mimetypes:'.implode(',', self::FILE_MIMETYPES),
                'max:10240',
            ],
        ]);
    }

    private function payload(array $validated, ?int $unitId): array
    {
        return [
            'unit_sekolah_id' => $unitId,
            'judul' => $validated['judul'],
            'kategori' => $validated['kategori'],
            'nomor' => $validated['nomor'] ?? null,
            'penerbit' => $validated['penerbit'] ?? null,
            'tanggal_dokumen' => $validated['tanggal_dokumen'] ?? null,
            'tanggal_kedaluwarsa' => $validated['tanggal_kedaluwarsa'] ?? null,
            'keterangan' => $validated['keterangan'] ?? null,
        ];
    }

    private function normalizeUnitId(mixed $value): ?int
    {
        return $value ? (int) $value : null;
    }

    private function storeFile($file, ?int $unitId): string
    {
        $folder = $unitId ? 'unit_'.$unitId : 'yayasan';
        $extension = $file->guessExtension() ?: 'bin';

        return $file->storeAs('dokumen/'.$folder, Str::uuid().'.'.$extension, $this->disk());
    }

    private function disk(): string
    {
        return config('filesystems.dokumen_disk', 'local');
    }

    private function visibleQuery(User $user): Builder
    {
        $query = Dokumen::query();

        if ($user->can('view_all_units')) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user) {
            $query->whereNull('unit_sekolah_id');
            if ($user->unit_sekolah_id) {
                $query->orWhere('unit_sekolah_id', $user->unit_sekolah_id);
            }
        });
    }

    private function applyStatusFilter(Builder $query, string $status): void
    {
        $today = now()->startOfDay();
        $until = now()->addDays(30)->endOfDay();

        if ($status === 'expired') {
            $query->whereNotNull('tanggal_kedaluwarsa')->whereDate('tanggal_kedaluwarsa', '<', $today);
        } elseif ($status === 'expiring') {
            $query->whereNotNull('tanggal_kedaluwarsa')
                ->whereDate('tanggal_kedaluwarsa', '>=', $today)
                ->whereDate('tanggal_kedaluwarsa', '<=', $until);
        } elseif ($status === 'none') {
            $query->whereNull('tanggal_kedaluwarsa');
        } elseif ($status === 'perlu_perhatian') {
            $query->whereNotNull('tanggal_kedaluwarsa')->whereDate('tanggal_kedaluwarsa', '<=', $until);
        }
    }

    private function serialize(Dokumen $dokumen, User $user): array
    {
        return [
            'id' => $dokumen->id,
            'judul' => $dokumen->judul,
            'kategori' => $dokumen->kategori,
            'kategori_label' => DokumenKategori::label($dokumen->kategori),
            'nomor' => $dokumen->nomor,
            'penerbit' => $dokumen->penerbit,
            'tanggal_dokumen' => $dokumen->tanggal_dokumen?->format('Y-m-d'),
            'tanggal_kedaluwarsa' => $dokumen->tanggal_kedaluwarsa?->format('Y-m-d'),
            'keterangan' => $dokumen->keterangan,
            'file_name' => $dokumen->file_name,
            'file_extension' => $dokumen->file_extension,
            'unit_sekolah_id' => $dokumen->unit_sekolah_id,
            'unit_sekolah' => $dokumen->unitSekolah?->only(['id', 'nama', 'singkatan']),
            'can_manage' => $this->canManage($user, $dokumen),
        ];
    }

    private function unitOptions(User $user): array
    {
        $query = UnitSekolah::query()
            ->orderBy('nama')
            ->select('id', 'nama', 'singkatan');

        if (! $user->can('view_all_units')) {
            $query->whereKey($user->unit_sekolah_id);
        }

        return $query->get()->toArray();
    }

    private function canManage(User $user, Dokumen $dokumen): bool
    {
        if (! $user->can('manage_dokumen')) {
            return false;
        }

        if ($user->can('view_all_units')) {
            return true;
        }

        return $dokumen->unit_sekolah_id !== null
            && (int) $dokumen->unit_sekolah_id === (int) $user->unit_sekolah_id;
    }

    private function authorizeManageUnit(User $user, ?int $unitId): void
    {
        abort_unless($user->can('manage_dokumen'), 403, 'Akses ditolak.');

        if ($user->can('view_all_units')) {
            return;
        }

        abort_unless(
            $unitId !== null && (int) $unitId === (int) $user->unit_sekolah_id,
            403,
            'Akses ditolak.'
        );
    }

    private function authorizeManageDokumen(User $user, Dokumen $dokumen): void
    {
        abort_unless($this->canManage($user, $dokumen), 403, 'Akses ditolak.');
    }

    private function authorizeViewDokumen(User $user, Dokumen $dokumen): void
    {
        abort_unless($user->can('view_dokumen'), 403, 'Akses ditolak.');

        if ($user->can('view_all_units')) {
            return;
        }

        abort_unless(
            $dokumen->unit_sekolah_id === null
                || (int) $dokumen->unit_sekolah_id === (int) $user->unit_sekolah_id,
            403,
            'Akses ditolak.'
        );
    }
}
