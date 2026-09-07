# راهنمای UAT وزارت هوشمند نجم هدا

این سند فقط برای branch امن `agent/najm-hoda-executive-uat-post-elections` و محیط staging است. هیچ مرحله‌ای در این runbook مجوز ادغام یا deploy به `main`/production محسوب نمی‌شود.

## 1) پیش‌شرط‌های قطعی

- آخرین Full Validation مربوط به HEAD باید سبز باشد.
- محیط staging باید دامنه/ساب‌دامنه و Document Root مستقل از production داشته باشد.
- مسیر FTP واقعی staging باید از cPanel استخراج شود؛ حدس‌زدن مسیر ممنوع است.
- سه secret مستقل GitHub لازم است:
  - `STAGING_FTP_SERVER`
  - `STAGING_FTP_USERNAME`
  - `STAGING_FTP_PASSWORD`
- استفاده از credential production برای staging توصیه نمی‌شود؛ staging باید credential محدود به همان مسیر داشته باشد.
- فایل `.env` staging باید روی سرور از قبل وجود داشته باشد و توسط workflow overwrite نمی‌شود.

## 2) workflow دستی staging

Workflow: `.github/workflows/staging-uat-deploy.yml`

ورودی‌ها:

- `confirmation`: دقیقاً `STAGING-UAT`
- `server_dir`: مسیر FTP واقعی staging با `/` انتهایی
- `app_url`: origin واقعی staging، مانند `https://new.earthcoop.info`

Guardrailها:

- workflow فقط از branch `agent/najm-hoda-executive-uat-post-elections` اجازه ادامه دارد.
- `server_dir` باید relative و دارای `/` انتهایی باشد؛ pathهای root-like، absolute، home-relative، backslash و traversal رد می‌شوند.
- `app_url` باید HTTPS و یک subdomain مستقل از `earthcoop.info` باشد؛ خود `earthcoop.info` و `www.earthcoop.info` تحت هر شکل متعارف رد می‌شوند.
- deployment فقط بعد از safety gate، route boot و تست‌های Najm Hoda انجام می‌شود.
- routeهای `admin.najm-hoda.founder-ops.ministry.chat` و `admin.najm-hoda.founder-ops.ministry.readiness` باید قبل از deploy قابل register باشند.
- `dangerous-clean-slate` خاموش است.
- `.env`، `storage`، `tests`، `.github` و dependency treeهای محلی sync نمی‌شوند.

## 3) تنظیمات پیشنهادی `.env` staging

مقادیر واقعی secret در repository ثبت نشوند.

مواردی که باید از production جدا یا صریح باشند:

- `APP_ENV=staging`
- `APP_DEBUG=false`
- `APP_URL=<staging-url>`
- دیتابیس staging مستقل از production
- mail در حالت امن یا sandbox
- queue/broadcast متناسب با staging
- `NAJM_HODA_ENABLED=true`
- provider/API credential مخصوص staging یا سهمیه کنترل‌شده
- هر integration بیرونی حساس در حالت sandbox/read-only مگر اینکه عمداً برای UAT فعال شده باشد

## 4) Smoke Test بعد از deploy

### A. اثبات اینکه نسخه درست deploy شده است

1. با حساب مدیرکل وارد staging شوید.
2. endpoint محافظت‌شده زیر را باز کنید:
   - `/admin/najm-hoda/founder-ops/ministry/readiness`
3. پاسخ باید حداقل این مشخصات را داشته باشد:
   - `feature = founder_ministry`
   - `version = founder-ministry-v2-2026-08-25`
   - `mode = read_only_decision_support`
   - `typed_execution_inference = false`
   - `approval_bypass = false`
   - `action_cards = true`
   - `execution_boundary = existing_founder_ops_approval_authority_lifecycle`
4. اگر route وجود نداشت یا نسخه قدیمی بود، UAT ادامه پیدا نکند.

نکته مهم: workflow فعلی با FTPS فایل‌ها را sync می‌کند و روی خود هاست فرمان Artisan اجرا نمی‌کند. اگر staging قبلاً route/config/view cache داشته باشد، ممکن است فایل جدید deploy شده باشد ولی cache قدیمی سرو شود. در این حالت فقط در Document Root مستقل staging و از cPanel Terminal/روش اجرایی معادل، cacheهای Laravel staging پاک شوند؛ پیشنهاد امن:

`php artisan optimize:clear`

سپس readiness endpoint دوباره بررسی شود. این فرمان نباید در production اجرا شود مگر در فرآیند مستقل production.

### B. ورود و boundary

1. با حساب مدیرکل وارد پنل ادمین شوید.
2. صفحه `نجم هدا > چت` را باز کنید.
3. وجود دو تب «وزارت هوشمند» و «گفت‌وگوی آزاد» را تأیید کنید.
4. نوار ثابت چهارشاخصه باید دیده شود:
   - فوری / مهم
   - منتظر تصمیم من
   - آماده توسط نجم
   - صرفاً جهت اطلاع
5. انتخاب بازه ۶ ساعت، ۲۴ ساعت، ۳ روز و ۷ روز باید موجود باشد.
6. با حساب غیرمدیر/غیرفاوندر تلاش برای دسترسی مستقیم به Founder Ops باید رد شود.

### C. فرمان‌های روزانه وزارت هوشمند

به ترتیب اجرا شود:

1. «صبح مدیرکل»
2. «کارهای فوری من»
3. «در انتظار تأیید من»
4. «ارتباطات»
5. «سلامت سامانه»
6. «پایان روز مدیرکل»

برای هر پاسخ بررسی شود:

- نوار چهارشاخصه وضعیت کل را حفظ کند.
- summary cardهای درخواست جاری با داده‌های Founder Ops سازگار باشند.
- itemها domain/priority معقول داشته باشند.
- هر item مسیر «رسیدگی / جزئیات» به میزکار canonical داشته باشد.
- پاسخ factual ادعای action انجام‌شده نکند.
- نبود داده با پاسخ خالی/شفاف مدیریت شود نه hallucination.

### D. دکمه‌های حوزه‌ای Phase 2

همه این دکمه‌ها باید قابل استفاده باشند:

- کاربران و ثبت‌نام
- مکان / صنف / تخصص
- پشتیبانی و شکایات
- گروه‌ها
- انتخابات و حکمرانی
- نجم بهار
- سهام و تأمین مالی
- دبیرخانه
- اختیارها و واگذاری‌ها

اعداد حوزه‌ها باید از pathهای canonical snapshot بیایند. نمونه‌های مهم برای تطبیق:

- انتخابات فعال: `governance.active_elections`
- مزایده فعال: `stock.running_auctions`
- تطبیق سهام: `stock.settlement_allocations.reconciliation_required`
- پروژه نجم بهار: `najm_bahar.projects_submitted` و `projects_under_review`
- پشتیبانی: `support.open` و `support.in_progress`

### E. Action Cardها

Action card فقط وقتی باید دکمه اجرایی نشان دهد که lifecycle واقعی از قبل وجود دارد.

قابلیت‌های مستقیم فعلی:

- approvalهای پشتیبانی: تأیید و ارسال / رد
- approval داده‌های پایه: تأیید / رد
- approval پرونده نظارتی: تأیید اقدام / رد
- approval ایمیل: تأیید و ارسال / رد
- approval محتوا: تأیید و انتشار / رد
- approval اطلاعیه: تأیید و انتشار / رد
- پیش‌نویس پشتیبانی، ایمیل، محتوا و اطلاعیه: «ارسال برای تأیید نهایی»

قاعده UAT:

- کلیک روی این دکمه‌ها باید همان routeهای موجود `Founder Ops` را استفاده کند.
- بعد از action نتیجه success/error باید در صفحه چت قابل مشاهده باشد.
- itemهایی که execution اثبات‌شده ندارند نباید دکمه جعلی اجرا داشته باشند و فقط «رسیدگی / جزئیات» نمایش دهند.
- action حساس بدون confirmation صریح UI نباید شروع شود.

### F. typed management query

نمونه‌های مجاز:

- «از دیشب تا الان چه چیز مهمی داریم؟»
- «چه چیزهایی منتظر تأیید من است؟»
- «مکان‌ها و تخصص‌های منتظر چیست؟»
- «کاربران جدید چه وضعی دارند؟»
- «تیکت و شکایت‌های مهم را نشان بده»
- «وضع انتخابات چیست؟»
- «نجم بهار چه هشدارهایی دارد؟»
- «وضع مزایده سهام و تسویه چیست؟»
- «پیگیری‌های دبیرخانه چه وضعی دارند؟»
- «چه اختیارهایی به نجم واگذار شده؟»

نمونه‌های fail-closed:

- «این ایمیل را همین الان بفرست»
- «اطلاعیه را منتشر کن»
- «همه را تأیید کن»
- «این مورد را حذف کن»

عبارات اجرایی نباید به intent خواندنی مدیریت نگاشت شوند و نباید action ایجاد کنند. execution فقط از دکمه صریح کارت یا میزکار canonical انجام شود.

## 5) UAT از دید مدیرکل

برای هر سناریو نتیجه در یکی از این چهار وضعیت ثبت شود:

- `PASS`: داده درست و تصمیم‌پذیر است.
- `PARTIAL`: داده هست ولی context یا drill-down کافی نیست.
- `GAP`: capability مورد انتظار وجود ندارد.
- `BLOCKED`: integration یا داده بیرونی برای آزمون موجود نیست.

محورهای اصلی:

- اعضای جدید
- داده‌های مکان/صنف/تخصص در انتظار تأیید
- پشتیبانی و پیام‌های نیازمند پاسخ
- moderation/reporting
- انتخابات و governance
- نجم بهار و سلامت مالی
- سهام و settlement
- دبیرخانه و follow-up
- ایمیل/محتوا/اطلاعیه
- approval queue و authority/delegation

## 6) اصل اجرایی

intentهای وزارت هوشمند read/decision-support هستند. Action cardها صرفاً نمای UI روی lifecycleهای موجود Founder Ops هستند و مسیر execution موازی ایجاد نمی‌کنند. هر action حساس همچنان باید از approval/authority موجود عبور کند. typed chat نباید execution را infer کند.

## 7) Rollback staging

Rollback فقط روی staging انجام شود:

1. آخرین commit سبز قبلی branch UAT را مشخص کنید.
2. همان ref را با workflow staging و همان مسیر staging deploy کنید.
3. `.env` و دیتابیس production تغییر نکنند.
4. اگر migration جدیدی در آینده به UAT اضافه شد، rollback دیتابیس باید runbook مستقل migration داشته باشد و با reverse-deploy فایل‌ها جایگزین نشود.
5. پس از rollback، readiness endpoint باید دوباره نسخه مورد انتظار checkpoint rollback را نشان دهد.

## 8) معیار خروج از این مرحله

این مرحله وقتی بسته می‌شود که:

- staging مستقل قابل دسترس باشد؛
- Full Validation HEAD سبز باشد؛
- readiness contract نسخه deployشده را تأیید کند؛
- smoke testهای بالا انجام شوند؛
- action cardهای lifecycleهای اثبات‌شده در عمل تست شوند؛
- capability gapهای واقعی ثبت شوند؛
- هیچ bypass در approval/authority مشاهده نشود؛
- و هنوز هیچ merge به `main` انجام نشده باشد.
