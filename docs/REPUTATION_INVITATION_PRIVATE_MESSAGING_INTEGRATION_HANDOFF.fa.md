# Handoff — یکپارچه‌سازی Reputation + Invitation + Private Messaging

**Branch نهایی فعلی:** `agent/private-messaging-professionalization`

## هدف

این branch خط نهایی توسعه پیش از لانچ است و باید هم‌زمان دستاوردهای نهایی Reputation/Participation Points، سیستم دعوت لانچ و Private Messaging Phase 1 را نگه دارد؛ بدون هیچ merge به `main` تا زمان تأیید صریح نهایی.

## منشأها

- Reputation R6 freeze: `329ccee36be96543c42641672cdedf96b9b45d0e`
- Reputation last proven code checkpoint: `8503e2c73b20dbca5a915190f052a75610822320`
- Private messaging line before integration: `12740c6d0b5c186f9b0e926bca71db62bc5a0922`
- Common historical merge base: `145561809560ec58f44745dba4f87de10dd46a11`
- Integration merge commit: `c00ed8f442a7fa21e04f083a7fe770b9aa94d569`

## تصمیم‌های reconciliation

1. معماری جدید دعوت بر معماری قدیمی R6 مقدم است. پاداش دعوت از `InvitationLifecycleService` و پس از completion معتبر ثبت‌نام صادر می‌شود؛ مسیر قدیمی `NajmBaharController::processReferralParticipation` بازگردانده نشده است.
2. policy جدید دعوت 100 امتیاز Participation است و contract جدید lifecycle آن را صریحاً قفل می‌کند. این مقدار جای مقدار قدیمی 10 در freeze R6 را می‌گیرد.
3. stable event identity دعوت حفظ شده است: `invite_member:referrer:{referrer}:member:{invitee}`.
4. stable event identity حق عضویت نیز حفظ شده است. برای حفظ آخرین `NajmBaharMembershipFeeController`، canonical membership event key در `ReputationService` برای `membership_fee_paid` از user + payment year ساخته می‌شود.
5. `ParticipationPointSummaryService`، ledger conversion، چهار dimension Reputation، public/private privacy boundary، public profile card، «مشارکت‌های من → امتیازات من»، Najm Bahar wallet transparency و admin audit فارسی از R6 حفظ شده‌اند.
6. public member profile فقط Reputation عمومی را نمایش می‌دهد و state اقتصادی/convertibility خصوصی را دریافت یا منتشر نمی‌کند.
7. Invite lifecycle، participation gate و Private Messaging Phase 1 خط جدید حفظ شده‌اند.

## TDD / Regression Evidence

قبل از integration، `ReputationLatestBranchIntegrationContractTest` روی latest private-messaging line عمداً RED شد و Full Validation #2125 تنها به‌علت نبود `ParticipationPointSummaryService` شکست خورد. این failure همان regression مشاهده‌شده در UAT را بازتولید کرد.

پس از reconciliation، قراردادهای R6 و دعوت جدید در یک lineage قرار گرفته‌اند. freeze نهایی فقط وقتی معتبر است که Full + Responsive exact-head هر دو GREEN باشند.

## PRها

- PR #93: functional Private Messaging branch PR.
- PR #94: branch-to-branch diagnostic integration PR؛ پس از manual reconciliation دیگر مرجع merge نهایی نیست.
- PR #95: **VALIDATION ONLY** به `main` برای trigger کردن CI؛ **هرگز merge نشود**.

## UAT پس از pull

- `/profile-member/{user}`: کارت Reputation عمومی باید دیده شود.
- `/history` → «امتیازات من»: summary جدید، ابعاد Reputation، ظرفیت قابل تبدیل و CTA تبدیل به بهار باید دیده شود.
- Najm Bahar wallet: summary canonical و تفکیک earned/consumed/remaining باید دیده شود.
- Admin Reputation: audit فارسی و قواعد منسوخ انتخابات باید read-only/guarded باشند.
- Invitation lifecycle و Private Messaging Phase 1 باید بدون regression باقی مانده باشند.

## قانون ایمنی

هیچ merge یا تغییر مستقیم روی `main` در این integration مجاز نیست. ادغام production فقط بعد از اتمام UAT مشکلات باقی‌مانده و تأیید صریح کاربر انجام می‌شود.
