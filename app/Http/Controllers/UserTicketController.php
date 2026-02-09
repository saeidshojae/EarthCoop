<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketAttachment;
use App\Models\TicketTag;
use App\Models\KbArticle;
use App\Services\TicketTriageService;
use App\Services\TicketSlaService;
use App\Traits\LogsTicketActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class UserTicketController extends Controller
{
    use LogsTicketActivity;

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of user's tickets
     */
    public function index(Request $request)
    {
        $query = Ticket::where('user_id', auth()->id())
            ->orWhere('email', auth()->user()->email);

        // فیلتر بر اساس وضعیت
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // جستجو
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function($s) use ($q) {
                $s->where('tracking_code', 'like', "%{$q}%")
                  ->orWhere('subject', 'like', "%{$q}%")
                  ->orWhere('message', 'like', "%{$q}%");
            });
        }

        $tickets = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        // آمار
        $stats = [
            'total' => Ticket::where('user_id', auth()->id())
                ->orWhere('email', auth()->user()->email)
                ->count(),
            'open' => Ticket::where(function($q) {
                $q->where('user_id', auth()->id())
                  ->orWhere('email', auth()->user()->email);
            })->where('status', 'open')->count(),
            'in_progress' => Ticket::where(function($q) {
                $q->where('user_id', auth()->id())
                  ->orWhere('email', auth()->user()->email);
            })->where('status', 'in-progress')->count(),
            'closed' => Ticket::where(function($q) {
                $q->where('user_id', auth()->id())
                  ->orWhere('email', auth()->user()->email);
            })->where('status', 'closed')->count(),
        ];

        return view('user.tickets.index', compact('tickets', 'stats'));
    }

    /**
     * Show the form for creating a new ticket
     */
    public function create()
    {
        $tags = TicketTag::where('is_active', true)->orderBy('name')->get();
        $categories = [
            'technical' => 'مشکل فنی',
            'account' => 'حساب کاربری',
            'payment' => 'پرداخت',
            'general' => 'عمومی',
            'bug' => 'گزارش باگ',
            'feature' => 'درخواست ویژگی',
        ];
        $kbSuggestions = KbArticle::published()->latest('published_at')->take(4)->get();
        
        // آماده‌سازی داده‌های پیشنهادات برای JavaScript
        $kbSuggestionsJson = $kbSuggestions->map(function($article) {
            return [
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $article->excerpt,
                'category' => $article->category?->name,
            ];
        })->toArray();
        
        return view('user.tickets.create', compact('tags', 'categories', 'kbSuggestions', 'kbSuggestionsJson'));
    }

    /**
     * Store a newly created ticket
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|min:10',
            'priority' => 'nullable|in:low,normal,high',
            'category' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:ticket_tags,id',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,pdf,doc,docx,txt,zip,rar',
        ], [
            'subject.required' => 'موضوع الزامی است',
            'subject.max' => 'موضوع نمی‌تواند بیشتر از 255 کاراکتر باشد',
            'message.required' => 'پیام الزامی است',
            'message.min' => 'پیام باید حداقل 10 کاراکتر باشد',
            'attachments.*.max' => 'حجم هر فایل نمی‌تواند بیشتر از 10 مگابایت باشد',
            'attachments.*.mimes' => 'فرمت فایل مجاز نیست',
        ]);

        // ایجاد کد پیگیری منحصر به فرد
        do {
            $trackingCode = 'TK-' . strtoupper(Str::random(8));
        } while (Ticket::where('tracking_code', $trackingCode)->exists());

        // تریاژ خودکار
        $triageService = app(TicketTriageService::class);
        $triage = $triageService->triage($data['subject'], $data['message']);

        // محاسبه SLA
        $slaService = app(TicketSlaService::class);
        $slaDeadline = $slaService->calculateDeadline(
            new Ticket(['priority' => $data['priority'] ?? $triage['priority'], 'created_at' => now()])
        );

        $ticket = Ticket::create([
            'user_id' => auth()->id(),
            'tracking_code' => $trackingCode,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status' => 'open',
            'priority' => $data['priority'] ?? $triage['priority'],
            'category' => $data['category'] ?? null,
            'assignee_id' => $triage['assignee_id'],
            'sla_deadline' => $slaDeadline,
            'name' => auth()->user()->fullName(),
            'email' => auth()->user()->email,
            'phone' => auth()->user()->phone ?? null,
        ]);

        // افزودن تگ‌ها
        if (!empty($data['tags'])) {
            $ticket->tags()->attach($data['tags']);
        }

        // آپلود فایل‌های ضمیمه
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('tickets/attachments', 'public');
                
                TicketAttachment::create([
                    'ticket_id' => $ticket->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'uploaded_by' => auth()->id(),
                ]);
            }
        }

        // ثبت فعالیت
        $this->logTicketCreated($ticket);

        // ارسال نوتیفیکیشن
        try {
            auth()->user()->notify(new \App\Notifications\TicketCreatedNotification($ticket));
        } catch (\Exception $e) {
            \Log::error('Failed to send ticket notification: ' . $e->getMessage());
        }

        return redirect()->route('user.tickets.show', $ticket->id)
            ->with('success', 'تیکت شما با موفقیت ثبت شد. کد پیگیری: ' . $ticket->tracking_code);
    }

    /**
     * Display the specified ticket
     */
    public function show(Ticket $ticket)
    {
        // بررسی دسترسی
        if ($ticket->user_id != auth()->id() && $ticket->email != auth()->user()->email) {
            abort(403, 'شما دسترسی به این تیکت ندارید');
        }

        $ticket->load(['assignee', 'comments.user', 'comments.attachments', 'attachments', 'tags', 'activities.user']);

        $categories = [
            'technical' => 'مشکل فنی',
            'account' => 'حساب کاربری',
            'payment' => 'پرداخت',
            'general' => 'عمومی',
            'bug' => 'گزارش باگ',
            'feature' => 'درخواست ویژگی',
        ];

        return view('user.tickets.show', compact('ticket', 'categories'));
    }

    /**
     * Add a comment to a ticket
     */
    public function comment(Request $request, Ticket $ticket)
    {
        // بررسی دسترسی
        if ($ticket->user_id != auth()->id() && $ticket->email != auth()->user()->email) {
            abort(403, 'شما دسترسی به این تیکت ندارید');
        }

        // بررسی بسته بودن تیکت
        if ($ticket->status == 'closed') {
            return back()->with('error', 'این تیکت بسته شده است و امکان ارسال پاسخ وجود ندارد');
        }

        $data = $request->validate([
            'message' => 'required|string|min:5',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,pdf,doc,docx,txt,zip,rar',
        ], [
            'message.required' => 'پیام الزامی است',
            'message.min' => 'پیام باید حداقل 5 کاراکتر باشد',
            'attachments.*.max' => 'حجم هر فایل نمی‌تواند بیشتر از 10 مگابایت باشد',
            'attachments.*.mimes' => 'فرمت فایل مجاز نیست',
        ]);

        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'message' => $data['message']
        ]);

        // آپلود فایل‌های ضمیمه
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('tickets/attachments', 'public');
                
                TicketAttachment::create([
                    'ticket_id' => $ticket->id,
                    'comment_id' => $comment->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'uploaded_by' => auth()->id(),
                ]);
            }
        }

        // ثبت اولین پاسخ
        if (!$ticket->first_response_at) {
            $ticket->first_response_at = now();
            $ticket->save();
        }

        // تغییر وضعیت به در حال بررسی در صورت باز بودن
        $oldStatus = $ticket->status;
        if ($ticket->status === 'open') {
            $ticket->status = 'in-progress';
            $ticket->save();
            $this->logStatusChange($ticket, $oldStatus, 'in-progress');
        }

        // ثبت فعالیت
        $this->logCommentAdded($ticket);

        return back()->with('success', 'پاسخ شما با موفقیت ثبت شد');
    }
}


