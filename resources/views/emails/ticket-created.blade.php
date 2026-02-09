<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تیکت جدید شما</title>
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
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .ticket-code {
            font-size: 24px;
            font-weight: 700;
            color: #047857;
            margin: 10px 0;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #6b7280;
        }
        .info-value {
            color: #111827;
        }
        .message-box {
            background: #f9fafb;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            border-right: 4px solid #10b981;
            white-space: pre-wrap;
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
        .priority-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .priority-high {
            background: #fee2e2;
            color: #991b1b;
        }
        .priority-normal {
            background: #fef3c7;
            color: #92400e;
        }
        .priority-low {
            background: #d1fae5;
            color: #065f46;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>تیکت جدید شما ایجاد شد</h1>
        </div>
        <div class="content">
            <p>با سلام {{ $ticket->name ?? 'کاربر عزیز' }}،</p>
            <p>تیکت پشتیبانی شما با موفقیت ثبت شد. جزئیات تیکت در زیر آمده است:</p>
            
            <div class="ticket-info">
                <div class="ticket-code">کد پیگیری: {{ $ticket->tracking_code }}</div>
                
                <div class="info-row">
                    <span class="info-label">موضوع:</span>
                    <span class="info-value">{{ $ticket->subject }}</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">وضعیت:</span>
                    <span class="info-value">{{ $ticket->getStatusLabelAttribute() }}</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">اولویت:</span>
                    <span class="info-value">
                        <span class="priority-badge priority-{{ $ticket->priority }}">{{ $ticket->getPriorityLabelAttribute() }}</span>
                    </span>
                </div>
                
                @if($ticket->category)
                <div class="info-row">
                    <span class="info-label">دسته‌بندی:</span>
                    <span class="info-value">{{ $ticket->category }}</span>
                </div>
                @endif
                
                <div class="info-row">
                    <span class="info-label">تاریخ ایجاد:</span>
                    <span class="info-value">{{ \Morilog\Jalali\Jalalian::fromDateTime($ticket->created_at)->format('Y/m/d H:i') }}</span>
                </div>
            </div>

            <div class="message-box">
                {{ $ticket->message }}
            </div>

            <p style="text-align: center;">
                <a href="{{ route('user.tickets.show', $ticket->id) }}" class="btn">مشاهده تیکت</a>
            </p>

            <p><strong>نکته مهم:</strong> پاسخ تیم پشتیبانی به این ایمیل ارسال خواهد شد. همچنین می‌توانید از طریق پنل کاربری خود به تیکت دسترسی داشته باشید.</p>
            
            <p>با تشکر،<br>تیم پشتیبانی Earth Coop</p>
        </div>
        <div class="footer">
            <p>این ایمیل به صورت خودکار ارسال شده است. لطفاً به این ایمیل پاسخ ندهید.</p>
            <p><a href="{{ url('/') }}">Earth Coop</a> | <a href="{{ route('user.tickets.index') }}">مدیریت تیکت‌های من</a></p>
        </div>
    </div>
</body>
</html>


