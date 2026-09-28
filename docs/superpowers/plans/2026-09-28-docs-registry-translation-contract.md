# Docs Registry & Translation Contract Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** ارتقای `EarthCoop-docs` به رجیستری سه‌زبانه‌ای که یک هویت سند، renditionهای FA/EN/AR، وضعیت ترجمه و تشخیص کهنگی را به‌صورت enforceable در CI ثبت کند.

**Architecture:** `docs-manifest.json` از schemaVersion 1 به 2 مهاجرت می‌کند، بدون تغییر status حقوقی اسناد موجود. هر entry هویت سند را نگه می‌دارد و `renditions` وضعیت فایل‌های زبانی را ثبت می‌کند. validator Node قرارداد schema، وجود فایل‌ها، یکتایی هویت/slug و سازگاری sourceVersion را enforce می‌کند.

**Tech Stack:** JSON Schema Draft 2020-12, Node.js 22, ES modules, `node:test`, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-09-28-multilingual-documentation-architecture-design.md`

## Global Constraints

- `EarthCoop-docs` Source of Truth است؛ این plan هیچ متن محصولی را بازنویسی نمی‌کند.
- `canonicalLanguage` برای اسناد حقوقی/مرجع موجود `fa` است.
- translation status فقط `current`, `needs_review`, `outdated`, `not_translated` است.
- legal/publication status فعلی هر entry بدون تصمیم حقوقی مستقل تغییر نمی‌کند.
- فایل‌های release تاریخی immutable می‌مانند.
- schemaVersion 2 باید migration صریح داشته باشد؛ silent fallback به v1 ممنوع است.

## Review Focus

- rendition با `status=current` و فایل ناموجود باید validation را fail کند.
- rendition EN/AR با `sourceVersion` قدیمی‌تر از canonical نباید current باشد.
- دو document با `documentId` یکسان ولی metadata متناقض باید fail شوند.
- مسیرهای `../`, absolute path یا path خارج repo باید رد شوند.
- manifest v1 قدیمی باید پیام migration روشن بگیرد، نه crash مبهم.

---

### Task 1: Pin the v2 schema contract

**Files:**
- Modify in `saeidshojae/EarthCoop-docs`: `schemas/knowledge-document.schema.json`
- Create: `test/docs-manifest-v2-schema.test.mjs`

**Interfaces:**
- Consumes: schemaVersion 1 model in current repository.
- Produces: schemaVersion 2 with `documentId`, `canonicalLanguage`, `renditions` and optional `productStatus`.

- [ ] **Step 1: Write the failing schema test**

Create tests asserting a valid entry shaped as below is accepted by the JSON contract logic used by the test fixture, and invalid statuses/languages are rejected:

```js
const entry = {
  documentId: 'ECON-REF-01',
  slug: 'reference/economy/econ-ref-01',
  contentClass: 'reference',
  canonicalLanguage: 'fa',
  legalStatus: 'official_draft',
  authority: 'EarthCoop founder',
  version: '0.1',
  reviewedAt: '2026-09-28',
  renditions: {
    fa: { source: 'references/economy/ECON-REF-01-0.1.fa.md', status: 'current', sourceVersion: '0.1' },
    en: { source: null, status: 'not_translated', sourceVersion: null },
    ar: { source: null, status: 'not_translated', sourceVersion: null }
  }
};
```

- [ ] **Step 2: Run test to verify RED**

Run: `node --test test/docs-manifest-v2-schema.test.mjs`
Expected: FAIL because schemaVersion 2 / `renditions` are not supported.

- [ ] **Step 3: Update `schemas/knowledge-document.schema.json`**

Required top-level values/signatures:
- `schemaVersion: { const: 2 }`
- `sourceLanguage` becomes `canonicalDefaultLanguage` with enum `fa|en|ar`, default data value `fa`.
- Entry required keys: `documentId`, `slug`, `contentClass`, `canonicalLanguage`, `legalStatus`, `authority`, `version`, `reviewedAt`, `renditions`.
- `productStatus` nullable enum: `available|in_development|planned`.
- `renditions.fa|en|ar` each has `source`, `status`, `sourceVersion`; `source` and `sourceVersion` may be null only when status is `not_translated`.

- [ ] **Step 4: Run schema test GREEN**

Run: `node --test test/docs-manifest-v2-schema.test.mjs`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add schemas/knowledge-document.schema.json test/docs-manifest-v2-schema.test.mjs
git commit -m "feat(docs): define multilingual manifest v2 schema"
```

### Task 2: Upgrade validator semantics

**Files:**
- Modify: `scripts/validate-docs-manifest.mjs`
- Create: `test/validate-docs-manifest-v2.test.mjs`

**Interfaces:**
- Consumes: manifest v2 from Task 1.
- Produces: `validateDocsManifest(repositoryPath, manifest) -> { valid: boolean, errors: string[] }` preserving the existing export.

- [ ] **Step 1: Write failing validator tests**

Test names/assertions:
- `rejects current rendition with missing source file`
- `rejects not_translated rendition with non-null source`
- `rejects current translation whose sourceVersion differs from canonical version`
- `rejects unsafe rendition paths`
- `accepts canonical fa current with en/ar not_translated`
- `returns migration error for schemaVersion 1`

- [ ] **Step 2: Run RED**

Run: `node --test test/validate-docs-manifest-v2.test.mjs`
Expected: FAIL on v2 fixtures.

- [ ] **Step 3: Implement v2 validation in `scripts/validate-docs-manifest.mjs`**

Keep `validateDocsManifest()` public signature unchanged. Add helpers with exact names:

```js
function validateRendition({ repositoryPath, entryAt, language, rendition, canonicalVersion })
function validateEntryIdentity({ entry, at, documentIds, slugs })
```

Rules: file existence only when `source !== null`; `current` requires `sourceVersion === entry.version` for derived translations; canonical rendition must be `current`; safe relative path logic reused for every rendition.

- [ ] **Step 4: Run GREEN**

Run: `node --test test/validate-docs-manifest-v2.test.mjs`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add scripts/validate-docs-manifest.mjs test/validate-docs-manifest-v2.test.mjs
git commit -m "feat(docs): validate multilingual manifest semantics"
```

### Task 3: Migrate current manifest without changing legal status

**Files:**
- Modify: `docs-manifest.json`
- Modify: `REGISTRY_MODEL.md`
- Test: `test/docs-manifest-v2-schema.test.mjs`, `test/validate-docs-manifest-v2.test.mjs`

**Interfaces:**
- Consumes: v2 schema + validator.
- Produces: all current entries represented as one document identity with FA current and EN/AR either mapped to verified translations or `not_translated`.

- [ ] **Step 1: Add a migration fixture test**

Assert specifically that `ECON-REF-01` remains:
- version `0.1`
- legalStatus `official_draft`
- canonicalLanguage `fa`
- FA source `references/economy/ECON-REF-01-0.1.fa.md`

and that foundational documents keep their existing registered/non-effective semantics.

- [ ] **Step 2: Run RED against current manifest**

Run: `node --test test/docs-manifest-v2-schema.test.mjs test/validate-docs-manifest-v2.test.mjs`
Expected: FAIL because current `docs-manifest.json` is v1.

- [ ] **Step 3: Migrate `docs-manifest.json` to schemaVersion 2**

Do not infer EN/AR translations. Only map a rendition to `current` when an existing file has been manually verified as a translation of that exact version; otherwise set `source: null`, `status: not_translated`, `sourceVersion: null`.

- [ ] **Step 4: Update `REGISTRY_MODEL.md`**

Document distinction between `document-registry.json` public baseline and `docs-manifest.json` multilingual ingestion contract; explicitly state translation status has no legal effect.

- [ ] **Step 5: Run repository validation**

Run: `node --test && node scripts/validate-docs-manifest.mjs .`
Expected: all tests PASS and validator prints `Validated <N> knowledge-center candidate(s).`

- [ ] **Step 6: Commit**

```bash
git add docs-manifest.json REGISTRY_MODEL.md
git commit -m "docs: migrate knowledge manifest to multilingual v2"
```

### Task 4: Add canonical three-language terminology registry

**Files:**
- Create: `glossary/terms.json`
- Create: `schemas/terminology.schema.json`
- Create: `scripts/validate-terminology.mjs`
- Create: `test/terminology-contract.test.mjs`
- Modify: `.github/workflows/validate-knowledge-content.yml`

**Interfaces:**
- Produces: terminology records `{ key, fa, en, ar, notes? }` and validator command `node scripts/validate-terminology.mjs .`.

- [ ] **Step 1: Write RED tests**

Seed required keys at minimum: `earthcoop`, `bahar`, `gol`, `dim_bahar`, `activation`, `public_assembly`, `manager`, `inspector`, `value_participation_unit`, `najm_bahar`, `najm_hoda`.

Assert duplicate keys, blank translations and use of `NewEarthCoop` as an English product name fail.

- [ ] **Step 2: Run RED**

Run: `node --test test/terminology-contract.test.mjs`
Expected: FAIL because glossary/validator do not exist.

- [ ] **Step 3: Create glossary schema, initial terms and validator**

Use exact CLI: `node scripts/validate-terminology.mjs .`.

- [ ] **Step 4: Wire CI**

Append validator after manifest validation in `.github/workflows/validate-knowledge-content.yml`.

- [ ] **Step 5: Run GREEN**

Run: `node --test && node scripts/validate-docs-manifest.mjs . && node scripts/validate-terminology.mjs .`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add glossary schemas/terminology.schema.json scripts/validate-terminology.mjs test/terminology-contract.test.mjs .github/workflows/validate-knowledge-content.yml
git commit -m "feat(docs): add canonical multilingual terminology registry"
```

### Task 5: Add stale-translation detection

**Files:**
- Create: `scripts/check-translation-freshness.mjs`
- Create: `test/translation-freshness.test.mjs`
- Modify: `.github/workflows/validate-knowledge-content.yml`

**Interfaces:**
- Consumes: manifest v2.
- Produces CLI `node scripts/check-translation-freshness.mjs .`; exit 0 when statuses match versions, non-zero when `current` is stale.

- [ ] **Step 1: Write RED tests**

Cases:
- canonical version 0.2 + EN sourceVersion 0.1 + status current => FAIL
- same but status `outdated` => PASS
- missing translation + `not_translated` => PASS
- canonical changed while AR `needs_review` => PASS

- [ ] **Step 2: Run RED**

Run: `node --test test/translation-freshness.test.mjs`
Expected: FAIL.

- [ ] **Step 3: Implement CLI and CI step**

No auto-edit in this task; detector only reports exact documentId/language/version mismatch.

- [ ] **Step 4: Run full docs gate**

Run: `node --test && node scripts/validate-docs-manifest.mjs . && node scripts/validate-terminology.mjs . && node scripts/check-translation-freshness.mjs .`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add scripts/check-translation-freshness.mjs test/translation-freshness.test.mjs .github/workflows/validate-knowledge-content.yml
git commit -m "feat(docs): detect stale translations in CI"
```

## Plan Completion Gate

Before merging this plan's implementation PR:
- full Node test suite PASS;
- all three validators PASS;
- no release snapshot modified;
- diff audit confirms legal status/version of existing documents unchanged except schema representation;
- `ECON-REF-01` still `official_draft`, version `0.1`.