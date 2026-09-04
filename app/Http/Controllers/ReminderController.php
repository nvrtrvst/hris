<?php

namespace App\Http\Controllers;

use App\Helpers\NotificationHelper;
use App\Models\Pegawai;
use App\Models\Reminder;
use App\Models\UnitSekolah;
use App\Models\User;
use App\Notifications\ReminderPush;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReminderController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Reminder::with(['unitSekolah:id,nama', 'creator:id,name']);

        // Admin unit scope: hanya reminder yang mereka buat atau untuk unit mereka
        if ($user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhere('unit_sekolah_id', $user->unit_sekolah_id);
            });
        }

        if ($request->filled('type') && $request->type !== 'semua') {
            $query->where('type', $request->type);
        }

        $reminders = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        $units = $user->can('view_all_units')
            ? UnitSekolah::orderBy('nama')->get(['id', 'nama'])
            : UnitSekolah::where('id', $user->unit_sekolah_id)->get(['id', 'nama']);

        $pegawaiOptions = Pegawai::query()
            ->where('status_aktif', 'aktif')
            ->whereNotNull('user_id')
            ->when($user->unit_sekolah_id && ! $user->can('view_all_units'), fn ($q) => $q->forUnit($user->unit_sekolah_id))
            ->with('user:id,name')
            ->get(['id', 'nama_lengkap', 'user_id'])
            ->map(fn ($p) => ['id' => $p->user_id, 'nama' => $p->nama_lengkap ?? $p->user?->name])
            ->values()
            ->all();

        return Inertia::render('Reminder/Index', [
            'reminders' => $reminders,
            'units' => $units,
            'pegawaiOptions' => $pegawaiOptions,
            'filters' => $request->only(['type']),
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if (! $user || ! $user->can('manage_reminders')) {
            abort(403, 'Akses ditolak.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'type' => 'required|in:presensi,cuti,deadline,custom',
            'unit_sekolah_id' => 'nullable|exists:unit_sekolah,id',
            'target_all' => 'boolean',
            'target_user_ids' => 'nullable|array',
            'target_user_ids.*' => 'exists:users,id',
            'is_recurring' => 'boolean',
            'recurring_schedule' => 'nullable|in:daily,weekly,monthly',
            'recurring_time' => 'nullable|date_format:H:i',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        // Admin unit / pimpinan: kunci ke unit sendiri + filter pilihan pegawai.
        if ($user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $validated['unit_sekolah_id'] = $user->unit_sekolah_id;
            if (! empty($validated['target_user_ids'])) {
                $allowed = User::whereHas('pegawai', fn ($q) => $q->forUnit($user->unit_sekolah_id))
                    ->pluck('id')->all();
                $validated['target_user_ids'] = array_values(array_intersect($validated['target_user_ids'], $allowed));
            }
        }

        $validated['created_by'] = $user->id;
        if (empty($validated['target_all']) && empty($validated['target_user_ids'])) {
            $validated['target_all'] = true;
        }

        $reminder = Reminder::create($validated);

        // Auto-set recurring_days dari unit (Sen-Sab jika kerja Sabtu, Sen-Jum jika tidak)
        if ($reminder->is_recurring && empty($reminder->recurring_days)) {
            $unit = $reminder->unitSekolah;
            $hasSabtu = $unit && $unit->jam_kerja_sabtu_mulai;
            $reminder->update([
                'recurring_days' => $hasSabtu ? [1, 2, 3, 4, 5, 6] : [1, 2, 3, 4, 5],
            ]);
        }

        // Set next_run_at untuk recurring, atau langsung kirim untuk one-shot
        if ($reminder->is_recurring && $reminder->recurring_time) {
            $reminder->update(['next_run_at' => $reminder->getNextRunAt()]);
        } elseif (empty($validated['scheduled_at'])) {
            $this->sendReminder($reminder);
        }

        return back()->with('message', 'Reminder berhasil dibuat.'.(empty($validated['scheduled_at']) ? ' Telah dikirim.' : ' Terjadwal untuk dikirim.'));
    }

    public function destroy(Reminder $reminder)
    {
        $user = auth()->user();
        if (! $user || ! $user->can('manage_reminders')) {
            abort(403, 'Akses ditolak.');
        }

        // Admin unit / pimpinan: hanya reminder buatan sendiri.
        if ($user->unit_sekolah_id && ! $user->can('view_all_units') && $reminder->created_by !== $user->id) {
            abort(403, 'Akses ditolak.');
        }

        $reminder->delete();

        return back()->with('message', 'Reminder berhasil dihapus.');
    }

    public function sendNow(Reminder $reminder)
    {
        $user = auth()->user();
        if (! $user || ! $user->can('manage_reminders')) {
            abort(403, 'Akses ditolak.');
        }

        // Admin unit / pimpinan: hanya reminder buatan sendiri.
        if ($user->unit_sekolah_id && ! $user->can('view_all_units') && $reminder->created_by !== $user->id) {
            abort(403, 'Akses ditolak.');
        }

        // Atomic: claim the send slot — only first request wins
        $claimed = Reminder::where('id', $reminder->id)
            ->whereNull('sent_at')
            ->update(['sent_at' => now()]);

        if (! $claimed) {
            return back()->with('error', 'Reminder sudah terkirim sebelumnya.');
        }

        // sent_at already set by atomic claim; just send notifications
        $users = $this->resolveTargetUsers($reminder->fresh());
        foreach ($users as $targetUser) {
            NotificationHelper::sendSafely($targetUser, new ReminderPush($reminder));
        }

        return back()->with('message', 'Reminder berhasil dikirim ulang.');
    }

    /**
     * Kirim reminder ke target users via web push + database notification.
     */
    private function sendReminder(Reminder $reminder): void
    {
        $users = $this->resolveTargetUsers($reminder);

        foreach ($users as $targetUser) {
            NotificationHelper::sendSafely($targetUser, new ReminderPush($reminder));
        }

        $reminder->update(['sent_at' => now()]);
    }

    /**
     * Resolve target users berdasarkan unit & target config.
     */
    private function resolveTargetUsers(Reminder $reminder)
    {
        $query = User::whereHas('pegawai', function ($q) use ($reminder) {
            $q->where('status_aktif', 'aktif')
                ->when($reminder->target_all && $reminder->unit_sekolah_id, function ($uq) use ($reminder) {
                    $uq->forUnit($reminder->unit_sekolah_id);
                });
        });

        if (! $reminder->target_all && ! empty($reminder->target_user_ids)) {
            $query->whereIn('id', $reminder->target_user_ids);
        }

        return $query->get();
    }
}
