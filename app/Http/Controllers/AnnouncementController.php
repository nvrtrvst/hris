<?php

namespace App\Http\Controllers;

use App\Helpers\NotificationHelper;
use App\Models\Announcement;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Notifications\AnnouncementPush;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'image' => 'nullable|image|mimes:jpeg,png,webp|max:2048',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $disk = config('filesystems.image_disk', 'public');
            $imagePath = $request->file('image')->store('announcements', $disk);
        }

        $announcement = Announcement::create([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'unit_sekolah_id' => $validated['unit_sekolah_id'] ?? null,
            'is_pinned' => ! empty($validated['is_pinned']),
            'published_at' => $validated['published_at'] ?? now(),
            'image' => $imagePath,
            'created_by' => $user?->id,
        ]);

        $this->notifyTargetUsers($announcement);

        return back()->with('message', 'Pengumuman berhasil dipublikasikan.');
    }

    /**
     * Kirim push (judul saja) ke pegawai target saat pengumuman dipublikasikan.
     * Tidak membuat database notification (sesuai kebutuhan).
     */
    protected function notifyTargetUsers(Announcement $announcement): void
    {
        if ($announcement->published_at && $announcement->published_at->gt(now())) {
            return; // terjadwal masa depan: belum di-push
        }

        $users = User::whereHas('pegawai', function ($q) use ($announcement) {
            $q->where('status_aktif', 'aktif')
                ->when($announcement->unit_sekolah_id, fn ($uq) => $uq->forUnit($announcement->unit_sekolah_id));
        })->get();

        foreach ($users as $targetUser) {
            NotificationHelper::sendSafely($targetUser, new AnnouncementPush($announcement));
        }
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
            'image' => 'nullable|image|mimes:jpeg,png,webp|max:2048',
        ]);

        $data = [
            'title' => $validated['title'],
            'body' => $validated['body'],
            'is_pinned' => ! empty($validated['is_pinned']),
            'published_at' => $validated['published_at'] ?? $announcement->published_at,
        ];

        if ($request->hasFile('image')) {
            if ($announcement->image) {
                $disk = config('filesystems.image_disk', 'public');
                if (Storage::disk($disk)->exists($announcement->image)) {
                    Storage::disk($disk)->delete($announcement->image);
                }
            }
            $disk = config('filesystems.image_disk', 'public');
            $data['image'] = $request->file('image')->store('announcements', $disk);
        }

        $announcement->update($data);

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
