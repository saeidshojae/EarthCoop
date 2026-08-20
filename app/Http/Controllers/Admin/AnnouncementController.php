<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\Notifications\AnnouncementManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AnnouncementController extends Controller
{
    public function __construct(protected AnnouncementManagementService $announcements)
    {
    }

    public function index(Request $request)
    {
        $query = Announcement::with('creator');

        if ($request->filled('group_level')) {
            $query->where('group_level', $request->group_level);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $announcements = $query->latest()->get();
        $stats = [
            'total' => Announcement::count(),
            'by_level' => Announcement::selectRaw('group_level, COUNT(*) as count')
                ->groupBy('group_level')
                ->pluck('count', 'group_level')
                ->toArray(),
        ];

        return view('admin.announcements.index', compact('announcements', 'stats'));
    }

    public function create()
    {
        // Legacy route retained for compatibility with the existing admin UI.
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $validated['image'] = $this->storeImage($request, $validated['title']);
        $validated['should_pin'] = $request->has('should_pin');

        $this->announcements->create($validated, (int) auth()->id());

        return redirect()->route('admin.announcement.index')
            ->with('success', 'اطلاعیه با موفقیت ایجاد شد.');
    }

    public function show($id)
    {
        // Legacy route retained for compatibility.
    }

    public function edit(Announcement $announcement)
    {
        return view('admin.announcements.edit', compact('announcement'));
    }

    public function update(Request $request, Announcement $announcement)
    {
        $validated = $this->validated($request);
        $newImage = $this->storeImage($request, $validated['title']);

        if ($newImage !== null) {
            $this->deleteImageFile($announcement->image);
            $validated['image'] = $newImage;
        } else {
            $validated['image'] = $announcement->image;
        }

        $validated['should_pin'] = $request->has('should_pin');
        $this->announcements->update($announcement, $validated, (int) auth()->id());

        return redirect()->route('admin.announcement.index')
            ->with('success', 'اطلاعیه با موفقیت به‌روزرسانی شد.');
    }

    public function delete($id)
    {
        $announcement = Announcement::findOrFail($id);
        $image = $announcement->image;
        $this->announcements->delete($announcement);
        $this->deleteImageFile($image);

        return redirect()->route('admin.announcement.index')
            ->with('success', 'اطلاعیه با موفقیت حذف شد.');
    }

    public function unpin(Announcement $announcement)
    {
        $this->announcements->unpin($announcement);

        return redirect()->route('admin.announcement.index')
            ->with('success', 'اطلاعیه با موفقیت از پین خارج شد.');
    }

    /** @return array<string,mixed> */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'group_level' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'should_pin' => 'nullable|boolean',
        ]);
    }

    protected function storeImage(Request $request, string $title): ?string
    {
        if (! $request->hasFile('image')) return null;

        $image = $request->file('image');
        if (! $image || ! $image->isValid()) return null;

        $imageName = time() . '_' . Str::slug($title) . '.' . $image->getClientOriginalExtension();
        $image->move(public_path('images/announcements'), $imageName);

        return 'images/announcements/' . $imageName;
    }

    protected function deleteImageFile(?string $path): void
    {
        if ($path && file_exists(public_path($path))) {
            unlink(public_path($path));
        }
    }
}
