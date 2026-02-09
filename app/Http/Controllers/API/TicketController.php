<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketAttachment;
use App\Models\TicketTag;
use App\Services\TicketTriageService;
use App\Services\TicketSlaService;
use App\Services\EmailTicketIntegrationService;
use App\Traits\LogsTicketActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * کنترلر API برای تیکت‌های پشتیبانی
 * 
 * این کنترلر API عمومی برای ایجاد و مدیریت تیکت‌های پشتیبانی را فراهم می‌کند
 */
class TicketController extends Controller
{
    use LogsTicketActivity;

    protected TicketTriageService $triage;
    protected TicketSlaService $sla;
    protected EmailTicketIntegrationService $emailService;

    public function __construct(
        TicketTriageService $triage,
        TicketSlaService $sla,
        EmailTicketIntegrationService $emailService
    ) {
        $this->middleware('auth:sanctum');
        $this->triage = $triage;
        $this->sla = $sla;
        $this->emailService = $emailService;
    }

    /**
     * لیست تیکت‌های کاربر
     * 
     * GET /api/tickets
     */
    public function index(Request $request)
    {
        $query = Ticket::where('user_id', auth()->id())
            ->orWhere('email', auth()->user()->email);

        // فیلتر بر اساس وضعیت
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // فیلتر بر اساس اولویت
        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        // فیلتر بر اساس دسته‌بندی
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
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

        $tickets = $query->with(['assignee:id,first_name,last_name', 'tags:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $tickets->items(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    /**
     * ایجاد تیکت جدید
     * 
     * POST /api/tickets
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
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

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی داده‌ها',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // ایجاد کد پیگیری منحصر به فرد
        do {
            $trackingCode = 'TK-' . strtoupper(Str::random(8));
        } while (Ticket::where('tracking_code', $trackingCode)->exists());

        // تریاژ خودکار
        $triage = $this->triage->triage($data['subject'], $data['message']);

        // محاسبه SLA
        $slaDeadline = $this->sla->calculateDeadline(
            new Ticket(['priority' => $data['priority'] ?? $triage['priority'], 'created_at' => now()])
        );

        $user = auth()->user();

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'tracking_code' => $trackingCode,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status' => 'open',
            'priority' => $data['priority'] ?? $triage['priority'],
            'category' => $data['category'] ?? null,
            'assignee_id' => $triage['assignee_id'] ?? null,
            'sla_deadline' => $slaDeadline,
            'name' => $user->fullName(),
            'email' => $user->email,
            'phone' => $user->phone ?? null,
        ]);

        // افزودن تگ‌ها
        if (!empty($data['tags'])) {
            $ticket->tags()->attach($data['tags']);
        }

        // آپلود فایل‌های ضمیمه
        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('tickets/attachments', 'public');
                
                $attachment = TicketAttachment::create([
                    'ticket_id' => $ticket->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'uploaded_by' => $user->id,
                ]);

                $attachments[] = $attachment;
            }
        }

        // ثبت فعالیت
        $this->logTicketCreated($ticket);

        // ارسال ایمیل به کاربر
        try {
            $this->emailService->sendTicketCreatedEmail($ticket);
        } catch (\Exception $e) {
            \Log::error('خطا در ارسال ایمیل ایجاد تیکت: ' . $e->getMessage());
        }

        $ticket->load(['assignee:id,first_name,last_name', 'tags:id,name', 'attachments']);

        return response()->json([
            'success' => true,
            'message' => 'تیکت با موفقیت ایجاد شد',
            'data' => $ticket,
        ], 201);
    }

    /**
     * مشاهده تیکت خاص
     * 
     * GET /api/tickets/{id}
     */
    public function show($id)
    {
        $ticket = Ticket::where('id', $id)
            ->where(function($q) {
                $q->where('user_id', auth()->id())
                  ->orWhere('email', auth()->user()->email);
            })
            ->with([
                'assignee:id,first_name,last_name,email',
                'user:id,first_name,last_name,email',
                'comments' => function($q) {
                    $q->orderBy('created_at', 'asc');
                },
                'comments.user:id,first_name,last_name',
                'comments.attachments',
                'tags:id,name,color',
                'attachments',
                'activities.user:id,first_name,last_name',
            ])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $ticket,
        ]);
    }

    /**
     * افزودن کامنت به تیکت
     * 
     * POST /api/tickets/{id}/comments
     */
    public function addComment(Request $request, $id)
    {
        $ticket = Ticket::where('id', $id)
            ->where(function($q) {
                $q->where('user_id', auth()->id())
                  ->orWhere('email', auth()->user()->email);
            })
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'message' => 'required|string|min:5',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,pdf,doc,docx,txt,zip,rar',
        ], [
            'message.required' => 'پیام الزامی است',
            'message.min' => 'پیام باید حداقل 5 کاراکتر باشد',
            'attachments.*.max' => 'حجم هر فایل نمی‌تواند بیشتر از 10 مگابایت باشد',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی داده‌ها',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $user = auth()->user();

        // تنظیم first_response_at در صورت اولین کامنت
        if (!$ticket->first_response_at && $ticket->comments()->count() > 0) {
            $ticket->first_response_at = now();
        }

        // تغییر وضعیت تیکت در صورت بسته بودن
        if ($ticket->status === 'closed') {
            $ticket->status = 'open';
        }
        $ticket->save();

        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $data['message'],
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
                    'uploaded_by' => $user->id,
                ]);
            }
        }

        // ثبت فعالیت
        $this->logCommentAdded($ticket, $comment);

        // ارسال ایمیل به پشتیبانی
        try {
            $this->emailService->sendTicketReplyToEmail($ticket, $comment);
        } catch (\Exception $e) {
            \Log::error('خطا در ارسال ایمیل پاسخ تیکت: ' . $e->getMessage());
        }

        $comment->load(['user:id,first_name,last_name', 'attachments']);

        return response()->json([
            'success' => true,
            'message' => 'کامنت با موفقیت افزوده شد',
            'data' => $comment,
        ], 201);
    }

    /**
     * بستن تیکت
     * 
     * PUT /api/tickets/{id}/close
     */
    public function close($id)
    {
        $ticket = Ticket::where('id', $id)
            ->where(function($q) {
                $q->where('user_id', auth()->id())
                  ->orWhere('email', auth()->user()->email);
            })
            ->firstOrFail();

        if ($ticket->status === 'closed') {
            return response()->json([
                'success' => false,
                'message' => 'تیکت قبلاً بسته شده است',
            ], 400);
        }

        $ticket->status = 'closed';
        $ticket->resolved_at = now();
        $ticket->save();

        // ثبت فعالیت
        $this->logStatusChanged($ticket, 'closed', $ticket->status);

        return response()->json([
            'success' => true,
            'message' => 'تیکت با موفقیت بسته شد',
            'data' => $ticket,
        ]);
    }

    /**
     * آپدیت جزئی تیکت (فقط برای فیلدهای محدود)
     * 
     * PUT /api/tickets/{id}
     */
    public function update(Request $request, $id)
    {
        $ticket = Ticket::where('id', $id)
            ->where(function($q) {
                $q->where('user_id', auth()->id())
                  ->orWhere('email', auth()->user()->email);
            })
            ->firstOrFail();

        // کاربران فقط می‌توانند تیکت‌های باز را آپدیت کنند
        if ($ticket->status === 'closed') {
            return response()->json([
                'success' => false,
                'message' => 'نمی‌توان تیکت بسته را ویرایش کرد',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'priority' => 'sometimes|in:low,normal,high',
            'category' => 'sometimes|string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی داده‌ها',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // آپدیت فقط فیلدهای مجاز
        if (isset($data['priority'])) {
            $oldPriority = $ticket->priority;
            $ticket->priority = $data['priority'];
            
            // محاسبه مجدد SLA
            $slaDeadline = $this->sla->calculateDeadline(
                new Ticket(['priority' => $data['priority'], 'created_at' => $ticket->created_at])
            );
            $ticket->sla_deadline = $slaDeadline;
            
            // ثبت فعالیت
            $this->logFieldChanged($ticket, 'priority', $oldPriority, $data['priority']);
        }

        if (isset($data['category'])) {
            $oldCategory = $ticket->category;
            $ticket->category = $data['category'];
            
            // ثبت فعالیت
            $this->logFieldChanged($ticket, 'category', $oldCategory, $data['category']);
        }

        $ticket->save();

        return response()->json([
            'success' => true,
            'message' => 'تیکت با موفقیت به‌روزرسانی شد',
            'data' => $ticket->fresh(['assignee:id,first_name,last_name', 'tags:id,name']),
        ]);
    }

    /**
     * دریافت فایل ضمیمه
     * 
     * GET /api/tickets/{id}/attachments/{attachment_id}/download
     */
    public function downloadAttachment($id, $attachmentId)
    {
        $ticket = Ticket::where('id', $id)
            ->where(function($q) {
                $q->where('user_id', auth()->id())
                  ->orWhere('email', auth()->user()->email);
            })
            ->firstOrFail();

        $attachment = TicketAttachment::where('id', $attachmentId)
            ->where('ticket_id', $ticket->id)
            ->firstOrFail();

        if (!Storage::disk('public')->exists($attachment->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'فایل یافت نشد',
            ], 404);
        }

        return Storage::disk('public')->download(
            $attachment->file_path,
            $attachment->file_name
        );
    }

    /**
     * دریافت آمار تیکت‌های کاربر
     * 
     * GET /api/tickets/stats
     */
    public function stats()
    {
        $userId = auth()->id();
        $userEmail = auth()->user()->email;

        $stats = [
            'total' => Ticket::where(function($q) use ($userId, $userEmail) {
                $q->where('user_id', $userId)
                  ->orWhere('email', $userEmail);
            })->count(),
            'open' => Ticket::where(function($q) use ($userId, $userEmail) {
                $q->where('user_id', $userId)
                  ->orWhere('email', $userEmail);
            })->where('status', 'open')->count(),
            'in_progress' => Ticket::where(function($q) use ($userId, $userEmail) {
                $q->where('user_id', $userId)
                  ->orWhere('email', $userEmail);
            })->where('status', 'in-progress')->count(),
            'closed' => Ticket::where(function($q) use ($userId, $userEmail) {
                $q->where('user_id', $userId)
                  ->orWhere('email', $userEmail);
            })->where('status', 'closed')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
