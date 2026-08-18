<?php

namespace App\Http\Controllers;

use App\Constants\PegawaiConstants;
use App\Exports\PegawaiTemplateExport;
use App\Imports\PegawaiImport;
use App\Models\Jabatan;
use App\Models\KomponenGaji;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class PegawaiController extends Controller
{
    public function downloadTemplate()
    {
        $response = Excel::download(new PegawaiTemplateExport, 'template_pegawai.xlsx');

        // Mencegah browser memakai file template lama dari cache — dropdown baru
        // tidak akan terlihat kalau yang terunduh masih versi lama.
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $response;
    }

    public function import(Request $request)
    {
        $user = auth()->user();
        $rules = [
            'file' => 'required|mimes:xlsx,xls,csv|max:5120',
        ];

        if ($user && ! $user->can('view_all_units')) {
            $rules['unit_sekolah_id'] = 'required|exists:unit_sekolah,id';
        }

        $request->validate($rules);
        $unitId = ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) ? $user->unit_sekolah_id : $request->unit_sekolah_id;

        try {
            DB::transaction(function () use ($request, $unitId) {
                Excel::import(new PegawaiImport($unitId), $request->file('file'));
            });

            return back()->with('message', 'Data pegawai berhasil diimport.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->with('error', 'Gagal import, periksa kembali file Anda. Terjadi kesalahan validasi baris.');
        } catch (\Exception $e) {
            Log::error('Import error: '.$e->getMessage());

            return back()->with('error', 'Terjadi kesalahan sistem. Silakan coba lagi nanti atau hubungi administrator.');
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $request->validate([
            'unit_sekolah_id' => 'nullable|exists:unit_sekolah,id',
            'mata_pelajaran_id' => 'nullable|exists:mata_pelajaran,id',
            'jabatan_id' => 'nullable|exists:jabatan,id',
            'jenis_filter' => 'nullable|in:pendidik,kependidikan',
        ]);

        $query = Pegawai::with(['units:id,nama', 'jabatans:id,nama', 'mapels:id,nama']);

        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $query->whereHas('units', function ($q) use ($user) {
                $q->where('unit_sekolah.id', $user->unit_sekolah_id);
            });
        } elseif ($request->filled('unit_sekolah_id')) {
            $query->whereHas('units', function ($q) use ($request) {
                $q->where('unit_sekolah.id', $request->unit_sekolah_id);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', '%'.$search.'%')
                    // ?? '' utk hindari where('nik_hash', null) yang jadi IS NULL di Laravel
                    ->orWhere('nik_hash', Pegawai::nikHash($search) ?? '');
            });
        }

        if ($request->filled('mata_pelajaran_id')) {
            $query->whereHas('mapels', function ($q) use ($request) {
                $q->where('mata_pelajaran.id', $request->mata_pelajaran_id);
            });
        }

        if ($request->filled('jabatan_id')) {
            $query->whereHas('jabatans', function ($q) use ($request) {
                $q->where('jabatan.id', $request->jabatan_id);
            });
        }

        // Filter jenis pegawai (Dapodik-style): pendidik = punya jabatan guru,
        // kependidikan = tidak punya jabatan guru sama sekali
        if ($request->jenis_filter === 'pendidik') {
            $query->whereHas('jabatans', fn ($q) => $q->where('is_guru', true));
        } elseif ($request->jenis_filter === 'kependidikan') {
            $query->whereDoesntHave('jabatans', fn ($q) => $q->where('is_guru', true));
        }

        // Stats ringkasan: 1 query agregat + 1 count ringan (kontrak berakhir)
        $agg = (clone $query)->selectRaw("COUNT(*) as total, SUM(CASE WHEN status_aktif = 'aktif' THEN 1 ELSE 0 END) as aktif")->first();
        $kontrakEnd = now()->addDays(30)->format('Y-m-d');
        $kontrakBerakhir = (clone $query)
            ->where('status_kepegawaian', 'kontrak')
            ->whereNotNull('tanggal_akhir_kontrak')
            ->where('tanggal_akhir_kontrak', '<=', $kontrakEnd)
            ->count();

        $stats = [
            'total' => (int) ($agg->total ?? 0),
            'aktif' => (int) ($agg->aktif ?? 0),
            'nonaktif' => (int) ($agg->total ?? 0) - (int) ($agg->aktif ?? 0),
            'kontrak_berakhir' => (int) $kontrakBerakhir,
        ];

        $pegawais = $query->paginate(10)->withQueryString();

        $unitSekolahs = UnitSekolah::all();
        $mataPelajarans = MataPelajaran::all();
        $jabatans = Jabatan::orderBy('nama')->get();

        return inertia('Pegawai/Index', [
            'pegawais' => $pegawais,
            'stats' => $stats,
            'filters' => $request->only(['search', 'unit_sekolah_id', 'mata_pelajaran_id', 'jabatan_id', 'jenis_filter']),
            'userRole' => $user->roles->first()?->name ?? 'pegawai',
            'userUnitId' => $user->unit_sekolah_id,
            'unitSekolahs' => $unitSekolahs,
            'mataPelajarans' => $mataPelajarans,
            'jabatans' => $jabatans,
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $unitSekolahs = UnitSekolah::where('id', $user->unit_sekolah_id)->get();
        } else {
            $unitSekolahs = UnitSekolah::all();
        }
        $jabatans = Jabatan::all();

        return inertia('Pegawai/Create', [
            'unitSekolahs' => $unitSekolahs,
            'jabatans' => $jabatans,
            'statusKepegawaian' => PegawaiConstants::STATUS_KEPEGAWAIAN,
            'pendidikanTerakhir' => PegawaiConstants::PENDIDIKAN_TERAKHIR,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $request->merge(['unit_sekolah_id' => $user->unit_sekolah_id]);
        }

        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'nullable|string|min:8',
            'no_hp' => 'required|string|max:20',
            'unit_sekolah_id' => 'required|exists:unit_sekolah,id',
            'jabatan_id' => 'required|exists:jabatan,id',
            'status_kepegawaian' => 'required|in:'.implode(',', PegawaiConstants::STATUS_KEPEGAWAIAN),
        ]);

        $isAutoGenerated = ! $request->filled('password');
        $password = $isAutoGenerated ? \Str::random(12) : $request->password;

        $user = User::create([
            'name' => $request->nama_lengkap,
            'email' => $request->email,
            'username' => $request->email,
            'password' => Hash::make($password),
            'force_password_change' => $isAutoGenerated,
        ]);

        $user->syncRoles('pegawai');

        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nama_lengkap' => $request->nama_lengkap,
            'no_hp' => $request->no_hp,
            'status_kepegawaian' => $request->status_kepegawaian,
        ]);
        $pegawai->units()->attach($request->unit_sekolah_id, ['jabatan_id' => $request->jabatan_id, 'is_primary' => true]);

        $flash = $isAutoGenerated
            ? ['message' => 'Data Pegawai berhasil ditambahkan. Password: '.$password, 'generated_password' => $password]
            : ['message' => 'Data Pegawai berhasil ditambahkan.'];

        return redirect()->route('pegawai.index')->with($flash);
    }

    public function show(string $id)
    {
        // Eager-load dipersempit (kolom yang dipakai Show.jsx saja) + riwayat
        // dibatasi 30 terbaru agar payload ringan dan timeline tidak membengkak
        // seiring bertambahnya perubahan data.
        $pegawai = Pegawai::with([
            'user' => fn ($q) => $q->select('id', 'email', 'username'),
            'units',
            'jabatans',
            'mapels',
            'dokumen',
            'riwayat' => fn ($q) => $q->latest('id')->limit(30),
            'atasanLangsung' => fn ($q) => $q->select('id', 'nama_lengkap', 'foto'),
        ])->findOrFail($id);

        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $pegawai->units->pluck('id')->contains($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        // Show.jsx menampilkan sisa_cuti — eager-load + append eksplisit (P2).
        // Select kolom yang dipakai accessor saja (bukan seluruh baris izin).
        $pegawai->loadCutiInfo(fn ($q) => $q->select(['id', 'pegawai_id', 'jenis_izin', 'status', 'tanggal_mulai', 'tanggal_selesai']));

        return inertia('Pegawai/Show', [
            'pegawai' => $pegawai,
        ]);
    }

    public function edit(string $id)
    {
        $pegawai = Pegawai::with(['user', 'units', 'jabatans', 'mapels'])->findOrFail($id);

        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $pegawai->units->pluck('id')->contains($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $unitSekolahs = UnitSekolah::where('id', $user->unit_sekolah_id)->get();
        } else {
            $unitSekolahs = UnitSekolah::all();
        }
        $jabatans = Jabatan::all();
        $mapels = MataPelajaran::orderBy('nama')->get();

        // Opsi 2: expose decrypted NIK hanya untuk user dengan permission view_sensitive_data
        // Default model $hidden = ['nik'] agar tidak bocor via Index/Show. Edit butuh plaintext
        // agar field tidak kosong dan user tidak dipaksa retype.
        if ($user && $user->can('view_sensitive_data')) {
            // Expose plaintext NIK as a separate field 'nik_plain' (no cast) so the
            // serializer returns it as-is. Cast pada 'nik' tetap menghasilkan
            // ciphertext/auto-decrypt di sisi PHP; kita tidak pakai 'nik' di view.
            $raw = $pegawai->getRawOriginal('nik');
            try {
                $plain = \Crypt::decryptString($raw);
                // ponytail: legacy double-encrypt from earlier patch. Detect ciphertext
                // by JWT-shaped prefix and peel one extra layer. Read-only, never writes
                // back, so DB stays as-is until a separate normalize pass runs.
                if (is_string($plain) && preg_match('/^eyJ[A-Za-z0-9+\/=]+$/', $plain)) {
                    try {
                        $plain = \Crypt::decryptString($plain);
                    } catch (\Throwable) {
                        $plain = null;
                    }
                }
                $pegawai->nik_plain = $plain;
            } catch (DecryptException $e) {
                $pegawai->nik_plain = null;
            }
        }

        return inertia('Pegawai/Edit', [
            'pegawai' => $pegawai,
            'unitSekolahs' => $unitSekolahs,
            'jabatans' => $jabatans,
            'mapels' => $mapels,
            'statusKepegawaian' => PegawaiConstants::STATUS_KEPEGAWAIAN,
            'pendidikanTerakhir' => PegawaiConstants::PENDIDIKAN_TERAKHIR,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $pegawai = Pegawai::findOrFail($id);

        if (! $pegawai->user_id) {
            throw ValidationException::withMessages(['email' => 'Pegawai belum memiliki akun login. Buat akun melalui menu Manajemen User.']);
        }

        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $pegawai->units->pluck('id')->contains($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        $canViewSensitive = $user && $user->can('view_sensitive_data');

        $validated = $request->validate([
            'nik' => $canViewSensitive
                ? 'required|string|size:16|unique:pegawai,nik,'.$pegawai->id
                : 'nullable|string|size:16|unique:pegawai,nik,'.$pegawai->id,
            'nip' => 'nullable|string|max:50|unique:pegawai,nip,'.$pegawai->id,
            'nama_lengkap' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($pegawai->user_id)],
            'tempat_lahir' => 'required|string|max:255',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'required|in:L,P',
            'agama' => 'required|string|max:255',
            'status_pernikahan' => 'required|string|max:255',
            'alamat_ktp' => 'required|string',
            'no_hp' => 'required|string|max:20',
            'status_kepegawaian' => 'required|in:'.implode(',', PegawaiConstants::STATUS_KEPEGAWAIAN),
            'jatah_cuti_tahunan' => 'nullable|integer|min:0',
            'wajib_kantor' => 'boolean',
            'status_aktif' => 'required|in:aktif,cuti,nonaktif,resign',
            'tanggal_mulai_kerja' => 'required|date',
            'pendidikan_terakhir' => 'required|string|max:255',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'units' => 'nullable|array',
            'units.*.unit_sekolah_id' => 'nullable|exists:unit_sekolah,id',
            'units.*.jabatan_id' => 'nullable|exists:jabatan,id',
            'units.*.is_primary' => 'nullable|boolean',
            'mapels' => 'nullable|array',
            'mapels.*.mata_pelajaran_id' => 'nullable|exists:mata_pelajaran,id',
            'mapels.*.unit_sekolah_id' => 'nullable|exists:unit_sekolah,id',
        ]);

        if (! $canViewSensitive || empty($validated['nik'])) {
            $validated['nik'] = $pegawai->nik;
        }

        $dataToUpdate = collect($validated)->except(['email', 'foto'])->toArray();

        $fotoLama = $pegawai->foto;
        $fotoBaru = null;

        if ($request->hasFile('foto')) {
            // Store file baru DULU, baru hapus yang lama setelah update sukses —
            // kalau update gagal, foto lama tidak hilang & tidak ada file menggantung.
            $path = $request->file('foto')->store('pegawai_fotos', 'presensi');
            $fotoBaru = $path;
            $dataToUpdate['foto'] = $path;
        } elseif ($request->boolean('hapus_foto')) {
            $dataToUpdate['foto'] = null;
        }

        try {
            $pegawai->update($dataToUpdate);
        } catch (\Throwable $e) {
            if ($fotoBaru) {
                Storage::disk('presensi')->delete($fotoBaru);
            }
            throw $e;
        }

        if ($fotoLama && ($fotoBaru || $request->boolean('hapus_foto'))) {
            Storage::disk('presensi')->delete($fotoLama);
        }

        // Sinkronisasi penugasan unit + jabatan
        $syncUnits = [];
        foreach ($request->input('units', []) as $u) {
            if (empty($u['unit_sekolah_id']) || empty($u['jabatan_id'])) {
                continue;
            }
            $syncUnits[$u['unit_sekolah_id']] = [
                'jabatan_id' => $u['jabatan_id'],
                'is_primary' => ! empty($u['is_primary']),
            ];
        }
        $pegawai->units()->sync($syncUnits);

        // Sinkronisasi mata pelajaran (guru)
        $syncMapels = [];
        foreach ($request->input('mapels', []) as $m) {
            if (empty($m['mata_pelajaran_id']) || empty($m['unit_sekolah_id'])) {
                continue;
            }
            $syncMapels[$m['mata_pelajaran_id']] = [
                'unit_sekolah_id' => $m['unit_sekolah_id'],
            ];
        }
        $pegawai->mapels()->sync($syncMapels);

        if ($pegawai->user_id) {
            $userAcc = User::find($pegawai->user_id);
            if ($userAcc) {
                $userAcc->update([
                    'name' => $validated['nama_lengkap'],
                    'email' => $validated['email'],
                    'username' => $validated['nip'] ?: $validated['nik'],
                ]);
            }
        }

        return redirect()->route('pegawai.show', $pegawai->id)->with('message', 'Data Pegawai berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id)
    {
        $pegawai = Pegawai::findOrFail($id);

        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $pegawai->units->pluck('id')->contains($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate([
            'alasan_nonaktif' => 'required|string|max:255',
        ]);

        if ($pegawai->foto) {
            Storage::disk('presensi')->delete($pegawai->foto);
        }

        $pegawai->update([
            'status_aktif' => 'nonaktif',
            'alasan_nonaktif' => $request->alasan_nonaktif,
        ]);

        $pegawai->delete();

        return redirect()->route('pegawai.index')->with('message', 'Data Pegawai berhasil dinonaktifkan.');
    }

    /**
     * Menampilkan profil keuangan khusus (Tab Keuangan)
     */
    public function keuangan($id)
    {
        $pegawai = Pegawai::with('komponenGaji')->findOrFail($id);

        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $pegawai->units->pluck('id')->contains($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        $komponens = KomponenGaji::where('is_active', true)->get();

        return inertia('Pegawai/Keuangan', [
            'pegawai' => $pegawai,
            'komponens' => $komponens,
        ]);
    }

    /**
     * Menyimpan profil keuangan khusus
     */
    public function updateKeuangan(Request $request, $id)
    {
        $pegawai = Pegawai::findOrFail($id);

        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $pegawai->units->pluck('id')->contains($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate([
            'komponens' => 'nullable|array',
        ]);

        $syncData = [];
        if ($request->has('komponens')) {
            foreach ($request->komponens as $komponenId => $nominal) {
                if ($nominal !== null && $nominal !== '') {
                    $syncData[$komponenId] = ['nominal' => preg_replace('/[^0-9]/', '', $nominal)];
                }
            }
        }

        $pegawai->komponenGaji()->sync($syncData);

        return redirect()->back()->with('message', 'Profil Keuangan Pegawai berhasil diperbarui.');
    }

    /**
     * Tampilkan NIK plaintext (khusus HR/admin).
     * Gate `view_sensitive_data` ensures only role dengan permission ini yang bisa akses.
     * ponytail: pakai Log::warning untuk audit trail sederhana,
     * upgrade ke dedicated AuditLog model kalau compliance butuh.
     */
    public function nikAsli(Pegawai $pegawai)
    {
        Gate::authorize('view_sensitive_data');

        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $pegawai->units->pluck('id')->contains($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        Log::warning('Sensitive data access', [
            'user_id' => $user?->id,
            'pegawai_id' => $pegawai->id,
            'field' => 'nik',
        ]);

        return response()->json(['nik' => $pegawai->nik]);
    }
}
