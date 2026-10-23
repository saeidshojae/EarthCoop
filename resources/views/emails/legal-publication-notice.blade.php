<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>به‌روزرسانی اسناد حقوقی EarthCoop</title>
</head>
<body style="font-family:Tahoma,Arial,sans-serif;line-height:1.9;color:#1f2937;direction:rtl;text-align:right">
    <h2>به‌روزرسانی اسناد حقوقی EarthCoop</h2>
    <p>نسخه جدیدی از سند «{{ $documentTitle }}» منتشر شده است.</p>
    <p>شماره نسخه: {{ $version->version_label }}</p>
    @if($policy === 'material')
        <p><strong>اقدام لازم:</strong> این انتشار به‌عنوان تغییر اساسی ثبت شده است. لطفاً متن به‌روز‌شده را مطالعه و پذیرش خود را در حساب کاربری ثبت کنید.</p>
    @else
        <p>این انتشار برای اطلاع‌رسانی است و به‌خودی‌خود پذیرش مجدد را الزامی نمی‌کند.</p>
    @endif
    <p><a href="{{ $termsUrl }}">مشاهده اسناد حقوقی</a></p>
    <p>این پیام برای ثبت شفاف تغییرات حقوقی ارسال شده است.</p>
</body>
</html>
