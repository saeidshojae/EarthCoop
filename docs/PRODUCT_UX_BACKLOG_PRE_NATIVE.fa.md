# ممیزی Product & UX تا پیش از Native Mobile

**تاریخ ممیزی:** 2026-09-26  
**مبنای ممیزی:** گفتگوهای پروژه + وضعیت `main` + شواهد PR/Validation/UAT  
**هدف:** جلوگیری از گم‌شدن تصمیم‌های UX/Product در میان roadmapهای فنی و جلوگیری از بازکردن دوبارهٔ کارهای واقعاً بسته‌شده.

## قواعد وضعیت

- **CLOSED** — اجرا/merge و شواهد کافی برای بسته‌شدن وجود دارد.
- **IMPLEMENTED / VERIFY** — پیاده‌سازی گزارش شده، اما شواهد کامل merge/UAT نهایی در همان خط گفتگو ثبت نشده یا باید با main دوباره تطبیق شود.
- **OPEN** — تصمیم/نیاز پذیرفته شده ولی تکمیل نهایی آن ثبت نشده است.
- **DEFERRED** — عمداً برای مرحلهٔ بعد گذاشته شده و blocker بازگشت به Mobile Readiness نیست.

---

# 1. Home / Onboarding / Welcome

## 1.1 بازطراحی Home و onboarding — **OPEN**

این مورد در چت‌ها به‌عنوان کار مشخص برنامه‌ریزی شده و نباید زیر عنوان مبهم «cosmetic backlog» گم شود.

قرارداد توافق‌شده:

- حفظ سه کارت اصلی Home / My Groups؛
- اضافه‌کردن onboarding روشن برای کاربر جدید؛
- توضیح قدم‌های بعد از ثبت‌نام، از جمله:
  - بازکردن حساب نجم بهار؛
  - دعوت دوستان؛
  - آشنایی با مجامع و گروه‌ها؛
  - شروع مشارکت؛
- slider + متن قابل مدیریت توسط ادمین؛
- راهنمای خوش‌آمدگویی؛
- یکپارچه‌سازی style/behavior دکمه‌ها؛
- انیمیشن‌های هماهنگ و نه پراکنده؛
- حفظ سه کارت «گروه‌های من» و شمارش canonical؛
- mobile/responsive واقعی، نه فشرده‌سازی دسکتاپ.

شواهد main نشان می‌دهد مسیرهای مدیریت Welcome/slider وجود دارند، اما چت‌ها completion صریحِ بازطراحی Home/Onboarding فوق را ثبت نمی‌کنند. بنابراین این کار همچنان **OPEN** است.

## 1.2 Welcome/Login/Home stability قدیمی — **CLOSED as stability, not redesign**

در 2026-08-27 Welcome/Login/Home از نظر regressionهای آن مقطع stable گزارش شدند. این شواهد نباید با «بازطراحی Home/Onboarding» یکی تلقی شود.

---

# 2. Admin Location / Governance Control Center

## 2.1 هستهٔ Admin Control Center — **CLOSED**

پیاده‌سازی پایه در Task 10 و PRهای Location/Governance انجام شده است:

- Proposal Queue؛
- Reference Explorer؛
- official topology؛
- Community/import/health diagnostics؛
- status filter؛
- audit context؛
- support/threshold information؛
- mobile-responsive action forms؛
- approve / reject / merge / request-evidence؛
- reason/evidence lifecycle؛
- parent/dependency-aware review؛
- separate proposal/claim concepts.

این بخش دیگر نباید به‌عنوان «پنل ادمین وجود ندارد» در backlog ظاهر شود.

## 2.2 بازطراحی حرفه‌ای برای حجم بالا — **OPEN**

چت‌های UAT صریحاً نشان دادند که صفحه برای حجم زیاد پیشنهادها گیج‌کننده می‌شود و نیاز به redesign دارد.

باقی‌ماندهٔ توافق‌شده:

- queue density بهتر؛
- filter/sort مؤثرتر برای حجم بالا؛
- نمایش واضح parent path؛
- نمایش dependency / pending-parent state؛
- وضعیت‌های روشن `pending / ready_for_review / approved / rejected / needs_evidence`؛
- support progress/count برجسته‌تر؛
- evidence summary قابل اسکن؛
- duplicate/merge candidate visibility؛
- action hierarchy واضح‌تر؛
- UX مناسب موبایل برای review واقعی، نه فقط responsive stacking؛
- تفکیک شفاف proposal، structure claim، reference settlement review و residence-related queueها.

این مورد **OPEN** است و باید به‌عنوان Product/Operations UX دیده شود، نه backend feature gap.

---

# 3. Location proposal/support UX

## 3.1 backend support/review lifecycle — **CLOSED**

Distinct-user support، threshold پیش‌فرض ۱۰، committed-selection provenance، propagation، ready-for-review و rejection cleanup در Checkpoint 3 بسته شده است.

## 3.2 user-facing support progress + invite UX — **OPEN**

کارهای باقی‌ماندهٔ توافق‌شده:

- نمایش تعداد حمایت فعلی در برابر threshold؛
- توضیح اینکه threshold به معنی approval نیست؛
- CTA دعوت کاربران نزدیک/مرتبط برای حمایت؛
- توضیح اینکه انتخاب همان proposal توسط کاربر بعدی چگونه support ایجاد می‌کند؛
- state واضح برای `pending`، `ready_for_review` و `approved`؛
- UX جلوگیری از ابهام دربارهٔ «چرا هنوز تأیید نشده؟».

---

# 4. My Location & Governance

## 4.1 redesign mobile-first و official/community separation — **CLOSED**

چت‌ها و validationهای بعدی نشان می‌دهند موارد زیر اجرا و سخت‌گیری شده‌اند:

- لینک «مکان و حکمرانی من» در navigation مشترک؛
- responsive layout و touch targets؛
- تفکیک حکمرانی رسمی از Communityهای محلی؛
- نمایش chain رسمی؛
- Community برای Street/Alley/Complex/Building؛
- opt-in بودن Community؛
- cross-screen consistency با My Groups و Current Elections.

این بخش دیگر backlog redesign پایه نیست.

## 4.2 manual real-device polish — **IMPLEMENTED / VERIFY**

Automated responsive gates سبز شده‌اند، اما چت‌ها repeatedly یادآوری می‌کنند که automated responsive contract جای real-device/browser UAT را نمی‌گیرد. اگر قبل از launch نیاز باشد، یک viewport/device matrix نهایی باید ثبت شود.

---

# 5. Mobile navigation / header

## 5.1 categorized hamburger/drawer — **CLOSED**

نیاز پذیرفته‌شده و اجراشده:

- حفظ لینک‌های مهم؛
- دسته‌بندی با submenuهای بازشونده؛
- mobile drawer؛
- جداسازی sidebar دسکتاپ؛
- دسترسی Location/Governance؛
- اصلاح badgeهای canonical My Groups؛
- چند دور alignment/profile/back-button polish.

## 5.2 breakpoint architecture — **OPEN**

در چت 2026-08-26 مشخص شد که در عرض‌هایی مانند 1366px هنوز header compact دیده می‌شود و پیشنهاد/برنامهٔ «Mobile / Tablet-Compact / Desktop» به‌عنوان follow-up باقی ماند.

این مورد هنوز completion صریح ندارد و **OPEN** است.

---

# 6. My Groups / Group surfaces

## 6.1 responsive My Groups reference implementation — **CLOSED**

Responsive system و My Groups به‌عنوان reference implementation اجرا و validation شدند؛ بعداً canonical counts/sidebar و role/topology نیز در Location/Governance hardening اصلاح شدند.

## 6.2 legacy/pending role wording polish — **PARTIALLY SUPERSEDED / VERIFY**

در UAT یک مورد UX ثبت شد که pending group در ستون «سمت» مقدار «فعال» نشان می‌داد و پیشنهاد wordingهایی مانند «پایه پیشنهادی» یا «فعال پس از تأیید» مطرح شد. بعداً role presentation و pending semantics چند بار harden شدند، اما چت‌ها completion صریح همین wording را به‌عنوان UAT نهایی ثبت نمی‌کنند.

بنابراین این مورد باید فقط به‌صورت **VERIFY** بماند، نه task توسعهٔ بزرگ.

---

# 7. Group Chat / Group Control Center

## 7.1 functional/realtime hardening — **CLOSED**

Group chat، realtime update، unread behavior و role presentation چند دور regression/validation دریافت کرده‌اند.

## 7.2 Group Najm Hoda action queue — **CLOSED**

در 2026-08-16 پنل نجم هدی گروه از «مصوبات» به action queue عملیاتی بازطراحی شد، شامل:

- unassigned / urgent counts؛
- overdue / urgent / unassigned badges؛
- expandable source/evidence/history؛
- server-authorized edit برای assignee/due-date/status/priority.

این مورد backlog باز محسوب نمی‌شود.

---

# 8. Project create/edit UX

## 8.1 geographic target scope contract — **CLOSED**

کاربر می‌تواند روی هر سطح معتبر جغرافیایی متوقف شود و همان scope را ثبت کند. backend persistence و edit hydration در PR #120 و ادامهٔ hardening بسته شده است.

## 8.2 mobile-responsive redesign صفحه create/edit — **OPEN**

در همان خط گفتگو صریحاً تصمیم گرفته شد که بازطراحی حرفه‌ای موبایل صفحه create/edit Project به‌عنوان task جداگانه بعدی انجام شود. completion بعدی برای این redesign در چت‌ها پیدا نشد.

این مورد **OPEN** است.

---

# 9. Najm Bahar UX

## 9.1 mobile bottom-sheet / tabs / modal stacking — **IMPLEMENTED / VERIFY**

در 2026-08-31 موارد زیر پیاده‌سازی شدند:

- mobile bottom-sheet به‌جای secondary sidebar؛
- mobile-only tabs «حساب من / وضعیت سامانه»؛
- modal overlay بالای header؛
- Responsive Contract سبز شد.

اما همان خط گفتگو Full Validation/UAT نهایی را در لحظهٔ گزارش هنوز کامل نکرده بود. بخش‌های بعدی نجم بهار از نظر functional regression بالغ‌اند، ولی برای این specific UX بهتر است وضعیت **IMPLEMENTED / VERIFY** حفظ شود.

## 9.2 gold glow button + wallet parity + coin animation/thickness — **OPEN**

در همان چت کاربر درخواست کرد:

- دکمهٔ منوی موبایل طلایی با glow/pulse؛
- همان رفتار drawer روی wallet page؛
- animation سکه مطابق Welcome با rotation + vertical motion فقط؛
- ضخامت متناسب‌تر سکه در موبایل.

شواهد completion صریح این follow-upها در چت‌ها پیدا نشد. بنابراین **OPEN**.

---

# 10. Docs Center UX / Admin workflow

## 10.1 بسته‌شده‌ها — **CLOSED**

بر اساس آخرین چت مرکز اسناد:

- full-text search؛
- print/copy؛
- responsive/readability fixes؛
- repo-controlled import؛
- pinned commit/diff/private preview/human approval؛
- self-hosting روی `docs.earthcoop.ir`؛
- لوگوی EarthCoop؛
- PDF download؛
- status-evidence gate؛
- private admin publishing assistant؛
- نسخهٔ 0.7.0 با 99/99 tests.

## 10.2 باقی‌مانده‌ها — **OPEN**

- per-clause comments/feedback queue؛
- admin status-management UI؛
- Laravel/API/SSO integration؛
- automated cPanel deployment؛
- professional SEO multi-page architecture؛
- main-site SEO remediation که نیازمند تغییر Laravel است.

این backlog مستقل از Native Mobile است، اما نباید از roadmap محصول حذف شود.

---

# 11. Dashboard / button system / visual consistency

## 11.1 shared responsive system — **PARTIAL/CLOSED FOUNDATION**

در مرداد/شهریور responsive system مشترک، My Groups reference، mobile navigation و چندین صفحه harden شدند.

## 11.2 global button protocol / visual consistency — **OPEN as product polish**

در برنامهٔ Home/UX صریحاً یکپارچه‌سازی دکمه‌ها، animation behavior و consistency سراسری مطرح شد. چت‌ها completion سراسری این protocol را ثبت نمی‌کنند.

این کار نباید به پروژهٔ عظیم redesign تبدیل شود؛ بهتر است در قالب design-system/pattern cleanup و همراه با صفحات اولویت‌دار انجام شود.

---

# 12. مواردی که نباید دوباره به‌عنوان UX backlog باز شوند

- بازطراحی پایهٔ Registration Step 3 برای governance-base stopping؛
- deep pending proposal chain؛
- Street/Alley/Complex/Building branching؛
- My Location official/community separation؛
- mobile Location/Governance navigation link؛
- canonical My Groups counts/sidebar؛
- Election Portal responsive hardening؛
- proposal/review backend lifecycle؛
- admin basic review actions؛
- project geographic stop-at-any-level contract.

این‌ها regression targets هستند، نه پروژهٔ طراحی دوباره.

---

# 13. اولویت UX/Product پیش از Native PoC

این ledger جای M0–M5 فنی را نمی‌گیرد. ترتیب پیشنهادی برای جلوگیری از قاطی‌شدن دو مسیر:

## P0 — launch-critical product UX

1. Home / Onboarding redesign؛
2. Admin Location/Governance high-volume redesign؛
3. proposal support progress + invite UX؛
4. Project create/edit mobile redesign؛
5. verify pending-role wording و Najm Bahar mobile UX follow-ups؛
6. breakpoint cleanup برای Mobile/Tablet-Compact/Desktop.

## P1 — product operations/docs

1. Docs Center feedback/comments queue؛
2. Admin status-management UI؛
3. Docs SSO/API integration؛
4. SEO architecture/remediation؛
5. global button/design consistency cleanup.

## Parallel rule

M0/M1 API foundation می‌تواند هم‌زمان با P0 طراحی شود، اما Native PoC نباید روی UXهای critical و قراردادهای API ناپایدار قفل شود.

---

# نتیجه

برنامهٔ واقعی پیش از Native فقط M0–M5 فنی نیست. یک مسیر Product/UX موازی و صریح هم وجود دارد. مهم‌ترین موارد گم‌شده در سند قبلی عبارت بودند از:

- Home/Onboarding redesign؛
- Admin Location/Governance high-volume redesign؛
- support-progress/invite UX؛
- Project create/edit mobile redesign؛
- Najm Bahar mobile polish follow-ups؛
- mobile/tablet/desktop breakpoint cleanup؛
- Docs Center feedback/admin/SEO backlog؛
- global button/visual consistency.

این موارد باید از کارهای canonical Location/Governance بسته‌شده و از C14/featureهای deferred جدا نگه داشته شوند.