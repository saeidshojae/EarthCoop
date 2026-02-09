<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>کد تأیید ایمیل</title>
    <style>
        body {
            font-family: Tahoma, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            padding: 20px;
            text-align: right;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .verification-code {
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            padding: 20px;
            margin: 20px 0;
            background: #f8f9fa;
            border-radius: 5px;
            letter-spacing: 5px;
        }
        .note {
            font-size: 14px;
            color: #666;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>کد فراموشی رمز عبور</h2>
        <p>سلام،</p>
        <p>کد تاییدیه هویت برای تغییر رمز عبور</p>
        
        <div class="verification-code">
            {{ $code }}
        </div>
        
        <p class="note">این کد به مدت ۱۰ دقیقه معتبر است.</p>
        <p class="note">اگر شما درخواست این کد را نداده‌اید، لطفاً این ایمیل را نادیده بگیرید.</p>
    </div>
</body>
</html> 