# ریزتسک های فاز 1 (Runtime + Intelligent Layer)

## هدف
- ساخت لایه runtime مشترک برای ردیابی، کنترل، و هماهنگی عامل ها در تمام نقاط برنامه.

## لیست تسک ها

1. `P1-T01` Runtime Event Bus پایه
- شرح: تعریف abstraction برای انتشار/دریافت eventهای runtime و پیاده سازی in-memory.
- خروجی: رویدادهای پایه چرخه درخواست نجم هدا قابل ثبت و مشاهده باشند.
- وابستگی: اتمام فاز 0
- وضعیت: `done`

2. `P1-T02` Runtime Telemetry Persistence
- شرح: اتصال event bus به ذخیره ساز پایدار (DB/queue) با retention policy.
- خروجی: رویدادهای runtime بین processها حفظ شوند.
- وابستگی: `P1-T01`
- وضعیت: `done`

3. `P1-T03` Policy Gate مرکزی
- شرح: تعریف policy engine مرکزی برای بررسی اجازه اقدام قبل از هر action.
- خروجی: هر اقدام agent از یک نقطه کنترل مجوز عبور کند.
- وابستگی: `P1-T01`
- وضعیت: `done`

4. `P1-T04` Action Executor استاندارد
- شرح: یک executor یکنواخت برای post/poll/comment/reaction/private-message با dry-run.
- خروجی: مسیر اجرای actionها قابل audit و rollback-friendly باشد.
- وابستگی: `P1-T03`
- وضعیت: `done`

5. `P1-T05` Group/Platform Event Intake
- شرح: استانداردسازی ورودی رویدادها از chat, poll, election, moderation به runtime.
- خروجی: همه ورودی های کلیدی به event مدل مشترک نگاشت شوند.
- وابستگی: `P1-T01`
- وضعیت: `done`

6. `P1-T06` Safety Guardrails
- شرح: سقف سرعت اقدام، circuit breaker و fallback استاندارد برای failureها.
- خروجی: runtime در خطاهای خارجی پایدار بماند.
- وابستگی: `P1-T03`, `P1-T04`
- وضعیت: `done`

7. `P1-T07` Smoke + Regression Tests
- شرح: اضافه کردن تست های پایه runtime lifecycle + policy gate.
- خروجی: جلوگیری از regression در لایه هوشمند.
- وابستگی: `P1-T01` تا `P1-T06`
- وضعیت: `done`
