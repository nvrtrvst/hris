<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\UnitSekolah;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Announcement::with('creator:id,name')->withCount('unitSekolah');

        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $query->where(fn ($q) => $q->whereNull('unit_sekolah_id')->orWhere('unit_sekolah_id', $user->unit_sekolah_id));
        }

        $announcements = $query->orderByDesc('is_pinned')->orderByDesc('published_at')->paginate(10)->withQueryString();
        $units = UnitSekolah::orderBy('nama')->get(['id', 'nama', 'singkatan']);

        return inertia('Pengumuman/Index', [
            'announcements' => $announcements,
            'units' => $units,
            'userUnitId' => $user?->unit_sekolah_id,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $request->merge(['unit_sekolah_id' => $user->unit_sekolah_id]);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:5000',
            'unit_sekolah_id' => 'nullable|exists:unit_sekolah,id',
            'is_pinned' => 'nullable|boolean',
            'published_at' => 'nullable|date',
        ]);

        Announcement::create([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'unit_sekolah_id' => $validated['unit_sekolah_id'] ?? null,
            'is_pinned' => ! empty($validated['is_pinned']),
            'published_at' => $validated['published_at'] ?? now(),
            'created_by' => $user?->id,
        ]);

        return back()->with('message', 'Pengumuman berhasil dipublikasikan.');
    }

    public function update(Request $request, Announcement $announcement)
    {
        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')
            && $announcement->unit_sekolah_id !== null
            && $announcement->unit_sekolah_id !== $user->unit_sekolah_id) {
            abort(403, 'Akses ditolak.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:5000',
            'is_pinned' => 'nullable|boolean',
            'published_at' => 'nullable|date',
        ]);

        $announcement->update([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'is_pinned' => ! empty($validated['is_pinned']),
            'published_at' => $validated['published_at'] ?? $announcement->published_at,
        ]);

        return back()->with('message', 'Pengumuman berhasil diperbarui.');
    }

    public function destroy(Announcement $announcement)
    {
        $user = auth()->user();
        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')
            && $announcement->unit_sekolah_id !== null
            && $announcement->unit_sekolah_id !== $user->unit_sekolah_id) {
            abort(403, 'Akses ditolak.');
        }

        $announcement->delete();

        return back()->with('message', 'Pengumuman dihapus.');
    }
}
