# قرارداد API و قابلیت اطمینان چت گروهی — فاز ۲

## Envelope سازگار

تمام پاسخ‌های JSON مسیرهای چت دارای فیلدهای زیر هستند و کلیدهای قدیمی تا پایان مهاجرت حفظ می‌شوند:

```json
{
  "status": "success|error",
  "data": {},
  "error": null,
  "meta": {"api_version": "2026-08-05", "http_status": 200},
  "request_id": "uuid"
}
```

در خطا، `error` شامل `code`, `message`, `details`, `retryable` است. شناسهٔ درخواست در header با نام `X-Request-ID` نیز بازگردانده می‌شود.

## Idempotency

- کلاینت عملیات write را با `Idempotency-Key` بین ۸ تا ۱۰۰ نویسه ارسال می‌کند.
- scope کلید از user و route ساخته می‌شود؛ استفادهٔ یک کلید در route دیگر مستقل است.
- replay با payload یکسان همان status/body را با header `Idempotency-Replayed: true` بازمی‌گرداند.
- استفادهٔ مجدد با payload متفاوت خطای `409 conflict` است.
- درخواست هم‌زمانِ در حال پردازش `409 request_in_progress` و `Retry-After: 1` می‌گیرد.
- پاسخ 5xx ذخیره نمی‌شود و اجرای بعدی مجاز است. رکوردها پس از TTL یک‌روزه قابل پاک‌سازی‌اند.

## Retry و state

- کلاینت فقط یک retry برای خطای شبکه یا 5xx انجام می‌دهد و همان کلید را نگه می‌دارد.
- timeout پیش‌فرض ۱۵ ثانیه و قابل تنظیم با `window.__groupChatRequestTimeoutMs` است.
- optimistic message با state برابر `pending` ساخته، پس از پاسخ به `sent` تبدیل و هنگام خطا rollback می‌شود.
- state ذخیره‌شدهٔ پیام یکی از `sent`, `delivered`, `read`, `failed`, `deleted` است؛ در این فاز مسیر اصلی `sent/deleted` فعال شده و رسیدهای چندکاربره همچنان در مدل read موجود حفظ می‌شوند.

## حذف و ویرایش

- پس از اجرای migration، حذف پیام tombstone ایجاد می‌کند و شناسه و ترتیب رکورد حفظ می‌شود.
- متن و مسیر رسانه از tombstone حذف و actor/time ثبت می‌شود.
- هر ویرایش پیام old/new content، actor و زمان را در `group_chat_content_edits` ثبت می‌کند.

## Rollback

سه flag مستقل در `config/group-chat.php` وجود دارد: `api_envelope_v1`, `idempotency_v1`, `message_lifecycle_v1`. خاموش‌کردن هر flag مسیر همان قابلیت را بدون حذف داده متوقف می‌کند.
