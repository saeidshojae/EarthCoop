# ریزتسک های فاز 2 (Platform-wide Policy + Execution API)

## هدف
- اعمال policy enforcement و execution contract یکپارچه روی همه entrypointهای نجم هدا (نه فقط مسیر گروه).

## لیست تسک ها

1. `P2-T01` Entry Policy سراسری
- شرح: تعریف و اعمال policy ورودی (enabled/rate-limit) برای API/Admin chat entrypointها.
- خروجی: deny response یکنواخت با کدهای policy (`NAJM_HODA_DISABLED`, `NAJM_HODA_RATE_LIMITED`).
- وابستگی: اتمام فاز 1
- وضعیت: `done`

2. `P2-T02` Execution API مشترک
- شرح: تعریف لایه اجرای استاندارد برای orchestrator با قرارداد پاسخ یکسان (request_id, response_time, normalized payload).
- خروجی: controllerها از مسیر اجرای مشترک استفاده کنند.
- وابستگی: `P2-T01`
- وضعیت: `done`

3. `P2-T03` Coverage روی entrypointهای باقی‌مانده
- شرح: گسترش policy/execution به endpointهای باقی‌مانده NajmHoda API/Admin.
- خروجی: حذف پراکندگی منطق disable/rate/execute در controllerها.
- وابستگی: `P2-T01`, `P2-T02`
- وضعیت: `done`

4. `P2-T04` Tests برای policy/execution
- شرح: افزودن تست مسیرهای deny/success/failure در policy و execution service.
- خروجی: جلوگیری از regression در لایه ورودی سراسری.
- وابستگی: `P2-T01`, `P2-T02`
- وضعیت: `done`
