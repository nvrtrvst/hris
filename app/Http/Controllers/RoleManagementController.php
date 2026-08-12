<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $query = Role::with('permissions');

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $totalRole = (clone $query)->count();
        $systemRoles = ['superadmin', 'admin_unit', 'pegawai'];
        $totalSystem = (clone $query)->whereIn('name', $systemRoles)->count();
        $stats = [
            'total_role' => $totalRole,
            'total_system' => $totalSystem,
            'total_custom' => $totalRole - $totalSystem,
        ];

        $roles = $query->paginate(15)->withQueryString();

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'filters' => $request->only(['search']),
            'stats' => $stats,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $allPermissions = Permission::all()->groupBy(function ($perm) {
            return explode('_', $perm->name)[1] ?? 'lainnya';
        });

        return Inertia::render('Roles/Form', [
            'role' => null,
            'rolePermissions' => [],
            'allPermissions' => $allPermissions,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        // Guard eksplisit 'web': permission/role Spatie dibuat dgn guard 'web'
        // (RolePermissionSeeder). Tanpa ini, guard mengikuti AUTH_GUARD env
        // (mis. web_admin saat test) → syncPermissions gagal PermissionDoesNotExist.
        $role = Role::create(['name' => strtolower($request->name), 'guard_name' => 'web']);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return redirect()->route('roles.index')->with('message', 'Role berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Role $role)
    {
        $allPermissions = Permission::all()->groupBy(function ($perm) {
            return explode('_', $perm->name)[1] ?? 'lainnya';
        });

        return Inertia::render('Roles/Form', [
            'role' => $role,
            'rolePermissions' => $role->permissions->pluck('name'),
            'allPermissions' => $allPermissions,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Role $role)
    {
        if ($role->name === 'superadmin') {
            return back()->with('error', 'Role superadmin tidak dapat diubah.');
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,'.$role->id,
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role->update(['name' => strtolower($request->name)]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return redirect()->route('roles.index')->with('message', 'Role berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        if ($role->name === 'superadmin') {
            return back()->with('error', 'Role superadmin tidak dapat dihapus.');
        }

        if (in_array($role->name, ['admin_unit', 'pegawai'])) {
            return back()->with('error', 'Role bawaan sistem tidak dapat dihapus.');
        }

        $role->delete();

        return redirect()->route('roles.index')->with('message', 'Role berhasil dihapus.');
    }
}
