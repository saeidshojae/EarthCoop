<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>کد دعوت شما</title>
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
        .code-box {
            background: #f0fdf4;
            border: 2px dashed #10b981;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
            border-radius: 8px;
        }
        .code {
            font-size: 32px;
            font-weight: 700;
            color: #047857;
            letter-spacing: 5px;
            font-family: 'Courier New', monospace;
        }
        .instructions {
            background: #f9fafb;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            border-right: 4px solid #10b981;
        }
        .instructions p {
            margin: 5px 0;
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
            <h1>کد دعوت شما آماده است!</h1>
        </div>
        <div class="content">
            <p>با سلام،</p>
            <p>درخواست کد دعوت شما برای پیوستن به زیست‌بوم همکاری‌های جهانی <strong>Earth Coop</strong> تأیید شد.</p>
            
            <div class="code-box">
                <p style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;">کد دعوت شما:</p>
                <div class="code">{{ $code }}</div>
            </div>

            <div class="instructions">
                <p><strong>نحوه استفاده:</strong></p>
                <p>1. به صفحه ثبت‌نام بروید</p>
                <p>2. کد دعوت بالا را در قسمت مربوطه وارد کنید</p>
                <p>3. فرم ثبت‌نام را تکمیل کنید</p>
            </div>

            @if($expireAt)
            <p><strong>توجه:</strong> این کد تا {{ \Carbon\Carbon::parse($expireAt)->format('Y/m/d H:i') }} معتبر است.</p>
            @endif
            
            <p>با تشکر،<br>تیم Earth Coop</p>
        </div>
        <div class="footer">
            <p>این ایمیل به صورت خودکار ارسال شده است. لطفاً به این ایمیل پاسخ ندهید.</p>
            <p><a href="{{ url('/') }}">Earth Coop</a></p>
        </div>
    </div>
</body>
</html>
