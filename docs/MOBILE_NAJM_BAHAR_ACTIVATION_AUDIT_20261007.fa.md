# ممیزی فعال‌سازی نیتیو نجم بهار — ۷ اکتبر ۲۰۲۶

## نتیجه

سمت سرور مسیر واقعی فعال‌سازی از امتیاز مشارکت وجود دارد و از نظر حسابداری، idempotency دامنه و مصرف ledger امتیاز دارای زیرساخت مناسب است. موبایل فعلاً فقط eligibility را می‌خواند و mutation نیتیو ندارد.

هیچ فعال‌سازی واقعی، deploy یا تغییر production در این ممیزی انجام نشده است.

## قرارداد موجود

- GET `/api/v1/najm-bahar/activation/eligibility` وضعیت policy، امتیاز قابل تبدیل، نسبت امتیاز به گل، ظرفیت فعال‌سازی و موجودی Dim/Active را برمی‌گرداند.
- POST `/api/v1/najm-bahar/activation` به `Idempotency-Key` نیاز دارد.
- source فقط `participation` است.
- مبلغ فعال‌سازی را کلاینت تعیین نمی‌کند؛ سرور از points و policy محاسبه می‌کند.
- مصرف امتیازها در `UserPointConversion` و `UserPointConsumption` ثبت می‌شود.
- `MonetaryService::activateDim()` فقط bucket را از Dim به Active تبدیل می‌کند و پول جدید نمی‌سازد.
- replay دامنه با request_key همان conversion را برمی‌گرداند.
- policy disabled، کمبود امتیاز و کمبود Dim fail-closed هستند.

## شکاف‌های Native

1. eligibility هنوز `activation_contract_version` و `policy_version_id` را به‌عنوان قرارداد consent صریح ارائه نمی‌کند.
2. POST فقط `source` و `points` می‌گیرد؛ هیچ `expected` snapshot ندارد. نسبت، policy، امتیاز باقیمانده یا Dim می‌تواند بین review و submit عوض شود.
3. سرویس legacy هر requested points را به پایین‌ترین مضرب ratio گرد می‌کند. مثال: 250 با ratio=100 به 200 مصرفی تبدیل می‌شود. برای Native confirmation این رفتار نامناسب است؛ Native باید فقط مضرب دقیق ratio را بپذیرد.
4. بعد از transport ambiguity، endpoint خواندنی برای reconcile با همان idempotency key وجود ندارد.
5. موبایل DTO فعلی policy version/source را decode می‌کند ولی نگه نمی‌دارد و mutation intent/controller ندارد.
6. financial POST باید مثل payment/transfer با `allowAutomaticRetry=false` اجرا شود؛ هیچ offline financial queue مجاز نیست.
7. eligibility ممکن است point capacity بزرگ‌تر از ظرفیت واقعی Dim نشان دهد؛ UI باید سقف واقعی قابل فعال‌سازی را بر اساس `max_activation_gol` و ratio بفهمد، نه فقط `max_convertible_points`.

## تصمیم

نسخهٔ اول Native Activation فقط:
- source = participation؛
- مقصد = main Najm Bahar account همان کاربر؛
- requested points = مضرب دقیق ratio؛
- activation amount = کاملاً server-derived؛
- review = snapshot صریح policy/ratio/remaining points/Dim/max activation؛
- submit = idempotent، no automatic retry؛
- ambiguous result = GET-only reconciliation؛
- restart = no replay.

Legacy POST بدون `expected` رفتار موجود خود را حفظ می‌کند تا compatibility نشکند.
