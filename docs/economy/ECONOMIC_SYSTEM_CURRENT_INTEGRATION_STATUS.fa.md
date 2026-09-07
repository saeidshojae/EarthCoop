# وضعیت یکپارچه فعلی نظام اقتصادی EarthCoop

تاریخ: 2026-08-29

## هدف این سند
این سند نقطه handoff امن برای ادامه توسعه اقتصاد EarthCoop است و مشخص می‌کند کدام شاخه مبناست، چه چیزهایی فریز شده‌اند و چه deltaهایی باید پیش از توسعه جدید reconcile شوند.

## Baseline فعلی
- شاخه مبنا: `agent/pre-main-ui-polish-responsive-docfix`
- SHA مبنا هنگام ایجاد integration branch: `ce6b93da48b5b9a68b2b86eadf955b667404dd1a`
- شاخه integration جدید: `agent/economic-system-current-integration`
- `main` در زمان این ثبت: `7766f8c732099c67d0f93d83bfd78fa4b57d64c0`

## Najm Bahar frozen lineage
Production Hardening نجم بهار در SHA زیر فریز شده است:

`ec529da60e12cc5ff332901927d0240c34c8886f`

شاخه baseline فعلی descendant مستقیم این checkpoint است و نسبت به آن 2119 commit جلوتر و 0 commit عقب‌تر بوده است. بنابراین Releaseهای A/B/C/D/Production Hardening در ancestry فعلی حفظ شده‌اند و نباید دوباره cherry-pick یا rebase شوند.

## Main lineage
Baseline فعلی نسبت به `main` برابر 1629 commit جلوتر و 0 commit عقب‌تر بوده است. بنابراین تا زمان تصمیم نهایی، `main` نباید مبنای توسعه اقتصادی جدید قرار گیرد و هیچ merge نهایی به `main` در این مرحله مجاز نیست.

## Stock canonical divergence
شاخه `agent/stock-canonical-cutover-readiness` با baseline فعلی diverge شده است. در مقایسه ثبت‌شده:
- baseline فعلی حدود 1017 commit اختصاصی نسبت به merge-base دارد؛
- Stock canonical حدود 87 commit اختصاصی دارد که در baseline فعلی نیست.

این 87 commit نباید کورکورانه merge یا cherry-pick شوند. باید به چهار دسته تفکیک شوند:
1. already incorporated
2. superseded
3. still required
4. semantic conflict

## آنچه از Stock در baseline فعلی موجود است
در baseline فعلی بخش مهمی از معماری canonical Stock از قبل وجود دارد، از جمله:
- `ActiveBaharReservation`
- `ActiveBaharReservationService`
- `ExternalPaymentIntent`
- `ExternalPaymentReconciliation`
- `StockSettlementAllocation`
- `FiatQuoteSnapshot`
- `StockPricingService`
- `ExternalCapitalPaymentService`
- `StockAtomicSettlementService`
- `StockBidAcceptanceService`
- `StockCanonicalAuctionSettlementService`
- `NajmBaharSettlementGateway`
- `CanonicalAwareAuctionService`
- Stock secondary-market gate

بنابراین ادامه کار باید بر مبنای reconciliation و completion باشد، نه بازسازی از صفر.

## قواعد معماری غیرقابل نقض برای Release بعدی
1. تنها عرضه اولیه/خزانه‌ای سهام خود EarthCoop حق external settlement با IRR/USD را دارد.
2. پول خارجی به Bahar تبدیل نمی‌شود و باعث mint شدن Bahar نمی‌شود.
3. fiat وارد balance نجم بهار نمی‌شود.
4. Stock wallet نباید به سیستم پولی دوم تبدیل شود.
5. بازار ثانویه و سهام سایر پروژه‌ها فقط با Active Bahar settle می‌شوند.
6. قیمت‌گذاری سهام می‌تواند Bahar-denominated باشد و fiat فقط quote/settlement خارجی مجاز را نمایندگی کند.
7. Money ledger و Stock/asset ownership ledger باید مستقل ولی settlement آن‌ها idempotent و قابل audit باشد.
8. refund/reversal باید event جدید و صریح باشد، نه mutation یا حذف تاریخچه.
9. هیچ توسعه‌ای نباید frozen core نجم بهار را بدون ضرورت معماری باز کند.

## وضعیت آماده‌سازی
این branch صرفاً برای آماده‌سازی integration ساخته شده است. قبل از feature work بعدی باید:
- delta 87 commitی Stock canonical audit شود؛
- فایل‌های مشترک Stock/NajmBahar/Provider/Workflow از نظر semantic conflict بررسی شوند؛
- validationهای Najm Bahar، Stock و integration روی head فعلی اجرا/بررسی شوند؛
- فقط deltaهای لازم به این branch منتقل شوند؛
- سپس implementation roadmap کوتاه Release Stock × Najm Bahar × External Capital ثبت شود.

## ممنوعیت‌ها
- هیچ merge به `main` انجام نشود.
- branchهای frozen اقتصادی rewrite نشوند.
- Stock canonical به‌صورت bulk و بدون audit وارد نشود.
- هیچ مسیر جدیدی برای external fiat خارج از عرضه اولیه سهام خود EarthCoop ایجاد نشود.

## نقطه ادامه در چت بعدی
شروع از branch:

`agent/economic-system-current-integration`

و ابتدا اجرای Stock canonical reconciliation matrix، سپس validation و فقط بعد از آن توسعه featureهای باقی‌مانده.
