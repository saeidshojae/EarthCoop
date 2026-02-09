<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->orderBy('order')->get();
        return view('admin.roles.index', compact('roles'));
    }

    public function create()
    {
        $permissions = Permission::orderBy('module')->orderBy('order')->get()->groupBy('module');
        return view('admin.roles.create', compact('permissions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:roles,slug',
            'description' => 'nullable|string',
            'order' => 'nullable|integer',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'order' => $validated['order'] ?? 0,
            'is_system' => false,
        ]);

        if (isset($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        return redirect()->route('admin.roles.index')->with('success', 'نقش با موفقیت ایجاد شد');
    }

    public function show(Role $role)
    {
        $role->load('permissions');
        $allPermissions = Permission::orderBy('module')->orderBy('order')->get()->groupBy('module');
        return view('admin.roles.show', compact('role', 'allPermissions'));
    }

    public function edit(Role $role)
    {
        if ($role->is_system) {
            return redirect()->route('admin.roles.index')->with('error', 'نمی‌توان نقش سیستمی را ویرایش کرد');
        }

        $permissions = Permission::orderBy('module')->orderBy('order')->get()->groupBy('module');
        $role->load('permissions');
        return view('admin.roles.edit', compact('role', 'permissions'));
    }

    public function update(Request $request, Role $role)
    {
        if ($role->is_system) {
            return redirect()->route('admin.roles.index')->with('error', 'نمی‌توان نقش سیستمی را ویرایش کرد');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:roles,slug,' . $role->id,
            'description' => 'nullable|string',
            'order' => 'nullable|integer',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'order' => $validated['order'] ?? 0,
        ]);

        if (isset($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        } else {
            $role->permissions()->detach();
        }

        return redirect()->route('admin.roles.index')->with('success', 'نقش با موفقیت بروزرسانی شد');
    }

    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return redirect()->route('admin.roles.index')->with('error', 'نمی‌توان نقش سیستمی را حذف کرد');
        }

        $role->delete();
        return redirect()->route('admin.roles.index')->with('success', 'نقش با موفقیت حذف شد');
    }
}
