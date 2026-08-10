<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use NotificationChannels\WebPush\PushSubscription;

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

        // Upsert: endpoint unik per subscriber. Jika endpoint sama sudah terdaftar
        // untuk user lain (mis. pindah akun), ambil alih ke user aktif.
        $subscription = PushSubscription::query()
            ->updateOrCreate(
                ['endpoint' => $validated['endpoint']],
                [
                    'subscribable_id' => $user->id,
                    'subscribable_type' => $user->getMorphClass(),
                    'public_key' => $validated['public_key'] ?? null,
                    'auth_token' => $validated['auth_token'] ?? null,
                    'content_encoding' => $validated['content_encoding'] ?? 'aesgcm',
                ]
            );

        return response()->json(['success' => true, 'subscription_id' => $subscription->id]);
    }

    public function unsubscribe(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => 'required|string|url|max:500',
        ]);

        PushSubscription::query()
            ->where('endpoint', $validated['endpoint'])
            ->where('subscribable_id', $request->user()?->id)
            ->delete();

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
