<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\FaqQuestionAnswered;
use App\Models\FaqQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class FaqQuestionController extends Controller
{
    /**
     * Display FAQ questions list with filters
     */
    public function index(Request $request)
    {
        $query = FaqQuestion::query();

        // فیلتر بر اساس وضعیت
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // فیلتر بر اساس انتشار
        if ($request->filled('published')) {
            $query->where('is_published', $request->published == '1' ? 1 : 0);
        }

        // فیلتر بر اساس دسته‌بندی
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // جستجو
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('question', 'like', "%{$search}%")
                  ->orWhere('answer', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%")
                  ->orWhere('contact_email', 'like', "%{$search}%");
            });
        }

        // فیلتر بر اساس تاریخ
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $questions = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        // آمار
        $stats = [
            'total' => FaqQuestion::count(),
            'new' => FaqQuestion::where('status', 'new')->count(),
            'in_progress' => FaqQuestion::where('status', 'in_progress')->count(),
            'answered' => FaqQuestion::where('status', 'answered')->count(),
            'published' => FaqQuestion::where('is_published', true)->count(),
        ];

        // دسته‌بندی‌های موجود
        $categories = FaqQuestion::whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values();

        return view('admin.faq.index', compact('questions', 'stats', 'categories'));
    }

    /**
     * Update FAQ question
     */
    public function update(Request $request, FaqQuestion $question): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'question' => ['required', 'string'],
            'answer' => ['nullable', 'string'],
            'status' => ['required', 'in:new,in_progress,answered'],
            'is_published' => ['nullable', 'boolean'],
            'category' => ['nullable', 'string', 'max:255'],
        ], [
            'title.required' => 'عنوان سوال الزامی است',
            'title.max' => 'عنوان سوال نمی‌تواند بیشتر از 255 کاراکتر باشد',
            'question.required' => 'متن سوال الزامی است',
            'status.required' => 'وضعیت الزامی است',
            'status.in' => 'وضعیت انتخاب شده معتبر نیست',
        ]);

        $question->fill([
            'title' => $data['title'],
            'question' => $data['question'],
            'answer' => $data['answer'] ?? null,
            'status' => $data['status'],
            'is_published' => $request->boolean('is_published'),
            'category' => $data['category'] ?? null,
        ]);

        if ($question->status === 'answered' && $question->answer) {
            $question->answered_at = $question->answered_at ?: Carbon::now();
        } elseif ($question->answer === null) {
            $question->answered_at = null;
        }

        $question->save();

        // ارسال ایمیل در صورت پاسخ داده شدن
        if ($question->status === 'answered'
            && $question->answer
            && $question->contact_email
            && $question->notified_at === null) {
            try {
                Mail::to($question->contact_email)->send(new FaqQuestionAnswered($question));
                $question->forceFill(['notified_at' => Carbon::now()])->save();
            } catch (\Exception $e) {
                \Log::error('Failed to send FAQ answer email', [
                    'question_id' => $question->id,
                    'email' => $question->contact_email,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return back()->with('success', 'سوال با موفقیت به‌روزرسانی شد.');
    }

    /**
     * Bulk actions on FAQ questions
     */
    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:faq_questions,id',
            'action' => 'required|in:delete,publish,unpublish,mark_answered,mark_in_progress'
        ]);

        $count = 0;
        $questions = FaqQuestion::whereIn('id', $validated['ids'])->get();

        foreach ($questions as $question) {
            switch ($validated['action']) {
                case 'delete':
                    $question->delete();
                    $count++;
                    break;
                case 'publish':
                    $question->update(['is_published' => true]);
                    $count++;
                    break;
                case 'unpublish':
                    $question->update(['is_published' => false]);
                    $count++;
                    break;
                case 'mark_answered':
                    $question->update(['status' => 'answered', 'answered_at' => Carbon::now()]);
                    $count++;
                    break;
                case 'mark_in_progress':
                    $question->update(['status' => 'in_progress']);
                    $count++;
                    break;
            }
        }

        return back()->with('success', "عملیات روی {$count} سوال انجام شد.");
    }

    /**
     * Delete FAQ question
     */
    public function destroy(FaqQuestion $question): RedirectResponse
    {
        $question->delete();
        return back()->with('success', 'سوال با موفقیت حذف شد.');
    }
}

