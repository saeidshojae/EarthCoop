# وضعیت مرجع پیش از بازگشت به مسیر Native Mobile

**تاریخ تطبیق:** 2026-09-27  
**Baseline مخزن:** `main@b0f6e6082e3f248187a0bed66fb899dd0d7701c2`  
**وضعیت این سند:** مرجع جاری برای تشخیص «انجام‌شده / باز / عمداً deferred» تا پیش از شروع مسیر Native Mobile.

## هدف و ترتیب اعتبار شواهد

این سند اختلاف میان planهای تاریخی، UATهای واقعی، گفتگوهای پروژه و وضعیت merge‌شدهٔ مخزن را رفع می‌کند. وجود یک سناریو در plan یا UAT matrix به معنی «انجام‌نشده» بودن آن نیست.

ترتیب اعتبار شواهد:

1. کد و قرارداد merge‌شده در `main`؛
2. Full/Responsive validation روی SHA ثابت؛
3. UAT دستی ثبت‌شده؛
4. plan/runbook تاریخی که ممکن است بعداً اجرا یا supersede شده باشد.

برای backlog تفصیلی Product/UX، مرجع مکمل این سند `docs/PRODUCT_UX_BACKLOG_PRE_NATIVE.fa.md` است.

---

# 1. کارهای بزرگ بسته‌شده تا این Baseline

## 1.1 Location/Governance canonical architecture — **CLOSED**

کار بزرگ Location/Governance از نظر معماری canonical، residence/history، multidimensional membership، canonical groups، election topology، project target scope، Community separation، Registration/Profile/Admin/My Location/My Groups/current elections و responsive/UAT hardening بسته است.

سه جریان زیر backlog نیستند و نباید دوباره به‌عنوان UAT بعدی باز شوند:

- City without urban region؛
- Urban Region without neighborhood؛
- Village without neighborhood.

## 1.2 Iran 1404 v2 runtime/cutover — **CLOSED**

baseline ملی ۱۴۰۴، staging، guarded cutover، migration محدود وابستگی‌های identity-equivalent و runtime activation v2 در Production انجام شده است. C14 legacy retirement عمداً خارج از این closure است.

## 1.3 Checkpoint 2 — Structural Matrix — **CLOSED**

PR #146 قرارداد no-region/no-neighborhood، prerequisite/dependent structural claims، pending traversal، approval/rejection cleanup، re-anchor و contradiction guards را بست.

## 1.4 Checkpoint 3 — Proposal/Review Lifecycle — **CLOSED**

PR #147 distinct-user support، committed residence provenance، propagation، ready-for-review، atomic review transitions، reason/evidence و rejection cleanup را بست. Threshold پیش‌فرض ۱۰ approval نیست؛ فقط signal آمادگی برای review است.

## 1.5 Checkpoint 4 — Canonical Consumer Audit — **CLOSED**

PR #148 active consumerهای Profile/Admin/My Groups، chat roles، elections/policy/conflict/appointments، project/community، admin filters/temporary roles، Najm Bahar salary targeting، Najm Hoda context و `/api/groups/search` را با canonical truth هم‌راستا کرد.

---

# 2. کارهای پس از چهار Checkpoint که اکنون بسته شده‌اند

این بخش در تطبیق 2026-09-27 اضافه شد تا کارهایی که بعد از Checkpoint 4 انجام شدند دوباره به‌عنوان backlog باز نشوند.

## 2.1 Home / Onboarding redesign — **CLOSED**

PR #151 بازطراحی Home را با visual language ثبت‌نام، onboarding چهارمرحله‌ای، mobile-first composition، حفظ سه کارت canonical گروه‌ها، slider/content ادمین و responsive contract بست.

PR #152 لایهٔ read-only `HomeCivicDashboardService`، journey state، Today signals، next action، pending-location visibility و Home polish را اضافه کرد.

PR #153 وضعیت حق عضویت، deep-link پرداخت، معرفی نجم هدا و shared sidebar section polish را تکمیل کرد.

بنابراین «بازطراحی Home/Onboarding» دیگر P0 باز نیست؛ real-device polish عمومی می‌تواند جداگانه VERIFY شود.

## 2.2 Authenticated navigation taxonomy — **CLOSED**

PR #154 Sidebar و Drawer را روی یک taxonomy مشترک تثبیت کرد:

- خانه؛
- شبکه و ارتباطات؛
- حکمرانی و مشارکت؛
- اقتصاد؛
- سازمان و همکاری؛
- حساب و پشتیبانی؛
- کاوش و اسناد؛
- مدیریت برای ادمین.

همچنین لینک‌های legacy wallet/holding از navigation حذف و نام‌های «دفتر سهام ارزش» و «حراج‌های سهم ارزش» تثبیت شدند. پروفایل canonical نجم هدا جای duplicate about route را گرفت.

## 2.3 Main-site ↔ self-hosted Docs Center alignment — **CLOSED for current URL contract**

PR #155:

- legacy Mintlify links را از source-of-truth مرکزی حذف کرد؛
- `https://docs.earthcoop.ir` را مرکز canonical فعلی کرد؛
- FC تا STD را به ۱۰ سند بنیادین رساند؛
- publication policy را ثبت کرد؛
- duplication Footer را حذف کرد؛
- Welcome/Footer/Navigation را با config مرکزی هم‌راستا کرد؛
- چهار سند داخلی Laravel را عمداً حفظ کرد.

PR #156 سپس workaround موقت `docs-center-link-normalizer.js` را حذف کرد و Sidebar/Drawer را مستقیماً به `config/docs-links.php` متصل کرد.

Full Validation #3313 و #3314 روی SHAهای ثابت سبز شدند و نسخهٔ نهایی deploy شد.

**نکته:** professional SEO / canonical non-hash routing خود Docs Center هنوز backlog مستقل است و این closure آن را ادعا نمی‌کند.

---

# 3. Location/Governance — باقی‌مانده‌های واقعی و درست‌طبقه‌بندی‌شده

## 3.1 Product/Operations UX — **OPEN, non-canonical blocker**

- نمایش بهتر support progress برای proposal/claim؛
- CTA دعوت کاربران محلی/مرتبط برای حمایت؛
- توضیح روشن `pending / ready_for_review / approved`؛
- redesign صف Admin Location/Governance برای حجم بالا؛
- filter/sort/density/evidence/dependency visibility بهتر.

Core support/review lifecycle قبلاً بسته شده است.

## 3.2 Reference-settlement operational UAT — **PARTIAL / FEATURE-FLAGGED**

Automated contracts و بخش‌های مهم UAT وجود دارند، اما evidence دستی کامل برای تمام موارد زیر ثبت نشده است:

- threshold واقعی با ۱۰ کاربر مستقل؛
- full admin evidence-review lifecycle؛
- nonresidential cleanup end-to-end؛
- flag-off/flag-on replay؛
- mobile/RTL manual matrix کامل؛
- nationwide classification/promotion lifecycle.

این‌ها blocker معماری شروع Mobile Readiness نیستند مگر rollout عمومی settlement داخل launch scope قرار گیرد.

## 3.3 Reverse geolocation provider — **DEFERRED**

provider واقعی reverse geocoding تغییر مستقل privacy/configuration است و می‌تواند در M5 همراه device/location policy تصمیم‌گیری شود.

## 3.4 C14 legacy retirement — **DEFERRED / APPROVAL-GATED**

حذف `Address`، geography legacy، rollback scaffolding و تاریخچه صرفاً برای تمیزی کد مجاز نیست. C14 فقط پس از observation/audit و تأیید صریح جداگانه انجام می‌شود.

---

# 4. Mobile Readiness Foundations — مسیر فنی اصلی

معماری هدف:

`Laravel/domain core → stable services/capabilities/events → versioned API → Web/PWA + Native clients + Najm Hoda`

هدف این نیست که تمام featureهای آینده قبل از موبایل ساخته شوند؛ هدف این است که Native روی قراردادهای web-only و ناپایدار بنا نشود.

## M0 — API Constitution / domain boundary inventory — **OPEN / NEXT MAIN TASK**

وضعیت: **RED/YELLOW**.

Sanctum و APIهای پراکنده وجود دارند، اما `/api/v1` mobile contract یکپارچه هنوز freeze نشده است.

خروجی لازم:

- `/api/v1` boundary؛
- auth/session/token/device rules؛
- response/error envelope؛
- pagination/filter/sort؛
- idempotency/retry mutation contract؛
- authorization/resource policy boundary؛
- locale/timezone/date/number conventions؛
- upload/media/deep-link conventions؛
- deprecation/versioning policy؛
- capability/use-case inventory برای Native.

**این اولین task فنی اصلی بعدی است.**

## M1 — API v1 implementation — **OPEN / RED**

حداقل pilot/core journey باید API شود:

- auth/account/profile؛
- canonical residence/location/governance؛
- groups/membership/chat؛
- polls/elections؛
- projects core؛
- notifications؛
- Najm Bahar core؛
- Najm Hoda interaction context/capabilities.

## M2 — Najm Hoda stable mobile contract — **PARTIAL / YELLOW**

زیرساخت capability/runtime/safety/audit بالغ است، اما client contract هنوز freeze نشده است.

ضروری:

- explicit authority minting؛
- resource authorization برای capabilityهای executable؛
- mutation endpoint/session/Sanctum review؛
- action/consent/audit contract؛
- context ≠ authority؛
- stable schema برای propose/apply/approval/error/evidence.

Autonomy کامل نجم هدا blocker Native PoC نیست.

## M3 — Najm Bahar mobile contract — **CORE GREEN / CONTRACT OPEN**

هستهٔ issuance، ledger/events، Active/Dim/Committed/Reserved، activation، transfer، scheduled transfer، membership fee، treasury foundations و reservation/commitment invariants موجود است.

قبل از Native باید contract موبایل account/balance/transfer/fee/activation/audit freeze شود و mutationها idempotent باشند.

موارد غیرالزامی برای شروع PoC مگر وارد launch scope شوند:

- idle-tax redistribution کامل؛
- normalized future account migration؛
- provider واقعی Servix/ZarinPal؛
- تمام retirement/treasury UATهای نهایی.

## M4 — Organization / Marketplace / Shop boundary — **PARTIAL / RED**

Project و Secretariat foundation موجودند؛ Company/Marketplace/Shop subsystem کامل نیست.

قبل از Native فقط boundary حداقلی برای organization/legal-entity/shop/market actor لازم است تا API آینده breaking redesign نخواهد. ساخت broad marketplace و full company ecosystem blocker PoC نیست.

## M5 — Device / Push / Upload / Realtime / Offline — **OPEN / NATIVE-CRITICAL**

قبل از Native PoC باید حداقل این قراردادها بسته شوند:

- device registration + revoke/rotate؛
- APNs/FCM abstraction + notification preferences؛
- deep links؛
- upload/media API؛
- realtime transport/fallback؛
- offline/cache/sync/idempotent replay؛
- app/version compatibility + forced-minimum-version policy.

## M6 — Native PoC — **GATED**

PoC فقط بعد از تعریف و عبور gate مناسب M0/M1 و قراردادهای critical M2–M5 آغاز می‌شود.

---

# 5. مسیر موازی Product/UX پیش از Native

فهرست تفصیلی و وضعیت هر مورد در `docs/PRODUCT_UX_BACKLOG_PRE_NATIVE.fa.md` نگهداری می‌شود.

پس از PRهای #151 تا #156، P0های اصلی باز عبارت‌اند از:

1. Admin Location/Governance high-volume review UX؛
2. proposal support progress + invite UX؛
3. Project create/edit mobile redesign؛
4. Najm Bahar mobile UX verification/follow-upهای تأییدشده؛
5. breakpoint architecture برای Mobile / Tablet-Compact / Desktop؛
6. real-device/browser verificationهای نهایی در نقاطی که automated responsive contract کافی نیست.

Home/Onboarding، taxonomy اصلی navigation و اتصال main-site به self-hosted Docs Center دیگر در این فهرست باز نیستند.

---

# 6. کارهایی که نباید به اشتباه blocker Native شوند

- C14 legacy retirement؛
- broad Marketplace implementation؛
- full Company ecosystem؛
- nationwide settlement residential classification/promotion؛
- autonomy کامل نجم هدا؛
- idle-tax redistribution نهایی؛
- reverse-geocoder provider واقعی؛
- external payment provider UAT؛
- تمام cosmetic UX backlogهای وب؛
- professional SEO نهایی Docs Center، مگر launch scope صریحاً آن را gate کند.

---

# 7. ترتیب عملی از این Baseline

1. **Documentation reconciliation 2026-09-27 — CLOSED.**
2. **M0 — API Constitution + Mobile Capability Inventory.**
3. M1 — `/api/v1` foundation + auth/device boundary.
4. M2/M3 — freeze قراردادهای Najm Hoda و Najm Bahar.
5. M4 — حداقل Organization/Marketplace/Shop domain boundary.
6. M5 — device/push/upload/realtime/offline foundation.
7. Architecture gate.
8. Native PoC و سپس تثبیت technology path.

P0های Product/UX می‌توانند موازی با M0/M1 پیش بروند، اما نباید جای API foundation را بگیرند یا کارهای canonical بسته‌شده را دوباره باز کنند.

---

# 8. اسناد مرتبط

- `docs/PRODUCT_UX_BACKLOG_PRE_NATIVE.fa.md`: backlog تفصیلی Product/UX و وضعیت جاری.
- `docs/location-governance/UAT_SCENARIOS.md`: ماتریس پذیرش دائمی، نه backlog execution.
- `docs/location-governance/CHECKPOINT_2_STRUCTURAL_MATRIX_2026-09-25.md`: closure ساختاری.
- `docs/location-governance/CHECKPOINT_4_CANONICAL_CONSUMER_AUDIT_2026-09-26.md`: closure canonical consumers.
- `docs/location-governance/IR_1404_SETTLEMENT_CATALOG_IMPLEMENTATION_PLAN_2026-09-24.md`: plan تاریخی/بخشی اجراشده.
- `docs/location-governance/CUTOVER_READINESS.md`: pre-cutover historical readiness.
- `docs/NAJM_HODA_SECURITY_HARDENING_STATUS.md`: شکاف‌های security/autonomy نجم هدا.
- `docs/NAJM_BAHAR_UAT_READINESS_MATRIX.fa.md`: maturity و UAT هستهٔ اقتصادی.

## نتیجهٔ مرجع

**Location/Governance detour، چهار Checkpoint، Home/Onboarding follow-up، navigation taxonomy و main-site Docs Center alignment بسته شده‌اند.**

کار اصلی بعدی دیگر documentation cleanup یا تکرار UAT ساختاری نیست؛ **M0 — API Constitution و Mobile Capability Inventory** است، در کنار P0های Product/UX باقی‌مانده که در سند مکمل ثبت شده‌اند.
