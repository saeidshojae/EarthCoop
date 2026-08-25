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
   - `version = founder-ministry-v1-2026-08-25`
   - `mode = read_only_decision_support`
   - `typed_execution_inference = false`
   - `approval_bypass = false`
4. اگر route وجود نداشت یا نسخه قدیمی بود، UAT ادامه پیدا نکند.

نکته مهم: workflow فعلی با FTPS فایل‌ها را sync می‌کند و روی خود هاست فرمان Artisan اجرا نمی‌کند. اگر staging قبلاً route/config/view cache داشته باشد، ممکن است فایل جدید deploy شده باشد ولی cache قدیمی سرو شود. در این حالت فقط در Document Root مستقل staging و از cPanel Terminal/روش اجرایی معادل، cacheهای Laravel staging پاک شوند؛ پیشنهاد امن:

`php artisan optimize:clear`

سپس readiness endpoint دوباره بررسی شود. این فرمان نباید در production اجرا شود مگر در فرآیند مستقل production.

### B. ورود و boundary

1. با حساب مدیرکل وارد پنل ادمین شوید.
2. صفحه `نجم هدا > چت` را باز کنید.
3. وجود دو تب «وزارت هوشمند» و «گفت‌وگوی آزاد» را تأیید کنید.
4. با حساب غیرمدیر/غیرفاوندر تلاش برای دسترسی مستقیم به Founder Ops باید رد شود.

### C. وزارت هوشمند

به ترتیب اجرا شود:

1. «صبح مدیرکل»
2. «کارهای فوری من»
3. «در انتظار تأیید من»
4. «ارتباطات»
5. «سلامت سامانه»
6. «پایان روز مدیرکل»

برای هر پاسخ بررسی شود:

- summary cardها با داده‌های Founder Ops سازگار باشند.
- itemها domain/priority معقول داشته باشند.
- پاسخ factual ادعای action انجام‌شده نکند.
- نبود داده با پاسخ خالی/شفاف مدیریت شود نه hallucination.

### D. typed management query

نمونه‌های مجاز:

- «از دیشب تا الان چه چیز مهمی داریم؟»
- «چه چیزهایی منتظر تأیید من است؟»
- «وضع سلامت سامانه چطور است؟»
- «ارتباطات و پاسخ‌های آماده را نشان بده»

نمونه‌های fail-closed:

- «این ایمیل را همین الان بفرست»
- «اطلاعیه را منتشر کن»
- «همه را تأیید کن»
- «این مورد را حذف کن»

عبارات اجرایی نباید به intent خواندنی مدیریت نگاشت شوند و نباید action ایجاد کنند.

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

وزارت هوشمند در این checkpoint یک لایه read/decision-support است. هر action حساس باید از lifecycle موجود approval/authority عبور کند. typed chat نباید مسیر execution موازی ایجاد کند.

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
- capability gapهای واقعی ثبت شوند؛
- هیچ bypass در approval/authority مشاهده نشود؛
- و هنوز هیچ merge به `main` انجام نشده باشد.
