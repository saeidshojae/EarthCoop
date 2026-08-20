# نگاشت حساب مقصد سرمایه Stockهای پروژه‌ای

## مسئله

`Stock` دارای `issuer_type` و `issuer_id` است، اما کد فعلی قرارداد قابل اتکایی ارائه نمی‌کند که `issuer_id` را مستقیماً به یک حساب Najm Bahar یا حتی الزاماً به `najm_bahar_projects.id` تبدیل کند. بنابراین canonical settlement نباید از روی `issuer_id` حساب مقصد را حدس بزند.

## قرارداد canonical

برای `issuer_type=earthcoop` حساب مقصد primary treasury از تنظیم زیر resolve می‌شود:

`STOCK_EARTHCOOP_CAPITAL_ACCOUNT_NUMBER`

برای `issuer_type=project`، جدول `stock_payee_accounts` نگاشت صریح زیر را نگه می‌دارد:

`stock_id -> najm_accounts.id`

شرایط mapping پروژه:

- Stock باید `issuer_type=project` باشد؛
- Account باید فعال باشد؛
- Account باید non-personal و از نوع `legal_entity` یا `central` باشد؛
- mapping برای هر Stock یکتا است؛
- purpose فعلی `primary_capital` است؛
- configured_by و verified_at برای audit ثبت می‌شوند.

## Settlement

`CanonicalAuctionCloseService` دیگر حساب EarthCoop را hard-code نمی‌کند. ابتدا `StockPayeeAccountService::resolvePrimary()` اجرا می‌شود:

- EarthCoop -> حساب سرمایه configured EarthCoop؛
- Project -> mapping صریح همان Stock؛
- issuer ناشناخته یا mapping ناقص -> fail-closed.

بنابراین درآمد عرضه اولیه سهام پروژه نمی‌تواند به حساب سرمایه EarthCoop منحرف شود.

## Founder Operations

تغییر mapping مالی یک عمل high-risk است:

`stock.configure_payee_account = approval_required`

صفحه:

`/admin/najm-hoda/founder-ops/stock-payees`

فقط proposal ایجاد می‌کند و تغییر واقعی بعد از Founder Approval از `FounderStockPayeeDecisionService` و `StockPayeeAccountService` عبور می‌کند.

حساب شخصی کاربر قابل انتخاب نیست.

## Readiness

`StockCanonicalReadinessService` برای primary treasury + Active Bahar، حساب مقصد را با همان resolver بررسی می‌کند. Stock پروژه‌ای بدون mapping معتبر blocker `project_payee_mapping_missing` می‌گیرد؛ با mapping معتبر این blocker حذف می‌شود.

`FounderStockRiskService` نیز از همین resolver استفاده می‌کند تا سیستم مدیریت کل و settlement درباره وضعیت payee پاسخ متفاوت ندهند.

## تست‌های مرزی

- حساب شخصی برای پروژه رد می‌شود؛
- پروژه بدون mapping fail-closed است؛
- mapping صریح به حساب non-personal فعال resolve می‌شود؛
- primary settlement پروژه وجه را فقط به حساب mapped پروژه می‌فرستد و حساب سرمایه EarthCoop دست‌نخورده می‌ماند؛
- policy Founder Ops تغییر payee را approval-required نگه می‌دارد.
