# وضعیت مرجع پیش از بازگشت به مسیر Native Mobile

**تاریخ تطبیق:** 2026-09-26  
**Baseline مخزن:** `main@9b936dd9c9b194098f6736eeac8917ccb2d622f6`  
**وضعیت این سند:** مرجع جاری برای تشخیص «انجام‌شده / باز / عمداً deferred» تا پیش از شروع دوبارهٔ مسیر آماده‌سازی Native Mobile.

## هدف

این سند برای رفع اختلاف میان planهای تاریخی، UATهای واقعی، گفتگوهای پروژه و وضعیت merge‌شدهٔ مخزن ایجاد شده است. وجود یک سناریو در plan یا UAT matrix به معنی «انجام‌نشده» بودن آن نیست؛ وضعیت واقعی ابتدا از `main` و PRهای merge‌شده، سپس از شواهد UAT اپراتور/کاربر، و بعد از اسناد تاریخی خوانده می‌شود.

ترتیب اعتبار شواهد در این سند:

1. کد و قرارداد merge‌شده در `main`؛
2. Full/Responsive validation روی SHA ثابت؛
3. UAT دستی ثبت‌شده در گفتگوهای پروژه؛
4. plan/runbook تاریخی که ممکن است بعداً اجرا یا supersede شده باشد.

## خلاصهٔ اجرایی

کار بزرگ Location/Governance که در میانهٔ برنامهٔ اصلی وارد آن شدیم، از نظر معماری canonical، UAT ساختاری، Iran 1404 v2 cutover، proposal/review lifecycle و canonical consumer audit بسته شده است.

سه جریان زیر **backlog نیستند** و نباید دوباره به‌عنوان «UAT بعدی» برنامه‌ریزی شوند:

- City without urban region؛
- Urban Region without neighborhood؛
- Village without neighborhood.

این مسیرها در UAT واقعی پیدا/اصلاح شدند و بعد در قرارداد ساختاری دائمی و Checkpoint 2 بسته شدند.

همچنین «support threshold» دیگر یک قابلیت backend انجام‌نشده نیست. منطق distinct-user support، committed residence selection، propagation، ready-for-review و rejection cleanup در Checkpoint 3 بسته شده است. آنچه ممکن است بعداً برای این بخش بخواهیم، **UX بهترِ progress/invite و مقیاس‌پذیری صف ادمین** است، نه اختراع دوبارهٔ lifecycle حمایت.

---

# 1. کارهایی که واقعاً انجام و merge شده‌اند

## 1.1 معماری جهانی Location/Governance — بسته

PR #131 معماری global Location/Governance و hardening نهایی اولیه را merge کرد، شامل:

- canonical Location و GovernanceArea مستقل؛
- residence/history؛
- multidimensional membership؛
- canonical groups؛
- systemic election topology؛
- project target scope؛
- Community separation؛
- Registration/Profile/Admin/My Location/My Groups/current elections؛
- responsive/UAT hardening.

این مرحله پایهٔ architectural detour را بسته است؛ حذف legacy در آن انجام نشده و نباید هم انجام می‌شد.

## 1.2 Residence picker / proposal chain / mobile web polish — بسته

PRهای #123 تا #130 مجموعهٔ UAT و اصلاحات production-facing را بستند، از جمله:

- canonical Step 3 UX؛
- deep pending proposal chain؛
- continent traversal؛
- mobile-first proposal UX؛
- Street/Alley/Complex/Building branching؛
- cancel-without-submit side-effect bug؛
- readiness guard برای migrationهای proposal-chain.

Project scope نیز در PRهای #120 تا #122 قرارداد «stop at any selected valid level» و mobile UAT/polish را دریافت کرد.

## 1.3 UAT واقعی no-region / no-neighborhood — بسته

شواهد UAT کاربر و mergeهای بعدی نشان می‌دهد:

- Kiasar برای City without Urban Region عملاً تست شد؛
- Village without Neighborhood در UAT اشکال داشت، اصلاح و دوباره بررسی شد؛
- Urban Region without Neighborhood در UAT اشکال terminal/street داشت، اصلاح شد؛
- PR #133 با عنوان `complete no-neighborhood registration UAT` همین خط UAT را merge کرد؛
- PR #146 کل canonical + pending structural-state matrix را به‌صورت نهایی بست.

بنابراین LG-UAT-03 / 18 / 19 / 20 سناریوهای پذیرش دائمی‌اند، نه فهرست کارهای انجام‌نشده.

## 1.4 Iran 1404 national administrative runtime و v1→v2 cutover — بسته

PR #141 baseline ملی ۱۴۰۴ را وارد runtime کرد: 31 استان، 484 شهرستان، 1,193 بخش، 2,777 دهستان، 1,481 شهر و 191 منطقهٔ شهری، با حفظ v1 history و fail-closed conflict policy.

PRهای #143 تا #145 سپس مسیر Production-safe را تکمیل کردند:

- read-only runtime audit / dry-run؛
- additive staging؛
- guarded topology/cutover path؛
- DB-backed runtime activation boundary؛
- migration of verified identity-equivalent live dependencies؛
- activation در آخرین مرحله.

شواهد عملیاتی ثبت‌شده در گفتگو در 2026-09-25 نیز cutover واقعی را موفق گزارش کرد: runtime v2 active، blocker=0، و migration محدودِ وابستگی‌های موجود شامل residence، proposal chainها، canonical groups و project target/scope انجام شد. این شواهد اپراتوری است و باید از CI-only evidence تفکیک شود.

## 1.5 Checkpoint 2 — Structural Matrix — بسته

PR #146 (`Close Checkpoint 2 structural-state matrix`) قراردادهای زیر را نهایی کرد:

- `no_urban_region`؛
- `no_neighborhood` برای City / Urban Region / Village؛
- prerequisite/dependent structural claims؛
- pending proposal traversal؛
- approval/rejection cleanup؛
- re-anchor on approval/merge؛
- contradiction guards؛
- Registration/Profile/Admin entry-point parity.

## 1.6 Checkpoint 3 — Proposal/Review Lifecycle — بسته

PR #147 (`Close Checkpoint 3 proposal and review lifecycle`) این موارد را بست:

- support فقط از residence selection معتبر و committed؛
- support propagation روی proposal ancestry باز؛
- atomic review transitions؛
- reason-gated review؛
- pending residence cancellation on rejection؛
- anchor residence preservation؛
- dependent pending-group visibility/count در admin queue؛
- lifecycle regression coverage.

پس «آستانهٔ ۱۰ حمایت» از نظر lifecycle/backend انجام شده است؛ ۱۰ حمایت approval نیست و فقط review priority/readiness است.

## 1.7 Checkpoint 4 — Canonical Consumer Audit — بسته

PR #148 در `main@9b936dd9...` merge شد و active consumerها را با Residence/GovernanceArea/Membership canonical هم‌راستا کرد:

- Profile/Admin/My Groups؛
- group chat / moderation role presentation؛
- elections/policy/conflict/appointments؛
- project/community re-audit؛
- admin group filters / temporary roles؛
- Najm Bahar salary targeting؛
- Najm Hoda context؛
- canonical `/api/groups/search` scope/roles/security/member-count contract.

SHA نهایی branch یعنی `1138435326f1a0bf1f3cb6f1e217438fee5157b8` روی Responsive #707 و Integration Full Validation #3266 سبز شد و سپس با merge commit `9b936dd9c9b194098f6736eeac8917ccb2d622f6` وارد main شد.

---

# 2. Iran 1404 settlement/reference-settlement — وضعیت واقعی

Plan تاریخی 2026-09-24 دیگر `PLAN ONLY` نیست. روی main اکنون مدل‌ها و سرویس‌های `ReferenceSettlement`, residence claim, review, structure claim, registration bridge و admin review وجود دارند.

## انجام‌شده و موجود در main

- neutral `reference_settlements` catalog؛
- 99,317 settlement source identity در مسیر catalog/UAT؛
- evidence/review model؛
- residence claim روی source identity؛
- registration bridge با canonical parent anchor؛
- pending settlement display؛
- pending group shells؛
- settlement→pending-neighborhood bridge؛
- non-authorizing policy؛
- read-only v1→v2 runtime audit؛
- fail-closed parent/crosswalk behavior؛
- support threshold به‌عنوان review signal، نه approval.

## UAT دستی که واقعاً انجام شده

در UAT «وری» و مسیرهای مرتبط، شواهد گفتگو ثبت می‌کند که موارد زیر عملاً بررسی شدند:

- settlement search زیر parent معتبر؛
- تکمیل registration تا pending neighborhood؛
- Step 3 / Profile / Admin shared picker و hydration؛
- breadcrumb/path نمایش «وری»؛
- pending settlement/neighborhood bridge؛
- pending group ordering/role presentation.

## UAT دستی که نباید به اشتباه سبز اعلام شود

برای همهٔ موارد زیر evidence دستی کامل در گفتگوها ثبت نشده است، حتی اگر automated contract وجود داشته باشد:

- رسیدن واقعی ۱۰ کاربر مستقل به threshold برای یک settlement؛
- full admin evidence-review lifecycle از needs-evidence تا residential/nonresidential؛
- manual nonresidential cleanup end-to-end؛
- manual flag-off/flag-on replay؛
- کامل‌بودن mobile/RTL manual matrix برای settlement admin/review؛
- classification/promotion lifecycle در مقیاس ملی.

این موارد **feature-flagged/operational UAT backlog** هستند و blocker معماری برای شروع Mobile Readiness محسوب نمی‌شوند، تا زمانی که settlement rollout عمومی جزو launch scope انتخاب نشده باشد.

---

# 3. کارهای باقی‌ماندهٔ Location/Governance که باید درست طبقه‌بندی شوند

## 3.1 UX polish — باز، اما نه canonical blocker

کارهای ثبت‌شده‌ای که هنوز ارزش انجام دارند:

- نمایش بهتر progress حمایت برای proposal/claim؛
- CTA مناسب برای دعوت کاربران محلی به حمایت؛
- بهبود صف/فیلتر/تراکم Admin Location/Governance برای حجم بالا؛
- توضیح بهترِ تفاوت `pending`, `ready_for_review`, `approved` برای کاربر و ادمین.

این‌ها enhancement هستند؛ core support/review lifecycle قبلاً بسته شده است.

## 3.2 Reverse geolocation provider — deferred external/independent

UI و abstraction کمک مکانی وجود دارد، اما provider واقعی reverse geocoding قبلاً عمداً به‌عنوان تغییر مستقل privacy/configuration باقی گذاشته شد. این مورد blocker شروع Mobile Readiness نیست؛ در M5 می‌تواند همراه device/location policy دوباره تصمیم‌گیری شود.

## 3.3 C14 legacy retirement — عمداً فعلاً انجام نمی‌شود

`Address`, legacy geography columns/routes و rollback scaffolding نباید صرفاً برای «تمیزشدن کد» حذف شوند. C14 فقط بعد از post-cutover observation/audit و تأیید صریح جداگانه مجاز است. بنابراین C14 جزو کارهای لازم پیش از Native Mobile نیست.

---

# 4. برنامهٔ اصلی که باید حالا به آن برگردیم: Mobile Readiness Foundations

تصمیم قبلی پروژه این بود که قبل از Native Production، یک لایهٔ پایدار بین Laravel core و clientها ساخته شود:

`Laravel/domain core → stable services/capabilities/events → versioned API → Web/PWA + Native clients + Najm Hoda`

این به معنی تکمیل همهٔ featureهای آینده قبل از موبایل نیست. هدف این است که Native روی قراردادهای ناپایدار web-only ساخته نشود.

## M0 — API Constitution / domain boundary inventory — **باز و اولین کار بعدی**

وضعیت امروز: **RED/YELLOW**.

Sanctum در پروژه وجود دارد، اما `routes/api.php` هنوز versioned mobile contract نیست و endpointهای legacy/closure-based، از جمله legacy geography، در آن وجود دارند.

خروجی لازم M0:

- تعریف `/api/v1` contract boundary؛
- auth/session/token/device rules؛
- response/error envelope؛
- pagination/filter/sort contract؛
- idempotency/retry contract برای mutationها؛
- authorization/resource policy boundary؛
- locale/timezone/date/number conventions؛
- upload/media/deep-link conventions؛
- deprecation/versioning policy؛
- inventory دقیق اینکه کدام use-caseها برای Native باید API شوند.

**این اولین task اصلی بعد از بستن مستندات فعلی است.**

## M1 — API v1 implementation — **باز**

وضعیت امروز: **RED** به‌عنوان API یکپارچهٔ mobile-first، هرچند APIهای پراکنده و Sanctum موجودند.

حداقل API v1 باید pilot/core journey را پوشش دهد، نه تمام صفحات سایت:

- auth/account/profile؛
- canonical residence/location/governance؛
- groups/membership/chat؛
- polls/elections؛
- projects core؛
- notifications؛
- Najm Bahar core account/actions؛
- Najm Hoda interaction context/capabilities.

## M2 — Najm Hoda stable mobile contract — **PARTIAL / YELLOW**

نجم هدی از نظر capability/runtime/safety/audit زیرساخت بالغی دارد، اما mobile contract هنوز freeze نشده است.

کارهای ضروری قبل از قرار دادن آن پشت Native client:

- explicit authority minting services؛
- کامل‌کردن resource authorization برای capabilityهای گروه/مالی/content/admin در صورت executable شدن؛
- mutation endpoint CSRF/session/Sanctum review؛
- action/consent/audit contract قابل مصرف توسط client؛
- جلوگیری از اینکه client دادهٔ context را authority تلقی کند؛
- stable API schema برای propose/apply/approval/error/evidence.

مواردی مانند autonomy کامل نجم هدی یا همهٔ agentهای آینده شرط شروع Native نیستند.

## M3 — Najm Bahar mobile contract — **CORE GREEN / MOBILE CONTRACT OPEN**

هستهٔ اقتصادی نسبت به roadmap اولیه بسیار جلوتر رفته است:

- issuance؛
- ledger/events؛
- Active/Dim/Committed/Reserved؛
- activation؛
- transfers؛
- scheduled transfers؛
- membership fee؛
- treasury foundations؛
- reservation/commitment invariants.

قبل از Native لازم نیست همهٔ roadmap اقتصادی تمام شود. لازم است contract موبایل برای account/wallet/balance/transfer/fee/activation/audit freeze شود و idempotent mutation semantics داشته باشد.

این موارد می‌توانند جدا بمانند و شروع Native را block نکنند مگر وارد launch scope شوند:

- idle-tax redistribution engine کامل؛
- normalized future account schema migration؛
- provider واقعی Servix/ZarinPal؛
- همهٔ retirement/treasury UATهای نهایی.

## M4 — Organization / Marketplace / Shop domain foundation — **PARTIAL/RED**

Project و Secretariat domain foundation موجود و نسبتاً بالغ‌اند، اما Marketplace/Shop/Company به‌عنوان subsystem یکپارچهٔ production-grade روی main پیدا نمی‌شود.

تصمیم فعلی:

- **قبل از Native:** contract/domain boundary حداقلی برای organization/legal-entity/shop/market actor تعریف شود تا client بعداً مجبور به API breaking redesign نشود؛
- **قبل از Native لازم نیست:** broad marketplace و company ecosystem کامل ساخته شود.

این مرزبندی تصمیم قدیمی «اول همه‌چیز را کامل کنیم» را با roadmap بعدی pilot/mobile reconciliation می‌کند.

## M5 — Device / Push / Upload / Realtime / Offline foundation — **باز و Native-critical**

وضعیت امروز:

- Web/PWA و service worker وجود دارد؛
- responsive/mobile web contracts بالغ‌تر شده‌اند؛
- اما native device registration/push contract (APNs/FCM abstraction)، device session model و unified native notification/deep-link contract هنوز به‌صورت subsystem پایدار دیده نمی‌شود.

قبل از Native PoC باید تصمیم/قرارداد حداقلی این موارد بسته شود:

- device registration + revoke/rotate؛
- push provider abstraction + notification preferences؛
- deep links؛
- upload/media API؛
- realtime transport/fallback؛
- offline/cache/sync/idempotent replay policy؛
- app/version compatibility and forced-minimum-version policy.

## M6 — Native PoC — **شروع نشود تا M0–M5 gate تعریف شود**

PoC می‌تواند قبل از کامل‌شدن تمام featureهای EarthCoop آغاز شود، اما نه قبل از اینکه M0/M1 و قراردادهای critical M2–M5 به حد کافی پایدار باشند.

---

# 5. چیزهایی که «قبل از Native» نباید به اشتباه blocker شوند

موارد زیر کارهای واقعی پروژه‌اند، اما completion آن‌ها شرط شروع Native foundation/PoC نیست مگر scope محصول تغییر کند:

- C14 legacy retirement؛
- broad Marketplace implementation؛
- full Company ecosystem؛
- nationwide settlement residential classification/promotion؛
- کامل‌کردن تمام autonomous Najm Hoda capabilities؛
- idle-tax redistribution engine نهایی؛
- reverse-geocoder provider واقعی؛
- external payment provider UAT؛
- تمام cosmetic UX backlogهای وب.

---

# 6. ترتیب عملی از این لحظه

1. **بستن این documentation reconciliation** و مشخص‌کردن planهای historical/superseded.
2. **M0 — API Constitution + mobile capability inventory** روی main فعلی.
3. M1 — `/api/v1` foundation و mobile auth/device boundary.
4. M2/M3 — freeze contractهای Najm Hoda و Najm Bahar برای mobile client.
5. M4 — حداقل Organization/Marketplace/Shop domain contracts، بدون ساخت کامل بازار.
6. M5 — device/push/upload/realtime/offline foundation.
7. Architecture gate.
8. سپس Native PoC و انتخاب/تثبیت technology path.

این ترتیب جایگزین برنامه‌های قدیمی‌ای است که ممکن است از متن آن‌ها چنین برداشت شود که Location/Governance UAT یا کل Marketplace/Company باید دوباره/کامل قبل از شروع mobile foundation انجام شود.

---

# 7. اسناد مرتبط و نحوهٔ خواندن آن‌ها

- `docs/location-governance/UAT_SCENARIOS.md`: **ماتریس پذیرش دائمی**؛ backlog execution نیست.
- `docs/location-governance/CHECKPOINT_2_STRUCTURAL_MATRIX_2026-09-25.md`: closure ساختاری no-region/no-neighborhood.
- `docs/location-governance/CHECKPOINT_4_CANONICAL_CONSUMER_AUDIT_2026-09-26.md`: closure consumerهای canonical؛ C14 نیست.
- `docs/location-governance/IR_1404_SETTLEMENT_CATALOG_IMPLEMENTATION_PLAN_2026-09-24.md`: plan تاریخی؛ بخش عمدهٔ C1–C5 بعداً اجرا شد و باید با status update خوانده شود.
- `docs/location-governance/CUTOVER_READINESS.md`: سند تاریخی pre-cutover؛ وضعیت بعد از 2026-09-25 باید با update جدید آن خوانده شود.
- `docs/NAJM_HODA_SECURITY_HARDENING_STATUS.md`: مرجع شکاف‌های security/autonomy نجم هدی، ولی همهٔ آن‌ها blocker Native PoC نیستند.
- `docs/NAJM_BAHAR_UAT_READINESS_MATRIX.fa.md`: مرجع maturity هستهٔ اقتصادی و UATهای باقیمانده.

## نتیجهٔ مرجع

**Location/Governance detour برای بازگشت به برنامهٔ اصلی، از نظر canonical architecture و checkpointهای 2–4 بسته است.**

کار اصلی بعدی، تکرار UAT شهر/منطقه/روستای بدون سطح نیست؛ **M0 — API Constitution و Mobile Readiness inventory** است.
