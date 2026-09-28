# Mintlify Transition & Cutover Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** انتقال امن `docs.earthcoop.ir` از وابستگی Production به Mintlify به مرکز اسناد اختصاصی EarthCoop، بدون شکستن لینک‌های عمومی یا از دست‌دادن امکان rollback.

**Architecture:** Mintlify تا آخرین Gate به‌عنوان fallback/preview باقی می‌ماند. قبل از cutover باید parity محتوا، سه‌زبانه‌بودن مرکز اختصاصی، crawl لینک‌ها، DNS/hosting ownership و rollback rehearsal تأیید شوند. سپس ترافیک به مرکز اختصاصی منتقل و Mintlify از Source-of-Truth/Production path خارج می‌شود.

**Tech Stack:** DNS/hosting فعلی پس از runtime audit Plan C، GitHub, HTTP redirect/health checks, existing EarthCoop link contracts.

**Spec:** `docs/superpowers/specs/2026-09-28-multilingual-documentation-architecture-design.md`

## Global Constraints

- Plan B (truth audit) و Plan C (multilingual center) باید کامل باشند.
- `EarthCoop-docs` Source of Truth باقی می‌ماند؛ cutover فقط Renderer/hosting را تغییر می‌دهد.
- هیچ تغییر DNS/hosting بدون rollback record و health check انجام نشود.
- لینک‌های قدیمی Mintlify که از خارج استفاده شده‌اند باید redirect یا compatibility mapping داشته باشند، نه 404 خام.
- تغییر domain routing نباید status حقوقی/نسخه سند را تغییر دهد.
- Mintlify بعد از cutover یا preview/fallback می‌شود یا بعد از دوره مشاهده جداگانه حذف می‌شود؛ حذف هم‌زمان با cutover ممنوع است.

## Review Focus

- cache/CDN ممکن است بخشی از کاربران را به renderer قدیمی بفرستد؛ health check باید origin واقعی را تشخیص دهد.
- لینک‌های قدیمی بدون locale ممکن است نیاز به redirect زبان پیش‌فرض داشته باشند.
- hash routes قدیمی `/#/documents/...` باید mapping مشخص به route جدید داشته باشند.
- rollback DNS بدون rollback محتوای registry نباید version drift ایجاد کند.
- robots/sitemap/search-index باید بعد از cutover به host جدید اشاره کنند.

---

### Task 1: Build route/link compatibility inventory

**Files:**
- Create in `saeidshojae/EarthCoop`: `docs/documentation/DOCS_CUTOVER_ROUTE_MAP.md`
- Create/modify test: `tests/Feature/Documentation/DocsCenterLinkContractTest.php`

**Interfaces:**
- Consumes: current `config/docs-links.php`, current Mintlify routes, new center routes from Plan C.
- Produces explicit mapping `old_url -> new_url -> behavior (200|301|302)`.

- [ ] **Step 1: Add RED contract cases for current official EarthCoop links**

Ensure existing document IDs and center/root links are enumerated and expected destination format is pinned before config changes.

- [ ] **Step 2: Run targeted RED/GREEN baseline**

Run: `php artisan test tests/Feature/Documentation/DocsCenterLinkContractTest.php`
Expected before changes: current contract PASS; new-route expectations remain pending until Task 3.

- [ ] **Step 3: Build compatibility map**

Include at minimum:
- `/`
- current `/#/documents/<id>` routes from `config/docs-links.php`
- Mintlify English root/article paths
- temporary `ar` locale workaround paths
- target `/fa|en|ar/...` paths.

- [ ] **Step 4: Commit inventory**

```bash
git add docs/documentation/DOCS_CUTOVER_ROUTE_MAP.md tests/Feature/Documentation/DocsCenterLinkContractTest.php
git commit -m "docs: map docs center cutover routes"
```

### Task 2: Prepare operational rollback and health checklist

**Files:**
- Create: `docs/runbooks/docs-center-cutover.md`

**Interfaces:**
- Produces operator checklist with exact current/target DNS records or provider routing, deploy identifiers, rollback steps, health URLs and stop conditions.

- [ ] **Step 1: Fill runbook from Plan C runtime audit**

Do not use generic DNS examples; record actual provider/record values at execution time.

- [ ] **Step 2: Define stop conditions**

Mandatory stop/rollback triggers: homepage unavailable, manifest load failure, >0 critical document 404, locale switch broken for canonical sample set, search unavailable, TLS failure.

- [ ] **Step 3: Perform rollback rehearsal without changing public DNS**

Use staging/preview or provider dry-run capability; record result/time/operator.

- [ ] **Step 4: Commit runbook**

```bash
git add docs/runbooks/docs-center-cutover.md
git commit -m "docs: add docs center cutover and rollback runbook"
```

### Task 3: Update EarthCoop official links to locale-aware canonical center URLs

**Files:**
- Modify: `config/docs-links.php`
- Modify: `tests/Feature/Documentation/DocsCenterLinkContractTest.php`
- Modify only if needed: `lang/fa/langWelcome.php`, `resources/lang/fa/langWelcome.php`

**Interfaces:**
- Produces canonical URL builder using the new center routing contract; default public language remains explicitly defined rather than inferred from browser state.

- [ ] **Step 1: Write RED tests for new canonical URLs**

Assert center and document links resolve to the chosen default locale namespace and that ECON-REF-01 uses its stable document identity/slug.

- [ ] **Step 2: Run RED**

Run: `php artisan test tests/Feature/Documentation/DocsCenterLinkContractTest.php`
Expected: FAIL until config is changed.

- [ ] **Step 3: Update `config/docs-links.php` minimally**

Do not hard-code a language different from the approved default. Preserve one central base URL and one document URL builder.

- [ ] **Step 4: Run GREEN**

Run: `php artisan test tests/Feature/Documentation/DocsCenterLinkContractTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add config/docs-links.php tests/Feature/Documentation/DocsCenterLinkContractTest.php lang/fa/langWelcome.php resources/lang/fa/langWelcome.php
git commit -m "feat(docs): point EarthCoop links to multilingual docs center"
```

### Task 4: Cut over `docs.earthcoop.ir`

**Files:**
- Operational provider/DNS change; no guessed repo file.
- Update runbook record after execution.

**Interfaces:**
- Consumes: production-ready center + route map + rollback runbook.
- Produces `docs.earthcoop.ir` served by EarthCoop-controlled center.

- [ ] **Step 1: Pre-cutover checks**

Confirm Plan B/C gates, latest backups, TLS readiness, DNS TTL awareness, and Mintlify fallback still live.

- [ ] **Step 2: Apply provider/DNS routing change**

Change only the minimum record/routing necessary to point public domain to the custom center.

- [ ] **Step 3: Run production smoke suite**

Check sample canonical docs in FA/EN/AR, root, search, language switch, hash-route compatibility, ECON-REF-01, and a foundational document.

- [ ] **Step 4: Roll back immediately on stop condition**

Use runbook exactly; do not debug in-place while public docs are critically unavailable.

- [ ] **Step 5: Record success/rollback evidence in runbook**

Commit only after service is stable.

### Task 5: Observe, then demote Mintlify

**Files:**
- Modify: `docs/runbooks/docs-center-cutover.md`
- Modify in `EarthCoop-docs`: `docs.json` / Mintlify integration only after observation decision.

**Interfaces:**
- Produces one explicit post-cutover state: `preview_only`, `fallback`, or `decommissioned`.

- [ ] **Step 1: Observation period**

Monitor broken links, search/indexing, locale errors and external inbound routes for the agreed observation window; do not remove Mintlify at cutover time.

- [ ] **Step 2: Choose post-cutover state based on evidence**

Record why Mintlify is retained or removed.

- [ ] **Step 3: If decommissioning, remove only deployment coupling**

Never delete canonical content because Mintlify is removed. Preserve repo history and any redirect requirements.

- [ ] **Step 4: Final verification**

Run EarthCoop docs link contract test plus EarthCoop-docs full Node validation and a live crawl of canonical routes.

## Plan Completion Gate

- custom center serves `docs.earthcoop.ir` reliably;
- all official EarthCoop links use canonical multilingual routes;
- old hash/Mintlify links redirect or resolve intentionally;
- rollback evidence exists;
- Mintlify status is explicitly recorded and no longer an accidental Source of Truth.