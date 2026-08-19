<?php

namespace App\Http\Controllers;

use App\Constants\PegawaiConstants;
use App\Exports\PegawaiExport;
use App\Exports\PegawaiTemplateExport;
use App\Http\Controllers\Concerns\ScopesPimpinan;
use App\Imports\PegawaiImport;
use App\Models\Jabatan;
use App\Models\KomponenGaji;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\UnitSekolah;
use App\Models\User;
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
    use ScopesPimpinan;

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
        $this->authorizePegawaiMutation();

        $user = auth()->user();
        $rules = [
            'file' => 'required|mimes:xlsx,xls,csv|max:5120',
        ];

        if ($user && ! $user->can('view_all_units')) {
            $rules['unit_sekolah_id'] = 'required|exists:unit_sekolah,id';
        }

        $request->validate($rules);
        $unitId = ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) ? $user->unit_sekolah_id : $request->unit_sekolah_id;

        // Kalau tidak ada unit sama sekali (modal kosong & kolom unit di template kosong),
        // tidak ada baris yang bisa disimpan — tolak lebih awal dengan pesan jelas.
        if (! $unitId && (! $user || ! $user->can('view_all_units'))) {
            throw ValidationException::withMessages(['unit_sekolah_id' => 'Pilih unit sekolah di form, atau isi kolom Unit Sekolah di template.']);
        }

        try {
            DB::transaction(function () use ($request, $unitId, $user) {
                $allowOverride = $user && $user->can('view_all_units');
                Excel::import(new PegawaiImport($unitId, $allowOverride), $request->file('file'));
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
     * Query pegawai dengan semua filter index (search, unit, mapel, jabatan, jenis).
     * Dipakai index() dan export() agar hasil export selalu sama dengan yang terlihat.
     */
    private function pegawaiQuery(Request $request, array $with = ['units:id,nama', 'jabatans:id,nama', 'mapels:id,nama'])
    {
        $request->validate([
            'unit_sekolah_id' => 'nullable|exists:unit_sekolah,id',
            'mata_pelajaran_id' => 'nullable|exists:mata_pelajaran,id',
            'jabatan_id' => 'nullable|exists:jabatan,id',
            'jenis_filter' => 'nullable|in:pendidik,kependidikan',
        ]);

        $query = Pegawai::with($with);

        $user = auth()->user();
        if ($user && $this->isPimpinanReadOnly($user)) {
            // Role pimpinan: HANYA bawahan langsung (aturan kontrak & pengawasan).
            $userPegawaiId = $user->pegawai?->id;
            if (! $userPegawaiId) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('atasan_langsung_id', $userPegawaiId);
            }
        } elseif ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
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

        return $query;
    }

    /**
     * Export data pegawai (backup/analisis) — mengikuti filter index saat ini.
     * NIK plaintext hanya untuk pemegang permission view_sensitive_data (superadmin);
     * selain itu NIK ter-mask (keamanan).
     */
    public function export(Request $request)
    {
        Gate::authorize('view_pegawai');

        $query = $this->pegawaiQuery($request, ['units:id,nama', 'jabatans:id,nama', 'user:id,email']);

        $withNik = (bool) (auth()->user()?->can('view_sensitive_data'));
        $pegawais = $query->get();

        if ($withNik) {
            Log::warning('Sensitive data export', [
                'user_id' => auth()->id(),
                'rows' => $pegawais->count(),
                'field' => 'nik',
            ]);
        }

        return Excel::download(
            new PegawaiExport($pegawais, $withNik),
            'data_pegawai_'.now()->format('Ymd_His').'.xlsx'
        );
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = $this->pegawaiQuery($request);
        $user = auth()->user();

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

        $isPimpinan = $this->isPimpinanReadOnly($user);

        // Pimpinan: filter opsi jabatan hanya jabatan yang dimiliki bawahan langsung.
        if ($isPimpinan) {
            $userPegawaiId = $user->pegawai?->id;
            $bawahanJabatanIds = $userPegawaiId
                ? Pegawai::where('atasan_langsung_id', $userPegawaiId)
                    ->where('status_aktif', 'aktif')
                    ->join('pegawai_unit', 'pegawai.id', '=', 'pegawai_unit.pegawai_id')
                    ->distinct()
                    ->pluck('pegawai_unit.jabatan_id')
                : collect();
            $jabatans = Jabatan::select('id', 'nama')->whereIn('id', $bawahanJabatanIds)->orderBy('nama')->get();
        } elseif ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            // Admin unit: jabatan & unit yang dipakai di unit ini saja
            $jabatans = Jabatan::select('id', 'nama')
                ->whereHas('pegawai', fn ($q) => $q->where('pegawai_unit.unit_sekolah_id', $user->unit_sekolah_id))
                ->orderBy('nama')->get();
        } else {
            $jabatans = Jabatan::select('id', 'nama')->orderBy('nama')->get();
        }

        $unitSekolahs = UnitSekolah::select('id', 'nama')->orderBy('nama')->get();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $unitSekolahs->where('id', $user->unit_sekolah_id);
        }
        $mataPelajarans = MataPelajaran::select('id', 'nama')->orderBy('nama')->get();

        return inertia('Pegawai/Index', [
            'pegawais' => $pegawais,
            'stats' => $stats,
            'filters' => $request->only(['search', 'unit_sekolah_id', 'mata_pelajaran_id', 'jabatan_id', 'jenis_filter']),
            'userRole' => $user->roles->first()?->name ?? 'pegawai',
            'userUnitId' => $user->unit_sekolah_id,
            'isPimpinan' => $isPimpinan,
            'unitSekolahs' => $unitSekolahs,
            'mataPelajarans' => $mataPelajarans,
            'jabatans' => $jabatans,
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $unitSekolahs = UnitSekolah::select('id', 'nama')->where('id', $user->unit_sekolah_id)->get();
            // Jabatan: hanya yg dipakai di unit ini
            $jabatans = Jabatan::select('id', 'nama')
                ->whereHas('pegawai', fn ($q) => $q->where('pegawai_unit.unit_sekolah_id', $user->unit_sekolah_id))
                ->orderBy('nama')->get();
        } else {
            $unitSekolahs = UnitSekolah::select('id', 'nama')->orderBy('nama')->get();
            $jabatans = Jabatan::select('id', 'nama')->orderBy('nama')->get();
        }

        return inertia('Pegawai/Create', [
            'unitSekolahs' => $unitSekolahs,
            'jabatans' => $jabatans,
            'statusKepegawaian' => PegawaiConstants::STATUS_KEPEGAWAIAN,
            'pendidikanTerakhir' => PegawaiConstants::PENDIDIKAN_TERAKHIR,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePegawaiMutation();

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
            'atasan_langsung_id' => 'nullable|integer|exists:pegawai,id',
        ]);

        // Admin unit: atasan wajib pegawai di unitnya sendiri (anti-bypass).
        $authUser = auth()->user();
        if ($authUser && $authUser->unit_sekolah_id && ! $authUser->can('view_all_units') && ! empty($validated['atasan_langsung_id'])) {
            $atasan = Pegawai::find($validated['atasan_langsung_id']);
            if (! $atasan || ! $atasan->units->pluck('id')->contains($authUser->unit_sekolah_id)) {
                throw ValidationException::withMessages(['atasan_langsung_id' => 'Atasan harus pegawai di unit Anda.']);
            }
        }

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
            'atasan_langsung_id' => $validated['atasan_langsung_id'] ?? null,
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
            'createdBy' => fn ($q) => $q->select('id', 'name'),
            'units' => fn ($q) => $q->select('unit_sekolah.id', 'unit_sekolah.nama'),
            'jabatans' => fn ($q) => $q->select('jabatan.id', 'jabatan.nama'),
            'mapels' => fn ($q) => $q->select('mata_pelajaran.id', 'mata_pelajaran.nama'),
            'dokumen' => fn ($q) => $q->select('id', 'pegawai_id', 'nama_dokumen', 'jenis', 'created_at'),
            'riwayat' => fn ($q) => $q->latest('id')->limit(30),
            'atasanLangsung' => fn ($q) => $q->select('id', 'nama_lengkap', 'foto'),
        ])->findOrFail($id);

        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $pegawai->units->pluck('id')->contains($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }
        if ($user && $this->isPimpinanReadOnly($user) && $pegawai->atasan_langsung_id !== $user->pegawai?->id) {
            abort(403, 'Akses ditolak. Pimpinan hanya dapat melihat bawahannya.');
        }

        // Show.jsx menampilkan sisa_cuti — eager-load + append eksplisit (P2).
        // Select kolom yang dipakai accessor saja (bukan seluruh baris izin).
        $pegawai->loadCutiInfo(fn ($q) => $q->select(['id', 'pegawai_id', 'jenis_izin', 'status', 'tanggal_mulai', 'tanggal_selesai']));

        // Visibilitas kontrak: superadmin, atasan langsung, atau dirinya sendiri.
        // (aturan: "yang tahu kontrak hanya atasan, superadmin, dan dirinya".)
        $userPegawaiId = $user?->pegawai?->id;
        $canViewKontrak = $user
            && ($user->can('view_all_units') || $user->can('manage_users')
                || $pegawai->id === $userPegawaiId
                || $pegawai->atasan_langsung_id === $userPegawaiId);

        return inertia('Pegawai/Show', [
            'pegawai' => $pegawai,
            'canViewKontrak' => (bool) $canViewKontrak,
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
            $unitSekolahs = UnitSekolah::select('id', 'nama')->where('id', $user->unit_sekolah_id)->get();
            $jabatans = Jabatan::select('id', 'nama')
                ->whereHas('pegawai', fn ($q) => $q->where('pegawai_unit.unit_sekolah_id', $user->unit_sekolah_id))
                ->orderBy('nama')->get();
        } else {
            $unitSekolahs = UnitSekolah::select('id', 'nama')->orderBy('nama')->get();
            $jabatans = Jabatan::select('id', 'nama')->orderBy('nama')->get();
        }
        $mapels = MataPelajaran::select('id', 'nama')->orderBy('nama')->get();

        // Kandidat atasan langsung: superadmin boleh semua, admin unit hanya
        // pegawai di unitnya sendiri (jangan termasuk diri sendiri).
        $atasanQuery = Pegawai::query()
            ->with(['units' => fn ($q) => $q->withPivot('jabatan_id', 'is_primary')])
            ->where('status_aktif', 'aktif');
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $atasanQuery->whereHas('units', fn ($q) => $q->where('unit_sekolah.id', $user->unit_sekolah_id));
        }
        $atasanCandidates = $atasanQuery->where('id', '!=', $pegawai->id)->get()
            ->map(function (Pegawai $p) use ($jabatans) {
                // N+1 fix: use pre-fetched $jabatans instead of jabatanPrimer() which calls DB per row.
                $primaryUnit = $p->units->first(fn ($u) => ! empty($u->pivot->is_primary)) ?? $p->units->first();
                $jabatanId = $primaryUnit?->pivot?->jabatan_id;
                $jabatanNama = $jabatanId ? ($jabatans->firstWhere('id', $jabatanId)?->nama ?? '') : '';

                return [
                    'id' => $p->id,
                    'nama_lengkap' => $p->nama_lengkap,
                    'jabatan' => $jabatanNama,
                    'unit' => $primaryUnit?->nama ?? '',
                ];
            })->values();

        // Expose plaintext NIK hanya untuk user dengan permission view_sensitive_data.
        // Default model $hidden = ['nik'] agar tidak bocor via Index/Show. Edit butuh
        // plaintext agar field tidak kosong dan user tidak dipaksa retype.
        if ($user && $user->can('view_sensitive_data')) {
            $pegawai->nik_plain = $pegawai->getNikPlaintext();
        }

        return inertia('Pegawai/Edit', [
            'pegawai' => $pegawai,
            'unitSekolahs' => $unitSekolahs,
            'jabatans' => $jabatans,
            'mapels' => $mapels,
            'atasanCandidates' => $atasanCandidates,
            'statusKepegawaian' => PegawaiConstants::STATUS_KEPEGAWAIAN,
            'pendidikanTerakhir' => PegawaiConstants::PENDIDIKAN_TERAKHIR,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $this->authorizePegawaiMutation();

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
            'atasan_langsung_id' => 'nullable|integer|exists:pegawai,id',
        ]);

        // Admin unit: atasan wajib pegawai di unitnya sendiri (anti-bypass).
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! empty($validated['atasan_langsung_id'])) {
            $atasan = Pegawai::find($validated['atasan_langsung_id']);
            if (! $atasan || ! $atasan->units->pluck('id')->contains($user->unit_sekolah_id)) {
                throw ValidationException::withMessages(['atasan_langsung_id' => 'Atasan harus pegawai di unit Anda.']);
            }
        }

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
                    'username' => ! empty($validated['nip'] ?? null) ? $validated['nip'] : $validated['nik'],
                ]);
            }
        }

        return redirect()->route('pegawai.show', $pegawai->id)->with('message', 'Data Pegawai berhasil diperbarui.');
    }

    /**
     * Blokir mutasi pegawai untuk role pimpinan (read-only).
     */
    private function authorizePegawaiMutation(): void
    {
        $user = auth()->user();
        if ($this->isPimpinanReadOnly($user)) {
            abort(403, 'Role pimpinan hanya dapat melihat data (read-only).');
        }
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
        $this->authorizePegawaiMutation();

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

        return response()->json(['nik' => $pegawai->getNikPlaintext()]);
    }

    /**
     * Tampilkan username plaintext (akun mobile login).
     * Gate view_sensitive_data — pola sama dengan nikAsli.
     */
    public function usernameAsli(Pegawai $pegawai)
    {
        Gate::authorize('view_sensitive_data');

        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && ! $pegawai->units->pluck('id')->contains($user->unit_sekolah_id)) {
            abort(403, 'Akses ditolak.');
        }

        Log::warning('Sensitive data access', [
            'user_id' => $user?->id,
            'pegawai_id' => $pegawai->id,
            'field' => 'username',
        ]);

        $plaintext = $pegawai->user?->getUsernamePlaintext();

        return response()->json(['username' => $plaintext]);
    }
}
