<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::orderBy('module')->orderBy('order')->get()->groupBy('module');
        return view('admin.permissions.index', compact('permissions'));
    }

    public function create()
    {
        return view('admin.permissions.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:permissions,slug',
            'description' => 'nullable|string',
            'module' => 'nullable|string|max:255',
            'order' => 'nullable|integer',
        ]);

        Permission::create($validated);

        return redirect()->route('admin.permissions.index')->with('success', 'دسترسی با موفقیت ایجاد شد');
    }

    public function edit(Permission $permission)
    {
        return view('admin.permissions.edit', compact('permission'));
    }

    public function update(Request $request, Permission $permission)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:permissions,slug,' . $permission->id,
            'description' => 'nullable|string',
            'module' => 'nullable|string|max:255',
            'order' => 'nullable|integer',
        ]);

        $permission->update($validated);

        return redirect()->route('admin.permissions.index')->with('success', 'دسترسی با موفقیت بروزرسانی شد');
    }

    public function destroy(Permission $permission)
    {
        $permission->delete();
        return redirect()->route('admin.permissions.index')->with('success', 'دسترسی با موفقیت حذف شد');
    }
}
