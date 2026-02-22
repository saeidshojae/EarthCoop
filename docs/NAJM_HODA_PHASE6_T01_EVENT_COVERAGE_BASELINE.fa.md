# خروجی اولیه P6-T01: ممیزی پوشش رویدادها (Baseline v0)

تاریخ: 2026-02-21
وضعیت: `in_progress`

## خلاصه وضعیت فعلی
- Event bus نجم هدا در لایه Runtime پوشش خوبی برای تصمیم و اجرا دارد.
- مسیرهای autonomy/ops/governance/readiness instrumentation شده اند.
- ورودی های گروهی (message/poll/feed/election) به runtime bus متصل شده اند.
- اما پوشش بین دامنه ای (auth/support/economy/content/admin غیر نجمی) هنوز کامل و استاندارد نیست.

## نقاط پوشش تایید شده
1. ورودی های گروهی به bus:
- `najm_hoda.input.group_message`
- `najm_hoda.input.group_poll`
- `najm_hoda.input.group_feed`
- `najm_hoda.input.group_election_started`
- `najm_hoda.input.group_election_finished`

2. تصمیم/اجرا در autonomy:
- executor: `intent`, `executed`, `failed`, `skipped`
- goal loop: `goal_loop.executed`, `goal_loop.kill_switched`, `plan_item.blocked`
- safety/contract: `safety.approved|blocked`, `contract.accepted|rejected`
- approvals/audit/control/cost کامل instrumentation شده اند.

3. ops/governance/readiness:
- ops health/incident/playbook/escalation/retention
- governance drift/alerts
- readiness reviewed + compliance evidence

4. orchestration و scheduler:
- cronهای `najm-hoda:ops-monitor`, `najm-hoda:goal-loop`, `najm-hoda:gameday` موجودند.

## gap های اصلی برای تکمیل P6-T01
1. نبود قرارداد یکپارچه رویداد برای دامنه های غیر نجمی:
- auth
- tickets/support lifecycle
- content management
- najm-bahar economy workflow

2. نبود شاخص coverage رسمی:
- هنوز ماتریس «دامنه -> رویدادهای باید/هست» به صورت نسخه بندی شده نداریم.

3. نبود سنجه کیفیت event schema:
- mandatory fields مثل `request_id`, `actor_id`, `scope`, `risk`, `correlation_id` در همه رویدادها enforce نشده است.

4. نبود policy برای event versioning:
- قرارداد ارتقا/سازگاری backward برای event ها تعریف رسمی نشده است.

## اقدام های بعدی (گام اجرایی مستقیم)
1. ساخت سند قرارداد رویداد (Event Contract v1) با فیلدهای اجباری.
2. ساخت ماتریس پوشش دامنه ها و استخراج gap قابل اندازه گیری.
3. افزودن instrumentation حداقلی برای 2 دامنه اولویت بالا:
- support/tickets
- najm-bahar transactions
4. افزودن تست smoke برای بررسی تولید رویدادهای حیاتی در هر دامنه.

## معیار عبور از P6-T01
- وجود فایل contract رسمی + ماتریس coverage نسخه بندی شده.
- حداقل 95% مسیرهای بحرانی در ماتریس دارای رویداد قابل ردیابی باشند.
- تست smoke تولید event برای دامنه های بحرانی پاس شود.
