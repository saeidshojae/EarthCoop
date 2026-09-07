# Stock Canonical Reconciliation Handoff

تاریخ: 2026-08-29

## وضعیت canonical فعلی

شاخه عملیاتی این workstream:
`agent/economic-system-current-integration`

PRهای ایزوله:
- `#87` — Draft integration surface
- `#88` — **VALIDATION ONLY — DO NOT MERGE** علیه `main`

هیچ merge یا deploy به `main` در این workstream مجاز یا انجام‌شده نیست.

## قواعد اقتصادی canonical که باید حفظ شوند

1. ارزش‌گذاری EarthCoop و قیمت پایه سهام در واحد صحیح **Gol** نگهداری می‌شود و معادل Bahar برای خوانایی نمایش داده می‌شود.
2. `1 Bahar = 100 Gol`.
3. مبنای قراردادی فعلی یعنی `1 Gol = 0.001 g` طلای خالص 24K.
4. عرضه‌ای که امکان تسویه خارجی دارد فقط عرضه **EarthCoop + primary + treasury** است.
5. fiat فقط quote/settlement است؛ fiat وارد موجودی Najm Bahar نمی‌شود و Bahar جدید ایجاد نمی‌کند.
6. Stock wallet نباید به سیستم پولی دوم تبدیل شود.
7. secondary market و ناشران/پروژه‌های دیگر فقط با **Active Bahar** تسویه می‌شوند.
8. ledger پول و ledger دارایی مستقل و audit-friendly باقی می‌مانند.
9. حداکثر برنامه عرضه اولیه EarthCoop برابر **10% کل سهام** است.
10. feature flagهای external capital و secondary market تا عبور از readiness/UAT/approval نهایی fail-closed باقی می‌مانند.

## وضعیت provider و quote

### Authoritative rate provider
پیاده‌سازی production adapter برای **Servix** وجود دارد:
- asset: `GOLD_24_RLS`
- source identifier: `servix:gold24:irr:v1`
- quote مستقیم قیمت یک گرم طلای خالص 24K به IRR
- `businessTime` به‌عنوان زمان quote
- API key از config/environment؛ هیچ secret در repository ثبت نشده است.

فرمول canonical IRR:
- اگر `P` قیمت مستقیم یک گرم طلای خالص 24K به IRR باشد، `1 Gol = P / 1000 IRR`.
- هیچ تبدیل 18K→24K و هیچ محاسبه float-based در منبع authoritative مجاز نیست.

### External payment provider
adapter برای **ZarinPal** وجود دارد:
- rollout نخست فقط IRR است.
- verification سمت سرور با merchant id + authority + مبلغ canonical انجام می‌شود.
- callback status به‌تنهایی trusted نیست.

### Currency scope
- rollout نخست: **IRR**.
- enabled currencies به‌صورت config-scoped و default-empty/fail-closed هستند.
- **USD همچنان disabled/fail-closed** است تا provider و UAT مستقل خود را داشته باشد.

وجود adapter به‌معنای تأیید حقوقی فروش سهام یا merchant approval نیست؛ requirements حقوقی، securities/KYC/AML و پذیرش provider مستقل باقی می‌مانند.

## EarthCoop Primary Offering Policy

`EarthCoopPrimaryOfferingPolicy` منبع حقیقت سقف عرضه اولیه است و موارد زیر را enforce می‌کند:
- issuer = `earthcoop`
- market = `primary`
- supply source = `treasury`
- سقف پیش‌فرض `1000 bps = 10%`
- offering <= treasury `available_shares`
- settled primary allocations + other open commitments + current offering <= policy envelope
- policy/disclosure version در evidence ثبت می‌شود.

در محاسبه cap، `total_shares - available_shares` به‌عنوان فروش تاریخی فرض نمی‌شود؛ settled allocations canonical مبنای مصرف cap هستند.

## Canonical Stock UI Cutover — تکمیل‌شده در current lineage

### Admin Stock valuation
فرم ادمین دیگر valuation یا base share price ریالی دریافت نمی‌کند.
- ورودی اصلی: `startup_valuation_bahar`
- تبدیل: Bahar → integer Gol
- قیمت پایه هر سهم از valuation Gol / total shares محاسبه می‌شود.
- اگر تقسیم به integer Gol/share دقیق نباشد، درخواست reject می‌شود.
- legacy decimal columns فقط برای compatibility داخلی transitional باقی مانده‌اند و منبع canonical UI نیستند.

### Admin auction create/edit
write path ادمین به controller canonical اختصاصی منتقل شده است.
- input قیمت: `base_price_gol` integer
- server-side identity locked to:
  - `market_type=primary`
  - `supply_source=treasury`
  - `quote_unit=gol`
  - `settlement_channel=external_capital`
- hidden UI fields authority محسوب نمی‌شوند؛ server contract تعیین‌کننده است.
- `EarthCoopPrimaryOfferingPolicy` در create/update reuse می‌شود و سقف 10% enforce می‌شود.

### Admin auction list/show
سطوح خواندنی ادمین اکنون صریحاً نمایش می‌دهند:
- قیمت پایه در Gol و معادل Bahar
- market type
- treasury supply source
- settlement channel
- وضعیت حراج/تسویه

عبارت و ورودی «قیمت پایه ریالی» از این سطوح canonical حذف شده است.

### Stock Book
Stock Book به دفتر دارایی سهام بازطراحی شده و نه wallet پولی:
- valuation کل: Bahar + Gol
- price/share: Gol + Bahar
- total shares و treasury available shares
- max 10% primary envelope
- open primary commitments و remaining envelope
- active/scheduled primary auctions
- user holdings به‌عنوان ownership asset
- secondary market به‌صورت صریح disabled تا rollout رسمی
- external settlement status به‌صورت مستقل و fail-closed

read path Stock Book از legacy side effectها جدا شده است:
- `WalletService` اجرا نمی‌شود.
- بازکردن Stock Book wallet پولی ایجاد نمی‌کند.
- `recalculateMarketData()` legacy در read اجرا نمی‌شود.

## External Capital Readiness

`ExternalCapitalReadinessGate` همچنان fail-closed است و برای rollout واقعی حداقل موارد زیر باید سبز/attested باشند:
- external-capital feature flag
- authoritative rate provider availability + allowlist
- payment provider availability
- primary offering policy/disclosure configuration
- rate provider UAT
- payment provider UAT
- refund/reversal GameDay
- offering-policy validation
- Stock regression
- Najm Bahar regression
- Full Validation
- founder rollout approval

بنابراین **وجود UI canonical یا adapterها به‌معنای فعال‌شدن خرید خارجی در production نیست**.

## Refund / reversal / reconciliation

- provider identity پس از binding immutable است.
- idempotent replay اجازه provider switch نمی‌دهد.
- reconciliation append-only است.
- refund/reversal eventهای صریح و جدید هستند.
- partial refund فعلاً fail-closed است.
- پس از asset settlement، refund/reversal بدون asset reversal صریح مجاز نیست.

## Validation evidence

آخرین **code-bearing** head که قبل از این documentation update به‌طور کامل validate شد:
`32139c5cda2efe28afe7a7cc55964bffcff193ae`

GitHub Actions روی همان head:
- EarthCoop Responsive Contract Validation #144: **SUCCESS**
- EarthCoop Integration Full Validation #1778: **SUCCESS**

Full Validation #1778 با success از همه gateهای enforce‌شده عبور کرد:
- full schema migration
- route and command boot
- Group Chat regression
- Group Admin / Identity regression
- Najm Hoda + n8n regression
- Governance regression
- Najm Bahar regression
- Stock regression
- Group Chat JavaScript regression
- Full Project PHPUnit
- regression gate enforcement

در Full Project PHPUnit، testهای canonical valuation، admin auction UI/write/read surfaces و Stock Book نیز پس از اصلاح test context سبز شدند.

## موارد واقعاً باز قبل از production rollout

- تنظیم credential واقعی Servix و UAT نرخ مستقیم 24K.
- تنظیم merchant credential واقعی ZarinPal و UAT request/verify/callback/reconciliation.
- اجرای refund/reversal GameDay end-to-end.
- تأیید حقوقی/عملیاتی عرضه سهام، securities/KYC/AML و پذیرش provider برای jurisdiction هدف.
- Founder rollout approval.
- فعال‌کردن explicit IRR در enabled currencies فقط پس از readiness کامل.
- USD provider/rate/UAT مستقل؛ تا آن زمان fail-closed.
- secondary market rollout مستقل؛ تا آن زمان disabled.
- reconciliation/migration صریح برای هر فروش primary تاریخی پیش از canonical ledger، اگر چنین داده‌ای در production وجود داشته باشد.

## Start point for next implementation/UAT session

Branch:
`agent/economic-system-current-integration`

Validation-only PR:
`#88` — **DO NOT MERGE**

گام منطقی بعدی دیگر بازنویسی معماری Stock نیست. مرحله بعد باید **UAT عملی UI + provider readiness** باشد:
1. UAT فرم Admin Stock valuation؛
2. UAT create/edit/list/show حراج primary treasury؛
3. UAT Stock Book به‌عنوان asset ledger؛
4. تهیه/اعمال credentialهای sandbox/test provider در محیط امن، نه repository؛
5. rate/payment provider UAT و reconciliation/refund/reversal GameDay؛
6. بررسی حقوقی rollout؛
7. فقط پس از همه این موارد، تصمیم Founder درباره فعال‌کردن feature flag؛
8. همچنان هیچ merge به `main` بدون تصمیم صریح Founder انجام نشود.
