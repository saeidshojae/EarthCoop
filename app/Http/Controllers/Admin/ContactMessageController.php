<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\Support\ContactMessageConversionService;
use App\Services\Support\ContactMessageReplyService;
use DomainException;
use Illuminate\Http\Request;

class ContactMessageController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:tickets.manage');
    }

    public function index(Request $request)
    {
        $query = ContactMessage::query()->with(['user', 'convertedTicket', 'handler']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($q) use ($term) {
                $q->where('subject', 'like', "%{$term}%")
                    ->orWhere('message', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        $messages = $query->latest()->paginate(25)->withQueryString();

        $stats = [
            'new' => ContactMessage::query()->where('status', 'new')->count(),
            'reviewing' => ContactMessage::query()->where('status', 'reviewing')->count(),
            'replied' => ContactMessage::query()->where('status', 'replied')->count(),
            'closed' => ContactMessage::query()->where('status', 'closed')->count(),
            'spam' => ContactMessage::query()->where('status', 'spam')->count(),
            'converted' => ContactMessage::query()->where('status', 'converted')->count(),
        ];

        return view('admin.contact-messages.index', compact('messages', 'stats'));
    }

    public function show(ContactMessage $contactMessage)
    {
        $contactMessage->load(['user', 'convertedTicket', 'handler']);
        $registeredUser = $contactMessage->user;

        return view('admin.contact-messages.show', compact('contactMessage', 'registeredUser'));
    }

    public function updateStatus(Request $request, ContactMessage $contactMessage)
    {
        if ($contactMessage->converted_ticket_id) {
            return back()->with('error', 'پیام تبدیل‌شده به تیکت از وضعیت تیکت پیروی می‌کند.');
        }

        $data = $request->validate([
            'status' => 'required|in:new,reviewing,replied,closed,spam',
        ]);

        $contactMessage->update([
            'status' => $data['status'],
            'handled_by' => $request->user()?->id,
            'handled_at' => in_array($data['status'], ['closed', 'spam'], true) ? now() : $contactMessage->handled_at,
        ]);

        return back()->with('success', 'وضعیت پیام تماس بروزرسانی شد.');
    }


    public function reply(
        Request $request,
        ContactMessage $contactMessage,
        ContactMessageReplyService $replies,
    ) {
        $data = $request->validate([
            'message' => 'required|string|min:5|max:10000',
        ]);

        try {
            $replies->reply(
                $contactMessage,
                (string) $data['message'],
                (int) $request->user()->id,
            );
        } catch (DomainException) {
            return back()->with('error', 'برای پاسخ ایمیلی، پیام تماس باید یک آدرس ایمیل معتبر داشته باشد.');
        }

        return back()->with('success', 'پاسخ از طریق مرکز ارتباطات در صف ارسال قرار گرفت.');
    }

    public function convert(
        Request $request,
        ContactMessage $contactMessage,
        ContactMessageConversionService $conversion,
    ) {
        try {
            $ticket = $conversion->convert($contactMessage, (int) $request->user()->id);
        } catch (DomainException) {
            return back()->with('error', 'این پیام در زمان ارسال به یک حساب واردشده متصل نبوده و برای جلوگیری از جعل هویت نمی‌تواند به تیکت کاربری تبدیل شود.');
        }

        return redirect()
            ->route('admin.tickets.show', $ticket)
            ->with('success', 'پیام تماس به تیکت کاربر تبدیل شد.');
    }
}
