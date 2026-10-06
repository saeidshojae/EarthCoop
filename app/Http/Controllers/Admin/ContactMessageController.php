<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\Support\ContactMessageConversionService;
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
            'closed' => ContactMessage::query()->where('status', 'closed')->count(),
            'spam' => ContactMessage::query()->where('status', 'spam')->count(),
            'converted' => ContactMessage::query()->where('status', 'converted')->count(),
        ];

        return view('admin.contact-messages.index', compact('messages', 'stats'));
    }

    public function show(ContactMessage $contactMessage)
    {
        $contactMessage->load(['user', 'convertedTicket', 'handler']);
        $registeredUser = $contactMessage->email
            ? User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($contactMessage->email)])->first()
            : null;

        return view('admin.contact-messages.show', compact('contactMessage', 'registeredUser'));
    }

    public function updateStatus(Request $request, ContactMessage $contactMessage)
    {
        $data = $request->validate([
            'status' => 'required|in:new,reviewing,closed,spam',
        ]);

        $contactMessage->update([
            'status' => $data['status'],
            'handled_by' => $request->user()?->id,
            'handled_at' => in_array($data['status'], ['closed', 'spam'], true) ? now() : $contactMessage->handled_at,
        ]);

        return back()->with('success', 'وضعیت پیام تماس بروزرسانی شد.');
    }

    public function convert(
        Request $request,
        ContactMessage $contactMessage,
        ContactMessageConversionService $conversion,
    ) {
        try {
            $ticket = $conversion->convert($contactMessage, (int) $request->user()->id);
        } catch (DomainException) {
            return back()->with('error', 'این پیام به ایمیل یک عضو ثبت‌شده متصل نیست و نمی‌تواند به تیکت کاربری تبدیل شود.');
        }

        return redirect()
            ->route('admin.tickets.show', $ticket)
            ->with('success', 'پیام تماس به تیکت کاربر تبدیل شد.');
    }
}
