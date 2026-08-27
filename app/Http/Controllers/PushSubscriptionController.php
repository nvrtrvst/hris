<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
}
