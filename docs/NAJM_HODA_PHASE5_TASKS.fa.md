# ریزتسک های فاز 5 (Governance و Hardening)

## هدف
- رساندن نجم‌هدا به سطح بهره‌برداری پایدار 24/7 با حاکمیت تصمیم، کنترل هزینه، آمادگی بحران، و ایمنی عملیاتی قابل ممیزی.

## لیست تسک ها

1. `P5-T01` Governance KPI Baseline + SLO Definition
- شرح: تعریف KPI/SLI/SLO رسمی برای خودگردانی (موفقیت اقدام، MTTR، rollback rate، approval latency، false positive).
- خروجی: baseline کمی + آستانه هشدار + فرمت محاسبه یکنواخت.
- وابستگی: اتمام فاز 4
- وضعیت: `done`

2. `P5-T02` Governance Metrics Aggregator
- شرح: ساخت سرویس تجمیع متریک از event bus + cache + runtime trace برای گزارش مدیریتی.
- خروجی: snapshot حکمرانی با بازه‌های 15m/1h/24h.
- وابستگی: `P5-T01`
- وضعیت: `done`

3. `P5-T03` Admin Governance Dashboard
- شرح: افزودن صفحه مدیریتی برای نمایش KPIها، روندها، و وضعیت SLOها.
- خروجی: UI عملیاتی با drill-down روی anomalyها.
- وابستگی: `P5-T02`
- وضعیت: `done`

4. `P5-T04` AI Cost Ledger + Budget Guard
- شرح: ثبت هزینه AI به تفکیک agent/action و تعریف budget سقف‌دار با هشدار/محدودسازی.
- خروجی: ledger دقیق + کنترل budget ماهانه/روزانه.
- وابستگی: `P5-T01`
- وضعیت: `done`

5. `P5-T05` Decision Policy Drift Detector
- شرح: تشخیص انحراف تصمیمات واقعی از policyهای تعریف‌شده (risk/mode/safety/approval).
- خروجی: گزارش drift با severity و دلیل.
- وابستگی: `P5-T02`
- وضعیت: `done`

6. `P5-T06` Runbook Registry + Execution Readiness
- شرح: استانداردسازی runbookها (incident, degraded, override, recovery) با نسخه و مالک.
- خروجی: registry رسمی runbook + checklists اجرایی.
- وابستگی: `P5-T01`
- وضعیت: `done`

7. `P5-T07` Fail-safe Global Kill Switch
- شرح: kill switch سراسری برای توقف فوری execution خودکار با مسیر بازگشت کنترل‌شده.
- خروجی: توقف deterministic + trace کامل فعال/غیرفعال‌سازی.
- وابستگی: `P5-T06`
- وضعیت: `in_progress`

8. `P5-T08` Alerting + Escalation SLA Guard
- شرح: تعریف alert rule برای breach در SLOها و SLA تصمیم انسانی/اجرای خودکار.
- خروجی: هشدار به‌موقع + escalation policy استاندارد.
- وابستگی: `P5-T02`, `P5-T06`
- وضعیت: `pending`

9. `P5-T09` Chaos Drill Automation (GameDay)
- شرح: خودکارسازی سناریوهای GameDay برای pause/override/fail/replay و تحلیل نتایج.
- خروجی: گزارش drill دوره‌ای + pass/fail معیارپذیر.
- وابستگی: `P5-T07`, `P5-T08`
- وضعیت: `pending`

10. `P5-T10` Security Hardening for Autonomy Surface
- شرح: سخت‌سازی endpointهای autonomy (RBAC دقیق‌تر، rate-limit اختصاصی، audit tamper-check).
- خروجی: کاهش سطح حمله + کنترل دسترسی دقیق.
- وابستگی: `P5-T03`
- وضعیت: `pending`

11. `P5-T11` Compliance Export + Audit Evidence Pack
- شرح: خروجی استاندارد برای ممیزی (decision trace, approvals, overrides, failures, replay events).
- خروجی: evidence pack قابل ارائه به تیم عملیات/امنیت.
- وابستگی: `P5-T05`, `P5-T10`
- وضعیت: `pending`

12. `P5-T12` Production Readiness Review + Go/No-Go
- شرح: مرور نهایی معیارها، ریسک‌های باز، برنامه rollback، و تصمیم Go/No-Go.
- خروجی: تصمیم بهره‌برداری پایدار 24/7 با چک‌لیست نهایی.
- وابستگی: `P5-T01` تا `P5-T11`
- وضعیت: `pending`

## معیار اتمام فاز 5
- KPI/SLOها به‌صورت کمی و دوره‌ای پایش شوند.
- داشبورد حکمرانی برای تصمیم‌گیری روزانه تیم عملیاتی کافی باشد.
- Fail-safe و GameDay حداقل در دو چرخه بدون failure بحرانی پاس شوند.
- بسته ممیزی (Audit Evidence Pack) کامل و قابل استخراج باشد.
- معیار Go/No-Go با شواهد فنی و عملیاتی تایید شود.
