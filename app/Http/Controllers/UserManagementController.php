<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Inertia\Inertia;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $query = User::with(['roles', 'permissions', 'unitSekolah']);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(15)->withQueryString();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search'])
        ]);
    }

    public function create()
    {
        $allRoles = \Spatie\Permission\Models\Role::all();
        $unitSekolah = \App\Models\UnitSekolah::all();

        return Inertia::render('Users/Create', [
            'allRoles' => $allRoles,
            'unitSekolah' => $unitSekolah
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|exists:roles,name',
            'unit_sekolah_id' => 'nullable|exists:unit_sekolah,id'
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
            'role' => $request->role,
            'unit_sekolah_id' => $request->unit_sekolah_id,
        ]);

        $user->assignRole($request->role);

        return redirect()->route('users.index')->with('message', 'User berhasil ditambahkan.');
    }

    public function edit(User $user)
    {
        $user->load(['roles', 'permissions', 'unitSekolah']);
        $allPermissions = \Spatie\Permission\Models\Permission::all();
        $allRoles = \Spatie\Permission\Models\Role::all();

        return Inertia::render('Users/Edit', [
            'userData' => $user, // Avoid conflict with auth.user
            'allPermissions' => $allPermissions,
            'allRoles' => $allRoles
        ]);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'role' => 'nullable|string|exists:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if ($request->has('role') && $request->role) {
            $user->syncRoles([$request->role]);
            $user->role = $request->role; // Keep the standard role column in sync for fallback
            $user->save();
        }

        if ($request->has('permissions')) {
            $user->syncPermissions($request->permissions);
        } else {
            $user->syncPermissions([]); // Revoke all direct permissions if array is missing/empty
        }

        if ($request->filled('password')) {
            $user->password = \Illuminate\Support\Facades\Hash::make($request->password);
            $user->save();
        }

        return redirect()->route('users.index')->with('message', 'Data user berhasil diperbarui.');
    }
}
