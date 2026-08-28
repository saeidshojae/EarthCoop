# Stock Canonical Reconciliation Handoff

تاریخ: 2026-08-29

## نتیجه ممیزی مقدماتی

### 1) lineage معتبر فعلی
شاخه `agent/economic-system-current-integration` از `agent/pre-main-ui-polish-responsive-docfix` ساخته شده و همه workهای frozen نجم بهار را در ancestry خود دارد.

### 2) Stock slices
`agent/stock-slice-6-secondary-market-gate` ancestor مستقیم baseline فعلی است؛ baseline نسبت به آن 991 commit جلوتر و 0 commit عقب‌تر است. بنابراین Slice 2b تا Slice 6 در lineage فعلی گم نشده‌اند و نباید دوباره cherry-pick شوند.

### 3) Stock canonical cutover branch
`agent/stock-canonical-cutover-readiness` یک شاخه validation-only تاریخی است و با lineage فعلی diverge شده است. PR #73 صراحتاً برای validation ساخته شده و DO NOT MERGE بوده است. آن PR scope زیر را validate می‌کرد:
- canonical Gol pricing / bid acceptance
- Active Bahar reservation / settlement
- primary treasury canonical close
- external capital intent/reconciliation boundary
- secondary seller reservation/listing/settlement/UX
- project payee mapping
- stock readiness gate
- Founder Ops risk visibility

همزمان در همان PR دو feature flag عمداً خاموش مانده بودند:
- `STOCK_SECONDARY_MARKET_ENABLED=false`
- `STOCK_EXTERNAL_CAPITAL_ENABLED=false`

### 4) چرا merge مستقیم canonical cutover ممنوع است
در شاخه فعلی implementationهای جدیدتری وجود دارد که در شاخه canonical cutover اصلاً وجود ندارند، از جمله `StockCanonicalAuctionSettlementService` و مجموعه serviceهای settlement جدید. همچنین migration external reconciliation در شاخه فعلی explicit short index names دارد و از نسخه تاریخی canonical مقاوم‌تر است.

بنابراین 87 commit اختصاصی canonical-cutover به‌عنوان یک merge payload معتبر تلقی نمی‌شوند. آن branch باید reference/validation history باشد، نه source branch برای merge bulk.

## Reconciliation classification

### Already incorporated
- Active Bahar reservation model/service
- external payment intent/reconciliation domain
- integer/Gol pricing foundation
- atomic stock settlement foundation
- secondary-market gate lineage
- canonical settlement gateway foundation
- Stock/Najm Hoda runtime visibility foundation

### Superseded on current lineage
- historical canonical cutover wiring that predates `StockCanonicalAuctionSettlementService`
- historical external reconciliation index form
- historical validation-only workflow/diagnostic commits where equivalent/newer validation exists in current lineage

### Still intentionally pending product completion
- real external payment provider integration
- authoritative gold/FX rate source and quote policy
- enabling external-capital feature flag after provider/rate-source validation
- enabling secondary market only after current-lineage validation/signoff
- final EarthCoop primary offering policy/cap enforcement and operational UI/UAT
- end-to-end reconciliation/refund/reversal UAT on current lineage

### Semantic conflict watchlist for next implementation session
- `app/Modules/Stock/*`
- `app/Modules/NajmBahar/Services/MonetaryPolicyService.php`
- `app/Providers/AppServiceProvider.php`
- `app/Providers/AssetPipelineServiceProvider.php`
- `app/Providers/RouteServiceProvider.php`
- Stock migrations duplicated under module/database migration paths
- Stock/FounderOps authority and risk surfaces
- `.github/workflows/integration-full-validation.yml`
- `.github/workflows/stock-secondary-market-gate.yml`

## Validation posture at handoff
- baseline SHA before docs: `ce6b93da48b5b9a68b2b86eadf955b667404dd1a`
- Responsive Contract Validation on that SHA: success
- Integration Full Validation on that exact SHA: cancelled, not failed; therefore it must be rerun/observed on the first code-bearing commit of the next session before declaring the new economic integration green.
- docs-only handoff commits did not create a new PR-triggered validation run.

## Start point for next chat
Branch:
`agent/economic-system-current-integration`

Draft PR:
`#87`

First implementation task:
1. re-read these two economy handoff docs;
2. inspect current Stock feature flags/config/provider/rate-source boundaries;
3. write a focused implementation plan for the remaining Stock × Najm Bahar × External Capital completion work;
4. implement only on this branch;
5. run Stock gates + Najm Bahar regression gates + Integration Full Validation;
6. do not merge to `main`.

## Canonical economic rules to preserve
- EarthCoop share valuation/auction price may be Bahar-denominated.
- fiat is only quote/settlement for EarthCoop primary/treasury share offering.
- fiat does not become Bahar and does not mint Bahar.
- Stock wallet is not a parallel monetary system.
- project shares and secondary market settle only with Active Bahar.
- money ledger and asset ledger remain separate and auditable.
