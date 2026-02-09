<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Group;
use App\Models\Message;
use App\Models\PinnedMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AnnouncementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Announcement::with('creator');

        // فیلتر سطح گروه
        if ($request->filled('group_level')) {
            $query->where('group_level', $request->group_level);
        }

        // جستجو
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $announcements = $query->latest()->get();
        
        // آمار
        $stats = [
            'total' => Announcement::count(),
            'by_level' => Announcement::selectRaw('group_level, COUNT(*) as count')
                ->groupBy('group_level')
                ->pluck('count', 'group_level')
                ->toArray()
        ];
        
        return view('admin.announcements.index', compact('announcements', 'stats'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'group_level' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'should_pin' => 'nullable|boolean',
        ]);

        // آپلود عکس
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . Str::slug($validated['title']) . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('images/announcements'), $imageName);
            $validated['image'] = 'images/announcements/' . $imageName;
        }

        $validated['should_pin'] = $request->has('should_pin') ? true : false;
        $validated['created_by'] = auth()->id();

        $announcement = Announcement::create($validated);

        // اگر باید پین شود، پیام‌های پین شده ایجاد کن
        if ($validated['should_pin']) {
            $thisLevelGroups = Group::where('location_level', $validated['group_level'])->get();
            
            foreach($thisLevelGroups as $group) {
                // ایجاد پیام
                $messageContent = $validated['content'];
                if ($validated['image']) {
                    $messageContent .= "\n\n📷 تصویر اطلاعیه: " . asset($validated['image']);
                }
                
                $newMessage = Message::create([
                    'group_id' => $group->id,
                    'user_id' => auth()->id(),
                    'message' => $messageContent
                ]);

                // ایجاد پیام پین شده
                PinnedMessage::create([
                    'message_id' => $newMessage->id,
                    'group_id' => $group->id,
                    'pinned_by' => auth()->id(),
                    'announcement_id' => $announcement->id
                ]);
            }
        }

        return redirect()->route('admin.announcement.index')
            ->with('success', 'اطلاعیه با موفقیت ایجاد شد.');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Announcement $announcement)
    {
        return view('admin.announcements.edit', compact('announcement'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Announcement $announcement)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'group_level' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'should_pin' => 'nullable|boolean',
        ]);

        // آپلود عکس جدید
        if ($request->hasFile('image')) {
            // حذف عکس قدیمی
            if ($announcement->image && file_exists(public_path($announcement->image))) {
                unlink(public_path($announcement->image));
            }

            $image = $request->file('image');
            $imageName = time() . '_' . Str::slug($validated['title']) . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('images/announcements'), $imageName);
            $validated['image'] = 'images/announcements/' . $imageName;
        } else {
            // حفظ عکس قدیمی
            $validated['image'] = $announcement->image;
        }

        $validated['should_pin'] = $request->has('should_pin') ? true : false;

        $oldShouldPin = $announcement->should_pin;
        $oldGroupLevel = $announcement->group_level;

        $announcement->update($validated);

        // اگر وضعیت پین یا سطح گروه تغییر کرده، پیام‌های پین شده را به‌روزرسانی کن
        if ($oldShouldPin != $validated['should_pin'] || $oldGroupLevel != $validated['group_level']) {
            // حذف پیام‌های پین شده قدیمی
            PinnedMessage::where('announcement_id', $announcement->id)->delete();

            // اگر باید پین شود، پیام‌های پین شده جدید ایجاد کن
            if ($validated['should_pin']) {
                $thisLevelGroups = Group::where('location_level', $validated['group_level'])->get();
                
                foreach($thisLevelGroups as $group) {
                    // ایجاد پیام
                    $messageContent = $validated['content'];
                    if ($validated['image']) {
                        $messageContent .= "\n\n📷 تصویر اطلاعیه: " . asset($validated['image']);
                    }
                    
                    $newMessage = Message::create([
                        'group_id' => $group->id,
                        'user_id' => auth()->id(),
                        'message' => $messageContent
                    ]);

                    // ایجاد پیام پین شده
                    PinnedMessage::create([
                        'message_id' => $newMessage->id,
                        'group_id' => $group->id,
                        'pinned_by' => auth()->id(),
                        'announcement_id' => $announcement->id
                    ]);
                }
            }
        }

        return redirect()->route('admin.announcement.index')
            ->with('success', 'اطلاعیه با موفقیت به‌روزرسانی شد.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete($id)
    {
        $ann = Announcement::findOrFail($id);
        
        // حذف عکس
        if ($ann->image && file_exists(public_path($ann->image))) {
            unlink(public_path($ann->image));
        }

        // حذف پیام‌های پین شده مرتبط
        PinnedMessage::where('announcement_id', $ann->id)->delete();

        $ann->delete();
        
        return redirect()->route('admin.announcement.index')
            ->with('success', 'اطلاعیه با موفقیت حذف شد.');
    }

    /**
     * Unpin announcement from all groups
     */
    public function unpin(Announcement $announcement)
    {
        // حذف پیام‌های پین شده
        PinnedMessage::where('announcement_id', $announcement->id)->delete();

        // به‌روزرسانی وضعیت پین
        $announcement->update(['should_pin' => false]);

        return redirect()->route('admin.announcement.index')
            ->with('success', 'اطلاعیه با موفقیت از پین خارج شد.');
    }
}
