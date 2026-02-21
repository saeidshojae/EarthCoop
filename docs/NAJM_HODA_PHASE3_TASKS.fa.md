# ریزتسک های فاز 3 (مدیریت خودکار عملیات)

## هدف
- تبدیل نجـم هدا از «پاسخ‌دهی» به «اپراتور عملیاتی» با پایش سلامت، triage خودکار، و اجرای playbookهای کم‌ریسک.

## لیست تسک ها

1. `P3-T01` Ops Health Monitoring پایه
- شرح: ساخت سرویس پایش سلامت بر پایه Runtime Event Bus و تولید snapshot وضعیت سلامت.
- خروجی: snapshot استاندارد شامل `status`, `error_rate`, `unresolved_requests` و ثبت event سلامت.
- وابستگی: اتمام فاز 2
- وضعیت: `done`

2. `P3-T02` Auto Triage پایه
- شرح: تحلیل snapshot سلامت و تشخیص incidentهای عملیاتی با severity و کد استاندارد.
- خروجی: eventهای incident قابل ردگیری و آماده اتصال به escalation.
- وابستگی: `P3-T01`
- وضعیت: `done`

3. `P3-T03` Low-risk Playbook Runner
- شرح: اجرای خودکار playbook کم‌ریسک در حالت degrade/healthy (بدون اقدام پرریسک).
- خروجی: فعال/غیرفعال شدن حالت degrade با TTL و audit event مربوطه.
- وابستگی: `P3-T02`
- وضعیت: `done`

4. `P3-T04` Scheduler/Command Integration
- شرح: افزودن command عملیاتی و زمان‌بندی دوره‌ای برای مانیتورینگ و triage.
- خروجی: اجرای منظم `najm-hoda:ops-monitor` توسط scheduler.
- وابستگی: `P3-T01`, `P3-T02`, `P3-T03`
- وضعیت: `done`

5. `P3-T05` Tests برای Ops Monitor/Triage
- شرح: افزودن تست برای snapshot سالم/بحرانی و اعمال degraded mode.
- خروجی: جلوگیری از regression در منطق عملیاتی فاز 3.
- وابستگی: `P3-T01` تا `P3-T04`
- وضعیت: `done`

6. `P3-T06` Incident Escalation به Ticketing
- شرح: اتصال incidentهای warning/critical به مسیر escalation با ایجاد ticket عملیاتی.
- خروجی: ثبت ticket با cooldown برای جلوگیری از تکرار و ثبت audit event escalation.
- وابستگی: `P3-T02`, `P3-T04`
- وضعیت: `done`

7. `P3-T07` Admin Notification برای Escalation
- شرح: ارسال اعلان داخلی به ادمین/اپراتور هنگام ثبت escalation ticket.
- خروجی: اطلاع‌رسانی سریع تیم عملیاتی با لینک مستقیم به ticket.
- وابستگی: `P3-T06`
- وضعیت: `done`

8. `P3-T08` Dynamic Degraded Rate Control
- شرح: ارتقای playbook کم‌ریسک برای اعمال multiplier پویا روی نرخ ورودی Najm Hoda در حالت warning/critical.
- خروجی: کنترل فشار ورودی در حالت degrade با key عملیاتی مشترک.
- وابستگی: `P3-T03`
- وضعیت: `done`

9. `P3-T09` Entry Policy Integration با Ops Playbook
- شرح: اتصال Entry Policy به multiplier عملیاتی تا تصمیم triage بلافاصله روی نرخ ورودی اثر بگذارد.
- خروجی: کاهش خودکار نرخ ورودی در بحران و بازگشت خودکار به baseline در حالت healthy.
- وابستگی: `P3-T08`
- وضعیت: `done`

10. `P3-T10` Playbook Catalog + Safety Constraints
- شرح: تبدیل playbookها به catalog و plan قابل‌پیکربندی با enforce اجرای low-risk.
- خروجی: اجرای policy-driven playbookها، محدودیت تعداد action در هر run، و skip event برای actionهای blocked.
- وابستگی: `P3-T03`
- وضعیت: `done`

11. `P3-T11` Playbook Cooldown + Telemetry
- شرح: افزودن cooldown مستقل برای هر action و ثبت telemetry عملیاتی برای outcome هر playbook action.
- خروجی: جلوگیری از loop اجرایی، شمارش outcomeها، و event telemetry برای پایش رفتاری runbook.
- وابستگی: `P3-T10`
- وضعیت: `done`

12. `P3-T12` Ops Run Summary Digest
- شرح: تولید summary استاندارد از هر اجرای monitor و نگهداری آخرین digest برای مصرف dashboard/trace.
- خروجی: event `ops.run.summary` + cache snapshot آخرین اجرای عملیاتی.
- وابستگی: `P3-T04`, `P3-T11`
- وضعیت: `done`

13. `P3-T13` Admin-facing Ops Digest Feed
- شرح: افزودن endpoint مدیریتی برای دریافت last summary, history و recent ops events.
- خروجی: feed قابل مصرف در پنل ادمین برای رهگیری عملیاتی لحظه‌ای.
- وابستگی: `P3-T12`
- وضعیت: `done`

14. `P3-T14` Ops Retention + Cleanup
- شرح: افزودن retention service برای پاکسازی تاریخچه summary و telemetry index های قدیمی.
- خروجی: کنترل رشد داده‌های عملیاتی cache با prune دوره‌ای و event حسابرسی retention.
- وابستگی: `P3-T11`, `P3-T12`
- وضعیت: `done`

15. `P3-T15` Lightweight Admin Ops UI
- شرح: افزودن صفحه سبک در پنل ادمین برای مشاهده digest عملیات (last summary + history + recent ops events).
- خروجی: UI قابل استفاده برای رهگیری سریع وضعیت عملیاتی نجـم‌هدا بدون نیاز به بررسی دستی cache/events.
- وابستگی: `P3-T13`
- وضعیت: `done`
