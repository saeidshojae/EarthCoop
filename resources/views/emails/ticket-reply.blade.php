<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پاسخ به تیکت شما</title>
    <style>
        body {
            font-family: 'Vazirmatn', 'Tahoma', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #10b981 0%, #047857 100%);
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        .content {
            padding: 30px 20px;
        }
        .ticket-info {
            background: #f0fdf4;
            border: 2px solid #10b981;
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .ticket-code {
            font-size: 20px;
            font-weight: 700;
            color: #047857;
            margin: 5px 0;
        }
        .reply-box {
            background: #f9fafb;
            padding: 20px;
            margin: 20px 0;
            border-radius: 6px;
            border-right: 4px solid #10b981;
            white-space: pre-wrap;
        }
        .commenter-info {
            background: #e0f2fe;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-size: 14px;
            color: #075985;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #10b981;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: 600;
        }
        .btn:hover {
            background: #047857;
        }
        .footer {
            background: #f9fafb;
            padding: 20px;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
        }
        .footer a {
            color: #10b981;
            text-decoration: none;
        }
        .reply-notice {
            background: #fef3c7;
            border-right: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            font-size: 14px;
            color: #92400e;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>پاسخ جدید به تیکت شما</h1>
        </div>
        <div class="content">
            <p>با سلام {{ $ticket->name ?? 'کاربر عزیز' }}،</p>
            <p>پاسخ جدیدی برای تیکت شما ارسال شده است:</p>
            
            <div class="ticket-info">
                <div class="ticket-code">{{ $ticket->tracking_code }} - {{ $ticket->subject }}</div>
            </div>

            @if($commenter)
            <div class="commenter-info">
                <strong>پاسخ از:</strong> {{ $commenter->first_name ?? '' }} {{ $commenter->last_name ?? '' }}
                <br>
                <strong>تاریخ:</strong> {{ \Morilog\Jalali\Jalalian::fromDateTime($comment->created_at)->format('Y/m/d H:i') }}
            </div>
            @endif

            <div class="reply-box">
                {{ $comment->message }}
            </div>

            <div class="reply-notice">
                <strong>💡 نکته:</strong> شما می‌توانید با پاسخ دادن به این ایمیل، پاسخ خود را برای تیم پشتیبانی ارسال کنید. پاسخ شما به عنوان کامنت جدید به تیکت اضافه خواهد شد.
            </div>

            <p style="text-align: center;">
                <a href="{{ route('user.tickets.show', $ticket->id) }}" class="btn">مشاهده تیکت کامل</a>
            </p>
            
            <p>با تشکر،<br>تیم پشتیبانی Earth Coop</p>
        </div>
        <div class="footer">
            <p>این ایمیل به صورت خودکار ارسال شده است.</p>
            <p><a href="{{ url('/') }}">Earth Coop</a> | <a href="{{ route('user.tickets.index') }}">مدیریت تیکت‌های من</a></p>
        </div>
    </div>
</body>
</html>


