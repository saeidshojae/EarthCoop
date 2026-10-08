# وضعیت مرجع توسعه اپ موبایل EarthCoop — ۸ اکتبر ۲۰۲۶

> **این سند نقطهٔ شروع مرجع برای ادامهٔ توسعه موبایل است.**
> هنگام بازگشت به پروژه یا شروع چت جدید، ابتدا این سند و سپس checkpointهای لینک‌شده بررسی شوند. گزارش‌های قدیمی‌تر فقط receipt تاریخی‌اند و نباید باعث تکرار کارهای بسته‌شده شوند.

## 1) شاخه و وضعیت Git

شاخهٔ مورد ممیزی:

`agent/mobile-bahar-transfer-20261007`

HEAD تأییدشده در زمان این ممیزی:

`382e395a8bc2f98ecc0e5605ec9d45f44c5d2aa8`

پیام HEAD:

`docs(mobile): plan native Bahar activation implementation`

این branch دیگر با `main` هم‌خط نیست. در ممیزی 2026-10-08 مقایسهٔ GitHub آن را **diverged** نشان داد (421 commit جلو و 969 commit عقب نسبت به main در همان لحظه). بنابراین:

- هیچ merge مستقیم و کور این branch به `main` مجاز نیست.
- پیش از هر integration باید state نهایی موبایل با latest main به‌صورت کنترل‌شده reconcile/port شود.
- خود این سند وضعیت محصول/قابلیت را ثبت می‌کند، نه مجوز merge/deploy.

## 2) آخرین Android UAT واقعاً بسته‌بندی‌شده: +18

نسخهٔ موجود در `apps/mobile/pubspec.yaml`:

`1.0.0+18`

**+18 آماده، ساخته و verify شده است.**

Frozen software SHA:

`17f6521c84508b6c7038757a88377135233b66df`

Packaging run:

`37553750514`

Artifact:

`11453868837`

Inner APK SHA-256:

`62ab9cda4f890be8c8cba2a8cb6bbcff822bca0dca5cf11d9b63c13ed2b03fea`

APK size:

`67,952,215 bytes`

Stable UAT certificate SHA-256:

`eda5c77121b0bbf1c08b82fb61e59f55a7ea61a2fe0fbbb23537d5bc134c8543`

FCM configuration was present and host publication was false.

### +18 دقیقاً چه چیزی را شامل می‌شود؟

+18 بستهٔ امضاشدهٔ **Native Membership Payment** و قابلیت‌های قبل از آن است. Final software gate برای payment روی run `37551833441` سبز شد:

- server full suite: **2426 tests / 13820 assertions**؛
- Flutter: **285 tests passed**؛
- analyzer: clean؛
- formatter: zero changes.

### مرز مهم

**+18 شامل Native Bahar External Transfer نیست.**

انتقال بعد از frozen source نسخه +18 پیاده‌سازی و کامل شده است. بنابراین +18 را نباید «transfer-capable APK» معرفی یا برای UAT انتقال استفاده کرد.

## 3) Native Bahar External Transfer

وضعیت:

**Software complete / final automated evidence green / signed delivery candidate not yet packaged.**

Frozen product code:

`05106f816123ed0fe196b03a8f312cbe8a4067da`

Final workflow-only evidence head:

`84799fc942fb1f2e81ceaf8bb7b6d8bfdf125c36`

Final workflow:

`37595959108`

Evidence:

- transfer contracts: **21 tests / 211 assertions**؛
- server full suite: **2439 tests / 13938 assertions**؛
- Flutter full suite: **311 tests passed**؛
- analyzer clean؛
- formatter: **148 files / 0 changed**؛
- whole-change review: no remaining Critical/Important finding.

قرارداد بسته‌شده شامل capability، source eligibility، destination preview/token، strict consent snapshot، idempotent POST، no automatic financial retry، GET-only ambiguity reconciliation، session/logout invalidation و UI کامل review/receipt است.

مرجع کامل:

`docs/MOBILE_BAHAR_TRANSFER_CHECKPOINT_20261007.md`

### عمداً انجام نشده

- APK امضاشدهٔ جدید برای transfer ساخته نشده؛
- physical Android/iOS transfer UAT انجام نشده؛
- API سازگار transfer روی production deploy نشده؛
- هیچ انتقال پول واقعی/آزمایشی انجام نشده؛
- branch به main merge نشده؛
- host publication انجام نشده.

## 4) گام بعدی طراحی‌شده: Native Bahar Participation Activation

بعد از بسته‌شدن transfer، repository audit انجام و طراحی/plan فعال‌سازی نوشته شده است.

Audit:

`docs/MOBILE_NAJM_BAHAR_ACTIVATION_AUDIT_20261007.fa.md`

Design:

`docs/superpowers/specs/2026-10-07-mobile-bahar-activation-design.md`

Implementation plan:

`docs/superpowers/plans/2026-10-07-mobile-bahar-activation.md`

وضعیت واقعی activation:

**Design complete / implementation NOT started.**

سمت سرور legacy activation وجود دارد، ولی contract نیتیو سخت‌گیرانه هنوز پیاده‌سازی نشده است. برنامهٔ مصوب هفت Task دارد:

1. Harden eligibility contract.
2. Strict consent-bound activation POST.
3. Read-only reconciliation by idempotency.
4. Flutter typed wire contract.
5. Controller state machine.
6. Native UI/runtime.
7. Final evidence + review + checkpoint.

بنابراین **شروع مجدد از transfer Task 1 یا Task 2 اشتباه است**. Transfer بسته شده است. اگر توسعه از state این branch ادامه داده شود، نخستین feature task باز، Activation Task 1 است؛ ولی به دلیل divergence شدید branch با main، قبل از implementation باید integration strategy با latest main کنترل شود تا کار سالم از بین نرود یا دوباره ساخته نشود.

## 5) وضعیت Physical UAT

آخرین candidate واقعاً امضاشده برای یک run تجمیعی روی دستگاه:

**Android UAT +18**

اما UATهای فیزیکی بازمانده هنوز بسته نشده‌اند. از جمله:

- membership-payment physical acceptance؛
- real push/device delivery و tap gates؛
- logout/cache/session boundaries در دستگاه؛
- group message/feed و attachment save/open بسته به آخرین وضعیت acceptance؛
- iOS physical/signing/provider؛
- HMS physical/configuration؛
- transfer physical UAT فقط پس از ساخت candidate جدیدی که transfer را واقعاً شامل شود.

تست‌های تاریخی candidateهای +13 تا +17 نباید جداگانه تکرار شوند مگر regression مشخصی دلیل آن باشد.

## 6) قواعد جلوگیری از دوباره‌کاری

از این checkpoint به بعد:

- +18 را **آماده و verify‌شده** بدان.
- membership-payment software را **complete** بدان؛ physical/deploy جداست.
- Native Bahar transfer را **software-complete** بدان؛ packaging/device/deploy جداست.
- Native Participation Activation را **designed but not implemented** بدان.
- از اجرای مجدد taskهای transfer خودداری کن.
- برای status روز، این سند بر متن‌های تاریخی `MOBILE_REMAINING_WORK_20261005.md` اولویت دارد.
- هیچ main merge، production deployment، host publication، push-driver activation یا live financial mutation بدون مجوز صریح جدید انجام نشود.
- هنگام ساخت candidate بعدی، صرفاً برای inspection نسخه نساز؛ تغییرات phone-free را تجمیع و یک signed candidate جدید بساز.

## 7) checkpoint ادامه

اگر چت یا context از دست رفت، این ترتیب بازیابی شود:

1. این فایل: `docs/MOBILE_DEVELOPMENT_STATUS_20261008.fa.md`
2. transfer checkpoint: `docs/MOBILE_BAHAR_TRANSFER_CHECKPOINT_20261007.md`
3. activation audit/design/plan بالا
4. `apps/mobile/docs/DEVICE_ACCEPTANCE.md` برای device gates
5. سپس GitHub HEAD و latest main دوباره verify شوند، چون branch divergence ممکن است تغییر کرده باشد.

**نقطهٔ ادامهٔ محصول:** انتقال را تکرار نکن؛ activation اولین قابلیت طراحی‌شدهٔ باز است، مشروط به reconcile امن با latest main.
