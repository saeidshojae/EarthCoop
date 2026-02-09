<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>درخواست کد دعوت</title>
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
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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
        .message {
            background: #fef2f2;
            border-right: 4px solid #ef4444;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .message p {
            margin: 0;
            color: #991b1b;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>درخواست کد دعوت شما بررسی شد</h1>
        </div>
        <div class="content">
            <p>با سلام،</p>
            <p>متأسفانه درخواست کد دعوت شما برای پیوستن به زیست‌بوم همکاری‌های جهانی Earth Coop در حال حاضر پذیرفته نشده است.</p>
            
            @if($adminNote)
            <div class="message">
                <p><strong>یادداشت مدیریت:</strong></p>
                <p>{{ $adminNote }}</p>
            </div>
            @endif

            <p>در صورت تمایل می‌توانید مجدداً درخواست خود را ارسال کنید یا با تیم پشتیبانی ما تماس بگیرید.</p>
            <p>با تشکر،<br>تیم Earth Coop</p>
        </div>
        <div class="footer">
            <p>این ایمیل به صورت خودکار ارسال شده است. لطفاً به این ایمیل پاسخ ندهید.</p>
            <p><a href="{{ url('/') }}">Earth Coop</a></p>
        </div>
    </div>
</body>
</html>

