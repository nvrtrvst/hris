<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Build query inbox dengan filter search + archive (shared admin & mobile).
     */
    private function query(Request $request)
    {
        $query = $request->user()->notifications();

        // Filter arsip: default tampil yang belum di-arsipkan; ?view=archived utk arsip.
        if ($request->input('view') === 'archived') {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at');
        }

        // Search di payload JSON notifikasi (message / nama pegawai).
        if ($request->filled('search')) {
            $query->where('data', 'like', '%'.$request->search.'%');
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Inbox notifikasi user yang login (desktop admin & staff).
     */
    public function index(Request $request)
    {
        $notifications = $this->query($request)->paginate(20)->withQueryString();

        return inertia('Notifications/Index', [
            'notifications' => $notifications,
            'filters' => $request->only(['search', 'view']),
        ]);
    }

    /**
     * Inbox notifikasi di portal mobile pegawai.
     */
    public function indexMobile(Request $request)
    {
        $notifications = $this->query($request)->paginate(20)->withQueryString();

        return inertia('Mobile/Notifikasi', [
            'notifications' => $notifications,
            'filters' => $request->only(['search', 'view']),
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        $request->user()->notifications()->whereKey($id)->update(['read_at' => now()]);

        return back();
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications()->whereNull('archived_at')->update(['read_at' => now()]);

        return back();
    }

    public function archive(Request $request, string $id)
    {
        $request->user()->notifications()->whereKey($id)->update(['archived_at' => now()]);

        return back();
    }

    public function restore(Request $request, string $id)
    {
        $request->user()->notifications()->whereKey($id)->update(['archived_at' => null]);

        return back();
    }
}
