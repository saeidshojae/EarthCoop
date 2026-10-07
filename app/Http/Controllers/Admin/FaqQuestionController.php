<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FaqQuestion;
use App\Services\Communication\CommunicationDispatcher;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class FaqQuestionController extends Controller
{
    /**
     * Display FAQ questions list with filters
     */
    public function index(
        Request $request,
        TemporalService $temporal,
        TemporalContextResolver $temporalContexts,
    )
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
        $temporalContext = $temporalContexts->forUser($request->user());
        try {
            if ($request->filled('from')) {
                $from = $temporal->parseDate((string) $request->input('from'), $temporalContext);
                $query->where('created_at', '>=', $temporal->startOfDay($from, $temporalContext));
            }
            if ($request->filled('to')) {
                $to = $temporal->parseDate((string) $request->input('to'), $temporalContext);
                $query->where('created_at', '<=', $temporal->endOfDay($to, $temporalContext));
            }
        } catch (\Throwable) {
            // Invalid optional filters are ignored.
        }

        $questions = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $temporalContext = $temporalContexts->forUser($request->user());
        $questions->through(function (FaqQuestion $question) use ($temporal, $temporalContext) {
            $question->setAttribute(
                'notified_at_display',
                $question->notified_at
                    ? $temporal->date($question->notified_at, $temporalContext, 'short')
                    : null,
            );

            return $question;
        });

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
    public function update(
        Request $request,
        FaqQuestion $question,
        CommunicationDispatcher $communications,
    ): RedirectResponse {
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

        // Once the canonical engine accepts the logical message, notified_at records
        // that notification work has been accepted. Delivery truth lives in the
        // communication recipient/attempt records and is retryable independently.
        if ($question->status === 'answered'
            && $question->answer
            && $question->contact_email
            && $question->notified_at === null) {
            try {
                $communications->dispatchExternal(
                    'faq.answer',
                    ['type' => 'faq', 'id' => (string) $question->id],
                    [['email' => $question->contact_email, 'locale' => 'fa']],
                    [
                        'title' => (string) $question->title,
                        'answer' => nl2br(e((string) $question->answer)),
                    ],
                    [
                        'deduplication_key' => 'faq:'.$question->id.':answered',
                        'priority' => 2,
                    ],
                );

                $question->forceFill(['notified_at' => Carbon::now()])->save();
            } catch (\Throwable $e) {
                Log::error('Failed to queue FAQ answer communication', [
                    'question_id' => $question->id,
                    'email' => $question->contact_email,
                    'error' => $e->getMessage(),
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
