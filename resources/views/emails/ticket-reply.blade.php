@extends('emails.layout', ['emailTitle' => 'پاسخ جدید به تیکت شما'])

@section('content')
    <h1 style="margin-top:0;">پاسخ جدید به تیکت شما</h1>

    <p>{{ $ticket->name ?? 'کاربر عزیز' }}، پاسخ جدیدی برای تیکت شما ثبت شده است.</p>

    <div class="ec-box">
        <p style="margin-bottom:0;"><strong>{{ $ticket->tracking_code }}</strong> — {{ $ticket->subject }}</p>
    </div>

    @if($commenter)
        <p style="font-size:14px;color:#4b5563;">
            <strong>پاسخ از:</strong> {{ $commenter->displayName() ?: 'تیم پشتیبانی EarthCoop' }}
            <br>
            <strong>زمان:</strong> <x-temporal.date-time :value="$comment->created_at" style="short" />
        </p>
    @endif

    <div style="white-space:pre-wrap;background:#f9fafb;border-radius:10px;padding:16px;margin:18px 0;">{{ $comment->message }}</div>

    <p style="text-align:center;">
        <a href="{{ route('user.tickets.show', $ticket->id) }}" class="ec-button">مشاهده و پاسخ به تیکت</a>
    </p>

    <div class="ec-note">
        برای پاسخ، تیکت را در EarthCoop باز کنید. پاسخ مستقیم به این ایمیل در حال حاضر به تیکت شما اضافه نمی‌شود.
    </div>
@endsection
