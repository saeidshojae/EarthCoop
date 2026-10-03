# ممیزی Product & UX تا پیش از Native Mobile

**تاریخ تطبیق:** 2026-09-27  
**Baseline:** `main@b0f6e6082e3f248187a0bed66fb899dd0d7701c2`  
**مبنای ممیزی:** `main` + PRهای merge‌شده + Validation/UAT ثبت‌شده + تصمیم‌های Product/UX پروژه.  
**وضعیت:** نسخهٔ جاری و جایگزین پیش‌نویس قدیمی PR #150.

## قواعد وضعیت

- **CLOSED** — اجرا/merge شده و شواهد کافی برای بسته‌شدن دارد.
- **IMPLEMENTED / VERIFY** — پیاده‌سازی وجود دارد، اما manual UAT نهایی همان UX هنوز بهتر است ثبت شود.
- **OPEN** — نیاز پذیرفته شده و هنوز completion ثبت نشده است.
- **DEFERRED** — عمداً برای بعد گذاشته شده و blocker Mobile Readiness نیست.

---

# 1. Home / Onboarding / Welcome

## 1.1 بازطراحی Home و onboarding — **CLOSED**

این مورد که در ممیزی 2026-09-26 هنوز OPEN بود، با PRهای #151 تا #153 بسته شد.

بسته‌شده‌ها:

- حفظ سه کارت canonical گروه‌ها و شمارش‌های موجود؛
- onboarding چهارمرحله‌ای Location/Governance → Najm Bahar → Groups → Invite/Participation؛
- visual language هماهنگ با Registration؛
- mobile-first composition واقعی؛
- admin-managed slider/content؛
- journey state + Today signals + next action؛
- pending location visibility؛
- membership-fee state و deep-link پرداخت؛
- معرفی سبک نجم هدا؛
- shared sidebar section polish؛
- responsive contract دائمی.

PR #151، #152 و #153 همگی merge شده‌اند. بنابراین Home/Onboarding دیگر P0 باز نیست.

## 1.2 real-device polish Home — **VERIFY ONLY**

Automated Responsive/Integration gates سبز هستند. اگر قبل از launch لازم باشد، فقط UAT بصری روی چند viewport/device نهایی باقی می‌ماند؛ این یک redesign جدید نیست.

---

# 2. Admin Location / Governance Control Center

## 2.1 هستهٔ Admin Control Center — **CLOSED**

موجود و بسته:

- Proposal Queue؛
- Reference Explorer؛
- official topology؛
- Community/import/health diagnostics؛
- status filtering؛
- audit context؛
- support/threshold information؛
- approve / reject / merge / request-evidence؛
- reason/evidence lifecycle؛
- parent/dependency-aware review؛
- separate proposal / structure claim / settlement concepts؛
- responsive action forms.

## 2.2 بازطراحی حرفه‌ای برای حجم بالا — **OPEN**

باقی‌مانده:

- queue density بهتر؛
- filter/sort مؤثر برای حجم بالا؛
- parent path و pending-parent state واضح؛
- stateهای روشن `pending / ready_for_review / approved / rejected / needs_evidence`؛
- support progress/count برجسته؛
- evidence summary قابل اسکن؛
- duplicate/merge candidate visibility؛
- action hierarchy واضح؛
- mobile review UX واقعی؛
- تفکیک بصری proposal، structure claim، reference settlement و residence-related queueها.

این یک Product/Operations UX gap است، نه backend lifecycle gap.

---

# 3. Location proposal/support UX

## 3.1 backend support/review lifecycle — **CLOSED**

Distinct-user support، threshold پیش‌فرض ۱۰، committed-selection provenance، propagation، ready-for-review و rejection cleanup در Checkpoint 3 بسته شده‌اند.

## 3.2 user-facing support progress + invite UX — **OPEN**

باقی‌مانده:

- نمایش support count در برابر threshold؛
- توضیح اینکه threshold به معنی approval نیست؛
- CTA دعوت کاربران نزدیک/مرتبط؛
- توضیح اینکه انتخاب proposal توسط کاربر بعدی چگونه support می‌سازد؛
- state روشن `pending / ready_for_review / approved`؛
- پاسخ UX به سؤال «چرا هنوز تأیید نشده؟».

---

# 4. My Location & Governance

## 4.1 redesign mobile-first و official/community separation — **CLOSED**

- navigation مشترک؛
- responsive layout/touch targets؛
- official governance chain؛
- Community برای Street/Alley/Complex/Building؛
- opt-in Community؛
- consistency با My Groups و Current Elections.

## 4.2 manual real-device polish — **IMPLEMENTED / VERIFY**

Automated gates سبزند؛ real-device/browser matrix نهایی در صورت نیاز launch باقی می‌ماند.

---

# 5. Navigation / Header / Drawer / Sidebar

## 5.1 categorized mobile drawer baseline — **CLOSED**

پایهٔ drawer، submenuها، Location/Governance link، badgeهای canonical و sidebar دسکتاپ قبلاً بسته شده بود.

## 5.2 unified authenticated taxonomy — **CLOSED**

PR #154 taxonomy مشترک Sidebar/Drawer را تثبیت کرد:

- خانه؛
- شبکه و ارتباطات؛
- حکمرانی و مشارکت؛
- اقتصاد؛
- سازمان و همکاری؛
- حساب و پشتیبانی؛
- کاوش و اسناد؛
- مدیریت برای ادمین.

همچنین:

- legacy wallet/holding navigation حذف شد؛
- «دفتر سهام ارزش» و «حراج‌های سهم ارزش» تثبیت شدند؛
- duplicate Najm Hoda about route/view حذف و profile canonical استفاده شد.

## 5.3 Docs Center navigation alignment — **CLOSED**

PR #155 لینک‌های legacy Mintlify را با self-hosted Docs Center جایگزین کرد، STD را به‌عنوان سند بنیادین دهم اضافه کرد و Footer/Welcome/config را هم‌راستا کرد.

PR #156 Sidebar/Drawer را مستقیماً به config canonical وصل و `docs-center-link-normalizer.js` موقت را حذف کرد.

این بخش deploy شده است.

## 5.4 breakpoint architecture — **OPEN**

معماری واضح Mobile / Tablet-Compact / Desktop هنوز completion صریح ندارد. مواردی مثل رفتار header در 1366px باید به‌عنوان یک cleanup محدود و قابل‌اندازه‌گیری بسته شوند، نه redesign کلی.

---

# 6. My Groups / Group surfaces

## 6.1 responsive My Groups reference implementation — **CLOSED**

responsive system، canonical counts/sidebar، role/topology alignment و pending-group display harden شده‌اند.

## 6.2 pending-role wording polish — **VERIFY**

در UAT قدیمی wording «فعال» برای pending group محل ابهام بود. بعداً role presentation چند دور اصلاح شد، اما completion دستی دقیق همین wording بهتر است فقط verify شود. Task توسعهٔ بزرگ نیست.

---

# 7. Group Chat / Group Control Center

## 7.1 functional/realtime hardening — **CLOSED**

Group Chat، realtime، unread behavior و role presentation regressionهای بالغ دارند.

## 7.2 Group Najm Hoda action queue — **CLOSED**

unassigned/urgent، overdue، evidence/history و server-authorized edits پیاده‌سازی شده‌اند و backlog باز نیستند.

---

# 8. Project create/edit UX

## 8.1 geographic target scope contract — **CLOSED**

کاربر می‌تواند روی هر سطح معتبر جغرافیایی متوقف شود و همان scope را ذخیره کند؛ persistence/edit hydration بسته شده است.

## 8.2 mobile-responsive redesign create/edit — **OPEN**

صفحهٔ create/edit Project هنوز نیازمند redesign حرفه‌ای موبایل به‌عنوان task مستقل است. این مورد در PRهای بعدی بسته نشده است.

---

# 9. Najm Bahar UX

## 9.1 mobile bottom-sheet / tabs / modal stacking — **IMPLEMENTED / VERIFY**

موارد گزارش‌شده:

- mobile bottom-sheet؛
- mobile tabs «حساب من / وضعیت سامانه»؛
- modal overlay بالای header؛
- responsive validation.

برای این specific UX بهتر است یک UAT دستی نهایی ثبت شود.

## 9.2 follow-upهای بصری/رفتاری — **OPEN / REVALIDATE BEFORE WORK**

موارد قدیمی:

- gold glow/pulse برای mobile menu؛
- wallet-page parity؛
- coin animation مطابق Welcome؛
- ضخامت مناسب‌تر سکه در موبایل.

قبل از اجرا باید روی main فعلی دوباره سنجیده شوند تا چیزی که supersede شده دوباره ساخته نشود.

---

# 10. Docs Center UX / Admin / SEO

## 10.1 self-hosted Docs Center core — **CLOSED**

بسته‌شده‌ها:

- full-text search؛
- print/copy؛
- responsive/readability fixes؛
- repo-controlled import؛
- pinned commit/diff/private preview/human approval؛
- self-hosting روی `docs.earthcoop.ir`؛
- EarthCoop logo؛
- PDF download؛
- status-evidence gate؛
- private admin publishing assistant؛
- نسخهٔ 0.7.0 و validationهای آن.

## 10.2 main-site integration — **CLOSED**

PR #155 و #156:

- legacy `/fa/...` Mintlify links را از source-of-truth فعلی حذف کردند؛
- ۱۰ سند FC تا STD را وارد config کردند؛
- publication policy را canonical کردند؛
- Footer duplication را حذف کردند؛
- Welcome/Footer/Sidebar/Drawer را هم‌راستا کردند؛
- shim runtime را حذف کردند.

## 10.3 backlog مستقل Docs Center — **OPEN**

- per-clause comments/feedback queue؛
- admin status-management UI؛
- Laravel/API/SSO integration؛
- automated cPanel deployment؛
- professional SEO multi-page architecture؛
- canonical non-hash URL architecture به‌جای اتکای نهایی به `#/documents/...`؛
- main-site SEO remediation در سطح Laravel/metadata/sitemap/indexing.

این موارد roadmap محصول‌اند، اما blocker مستقیم Native PoC نیستند مگر launch scope آن‌ها را gate کند.

---

# 11. Dashboard / Button System / Visual Consistency

## 11.1 shared responsive foundation — **CLOSED FOUNDATION**

responsive primitives، My Groups reference، Home responsive contracts و mobile navigation foundation وجود دارند.

## 11.2 Home-local button/card hierarchy — **CLOSED**

PRهای #151 تا #153 hierarchy و visual system Home را تثبیت کردند.

## 11.3 global button/design consistency — **OPEN**

هنوز یک protocol سراسری برای button variants، animation behavior، spacing و reusable states در همهٔ صفحات ثبت‌شده به‌عنوان Done نداریم.

این کار باید pattern cleanup باشد، نه پروژهٔ redesign عظیم.

---

# 12. کارهایی که نباید دوباره به‌عنوان UX backlog باز شوند

- Registration Step 3 governance-base stopping؛
- deep pending proposal chain؛
- Street/Alley/Complex/Building branching؛
- no-region/no-neighborhood flows؛
- My Location official/community separation؛
- canonical My Groups counts/sidebar؛
- Current Elections responsive hardening؛
- proposal/review backend lifecycle؛
- admin basic review actions؛
- project stop-at-any-level geographic scope؛
- Home/Onboarding redesign؛
- authenticated navigation taxonomy؛
- main-site Docs Center link alignment؛
- temporary docs-link normalizer removal.

این‌ها regression targets هستند، نه پروژهٔ جدید.

---

# 13. Product/UX priorities before Native PoC

## P0 — launch-critical/open

1. **Admin Location/Governance high-volume redesign**؛
2. **proposal support progress + invite UX**؛
3. **Project create/edit mobile redesign**؛
4. **Najm Bahar mobile UX verify + فقط follow-upهای واقعاً باقی‌مانده**؛
5. **breakpoint architecture cleanup** برای Mobile / Tablet-Compact / Desktop؛
6. **targeted real-device/browser verification** برای صفحات critical.

Home/Onboarding دیگر از P0 حذف شده چون با PRهای #151–#153 بسته شده است.

## P1 — product operations / docs / design

1. Docs Center feedback/comments queue؛
2. Admin status-management UI؛
3. Docs SSO/API integration؛
4. SEO/canonical multi-page architecture؛
5. automated cPanel deployment؛
6. global button/design consistency cleanup.

---

# 14. Technical Mobile Readiness lane — مرجع موازی

این سند جای roadmap فنی را نمی‌گیرد. ترتیب فنی در `docs/PRE_NATIVE_MOBILE_READINESS_STATUS.fa.md` است:

1. M0 — API Constitution + capability inventory؛
2. M1 — `/api/v1` implementation؛
3. M2 — Najm Hoda stable mobile contract؛
4. M3 — Najm Bahar mobile contract؛
5. M4 — minimal Organization/Marketplace/Shop domain boundary؛
6. M5 — device/push/upload/realtime/offline foundation؛
7. Architecture gate؛
8. Native PoC.

P0های Product/UX می‌توانند موازی با M0/M1 اجرا شوند. Native PoC نباید روی API contractهای ناپایدار قفل شود.

---

# نتیجهٔ جاری

از فهرست قبلی، سه خوشهٔ مهم اکنون بسته و حذف‌شده از backlog باز هستند:

- Home/Onboarding؛
- unified authenticated navigation؛
- main-site ↔ self-hosted Docs Center link integration + shim cleanup.

باقی‌ماندهٔ واقعی Product/UX پیش از Native عمدتاً این‌هاست:

- Admin Location/Governance high-volume UX؛
- support-progress/invite UX؛
- Project create/edit mobile redesign؛
- Najm Bahar targeted verification/follow-ups؛
- breakpoint cleanup؛
- targeted real-device verification؛
- Docs feedback/admin/SEO backlog؛
- global design consistency.

کار اصلی فنی بعدی نیز **M0 — API Constitution و Mobile Capability Inventory** است.
