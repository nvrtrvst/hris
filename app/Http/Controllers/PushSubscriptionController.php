<?php

namespace App\Http\Controllers;

use App\Notifications\PushTestNotification;
use Illuminate\Http\Request;
use Throwable;

class PushSubscriptionController extends Controller
{
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => 'required|string|url|max:500',
            'public_key' => 'nullable|string|max:255',
            'auth_token' => 'nullable|string|max:255',
            'content_encoding' => 'nullable|in:aesgcm,aes128gcm',
        ]);

        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        // Upsert via relasi morphMany: kolom subscribable_id/subscribable_type TIDAK
        // ada di $fillable model package — updateOrCreate via query builder akan
        // gagal NOT NULL constraint (bug 500). updatePushSubscription() bawaan
        // package mengisi morph key otomatis & mengambil alih endpoint yang
        // dipakai user lain (mis. pindah akun).
        $subscription = $user->updatePushSubscription(
            $validated['endpoint'],
            $validated['public_key'] ?? null,
            $validated['auth_token'] ?? null,
            $validated['content_encoding'] ?? 'aes128gcm',
        );

        // Batasi jumlah subscription per user (cegah abuse / akumulasi row tak terbatas).
        // Sisakan 5 terbaru; hapus yang paling lama bila lewat.
        $max = 5;
        $count = $user->pushSubscriptions()->count();
        if ($count > $max) {
            $user->pushSubscriptions()
                ->orderBy('created_at')
                ->limit($count - $max)
                ->delete();
        }

        return response()->json(['success' => true, 'subscription_id' => $subscription->id]);
    }

    public function unsubscribe(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => 'required|string|url|max:500',
        ]);

        $user = $request->user();
        if ($user) {
            $user->deletePushSubscription($validated['endpoint']);
        }

        return response()->json(['success' => true]);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $subscriptions = $user
            ? $user->pushSubscriptions()->get(['id', 'endpoint', 'created_at'])
            : collect();

        return response()->json(['subscriptions' => $subscriptions]);
    }

    /**
     * Kirim notifikasi tes ke device user sendiri (diagnostic on-device).
     * Mengembalikan pesan error nyata bila VAPID belum di-set / subscription kosong,
     * sehingga kegagalan terlihat (tidak dibungkam seperti sendSafely).
     */
    public function test(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $count = $user->pushSubscriptions()->count();
        if ($count === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada subscription. Pastikan VAPID di-set di .env, lalu RELOAD PWA & grant izin notifikasi.',
            ]);
        }

        try {
            $user->notify(new PushTestNotification);

            return response()->json([
                'success' => true,
                'message' => "Push tes dikirim ke {$count} device. Cek notification bar HP Anda.",
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal kirim: '.$e->getMessage(),
            ]);
        }
    }
}
