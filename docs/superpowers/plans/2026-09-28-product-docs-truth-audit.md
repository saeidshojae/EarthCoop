# Product Documentation Truth Audit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** ممیزی و بازنویسی راهنماهای محصول EarthCoop به‌گونه‌ای که متن انگلیسی فقط واقعیت فعلی را Available معرفی کند، وضعیت آینده را شفاف برچسب بزند، نام قدیمی `NewEarthCoop` حذف شود و ترجمه عربی از متن تأییدشده مشتق شود.

**Architecture:** ابتدا inventory ماشینی از تمام صفحات راهنمای انگلیسی و ادعاهای حساس ساخته می‌شود. سپس برای هر صفحه evidence map از Production/code/test/approved spec ثبت می‌شود و صفحه به Keep/Correct، Rewrite یا Remove/Archive طبقه‌بندی می‌گردد. بازنویسی انگلیسی قبل از تولید عربی انجام می‌شود؛ فارسی حقوقی/مرجع مستقل از این plan باقی می‌ماند.

**Tech Stack:** Markdown/MDX, Node.js 22, `node:test`, GitHub code search/read-only evidence from `saeidshojae/EarthCoop`, multilingual glossary from Plan A.

**Spec:** `docs/superpowers/specs/2026-09-28-multilingual-documentation-architecture-design.md`

## Global Constraints

- Plan A باید merged و manifest v2/glossary در دسترس باشد.
- AI حق ندارد برای پرکردن شکاف، Feature موجود اختراع کند.
- evidence precedence: Production/UI → code/tests → approved spec/plan → foundational/reference docs → old guide.
- `available` فقط با evidence معتبر؛ `in_development` فقط با implementation فعال/approved plan؛ `planned` برای معماری/roadmap بدون feature فعلی.
- `NewEarthCoop` در محتوای عمومی فقط در context تاریخی صریح مجاز است؛ نام محصول جاری `EarthCoop` است.
- Bahar ارز/واحد اصلی است و Gol زیرواحد است؛ هیچ صفحه‌ای Gol را currency مستقل معرفی نکند.
- راهنماهای محصول نباید status حقوقی اسناد را تغییر دهند.

## Review Focus

- یک صفحه قدیمی ممکن است از نظر UI هنوز route داشته باشد اما semantics آن منسوخ باشد؛ route وجود داشتن evidence کافی برای متن نیست.
- feature partially implemented نباید کل workflow را Available نشان دهد.
- alias یا نام legacy داخلی دیتابیس/تست نباید در متن عمومی محصول ظاهر شود.
- ترجمه عربی نباید متن انگلیسی منسوخ را mirror کند؛ فقط از نسخه English reviewed/current ساخته شود.
- لینک‌های cross-page بعد از archive/rewrite نباید 404 شوند.

---

### Task 1: Build deterministic documentation inventory

**Files:**
- Create in `saeidshojae/EarthCoop-docs`: `scripts/inventory-product-guides.mjs`
- Create: `test/product-guide-inventory.test.mjs`
- Create generated artifact: `audits/product-guides/2026-09-28-inventory.json`

**Interfaces:**
- Produces CLI: `node scripts/inventory-product-guides.mjs . --out audits/product-guides/2026-09-28-inventory.json`
- Output item: `{ path, title, links, legacyTerms, sensitiveClaims }`.

- [ ] **Step 1: Write RED inventory test**

Fixtures must prove scanner:
- includes English `introduction.mdx`, `quickstart.mdx`, `groups/overview.mdx`, `projects/overview.mdx`, `najm-bahar/overview.mdx`, `najm-hoda/overview.mdx`;
- excludes `fa/`, `ar/`, `published/`, `releases/` from product-guide inventory;
- flags case-insensitive `NewEarthCoop`, `GOL unit`, `fully available`, `scheduled transfers`, and `create a group` as sensitive/legacy phrases.

- [ ] **Step 2: Run RED**

Run: `node --test test/product-guide-inventory.test.mjs`
Expected: FAIL.

- [ ] **Step 3: Implement scanner and generate inventory**

Do not modify source pages in this task.

- [ ] **Step 4: Run GREEN and commit**

Run: `node --test test/product-guide-inventory.test.mjs && node scripts/inventory-product-guides.mjs . --out audits/product-guides/2026-09-28-inventory.json`
Expected: PASS and stable JSON output.

```bash
git add scripts/inventory-product-guides.mjs test/product-guide-inventory.test.mjs audits/product-guides/2026-09-28-inventory.json
git commit -m "audit(docs): inventory current product guides"
```

### Task 2: Create evidence-backed page classification

**Files:**
- Create: `audits/product-guides/2026-09-28-truth-audit.md`
- Create: `audits/product-guides/2026-09-28-evidence.json`
- Create: `test/product-guide-evidence-contract.test.mjs`

**Interfaces:**
- Evidence record exact shape: `{ page, claimId, classification, productStatus, evidence: [{ repo, path, ref, note }] }`.
- `classification`: `keep_correct|rewrite|remove_archive`.
- `productStatus`: `available|in_development|planned|not_applicable`.

- [ ] **Step 1: Write contract test**

Assert every inventory page has classification; every `available` claim has at least one evidence record from `EarthCoop`; no evidence path may point only to the page being audited.

- [ ] **Step 2: Run RED**

Run: `node --test test/product-guide-evidence-contract.test.mjs`
Expected: FAIL because evidence artifacts do not exist.

- [ ] **Step 3: Perform read-only evidence audit against `saeidshojae/EarthCoop`**

At minimum cover:
- registration/KYC and invitation gate;
- three system group families and Active/Observer behavior;
- Location/Governance current architecture;
- current elections contract;
- projects/public proposal workflow;
- Najm Bahar aligned with ECON 0.2;
- Najm Hoda actual current user-facing behavior vs planned architecture;
- support/tickets;
- Marketplace/mobile/other not-yet-current surfaces.

Record conflicts rather than resolving by guess.

- [ ] **Step 4: Write audit summary and evidence JSON**

The audit must explicitly classify the six known suspect pages:
`introduction.mdx`, `quickstart.mdx`, `groups/overview.mdx`, `projects/overview.mdx`, `najm-bahar/overview.mdx`, `najm-hoda/overview.mdx`.

- [ ] **Step 5: Run GREEN and commit**

Run: `node --test test/product-guide-evidence-contract.test.mjs`
Expected: PASS.

```bash
git add audits/product-guides test/product-guide-evidence-contract.test.mjs
git commit -m "audit(docs): classify product guide truth against current EarthCoop"
```

### Task 3: Remove public legacy naming and false top-level positioning

**Files:**
- Modify: `introduction.mdx`
- Modify: `quickstart.mdx`
- Modify: `account-setup.mdx` if audit flags stale claims
- Create: `test/public-product-copy-contract.test.mjs`

**Interfaces:**
- Consumes: evidence map from Task 2 and glossary from Plan A.
- Produces: top-level English copy that names product only `EarthCoop` and distinguishes current vs future capabilities.

- [ ] **Step 1: Write RED copy tests**

Assertions across public English guide sources:
- no `NewEarthCoop` outside explicitly whitelisted historical docs;
- introduction does not claim all planned modules are currently live;
- multilingual statement reflects actual current product support, not future docs-center goals;
- quickstart describes actual registration flow and automatic/system group behavior from evidence map.

- [ ] **Step 2: Run RED**

Run: `node --test test/public-product-copy-contract.test.mjs`
Expected: FAIL on current copy.

- [ ] **Step 3: Rewrite top-level pages minimally from evidence**

Use visible callouts for `In development` / `Planned` where relevant. Do not mention internal implementation names unless user-facing.

- [ ] **Step 4: Run GREEN and commit**

Run: `node --test test/public-product-copy-contract.test.mjs`
Expected: PASS.

```bash
git add introduction.mdx quickstart.mdx account-setup.mdx test/public-product-copy-contract.test.mjs
git commit -m "docs: align English onboarding with current EarthCoop"
```

### Task 4: Rewrite Groups, Location/Governance and Elections guides

**Files:**
- Modify: `groups/overview.mdx`
- Modify only audit-confirmed related files under `groups/`
- Create if absent and evidence supports: `governance/location-and-governance.mdx`
- Create/update elections guide path chosen by existing navigation pattern
- Test: `test/governance-guide-contract.test.mjs`

**Interfaces:**
- Produces guide text reflecting three system group families, geographic hierarchy, base-governance rules, optional micro-location, pending proposals, Active/Observer/Temporary Active semantics, and elections only to the extent evidence marks available.

- [ ] **Step 1: Write RED contract tests**

Assert overview includes three families: public assemblies, professional/sector, age/gender; does not model them merely as public/private custom groups; distinguishes automatic system membership from user-created groups; does not grant higher-level direct participation contrary to current role rules.

- [ ] **Step 2: Run RED**

Run: `node --test test/governance-guide-contract.test.mjs`
Expected: FAIL.

- [ ] **Step 3: Rewrite from evidence map**

Preserve feature-status badges/callouts for flows not yet fully production-ready.

- [ ] **Step 4: Run GREEN and commit**

```bash
node --test test/governance-guide-contract.test.mjs
git add groups governance test/governance-guide-contract.test.mjs
git commit -m "docs: align group and governance guides with current architecture"
```

### Task 5: Rewrite Projects and Najm Bahar guides against ECON 0.2

**Files:**
- Modify: `projects/overview.mdx`
- Modify audit-confirmed related files under `projects/`
- Modify: `najm-bahar/overview.mdx`
- Modify audit-confirmed related files under `najm-bahar/`
- Create: `test/economy-guide-contract.test.mjs`

**Interfaces:**
- Consumes: ECON 0.2 + ECON-REF-01 as normative/reference evidence and current code evidence for availability.
- Produces: product guide that separates current implementation from legal/reference architecture.

- [ ] **Step 1: Write RED tests**

Assertions:
- never says Gol is the currency; Bahar is primary and Gol subunit;
- no unsupported `burn pool` language when the legal model is money retirement;
- no claim that project approval/investment automatically transfers funds directly to owner unless current code/evidence proves exact flow;
- public-project support threshold is not described as final approval;
- Creation and Activation are not conflated;
- planned VPU/Marketplace capabilities are labeled planned/in development unless current evidence marks available.

- [ ] **Step 2: Run RED**

Run: `node --test test/economy-guide-contract.test.mjs`
Expected: FAIL.

- [ ] **Step 3: Rewrite economy/product pages**

Where implementation lags ECON, state both clearly: normative rule in reference docs, current product status in guide.

- [ ] **Step 4: Run GREEN and commit**

```bash
node --test test/economy-guide-contract.test.mjs
git add projects najm-bahar test/economy-guide-contract.test.mjs
git commit -m "docs: align project and Najm Bahar guides with ECON and product reality"
```

### Task 6: Rewrite Najm Hoda and support documentation

**Files:**
- Modify: `najm-hoda/overview.mdx`
- Modify audit-confirmed related files under `najm-hoda/`
- Modify `account/support-tickets.mdx` only if evidence supports current ticket workflow
- Create: `test/najm-hoda-guide-contract.test.mjs`

**Interfaces:**
- Produces: user-facing current Najm Hoda description; future assistant/legal/economic agency capabilities marked planned/in development.

- [ ] **Step 1: Write RED tests**

Assert no unverified claim that every response searches a fixed count of KB categories/articles; no specialist agent is presented as member-facing current capability without evidence; no autonomous economic/legal authority is implied.

- [ ] **Step 2: Run RED, rewrite from evidence, run GREEN**

Run: `node --test test/najm-hoda-guide-contract.test.mjs`
Expected before rewrite FAIL, after rewrite PASS.

- [ ] **Step 3: Commit**

```bash
git add najm-hoda account/support-tickets.mdx test/najm-hoda-guide-contract.test.mjs
git commit -m "docs: align Najm Hoda guidance with current and planned capabilities"
```

### Task 7: Produce reviewed Arabic renditions from approved English current pages

**Files:**
- Modify/create corresponding `ar/...` pages only after English pages are reviewed/current.
- Modify: `docs-manifest.json` rendition statuses/sourceVersions.
- Create: `test/arabic-rendition-contract.test.mjs`

**Interfaces:**
- Consumes: reviewed EN pages + glossary.
- Produces: Arabic translations with no added functionality/claims.

- [ ] **Step 1: Write RED translation parity tests**

Test every translated page has same stable document/page identity and no `not_translated` status; glossary terms use registered Arabic equivalents; feature-status labels match English source.

- [ ] **Step 2: Translate page-by-page, semantic review against English source**

Do not use the current `ar/` mirror as authoritative translation; it is only migration input.

- [ ] **Step 3: Update manifest only after review**

Set `status=current` and `sourceVersion` to the reviewed source version only after semantic comparison.

- [ ] **Step 4: Run complete docs quality gate**

Run:
`node --test && node scripts/validate-docs-manifest.mjs . && node scripts/validate-terminology.mjs . && node scripts/check-translation-freshness.mjs .`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add ar docs-manifest.json test/arabic-rendition-contract.test.mjs
git commit -m "docs: add reviewed Arabic product-guide renditions"
```

## Plan Completion Gate

Before merge:
- audit inventory and evidence map complete;
- all English guide pages classified;
- no public `NewEarthCoop` legacy naming outside historical whitelist;
- all `available` claims have evidence;
- economy copy passes ECON contract tests;
- Arabic published only for reviewed/current English sources;
- link/navigation crawl has no broken internal links.