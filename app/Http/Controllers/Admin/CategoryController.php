<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryGroupSetting;
use App\Models\GroupSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $groupSettings = GroupSetting::orderBy('level')->get();

        $query = Category::with(['groupSettings', 'groupSettingLinks'])->orderBy('name');

        if ($search = $request->get('q')) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        if ($groupFilter = $request->get('group')) {
            if ($groupFilter === 'all') {
                $query->whereHas('groupSettingLinks', function ($q) {
                    $q->where('group_setting_id', 0);
                });
            } elseif ($groupFilter === 'custom') {
                $query->whereHas('groupSettingLinks', function ($q) {
                    $q->where('group_setting_id', '>', 0);
                });
            } elseif ($groupFilter !== 'any') {
                $query->whereHas('groupSettingLinks', function ($q) use ($groupFilter) {
                    $q->where('group_setting_id', (int) $groupFilter);
                });
            }
        }

        $categories = $query->paginate(12)->withQueryString();

        $stats = [
            'total' => Category::count(),
            'global' => CategoryGroupSetting::where('group_setting_id', 0)->distinct('category_id')->count('category_id'),
            'custom' => CategoryGroupSetting::where('group_setting_id', '>', 0)->distinct('category_id')->count('category_id'),
        ];

        return view('admin.system-settings.categories.index', [
            'categories' => $categories,
            'groupSettings' => $groupSettings,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'group' => $groupFilter ?? 'any',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        DB::transaction(function () use ($data) {
            $category = Category::create(['name' => $data['name']]);
            $this->syncGroups($category, $data['groups']);
        });

        return redirect()->route('admin.categories.index')->with('success', 'دسته‌بندی جدید با موفقیت ثبت شد.');
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validatedData($request, $category);

        DB::transaction(function () use ($category, $data) {
            $category->update(['name' => $data['name']]);
            $this->syncGroups($category, $data['groups']);
        });

        return redirect()->route('admin.categories.index')->with('success', 'دسته‌بندی با موفقیت به‌روزرسانی شد.');
    }

    public function destroy(Category $category)
    {
        DB::transaction(function () use ($category) {
            $category->groupSettingLinks()->delete();
            $category->delete();
        });

        return redirect()->route('admin.categories.index')->with('success', 'دسته‌بندی حذف شد.');
    }

    protected function validatedData(Request $request, ?Category $category = null): array
    {
        $groupIds = GroupSetting::pluck('id')->map(fn ($id) => (int) $id)->toArray();

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->ignore($category?->id),
            ],
            'groups' => ['required', 'array', 'min:1'],
            'groups.*' => [
                'integer',
                function ($attribute, $value, $fail) use ($groupIds) {
                    $value = (int) $value;
                    if ($value === 0) {
                        return;
                    }
                    if (!in_array($value, $groupIds, true)) {
                        $fail('گروه انتخاب شده معتبر نیست.');
                    }
                },
            ],
        ]);
    }

    protected function syncGroups(Category $category, array $groups): void
    {
        $groupIds = collect($groups)->map(fn ($id) => (int) $id)->unique()->values();

        $category->groupSettingLinks()->delete();

        if ($groupIds->contains(0)) {
            $category->groupSettingLinks()->create(['group_setting_id' => 0]);
            return;
        }

        $category->groupSettingLinks()->createMany(
            $groupIds->map(fn ($id) => ['group_setting_id' => $id])->all()
        );
    }
}

