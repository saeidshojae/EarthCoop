@extends('emails.layout', ['emailTitle' => 'تیکت شما ثبت شد'])

@section('content')
    <h1 style="margin-top:0;">تیکت شما ثبت شد</h1>

    <p>{{ $ticket->name ?? 'کاربر عزیز' }}، درخواست پشتیبانی شما با موفقیت ثبت شد.</p>

    <div class="ec-box">
        <p style="margin-bottom:8px;"><strong>کد پیگیری:</strong> {{ $ticket->tracking_code }}</p>
        <p style="margin-bottom:8px;"><strong>موضوع:</strong> {{ $ticket->subject }}</p>
        <p style="margin-bottom:8px;"><strong>وضعیت:</strong> {{ $ticket->getStatusLabelAttribute() }}</p>
        <p style="margin-bottom:8px;"><strong>اولویت:</strong> {{ $ticket->getPriorityLabelAttribute() }}</p>
        @if($ticket->category)
            <p style="margin-bottom:8px;"><strong>دسته‌بندی:</strong> {{ $ticket->category }}</p>
        @endif
        <p style="margin-bottom:0;"><strong>زمان ثبت:</strong> <x-temporal.date-time :value="$ticket->created_at" style="short" /></p>
    </div>

    <p><strong>متن درخواست:</strong></p>
    <div style="white-space:pre-wrap;background:#f9fafb;border-radius:10px;padding:14px 16px;margin-bottom:20px;">{{ $ticket->message }}</div>

    <p style="text-align:center;">
        <a href="{{ route('user.tickets.show', $ticket->id) }}" class="ec-button">مشاهده تیکت</a>
    </p>

    <p>پاسخ‌های تیم پشتیبانی داخل EarthCoop ثبت می‌شوند و در صورت فعال بودن اعلان ایمیلی، نسخه‌ای از پاسخ برای شما نیز ارسال می‌شود.</p>

    <div class="ec-note">
        برای ادامه گفتگو یا ارسال پاسخ، تیکت را در EarthCoop باز کنید. پاسخ مستقیم به این ایمیل در حال حاضر به تیکت اضافه نمی‌شود.
    </div>
@endsection
