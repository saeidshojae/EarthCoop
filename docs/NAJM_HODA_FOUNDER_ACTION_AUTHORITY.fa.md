# مدل اختیار اجرایی Founder Operations نجم هدا

## هدف

این سند مرز بین «دانستن»، «پیشنهاد دادن» و «اقدام کردن» نجم هدا را برای کل EarthCoop تعریف می‌کند.

هیچ اتصال داده‌ای، Observer، Snapshot یا Attention Rule به‌تنهایی اختیار mutation ایجاد نمی‌کند. هر اقدام باید از `FounderActionAuthorityService` عبور کند.

## حالت‌های اختیار

- `observe`: فقط خواندن و گزارش؛
- `propose`: آماده‌سازی پیشنهاد یا پیش‌نویس؛
- `approval_required`: اقدام فقط پس از تأیید صریح Founder؛
- `delegated_safe`: اقدام کم‌خطر فقط در صورت فعال بودن delegation و allowlist صریح همان domain/action؛
- `forbidden`: حتی با approval flag از مسیر Founder Ops قابل اجرا نیست.

## Fail-closed

Action ناشناخته به‌صورت پیش‌فرض `forbidden` است. افزودن ماژول یا command جدید بدون ثبت Policy، هیچ اختیار اجرایی به نجم هدا نمی‌دهد.

## Delegation

برچسب `delegated_safe` به‌تنهایی کافی نیست. در وضعیت اولیه:

- `delegation.enabled = false`
- `allowed_domains = []`
- `allowed_actions = []`

بنابراین در شروع rollout هیچ delegated mutation فعالی وجود ندارد.

فعال‌سازی آینده باید دو شرط همزمان داشته باشد:

1. دامنه در `allowed_domains` باشد؛
2. action کامل مانند `support.classify_ticket` در `allowed_actions` باشد.

## صف تأیید واحد

Founder Operations صف تأیید موازی ایجاد نمی‌کند. `FounderActionRequestService` درخواست‌های `approval_required` را با پیشوند `founder_ops:` وارد `NajmHodaAutonomyApprovalService` موجود می‌کند.

این سرویس از قبل:

- شناسه درخواست تولید می‌کند؛
- SLA و deadline دارد؛
- pending/history نگه می‌دارد؛
- approve/reject ثبت می‌کند؛
- event audit تولید می‌کند؛
- و در صورت فعال بودن تنظیمات، ادمین‌ها را مطلع می‌کند.

`FounderApprovalInboxService` فقط درخواست‌های متعلق به Founder Operations را از همان صف واحد استخراج می‌کند.

## حداقل‌سازی داده در Approval Queue

Context درخواست Founder Ops فقط metadata محدود زیر را می‌پذیرد:

- `entity_type`
- `entity_id`
- `source_event`
- `attention_priority`
- `reason_code`
- `requested_by`
- `correlation_id`

متن ایمیل، گیرنده، متن گزارش، محتوای سند، payload مالی و سایر داده‌های حساس نباید صرفاً برای approval routing داخل صف کپی شوند.

## نمونه‌های سیاست فعلی

### Support

طبقه‌بندی، تعیین priority و drafting به‌عنوان `delegated_safe` تعریف شده‌اند، ولی چون delegation در سطح global خاموش است هنوز خودکار اجرا نمی‌شوند. ارسال پاسخ یا بستن Ticket نیازمند approval است.

### Email

Draft و preview می‌توانند آماده شوند؛ ارسال ایمیل و bulk send نیازمند approval هستند.

### Governance

Summary و anomaly flagging می‌توانند کم‌خطر باشند. تغییر قواعد نیازمند approval است. تغییر vote یا result مطلقاً forbidden است.

### Stock

خلاصه مزایده و flag کردن settlement issue کم‌خطرند. ایجاد/تسویه مزایده و انتقال سهم نیازمند approval است. دستکاری تاریخچه مالکیت forbidden است.

### Najm Bahar

Summary، anomaly flagging و draft review در لایه پیشنهاد/کم‌خطر قرار دارند. اجرای تراکنش، approval پروژه و تغییر policy پولی نیازمند approval است. دستکاری ledger history forbidden است.

### Secretariat

Draft correspondence و follow-up قابل آماده‌سازی هستند. ثبت سند رسمی، dispatch رسمی و بستن پرونده نیازمند approval است. بازنویسی تاریخچه forbidden است.

## قاعده برای هر Domain Command Service

هیچ command service جدیدی نباید مستقیماً به خاطر «ادمین بودن» یا «نجم هدا بودن» مجاز به mutation شود. قبل از mutation باید:

1. domain و action پایدار تعیین کند؛
2. Policy Gate را ارزیابی کند؛
3. اگر `approval_required` است، request معتبر و approved داشته باشد؛
4. اگر `delegated_safe` است، delegation صریح فعال باشد؛
5. action/result را در audit/runtime ثبت کند؛
6. فقط سپس سرویس تخصصی همان domain را صدا بزند.

## وضعیت rollout

در این مرحله Policy Gate، Approval Bridge و Approval Inbox ساخته شده‌اند، اما هیچ delegated mutation به‌صورت global فعال نشده است. بنابراین این مرحله اختیارها را تعریف و مهار می‌کند؛ خودکارسازی اجرایی باید به‌صورت تدریجی و domain-by-domain فعال شود.
