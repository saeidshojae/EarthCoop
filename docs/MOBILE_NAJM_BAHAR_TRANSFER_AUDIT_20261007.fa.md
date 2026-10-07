# ممیزی انتقال نیتیو نجم بهار — ۷ اکتبر ۲۰۲۶

## دامنه و نتیجه

این ممیزی بر قرارداد واقعی API v1 و مسیرهای canonical نجم بهار انجام شد. هیچ انتقال واقعی، deploy یا تغییر production انجام نشده است.

نتیجهٔ اصلی: `POST /api/v1/najm-bahar/transfers` امروز executor امن و idempotent دارد، اما read-contract لازم برای UI مالی قابل‌اعتماد را ندارد. کلاینت فعلی نمی‌تواند قبل از POST به‌طور authoritative بداند انتقال بیرونی باز است یا بسته، کدام حساب فرعی دقیقاً منبع مجاز و چه مقدار Active واقعاً قابل‌خرج دارد، یا مقصد واردشده به چه حساب فعالی تعلق دارد.

## قرارداد موجود

- POST نیازمند `Idempotency-Key` است و middleware کلید را به کاربر، route و fingerprint بدنه مقید می‌کند.
- replay همان درخواست، پاسخ قبلی را برمی‌گرداند؛ کلید یکسان با بدنهٔ متفاوت `idempotency_key_reused` می‌دهد؛ درخواست درحال اجرا `request_in_progress` است.
- payload فعلی: `source_account_id`، `destination_account_number`، `amount_gol`، `balance_bucket` و description اختیاری.
- مالکیت منبع در سرور حل می‌شود؛ authority flag از کلاینت پذیرفته نمی‌شود.
- موجودی Active رزروشده قابل خرج نیست.
- انتقال cross-owner قبل از حدنصاب سراسری قفل است.
- Dim بین اشخاص/نهادهای مستقل قابل انتقال نیست. صرف ارسال `balance_bucket=dim` این سیاست را دور نمی‌زند.
- قواعد canonical اجازه نمی‌دهند حساب اصلی کاربر مستقیماً به حساب مستقل دیگری منتقل کند؛ انتقال بیرونی در عمل باید از حساب فرعی انجام شود.
- انتقال داخلی Main↔Sub یا بین حساب‌های تحت مالکیت مؤثر یکسان، دامنه و قواعد دیگری دارد و نباید با ارسال بیرونی در یک UI مخلوط شود.

## شکاف‌های لازم قبل از UI

1. capability خواندنی برای باز/بسته بودن انتقال بیرونی و دلیل آن وجود ندارد.
2. API اصلی حساب فقط main را برمی‌گرداند؛ فهرست sourceهای owned subaccount با `available_active_gol` ندارد.
3. balance endpoint فعلی ownership مستقیم `user_id` را می‌سنجد و read model مناسبی برای mirror حساب فرعی نیست.
4. API v1 مقصد exact-account preview ندارد. وب قدیمی preview دارد، اما قرارداد/احراز/shape آن برای native v1 نیست.
5. POST فعلی snapshot تأییدشدهٔ کاربر را bind نمی‌کند؛ بین preview و submit ممکن است source availability، مقصد یا policy تغییر کند.
6. ApiClient اکنون امکان خاموش‌کردن retry خودکار مالی را دارد و پرداخت عضویت آن را استفاده می‌کند؛ transfer نیز باید همین الگو را استفاده کند.
7. هیچ financial offline queue مجاز نیست.

## تصمیم طراحی

نسخهٔ اول Native Bahar Transfer فقط «ارسال بیرونی» را پوشش می‌دهد:
- source: یک owned active subaccount؛
- destination: یک active destination subaccount که با شمارهٔ دقیق resolve شده؛
- bucket: فقط Active و در UI قابل انتخاب نیست؛
- amount: integer Gol مثبت؛
- description: اختیاری، حداکثر ۵۰۰؛
- policy: سرور authoritative؛
- Dim: توضیح داده می‌شود که انتقال بیرونی نیست؛
- internal redistribution و scheduled transfer خارج از این task هستند.

این scope کوچک‌تر از UI وب قدیمی است، اما دقیقاً با policy فعلی و boundary امن API v1 منطبق است.
