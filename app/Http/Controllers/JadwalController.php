<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesPimpinan;
use App\Models\Jadwal;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PegawaiMapel;
use App\Models\Presensi;
use App\Models\UnitSekolah;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class JadwalController extends Controller
{
    use ScopesPimpinan;

    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user && ($user->can('view_jadwal') || $user->can('manage_jadwal'));
        $search = trim((string) $request->input('search', ''));

        $query = Jadwal::with(['pegawai:id,nama_lengkap', 'unitSekolah:id,nama,singkatan', 'pegawaiMapel.mataPelajaran:id,nama']);

        if ($this->isPimpinanReadOnly($user)) {
            // Pimpinan (kepsek/kepala TU/ketua yayasan): HANYA jadwal bawahan langsung.
            $this->scopePimpinanBawahan($query, $user);
        } elseif (! $isAdmin) {
            $pegawai = Pegawai::where('user_id', auth()->id())->first();
            if ($pegawai) {
                $query->where('pegawai_id', $pegawai->id);
            } else {
                $query->where('id', -1);
            }
        } elseif ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $query->where('unit_sekolah_id', $user->unit_sekolah_id);
        } elseif ($request->filled('unit_sekolah_id')) {
            $query->where('unit_sekolah_id', $request->unit_sekolah_id);
        }

        if ($request->filled('kelas_label')) {
            $query->where('kelas_label', $request->kelas_label);
        }

        if ($search !== '') {
            $query->whereHas('pegawai', fn ($q) => $q->where('nama_lengkap', 'like', "%{$search}%"));
        }

        // Filter jenis pegawai (Dapodik-style). Default KOSONG = pendidik
        // (jadwal mengajar = papan guru; TU tak punya jadwal). 'semua' = tampilkan semua.
        $jenis = $request->input('jenis_filter');
        if ($jenis === '' || $jenis === null) {
            $jenis = 'pendidik';
        }
        if ($jenis === 'pendidik') {
            $query->whereHas('pegawai.jabatans', fn ($q) => $q->where('is_guru', true));
        } elseif ($jenis === 'kependidikan') {
            $query->whereHas('pegawai', fn ($q) => $q->whereDoesntHave('jabatans', fn ($q2) => $q2->where('is_guru', true)));
        }

        $jadwals = $query->orderByRaw("CASE hari WHEN 'Senin' THEN 1 WHEN 'Selasa' THEN 2 WHEN 'Rabu' THEN 3 WHEN 'Kamis' THEN 4 WHEN 'Jumat' THEN 5 WHEN 'Sabtu' THEN 6 WHEN 'Minggu' THEN 7 END")
            ->orderBy('jam_mulai')
            ->get();

        $kelasLabels = $jadwals->pluck('kelas_label')->filter()->unique()->sort()->values();

        // Scope unit dropdown — admin_unit/pimpinan hanya lihat unit sendiri
        $unitsQuery = UnitSekolah::query()->select(['id', 'nama', 'singkatan']);
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $unitsQuery->where('id', $user->unit_sekolah_id);
        }
        $units = $unitsQuery->get();
        $mapel = MataPelajaran::all(['id', 'nama']);

        // Get Pegawai for Matrix Rows — HANYA kolom yang dibutuhkan frontend
        $pegawaiQuery = Pegawai::with(['units:id,singkatan'])
            ->select(['id', 'nama_lengkap'])
            ->where('status_aktif', 'aktif');

        if (! $isAdmin) {
            $pegawai = Pegawai::where('user_id', auth()->id())->first();
            if ($pegawai) {
                $pegawaiQuery->where('id', $pegawai->id);
            } else {
                $pegawaiQuery->where('id', -1);
            }
        } elseif ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $pegawaiQuery->whereHas('units', function ($q) use ($user) {
                $q->where('unit_sekolah.id', $user->unit_sekolah_id);
            });
        } elseif ($request->filled('unit_sekolah_id')) {
            $pegawaiQuery->whereHas('units', function ($q) use ($request) {
                $q->where('unit_sekolah.id', $request->unit_sekolah_id);
            });
        }

        if ($search !== '') {
            $pegawaiQuery->where('nama_lengkap', 'like', "%{$search}%");
        }

        // Filter jenis pegawai (Dapodik-style) untuk matrix rows. Default = pendidik.
        $jenisPeg = $request->input('jenis_filter');
        if ($jenisPeg === '' || $jenisPeg === null) {
            $jenisPeg = 'pendidik';
        }
        if ($jenisPeg === 'pendidik') {
            $pegawaiQuery->whereHas('jabatans', fn ($q) => $q->where('is_guru', true));
        } elseif ($jenisPeg === 'kependidikan') {
            $pegawaiQuery->whereDoesntHave('jabatans', fn ($q) => $q->where('is_guru', true));
        }

        $pegawais = $pegawaiQuery->orderBy('nama_lengkap')->get();

        // Ringkasan statistik: 1 pass agregasi PHP — TANPA query tambahan
        $totalMenitMengajar = 0;
        $totalMengajar = 0;
        foreach ($jadwals as $j) {
            if ($j->jenis_jadwal !== 'mengajar') {
                continue;
            }
            $totalMengajar++;
            [$h1, $m1] = array_pad(explode(':', (string) $j->jam_mulai), 2, 0);
            [$h2, $m2] = array_pad(explode(':', (string) $j->jam_selesai), 2, 0);
            $totalMenitMengajar += max(0, (int) $h2 * 60 + (int) $m2 - ((int) $h1 * 60 + (int) $m1));
        }

        $stats = [
            'total_jadwal' => $jadwals->count(),
            'total_mengajar' => $totalMengajar,
            'total_jam_menit' => $totalMenitMengajar,
            'total_pegawai' => $pegawais->count(),
            'total_kelas' => $kelasLabels->count(),
        ];

        // Presensi mengajar HARI INI — dipakai badge status live di matriks jadwal
        // (Mengajar / Selesai), di-polling 60 detik oleh frontend.
        // Frontend hanya membaca jadwal_id + jam_masuk — tanpa eager-load ekstra.
        $presensiHariIni = Presensi::where('tanggal', now()->toDateString())
            ->whereNotNull('jadwal_id')
            ->select(['pegawai_id', 'jadwal_id', 'jam_masuk', 'jam_keluar', 'status']);
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $presensiHariIni->where('unit_sekolah_id', $user->unit_sekolah_id);
        } elseif ($request->filled('unit_sekolah_id')) {
            $presensiHariIni->where('unit_sekolah_id', $request->unit_sekolah_id);
        }

        return inertia('Jadwal/Index', [
            'jadwals' => $jadwals,
            'pegawais' => $pegawais,
            'units' => $units,
            'mapel' => $mapel,
            'kelasLabels' => $kelasLabels,
            'stats' => $stats,
            'presensiHariIni' => $presensiHariIni->get()->toArray(),
            'filters' => $request->only(['unit_sekolah_id', 'kelas_label', 'search', 'jenis_filter']),
            'canMutateJadwal' => (bool) $user?->can('manage_jadwal'),
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        if (! $user || ! $user->can('manage_jadwal')) {
            abort(403);
        }

        $units = UnitSekolah::query()->select(['id', 'nama', 'singkatan', 'durasi_jp', 'jam_masuk_kantor', 'jam_pulang_kantor']);
        $pegawais = Pegawai::query()->where('status_aktif', 'aktif')->select(['id', 'nama_lengkap']);

        if ($user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $units->where('id', $user->unit_sekolah_id);
            $pegawais->whereHas('units', fn ($q) => $q->where('unit_sekolah.id', $user->unit_sekolah_id));
        }

        return inertia('Jadwal/Create', [
            'pegawais' => $pegawais->orderBy('nama_lengkap')->get(),
            'units' => $units->get(),
            'mapel' => MataPelajaran::all(['id', 'nama']),
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if (! $user || ! $user->can('manage_jadwal')) {
            abort(403);
        }

        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $request->merge(['unit_sekolah_id' => $user->unit_sekolah_id]);
        }

        $validated = $request->validate([
            'pegawai_id' => 'required|exists:pegawai,id',
            'unit_sekolah_id' => 'required|exists:unit_sekolah,id',
            'kelas_label' => 'nullable|string|max:255',
            // Mapel WAJIB untuk jadwal mengajar, opsional untuk jenis lain (piket/ekskul/shift).
            'mata_pelajaran_id' => 'nullable|required_if:jenis_jadwal,mengajar|exists:mata_pelajaran,id',
            'hari' => 'required|array|min:1',
            'hari.*' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu|distinct',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'jenis_jadwal' => 'required|in:mengajar,piket,ekskul,shift_satpam,shift_kebersihan,lainnya',
            'tahun_ajaran' => 'required|string|max:10',
            'semester' => 'required|integer|in:1,2',
        ]);

        // Security: user yang ter-scope unit tidak boleh membuat jadwal untuk pegawai unit lain.
        if ($user->unit_sekolah_id && ! $user->can('view_all_units')
            && ! $this->pegawaiBelongsToUnit($validated['pegawai_id'], $validated['unit_sekolah_id'])) {
            return back()->withErrors(['pegawai_id' => 'Pegawai tidak terdaftar di unit Anda.'])->withInput();
        }

        $unit = UnitSekolah::find($validated['unit_sekolah_id']);
        if ($unit && $unit->jam_masuk_kantor && strtotime($validated['jam_mulai']) < strtotime($unit->jam_masuk_kantor)) {
            return back()->withErrors(['jam_mulai' => "Jam mulai ({$validated['jam_mulai']}) sebelum jam masuk kantor ({$unit->jam_masuk_kantor})."])->withInput();
        }
        if ($unit && $unit->jam_pulang_kantor && strtotime($validated['jam_selesai']) > strtotime($unit->jam_pulang_kantor)) {
            return back()->withErrors(['jam_selesai' => "Jam selesai ({$validated['jam_selesai']}) setelah jam pulang kantor ({$unit->jam_pulang_kantor})."])->withInput();
        }

        $hariList = $validated['hari'];

        return DB::transaction(function () use ($validated, $hariList) {
            foreach ($hariList as $hari) {
                $conflict = Jadwal::where('pegawai_id', $validated['pegawai_id'])
                    ->where('hari', $hari)
                    ->where(function ($query) use ($validated) {
                        $query->where('jam_mulai', '<', $validated['jam_selesai'])
                            ->where('jam_selesai', '>', $validated['jam_mulai']);
                    })
                    ->lockForUpdate()
                    ->with('unitSekolah:id,nama')
                    ->first();

                if ($conflict) {
                    return back()->withErrors([
                        'conflict' => "Terdeteksi bentrok jadwal! Pegawai ini sudah memiliki jadwal {$conflict->jenis_jadwal} di unit {$conflict->unitSekolah->nama} pada hari {$hari} pukul ".substr($conflict->jam_mulai, 0, 5).' - '.substr($conflict->jam_selesai, 0, 5),
                    ])->withInput();
                }
            }

            if ($validated['jenis_jadwal'] === 'mengajar') {
                $exceeded = $this->exceedsWeeklyHourLimitBatch($validated['pegawai_id'], $validated['unit_sekolah_id'], $validated['jam_mulai'], $validated['jam_selesai'], count($hariList));
                if ($exceeded) {
                    return back()->withErrors(['jam_selesai' => $exceeded])->withInput();
                }
            }

            $pegawaiMapelId = $this->resolvePegawaiMapelId($validated);

            foreach ($hariList as $hari) {
                Jadwal::create(array_merge($validated, [
                    'hari' => $hari,
                    'pegawai_mapel_id' => $pegawaiMapelId,
                ]));
            }

            $this->clearJadwalCache($validated['pegawai_id']);

            $message = count($hariList) > 1
                ? 'Jadwal berhasil ditambahkan untuk '.count($hariList).' hari.'
                : 'Jadwal berhasil ditambahkan.';

            return redirect()->route('jadwal.index')->with('message', $message);
        });
    }

    public function edit(Jadwal $jadwal)
    {
        $user = auth()->user();
        if (! $user || ! $user->can('manage_jadwal')) {
            abort(403);
        }
        if ($user->unit_sekolah_id && ! $user->can('view_all_units') && $jadwal->unit_sekolah_id !== $user->unit_sekolah_id) {
            abort(403);
        }

        $units = UnitSekolah::query()->select(['id', 'nama', 'singkatan', 'durasi_jp', 'jam_masuk_kantor', 'jam_pulang_kantor']);
        $pegawais = Pegawai::query()->where('status_aktif', 'aktif')->select(['id', 'nama_lengkap']);

        if ($user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $units->where('id', $user->unit_sekolah_id);
            $pegawais->whereHas('units', fn ($q) => $q->where('unit_sekolah.id', $user->unit_sekolah_id));
        }

        return inertia('Jadwal/Edit', [
            'jadwal' => $jadwal,
            'pegawais' => $pegawais->orderBy('nama_lengkap')->get(),
            'units' => $units->get(),
            'mapel' => MataPelajaran::all(['id', 'nama']),
        ]);
    }

    public function update(Request $request, Jadwal $jadwal)
    {
        $user = auth()->user();
        if (! $user || ! $user->can('manage_jadwal')) {
            abort(403);
        }
        if ($user->unit_sekolah_id && ! $user->can('view_all_units')) {
            if ($jadwal->unit_sekolah_id !== $user->unit_sekolah_id) {
                abort(403);
            }
            $request->merge(['unit_sekolah_id' => $user->unit_sekolah_id]);
        }

        $validated = $request->validate([
            'pegawai_id' => 'required|exists:pegawai,id',
            'unit_sekolah_id' => 'required|exists:unit_sekolah,id',
            'kelas_label' => 'nullable|string|max:255',
            // Mapel WAJIB untuk jadwal mengajar, opsional untuk jenis lain (piket/ekskul/shift).
            'mata_pelajaran_id' => 'nullable|required_if:jenis_jadwal,mengajar|exists:mata_pelajaran,id',
            'hari' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'jenis_jadwal' => 'required|in:mengajar,piket,ekskul,shift_satpam,shift_kebersihan,lainnya',
            'tahun_ajaran' => 'required|string|max:10',
            'semester' => 'required|integer|in:1,2',
        ]);

        // Security: user yang ter-scope unit tidak boleh edit jadwal ke pegawai unit lain.
        if ($user->unit_sekolah_id && ! $user->can('view_all_units')
            && ! $this->pegawaiBelongsToUnit($validated['pegawai_id'], $validated['unit_sekolah_id'])) {
            return back()->withErrors(['pegawai_id' => 'Pegawai tidak terdaftar di unit Anda.'])->withInput();
        }

        $unit = UnitSekolah::find($validated['unit_sekolah_id']);
        if ($unit && $unit->jam_masuk_kantor && strtotime($validated['jam_mulai']) < strtotime($unit->jam_masuk_kantor)) {
            return back()->withErrors(['jam_mulai' => "Jam mulai ({$validated['jam_mulai']}) sebelum jam masuk kantor ({$unit->jam_masuk_kantor})."])->withInput();
        }
        if ($unit && $unit->jam_pulang_kantor && strtotime($validated['jam_selesai']) > strtotime($unit->jam_pulang_kantor)) {
            return back()->withErrors(['jam_selesai' => "Jam selesai ({$validated['jam_selesai']}) setelah jam pulang kantor ({$unit->jam_pulang_kantor})."])->withInput();
        }

        return DB::transaction(function () use ($validated, $jadwal) {
            $conflict = Jadwal::where('pegawai_id', $validated['pegawai_id'])
                ->where('hari', $validated['hari'])
                ->where('id', '!=', $jadwal->id)
                ->where(function ($query) use ($validated) {
                    $query->where('jam_mulai', '<', $validated['jam_selesai'])
                        ->where('jam_selesai', '>', $validated['jam_mulai']);
                })
                ->lockForUpdate()
                ->with('unitSekolah:id,nama')
                ->first();

            if ($conflict) {
                return back()->withErrors([
                    'conflict' => "Terdeteksi bentrok jadwal! Pegawai ini sudah memiliki jadwal {$conflict->jenis_jadwal} di unit {$conflict->unitSekolah->nama} pada pukul ".substr($conflict->jam_mulai, 0, 5).' - '.substr($conflict->jam_selesai, 0, 5),
                ])->withInput();
            }

            if ($validated['jenis_jadwal'] === 'mengajar') {
                $exceeded = $this->exceedsWeeklyHourLimit($validated['pegawai_id'], $validated['unit_sekolah_id'], $validated['jam_mulai'], $validated['jam_selesai'], $jadwal->id);
                if ($exceeded) {
                    return back()->withErrors(['jam_selesai' => $exceeded])->withInput();
                }
            }

            $validated['pegawai_mapel_id'] = $this->resolvePegawaiMapelId($validated);

            $jadwal->update($validated);

            $this->clearJadwalCache($validated['pegawai_id']);

            return redirect()->route('jadwal.index')->with('message', 'Jadwal berhasil diperbarui.');
        });
    }

    public function generate(Request $request)
    {
        $user = auth()->user();
        if (! $user || ! $user->can('manage_jadwal')) {
            abort(403);
        }

        // Generate SELALU membuat jadwal mengajar → mapel wajib (konsisten dgn store/update).
        $request->validate([
            'tahun_ajaran' => 'required|string|max:10',
            'semester' => 'required|integer|in:1,2',
            'unit_sekolah_id' => 'nullable|exists:unit_sekolah,id',
            'mata_pelajaran_id' => 'required|exists:mata_pelajaran,id',
            'waktu_mulai' => 'nullable|date_format:H:i',
            'waktu_selesai' => 'nullable|date_format:H:i',
        ]);

        $unitId = $request->unit_sekolah_id;
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $unitId = $user->unit_sekolah_id;
        }

        // Get pegawais to generate for
        $pegawaiQuery = Pegawai::where('status_aktif', 'aktif');
        if ($unitId) {
            $pegawaiQuery->whereHas('units', function ($q) use ($unitId) {
                $q->where('unit_sekolah.id', $unitId);
            });
        }
        $pegawais = $pegawaiQuery->with('units')->get(['id']);

        // Pre-fetch unit yang dipilih user (1 query, bukan per-pegawai).
        $selectedUnit = $unitId ? UnitSekolah::find($unitId) : null;

        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
        $timeBlocks = [
            ['07:00', '09:00'],
            ['09:30', '11:30'],
            ['13:00', '15:00'],
        ];

        // Filter timeBlocks based on input
        if ($request->waktu_mulai) {
            $timeBlocks = array_filter($timeBlocks, fn ($b) => $b[0] >= $request->waktu_mulai);
        }
        if ($request->waktu_selesai) {
            $timeBlocks = array_filter($timeBlocks, fn ($b) => $b[1] <= $request->waktu_selesai);
        }

        $timeBlocks = array_values($timeBlocks);

        if (empty($timeBlocks)) {
            return back()->withErrors(['waktu' => 'Tidak ada blok waktu yang tersedia dalam rentang waktu yang dipilih.']);
        }

        $mapel = MataPelajaran::findOrFail($request->mata_pelajaran_id);

        [$generatedCount, $skippedNoUnit, $skippedNoMax] = DB::transaction(function () use ($pegawais, $days, $timeBlocks, $mapel, $request) {
            $count = 0;
            $skippedNoUnit = 0;
            $skippedNoMax = 0;

            foreach ($pegawais as $pegawai) {
                // Jangan fallback ke unit id 1 — pegawai tanpa unit harus di-skip,
                // bukan dibuatkan jadwal di unit yang salah.
                $unit = $selectedUnit ?? $pegawai->units->first();
                if (! $unit) {
                    $skippedNoUnit++;

                    continue;
                }
                $maxMinutes = $unit->max_jam_minggu ? $unit->max_jam_minggu * 60 : 0;
                if ($maxMinutes === 0) {
                    $skippedNoMax++;

                    continue;
                }

                // Resolve pegawai_mapel for this pegawai+mapel+unit combo.
                $pegawaiMapel = PegawaiMapel::where('pegawai_id', $pegawai->id)
                    ->where('mata_pelajaran_id', $mapel->id)
                    ->where('unit_sekolah_id', $unit->id)
                    ->lockForUpdate()
                    ->first();

                if (! $pegawaiMapel) {
                    $pegawaiMapel = PegawaiMapel::create([
                        'pegawai_id' => $pegawai->id,
                        'mata_pelajaran_id' => $mapel->id,
                        'unit_sekolah_id' => $unit->id,
                    ]);
                }

                // Hitung existing minutes mingguan
                $existingMinutes = Jadwal::where('pegawai_id', $pegawai->id)
                    ->where('jenis_jadwal', 'mengajar')
                    ->lockForUpdate()
                    ->get()
                    ->sum(fn ($j) => max(0, (intval(substr($j->jam_selesai, 0, 2)) * 60 + intval(substr($j->jam_selesai, 3, 2))) - (intval(substr($j->jam_mulai, 0, 2)) * 60 + intval(substr($j->jam_mulai, 3, 2)))));

                if ($existingMinutes >= $maxMinutes) {
                    continue;
                }

                // Shuffle days dan timeBlocks untuk variasi
                $shuffledDays = $days;
                shuffle($shuffledDays);
                $shuffledBlocks = $timeBlocks;
                shuffle($shuffledBlocks);

                foreach ($shuffledDays as $day) {
                    if ($existingMinutes >= $maxMinutes) {
                        break;
                    }

                    foreach ($shuffledBlocks as $time) {
                        if ($existingMinutes >= $maxMinutes) {
                            break;
                        }

                        $conflict = Jadwal::where('pegawai_id', $pegawai->id)
                            ->where('hari', $day)
                            ->where(function ($query) use ($time) {
                                $query->where('jam_mulai', '<', $time[1])
                                    ->where('jam_selesai', '>', $time[0]);
                            })->lockForUpdate()->exists();

                        if (! $conflict) {
                            Jadwal::create([
                                'pegawai_id' => $pegawai->id,
                                'unit_sekolah_id' => $unit->id,
                                'pegawai_mapel_id' => $pegawaiMapel->id,
                                'hari' => $day,
                                'jam_mulai' => $time[0],
                                'jam_selesai' => $time[1],
                                'jenis_jadwal' => 'mengajar',
                                'tahun_ajaran' => $request->tahun_ajaran,
                                'semester' => $request->semester,
                            ]);
                            $count++;

                            $blockMinutes = (intval(substr($time[1], 0, 2)) * 60 + intval(substr($time[1], 3, 2))) - (intval(substr($time[0], 0, 2)) * 60 + intval(substr($time[0], 3, 2)));
                            $existingMinutes += $blockMinutes;
                        }
                    }
                }
            }

            return [$count, $skippedNoUnit, $skippedNoMax];
        });

        foreach ($pegawais as $p) {
            $this->clearJadwalCache($p->id);
        }

        $message = "Berhasil me-generate $generatedCount jadwal acak untuk guru.";
        if ($skippedNoUnit > 0) {
            $message .= " $skippedNoUnit pegawai di-skip (tidak memiliki unit).";
        }
        if ($skippedNoMax > 0) {
            $message .= " $skippedNoMax pegawai di-skip (max_jam_minggu 0).";
        }

        return redirect()->route('jadwal.index')->with('message', $message);
    }

    public function swap(Request $request)
    {
        $user = auth()->user();
        if (! $user || ! $user->can('manage_jadwal')) {
            abort(403);
        }

        $request->validate([
            'jadwal_asal_id' => 'required|exists:jadwal,id',
            'jadwal_tujuan_id' => 'required|exists:jadwal,id|different:jadwal_asal_id',
        ]);

        // [FIX] Seluruh operasi swap dibungkus dalam transaction + lockForUpdate
        return DB::transaction(function () use ($request, $user) {
            $jadwalAsal = Jadwal::lockForUpdate()->findOrFail($request->jadwal_asal_id);
            $jadwalTujuan = Jadwal::lockForUpdate()->findOrFail($request->jadwal_tujuan_id);

            // Security check for admin_unit
            if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
                if ($jadwalAsal->unit_sekolah_id !== $user->unit_sekolah_id || $jadwalTujuan->unit_sekolah_id !== $user->unit_sekolah_id) {
                    abort(403, 'Akses ditolak. Tidak bisa menukar lintas unit tanpa akses Superadmin.');
                }
            }

            // Cek Bentrok untuk Pegawai Asal di Jadwal Tujuan
            $conflictAsal = Jadwal::where('pegawai_id', $jadwalAsal->pegawai_id)
                ->where('hari', $jadwalTujuan->hari)
                ->where('id', '!=', $jadwalAsal->id)
                ->where(function ($query) use ($jadwalTujuan) {
                    $query->where('jam_mulai', '<', $jadwalTujuan->jam_selesai)
                        ->where('jam_selesai', '>', $jadwalTujuan->jam_mulai);
                })->exists();

            if ($conflictAsal) {
                return back()->withErrors(['conflict' => 'Pertukaran gagal! Pegawai Asal memiliki jadwal yang bentrok dengan jadwal tujuan.']);
            }

            // Cek Bentrok untuk Pegawai Tujuan di Jadwal Asal
            $conflictTujuan = Jadwal::where('pegawai_id', $jadwalTujuan->pegawai_id)
                ->where('hari', $jadwalAsal->hari)
                ->where('id', '!=', $jadwalTujuan->id)
                ->where(function ($query) use ($jadwalAsal) {
                    $query->where('jam_mulai', '<', $jadwalAsal->jam_selesai)
                        ->where('jam_selesai', '>', $jadwalAsal->jam_mulai);
                })->exists();

            if ($conflictTujuan) {
                return back()->withErrors(['conflict' => 'Pertukaran gagal! Pegawai Tujuan memiliki jadwal yang bentrok dengan jadwal asal.']);
            }

            // Cek batas jam mengajar
            if ($jadwalAsal->jenis_jadwal === 'mengajar') {
                $exceeded = $this->exceedsWeeklyHourLimit($jadwalTujuan->pegawai_id, $jadwalTujuan->unit_sekolah_id, $jadwalAsal->jam_mulai, $jadwalAsal->jam_selesai, $jadwalTujuan->id);
                if ($exceeded) {
                    return back()->withErrors(['conflict' => 'Pertukaran gagal! '.$exceeded]);
                }
            }
            if ($jadwalTujuan->jenis_jadwal === 'mengajar') {
                $exceeded = $this->exceedsWeeklyHourLimit($jadwalAsal->pegawai_id, $jadwalAsal->unit_sekolah_id, $jadwalTujuan->jam_mulai, $jadwalTujuan->jam_selesai, $jadwalAsal->id);
                if ($exceeded) {
                    return back()->withErrors(['conflict' => 'Pertukaran gagal! '.$exceeded]);
                }
            }

            // Lakukan penukaran (Hanya tukar pegawai_id) — sekarang aman dalam transaction
            $tempPegawaiId = $jadwalAsal->pegawai_id;
            $jadwalAsal->update(['pegawai_id' => $jadwalTujuan->pegawai_id]);
            $jadwalTujuan->update(['pegawai_id' => $tempPegawaiId]);

            $this->clearJadwalCache($jadwalAsal->pegawai_id);
            $this->clearJadwalCache($jadwalTujuan->pegawai_id);

            return redirect()->route('jadwal.index')->with('message', 'Pertukaran jadwal berhasil dilakukan!');
        });
    }

    public function destroy(string $id)
    {
        $user = auth()->user();
        if (! $user || ! $user->can('manage_jadwal')) {
            abort(403);
        }

        $jadwal = Jadwal::findOrFail($id);

        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units') && $jadwal->unit_sekolah_id !== $user->unit_sekolah_id) {
            abort(403, 'Akses ditolak.');
        }

        $jadwal->delete();
        $this->clearJadwalCache($jadwal->pegawai_id);

        return redirect()->route('jadwal.index')->with('message', 'Jadwal berhasil dihapus.');
    }

    /**
     * Resolve pegawai_mapel_id from validated jadwal data.
     * findOrCreate with lockForUpdate inside transaction for race condition safety.
     * Returns null for non-mengajar types.
     */
    private function resolvePegawaiMapelId(array $validated): ?int
    {
        if ($validated['jenis_jadwal'] !== 'mengajar' || empty($validated['mata_pelajaran_id'])) {
            return null;
        }

        $row = DB::table('pegawai_mapel')
            ->where('pegawai_id', $validated['pegawai_id'])
            ->where('mata_pelajaran_id', $validated['mata_pelajaran_id'])
            ->where('unit_sekolah_id', $validated['unit_sekolah_id'])
            ->first();

        if ($row) {
            return (int) $row->id;
        }

        return (int) DB::table('pegawai_mapel')->insertGetId([
            'pegawai_id' => $validated['pegawai_id'],
            'mata_pelajaran_id' => $validated['mata_pelajaran_id'],
            'unit_sekolah_id' => $validated['unit_sekolah_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function pegawaiBelongsToUnit(int $pegawaiId, int $unitId): bool
    {
        return Pegawai::where('id', $pegawaiId)
            ->whereHas('units', fn ($q) => $q->where('unit_sekolah.id', $unitId))
            ->exists();
    }

    private function minutesBetween(string $jamMulai, string $jamSelesai): int
    {
        [$h1, $m1] = array_pad(explode(':', $jamMulai), 2, 0);
        [$h2, $m2] = array_pad(explode(':', $jamSelesai), 2, 0);

        return max(0, ((int) $h2 * 60 + (int) $m2) - ((int) $h1 * 60 + (int) $m1));
    }

    private function exceedsWeeklyHourLimit(int $pegawaiId, int $unitId, string $jamMulai, string $jamSelesai, ?int $excludeJadwalId = null): ?string
    {
        $unit = UnitSekolah::find($unitId);
        if (! $unit || ! $unit->max_jam_minggu) {
            return null;
        }

        $query = Jadwal::where('pegawai_id', $pegawaiId)
            ->where('jenis_jadwal', 'mengajar');
        if ($excludeJadwalId) {
            $query->where('id', '!=', $excludeJadwalId);
        }

        $existingMinutes = $query->get()->sum(fn ($j) => $this->minutesBetween($j->jam_mulai, $j->jam_selesai));

        $maxMinutes = $unit->max_jam_minggu * 60;

        if (($existingMinutes + $this->minutesBetween($jamMulai, $jamSelesai)) > $maxMinutes) {
            $sisa = $maxMinutes - $existingMinutes;

            return 'Total jam mengajar/minggu melebihi batas '.$unit->max_jam_minggu.' jam. Sisa: '.max(0, $sisa).' menit.';
        }

        return null;
    }

    private function exceedsWeeklyHourLimitBatch(int $pegawaiId, int $unitId, string $jamMulai, string $jamSelesai, int $jumlahHari): ?string
    {
        $unit = UnitSekolah::find($unitId);
        if (! $unit || ! $unit->max_jam_minggu) {
            return null;
        }

        $existingMinutes = Jadwal::where('pegawai_id', $pegawaiId)
            ->where('jenis_jadwal', 'mengajar')
            ->get()
            ->sum(fn ($j) => $this->minutesBetween($j->jam_mulai, $j->jam_selesai));

        $maxMinutes = $unit->max_jam_minggu * 60;
        $proposedMinutes = $this->minutesBetween($jamMulai, $jamSelesai) * $jumlahHari;

        if (($existingMinutes + $proposedMinutes) > $maxMinutes) {
            $sisa = $maxMinutes - $existingMinutes;

            return 'Total jam mengajar/minggu melebihi batas '.$unit->max_jam_minggu.' jam. Sisa: '.max(0, $sisa).' menit.';
        }

        return null;
    }

    private function clearJadwalCache(int $pegawaiId): void
    {
        Cache::forget('mobile.jadwal.'.$pegawaiId);
        foreach (['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'] as $hari) {
            Cache::forget('mobile.jadwal.'.$pegawaiId.'.'.$hari);
        }
    }

    public function kelasByUnit(Request $request)
    {
        $request->validate(['q' => 'required|string|max:255']);
        $base = rtrim((string) config('keuangan.url'), '/');
        $key = (string) config('keuangan.key');
        if ($base === '' || $key === '' || $key === 'change-me-in-production') {
            return response()->json(['success' => false, 'kelas' => []]);
        }
        try {
            $response = Http::acceptJson()
                ->withHeaders(['x-internal-key' => $key])
                ->connectTimeout(2)
                ->timeout(5)
                ->get($base.'/api/integration/kelas-by-unit', ['unit' => $request->q]);
        } catch (ConnectionException) {
            return response()->json(['success' => false, 'kelas' => []]);
        }

        return response()->json($response->json() ?: ['success' => false, 'kelas' => []]);
    }

    // ──────────────────────────────────────────
    //  PDF Export
    // ──────────────────────────────────────────

    public function exportPdf(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user && ($user->can('view_jadwal') || $user->can('manage_jadwal'));

        $query = Jadwal::with(['pegawai:id,nama_lengkap', 'unitSekolah:id,nama,singkatan', 'pegawaiMapel.mataPelajaran:id,nama']);

        if ($this->isPimpinanReadOnly($user)) {
            $this->scopePimpinanBawahan($query, $user);
        } elseif (! $isAdmin) {
            $pegawai = Pegawai::where('user_id', auth()->id())->first();
            $query->where('pegawai_id', $pegawai?->id ?? -1);
        } elseif ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $query->where('unit_sekolah_id', $user->unit_sekolah_id);
        } elseif ($request->filled('unit_sekolah_id')) {
            $query->where('unit_sekolah_id', $request->unit_sekolah_id);
        }

        if ($request->filled('kelas_label')) {
            $query->where('kelas_label', $request->kelas_label);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->whereHas('pegawai', fn ($q) => $q->where('nama_lengkap', 'like', "%{$search}%"));
        }

        $jenis = $request->input('jenis_filter');
        if ($jenis === '' || $jenis === null) {
            $jenis = 'pendidik';
        }
        if ($jenis === 'pendidik') {
            $query->whereHas('pegawai.jabatans', fn ($q) => $q->where('is_guru', true));
        } elseif ($jenis === 'kependidikan') {
            $query->whereHas('pegawai', fn ($q) => $q->whereDoesntHave('jabatans', fn ($q2) => $q2->where('is_guru', true)));
        }

        $jadwals = $query->orderByRaw("CASE hari WHEN 'Senin' THEN 1 WHEN 'Selasa' THEN 2 WHEN 'Rabu' THEN 3 WHEN 'Kamis' THEN 4 WHEN 'Jumat' THEN 5 WHEN 'Sabtu' THEN 6 WHEN 'Minggu' THEN 7 END")
            ->orderBy('jam_mulai')
            ->get();

        $DAYS = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
        $matrix = [];
        $perGuru = [];

        foreach ($jadwals as $j) {
            $kelas = $j->kelas_label;
            $hari = $j->hari;
            if (! $kelas || ! in_array($hari, $DAYS)) {
                continue;
            }

            $slotNum = $this->getJamSlot($j->jam_mulai);
            if (! $slotNum) {
                continue;
            }

            $guru = $j->pegawai->nama_lengkap ?? '-';
            $mapel = $j->pegawaiMapel->mataPelajaran->nama ?? ($j->jenis_jadwal === 'mengajar' ? '-' : ucfirst($j->jenis_jadwal));

            $matrix[$kelas][$hari][$slotNum] = ['guru' => $guru, 'mapel' => $mapel];

            $pid = $j->pegawai_id;
            if (! isset($perGuru[$pid])) {
                $perGuru[$pid] = ['nama' => $guru, 'jadwals' => []];
            }
            $perGuru[$pid]['jadwals'][] = [
                'hari' => $hari,
                'jam_mulai' => substr($j->jam_mulai, 0, 5),
                'jam_selesai' => substr($j->jam_selesai, 0, 5),
                'kelas' => $kelas,
                'mapel' => $mapel,
                'jenis' => $j->jenis_jadwal,
            ];
        }

        ksort($matrix);
        foreach ($matrix as &$hariArr) {
            ksort($hariArr);
        }

        $kelasList = collect(array_keys($matrix))->sort()->values()->all();

        $jamSlotLabels = [
            1 => '07:00', 2 => '08:10', 3 => '08:50', 4 => '09:30',
            5 => '10:30', 6 => '11:10', 7 => '11:50', 8 => '13:00',
            9 => '13:40', 10 => '14:20', 11 => '15:00',
        ];

        $logoPath = $this->resolveYayasanLogoPath();
        $logoWidth = null;
        if ($logoPath && file_exists($logoPath)) {
            $sz = @getimagesize($logoPath);
            if ($sz) {
                $logoWidth = (int) round(64 * $sz[0] / $sz[1]);
            }
        }

        $unitName = 'Semua Unit';
        if (! empty($request->unit_sekolah_id)) {
            $unit = UnitSekolah::find($request->unit_sekolah_id);
            $unitName = $unit?->nama ?? $unitName;
        } elseif ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $unit = UnitSekolah::find($user->unit_sekolah_id);
            $unitName = $unit?->nama ?? $unitName;
        }

        $tahunAjaran = $jadwals->first()?->tahun_ajaran ?? date('Y').'/'.(date('Y') + 1);
        $semester = $jadwals->first()?->semester ?? 1;

        $pdf = Pdf::loadView('exports.pdf-jadwal', compact(
            'matrix', 'perGuru', 'kelasList', 'DAYS', 'jamSlotLabels',
            'logoPath', 'logoWidth', 'unitName', 'tahunAjaran', 'semester'
        ))->setPaper('a4', 'landscape');

        $safeName = preg_replace('#[/\\\\]#', '-', "Jadwal_Pelajaran_{$unitName}_{$tahunAjaran}_S{$semester}");

        return $pdf->download("{$safeName}.pdf");
    }

    // ──────────────────────────────────────────
    //  Import Jadwal from JSON
    // ──────────────────────────────────────────

    public function importPdf(Request $request)
    {
        $user = auth()->user();
        if (! $user || ! $user->can('manage_jadwal')) {
            abort(403);
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf|max:20480',
            'unit_sekolah_id' => 'required|exists:unit_sekolah,id',
            'delete_existing' => 'nullable|boolean',
        ]);

        $unitId = (int) $request->unit_sekolah_id;
        if ($user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $unitId = $user->unit_sekolah_id;
        }

        // Simpan PDF ke temp lalu jalankan extraction via Python.
        $pdfPath = $request->file('file')->storeAs('temp', 'jadwal_import_'.time().'.pdf');
        $fullPdfPath = Storage::path($pdfPath);
        $scriptPath = base_path('scripts/extract_jadwal.py');

        if (! file_exists($scriptPath)) {
            Storage::delete($pdfPath);

            return back()->withErrors(['file' => 'Script ekstraksi tidak ditemukan.']);
        }

        // Coba python3 (Linux), fallback python (Windows).
        $cmd = sprintf('python3 %s %s', escapeshellarg($scriptPath), escapeshellarg($fullPdfPath));
        $output = shell_exec($cmd);
        if ($output === null || $output === '') {
            $cmd = sprintf('python %s %s', escapeshellarg($scriptPath), escapeshellarg($fullPdfPath));
            $output = shell_exec($cmd);
        }
        Storage::delete($pdfPath);

        if ($output === null || $output === '') {
            return back()->withErrors(['file' => 'Gagal menjalankan script ekstraksi. Pastikan Python terinstal di server.']);
        }

        $data = json_decode($output, true);
        if (! $data || ! isset($data['jadwal'])) {
            return back()->withErrors(['file' => 'PDF tidak dapat diproses. Pastikan format PDF sesuai jadwal pelajaran.']);
        }

        $tahunAjaran = $data['tahun_ajaran'] ?? '2026/2027';
        $semester = $data['semester'] ?? 1;
        $entries = $data['jadwal'];

        $pegawaiByName = Pegawai::select('id', 'nama_lengkap')->get()->keyBy('nama_lengkap');
        $mapelByNama = MataPelajaran::select('id', 'nama')
            ->where('unit_sekolah_id', $unitId)->orWhereNull('unit_sekolah_id')
            ->get()->keyBy('nama');
        $pmlPairs = PegawaiMapel::select('id', 'pegawai_id', 'mata_pelajaran_id')
            ->where('unit_sekolah_id', $unitId)
            ->get()->keyBy(fn ($r) => "{$r->pegawai_id}_{$r->mata_pelajaran_id}");

        if ($request->boolean('delete_existing')) {
            Jadwal::where('unit_sekolah_id', $unitId)
                ->where('tahun_ajaran', $tahunAjaran)
                ->where('semester', $semester)
                ->delete();
        }

        $created = 0;
        $skipped = 0;
        $failures = [];

        DB::transaction(function () use ($entries, $unitId, $tahunAjaran, $semester, $pegawaiByName, $mapelByNama, $pmlPairs, &$created, &$skipped, &$failures) {
            foreach ($entries as $idx => $entry) {
                $row = $idx + 2;

                $pegawaiId = $this->resolvePegawaiIdForImport($entry['guru'] ?? '', $pegawaiByName);
                if (! $pegawaiId) {
                    $failures[] = "Baris {$row}: Pegawai tidak ditemukan untuk '{$entry['guru']}'";

                    continue;
                }

                $mapel = $mapelByNama[$entry['mapel'] ?? ''] ?? null;
                if (! $mapel) {
                    $failures[] = "Baris {$row}: Mapel tidak ditemukan untuk '{$entry['mapel']}'";

                    continue;
                }

                $pmlKey = "{$pegawaiId}_{$mapel->id}";
                $pml = $pmlPairs[$pmlKey] ?? null;
                if (! $pml) {
                    $pml = PegawaiMapel::create([
                        'pegawai_id' => $pegawaiId,
                        'mata_pelajaran_id' => $mapel->id,
                        'unit_sekolah_id' => $unitId,
                    ]);
                    $pmlPairs[$pmlKey] = $pml;
                }

                $exists = Jadwal::where('pegawai_id', $pegawaiId)
                    ->where('kelas_label', $entry['kelas'] ?? '')
                    ->where('hari', $entry['hari'] ?? '')
                    ->where('jam_mulai', $this->normalizeTime($entry['jam_mulai'] ?? ''))
                    ->where('tahun_ajaran', $tahunAjaran)
                    ->where('semester', $semester)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                Jadwal::create([
                    'pegawai_id' => $pegawaiId,
                    'unit_sekolah_id' => $unitId,
                    'kelas_label' => $entry['kelas'] ?? null,
                    'pegawai_mapel_id' => $pml->id,
                    'hari' => $entry['hari'] ?? '',
                    'jam_mulai' => $this->normalizeTime($entry['jam_mulai'] ?? ''),
                    'jam_selesai' => $this->normalizeTime($entry['jam_selesai'] ?? ''),
                    'jenis_jadwal' => 'mengajar',
                    'tahun_ajaran' => $tahunAjaran,
                    'semester' => $semester,
                ]);
                $created++;
            }
        });

        $affectedPegawai = Jadwal::where('unit_sekolah_id', $unitId)
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->pluck('pegawai_id')
            ->unique();
        foreach ($affectedPegawai as $pid) {
            $this->clearJadwalCache($pid);
        }

        $message = "Import selesai: {$created} jadwal dibuat, {$skipped} duplikat dilewati";
        if (count($failures) > 0) {
            $message .= ', '.count($failures).' gagal';
        }

        if (! empty($failures)) {
            if (! empty($failures)) {
                \Log::warning('Jadwal import failures', array_slice($failures, 0, 10));
                \Log::warning('Jadwal import sample entry', $entries[0] ?? []);
            }

            return back()->with('message', $message)->with('import_failures', array_slice($failures, 0, 50));
        }

        return back()->with('message', $message);
    }

    // ──────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────

    private function getJamSlot(string $jamMulai): ?int
    {
        $h = (int) substr($jamMulai, 0, 2);
        $m = (int) substr($jamMulai, 3, 2);
        $minutes = $h * 60 + $m;

        return match (true) {
            $minutes >= 420 && $minutes < 490 => 1,
            $minutes >= 490 && $minutes < 530 => 2,
            $minutes >= 530 && $minutes < 570 => 3,
            $minutes >= 570 && $minutes < 630 => 4,
            $minutes >= 630 && $minutes < 670 => 5,
            $minutes >= 670 && $minutes < 710 => 6,
            $minutes >= 710 && $minutes < 750 => 7,
            $minutes >= 780 && $minutes < 820 => 8,
            $minutes >= 820 && $minutes < 860 => 9,
            $minutes >= 860 && $minutes < 900 => 10,
            $minutes >= 900 && $minutes < 940 => 11,
            default => null,
        };
    }

    private function normalizeTime(string $time): string
    {
        $parts = explode(':', $time);
        if (count($parts) === 2) {
            return $time.':00';
        }

        return $time;
    }

    private function resolvePegawaiIdForImport(string $name, $pegawaiByName): ?int
    {
        if (isset($pegawaiByName[$name])) {
            return $pegawaiByName[$name]->id;
        }

        foreach ($pegawaiByName as $p) {
            if (stripos($p->nama_lengkap, $name) !== false || stripos($name, $p->nama_lengkap) !== false) {
                return $p->id;
            }
        }

        $clean = preg_replace('/,?\s*(S\.Pd\.?|S\.Kom\.?|S\.Ag\.?|S\.Si\.?|SE\.?|ST\.?|M\.Pd\.?|M\.Kom\.?|M\.MPd\.?|A\.Md\.Kom\.?|S\.S\.?|S\.A\.?.*)$/iu', '', trim($name));
        $clean = trim($clean);
        if ($clean !== '' && $clean !== $name) {
            foreach ($pegawaiByName as $p) {
                if (stripos($p->nama_lengkap, $clean) !== false) {
                    return $p->id;
                }
            }
        }

        return null;
    }

    private function resolveYayasanLogoPath(): ?string
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
}
