# EarthCoop Persian SEO Growth — Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the evidence-backed Persian SEO operating system for EarthCoop: establish the authoritative topic/keyword map, audit the current public/blog inventory, fix the first verified technical gaps, and produce the prioritized content backlog that later pillar/blog implementation plans will execute.

**Architecture:** Phase 1 is deliberately separated from content publishing. It creates durable source-of-truth artifacts under `docs/seo/`, validates the current Laravel SEO surface, and makes only small technical corrections that are already proven by the audit. Pillar pages, blog replacement, multilingual routes, and large copy changes are separate follow-up plans so that research conclusions can be reviewed before they become public URLs.

**Tech Stack:** Laravel/PHP, Blade, PHPUnit/Laravel feature tests, dynamic sitemap controller, Markdown/CSV-like research artifacts, Google Search Console for post-deploy measurement, web/SERP research for Persian query language.

**Spec:** `docs/superpowers/specs/2026-10-05-persian-seo-growth-design.md`

## Global Constraints

- Primary Phase-1 audience is all Persian-speaking people worldwide, not one country.
- Persian is the first SEO language; English and Arabic are later phases and require independent keyword research.
- Use Entity + Topic Cluster architecture, not one page per keyword.
- Index only concepts stable enough to make accurate public claims.
- EarthCoop is the entity to which «اقتصاد آزاد مردمی» is attributed; do not attribute the model publicly to a founder personally.
- «اقتصاد شیشه‌ای» means transparent **and** secure economy; transparency must not erase legitimate privacy or security.
- Broad concepts such as عدالت، آزادی، برابری، حق are foundational semantic clusters, not excuses for generic thin pages.
- Do not claim exact equivalence between EarthCoop concepts and external concepts such as liquid democracy, platform cooperativism, or complementary currency unless the mechanics actually match.
- Existing blog posts are test/legacy content by project decision, but do not delete or redirect them until index/backlink/impression value has been audited.
- `https://earthcoop.ir` remains the canonical origin.
- `docs.earthcoop.ir` remains the official legal/documentary reference layer; the main site and blog should link to it rather than duplicate long legal text.
- TDD for code changes. Run targeted tests first; reserve the full suite for the final integration gate.
- No direct changes to `main`; work through an isolated branch/worktree and PR review.

## Review Focus

1. **Semantic overclaim:** a generic or adjacent search term is mapped as if it were an exact EarthCoop definition. Expected: mark relationship as `exact`, `adjacent`, `comparison`, or `do-not-target` and require evidence.
2. **Keyword cannibalization:** multiple URLs target the same dominant Persian intent. Expected: one canonical target URL per intent cluster, with supporting articles linking into it.
3. **Unstable concept leakage:** evolving economic or governance details become indexable claims. Expected: stability gate blocks them from public SEO backlog until explicitly promoted to stable.
4. **Legacy URL loss:** an indexed/test blog URL is deleted without checking value or redirect destination. Expected: inventory records status and redirect decision before any removal.
5. **Sitemap/indexability drift:** a public `index,follow` route is missing from the sitemap or a `noindex` route enters it. Expected: feature tests enforce the allowlist policy.

---

## Roadmap boundaries

This program is intentionally split into separate implementation plans:

- **Phase 1 — Research, audit, keyword/topic operating system, first technical hardening** — this plan.
- **Phase 2 — Core Pillar architecture and public page implementation** — generated after Phase-1 research is reviewed.
- **Phase 3 — Blog reset and first Persian content clusters** — generated after final pillar targets are fixed.
- **Phase 4 — Internal linking, glossary/entity reinforcement, structured-data expansion** — generated after initial public content exists.
- **Phase 5 — Search Console feedback loop, CTR/content iteration, authority/outreach** — operational optimization after sufficient impressions accumulate.
- **Phase 6 — English and Arabic research + locale SEO architecture** — separate language-specific design/plan; no literal translation-only strategy.

Phase 1 must finish with enough evidence that Phases 2 and 3 do not guess their target queries or URLs.

---

### Task 1: Freeze the current SEO and public-content baseline

**Files:**
- Create: `docs/seo/2026-10-05-current-state-audit.md`
- Read/verify: `config/seo.php`
- Read/verify: `public/robots.txt`
- Read/verify: `app/Http/Controllers/Seo/SitemapController.php`
- Read/verify: `resources/views/components/seo-meta.blade.php`
- Read/verify: `resources/views/welcome.blade.php`
- Read/verify: `app/Modules/Blog/Controllers/BlogController.php`
- Read/verify: `routes/web.php`

**Interfaces:**
- Consumes: approved design spec and current `main` behavior.
- Produces: an evidence table of every currently indexable public route/type, canonical behavior, sitemap membership, metadata source, structured-data type, and known gap. Later tasks use this as the baseline and must not silently contradict it.

- [ ] **Step 1: Build the route/indexability inventory**

Record, at minimum, home, terms, privacy, blog index, blog post, managed public page, category/tag archives if publicly indexable, blog search, auth/registration pages, and sitemap itself. For each, record `robots`, canonical source, sitemap membership, and expected role (`pillar candidate`, `supporting content`, `utility`, `exclude`).

- [ ] **Step 2: Record deployed/Search Console evidence already established**

Document that `earthcoop.ir/sitemap.xml` is already accepted in the Domain property and currently reports 18 discovered pages, while the docs sitemap is separately successful with 35 discovered URLs. Mark these counts as dated observations, not permanent expectations.

- [ ] **Step 3: Verify the audit against code**

Run targeted inspection plus:

```bash
php artisan test tests/Feature/Seo/SitemapTest.php
```

Expected: PASS; any discrepancy between audit and test/code becomes an explicit Phase-1 finding.

- [ ] **Step 4: Commit**

```bash
git add docs/seo/2026-10-05-current-state-audit.md
git commit -m "docs: capture EarthCoop SEO current state"
```

---

### Task 2: Create the authoritative EarthCoop concept/entity inventory

**Files:**
- Create: `docs/seo/persian-topic-entity-map.md`
- Source: `docs/superpowers/specs/2026-10-05-persian-seo-growth-design.md`
- Source: EarthCoop foundational/economic/governance documents available in the project/documentation repository.

**Interfaces:**
- Consumes: project terminology and approved definitions.
- Produces: canonical concept IDs and definitions used by keyword research and content planning.

- [ ] **Step 1: Define the entity-map schema**

Each row/section must contain:

`id | official_fa_name | english_working_label | definition | stability | source_evidence | relation_to_earthcoop | allowed_public_claims | prohibited_or_unverified_equivalences`

Use stability values exactly: `stable`, `stable-core-evolving-details`, `draft`, `experimental`.

- [ ] **Step 2: Populate the approved seed families**

At minimum include:

- EarthCoop / ارث‌کوپ / تعاون بر زمین و ارث مشترک;
- تعاون جهانی، تعاونی دیجیتال، platform cooperative as an adjacent comparison term where appropriate;
- حکمرانی دموکراتیک و مشارکتی;
- انتخابات دائمی/مستمر، بدون نامزد، چندسطحی, and related adjacent terms;
- اقتصاد آزاد مردمی;
- اقتصاد مردمی;
- اقتصاد شیشه‌ای;
- مالکیت همگانی، مالکیت خصوصی مشروع بر دسترنج، حق مالکانه همگانی;
- بهار and نجم‌بهار;
- نجم هدا and digital governance/civic-tech adjacent terms;
- عدالت، حق، حقوق بنیادین/ذاتی where supported, آزادی مسئولانه، برابری بنیادین، کرامت انسانی، مسئولیت مشترک;
- زمین، منابع مشترک/commons, local-to-global governance, participation and oversight.

- [ ] **Step 3: Mark equivalence safety explicitly**

For concepts such as `liquid democracy`, `complementary currency`, `community currency`, `platform cooperative`, `commons`, and `civic tech`, set one of:

`exact | adjacent | comparison | do-not-target`

and provide the reason. No empty value is allowed.

- [ ] **Step 4: Self-check against the design spec**

Expected: every seed family in the spec maps to at least one entity; no entity marked `stable` lacks source evidence.

- [ ] **Step 5: Commit**

```bash
git add docs/seo/persian-topic-entity-map.md
git commit -m "docs: define Persian SEO entity map"
```

---

### Task 3: Perform Persian keyword and SERP research by intent

**Files:**
- Create: `docs/seo/persian-keyword-map.csv`
- Create: `docs/seo/persian-serp-research.md`

**Interfaces:**
- Consumes: canonical entity IDs from Task 2.
- Produces: query clusters with one recommended target per intent and evidence for terminology selection. Phase 2/3 plans must use these outputs rather than inventing keywords ad hoc.

- [ ] **Step 1: Create the keyword-map schema**

Columns:

`query, normalized_query, entity_id, cluster_id, intent, relationship, stability_gate, target_type, proposed_target_url, priority, competition_observation, earthcoop_fit, authority_potential, source_of_query, notes`

Allowed `intent` values:

`informational | comparative | navigational | problem | solution-model | branded`

Allowed `target_type` values:

`home | pillar | blog | glossary | docs | no-target`

- [ ] **Step 2: Research every seed family on the live Persian web/SERP**

For each family, capture real wording used by searchers/results, related questions, close variants, and the dominant intent. Include at least the families: cooperation, governance/elections, economy, transparency/security, ownership/commons, money, participation, justice/rights/freedom/equality, technology/Najm Hoda, project finance.

Do not use raw search-result presence alone as proof of equivalence.

- [ ] **Step 3: Cluster queries by dominant intent**

Expected: variants such as «انتخابات دائمی»، «انتخابات مستمر»، «انتخابات پویا» are merged or separated based on SERP/intent evidence, not wording alone.

- [ ] **Step 4: Score priorities**

Use a documented 1–5 score for:

`earthcoop_fit`, `authority_potential`, `stability`, `intent_value`, `competition_opportunity`.

Define the Phase-1 priority score as the sum of those five dimensions (maximum 25). Search volume may be recorded when trustworthy data exists but must not override semantic fit or stability.

- [ ] **Step 5: Record research limitations**

In `persian-serp-research.md`, distinguish observed SERP evidence, inference, and unavailable volume data. Do not fabricate numeric volume/difficulty.

- [ ] **Step 6: Commit**

```bash
git add docs/seo/persian-keyword-map.csv docs/seo/persian-serp-research.md
git commit -m "docs: map Persian SEO queries and intent"
```

---

### Task 4: Audit the legacy blog before any deletion

**Files:**
- Create: `docs/seo/blog-content-inventory.md`
- Read: blog routes/controller/models/seeders and current published post inventory.

**Interfaces:**
- Consumes: current blog URLs plus keyword map from Task 3.
- Produces: a per-URL action decision for Phase 3: `keep-rewrite`, `replace-same-url`, `redirect`, `remove-410-or-404`, or `hold`.

- [ ] **Step 1: Inventory every currently published blog URL**

For each post record slug, title, publish status/date, current indexability, category/tags, and whether the content is known test content.

- [ ] **Step 2: Check search value before removal**

Where Search Console data is available, record impressions, clicks, queries, and indexed status. Where backlink evidence is available, record it. If evidence is unavailable, mark `unknown`; do not treat unknown as zero.

- [ ] **Step 3: Map useful legacy URLs to future clusters**

If a legacy URL has value, choose a future canonical destination or a same-URL rewrite. Do not define a redirect to a semantically unrelated pillar.

- [ ] **Step 4: Produce the deletion/redirect gate**

Expected: no URL is marked `redirect` without a destination; no URL is marked `remove` if it has meaningful impressions/backlinks unless the audit explains why removal is still preferable.

- [ ] **Step 5: Commit**

```bash
git add docs/seo/blog-content-inventory.md
git commit -m "docs: audit legacy blog content for SEO migration"
```

---

### Task 5: Build the canonical target-URL and content backlog

**Files:**
- Create: `docs/seo/persian-content-backlog.md`
- Create: `docs/seo/internal-linking-map.md`

**Interfaces:**
- Consumes: Tasks 2–4.
- Produces: the approved candidates for Phase 2 Pillars and Phase 3 blog articles, with one canonical target per intent cluster.

- [ ] **Step 1: Select the first Pillar set**

Choose approximately 8–12 high-priority stable concepts. The candidate pool must include, but is not forced to publish all at once:

- EarthCoop / identity;
- cooperation/global-digital cooperative;
- governance;
- elections/continuous representation;
- local-to-global governance;
- اقتصاد آزاد مردمی;
- اقتصاد شیشه‌ای;
- ownership/commons/rights;
- justice/freedom/equality cluster;
- technology/Najm Hoda;
- Bahar/Najm-Bahar only if the exact public claims pass the stability gate.

For each, record primary intent, secondary queries, proposed slug, rationale, evidence, and supporting-article candidates.

- [ ] **Step 2: Resolve URL cannibalization before publication**

Expected: one `cluster_id` has one primary `proposed_target_url`. Supporting blog posts may target narrower intents but must link to the pillar.

- [ ] **Step 3: Build the first editorial wave**

For each selected pillar, define 3–5 launch articles, prioritized by query fit and explanatory value. Articles must educate first; EarthCoop-specific interpretation comes in a clearly identified section.

- [ ] **Step 4: Define cross-layer internal linking**

For each pillar specify:

`home/main-site entry → pillar → supporting blog → relevant docs` and appropriate reverse links where natural.

The docs link is evidence/reference, not duplicated legal copy.

- [ ] **Step 5: Commit**

```bash
git add docs/seo/persian-content-backlog.md docs/seo/internal-linking-map.md
git commit -m "docs: prioritize Persian SEO pillar and blog backlog"
```

---

### Task 6: Fix verified sitemap/indexability drift only

**Files:**
- Modify: `app/Http/Controllers/Seo/SitemapController.php`
- Modify: `tests/Feature/Seo/SitemapTest.php`

**Interfaces:**
- Consumes: Task-1 route/indexability audit.
- Produces: sitemap membership that exactly matches the currently approved public indexable static routes before new pillars are introduced.

- [ ] **Step 1: Write the failing sitemap regression test**

Add a test named to assert that `/privacy` is present in `/sitemap.xml` because the route explicitly emits `index,follow` and a stable canonical URL.

Also add/retain assertions that auth, registration, search-result, admin, API, and other private/noindex routes are absent.

- [ ] **Step 2: Run the targeted test and verify failure**

```bash
php artisan test tests/Feature/Seo/SitemapTest.php
```

Expected before implementation: FAIL because `https://earthcoop.ir/privacy` is absent.

- [ ] **Step 3: Add the canonical privacy entry**

In `SitemapController::__invoke`, add:

```php
new SitemapEntry($canonicalUrl->to('/privacy')),
```

next to the existing approved static public routes. Do not introspect the entire route table.

- [ ] **Step 4: Re-run the targeted test**

```bash
php artisan test tests/Feature/Seo/SitemapTest.php
```

Expected: PASS.

- [ ] **Step 5: Run the affected SEO gate**

```bash
php artisan test tests/Feature/Seo
```

Expected: PASS with zero failures.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Seo/SitemapController.php tests/Feature/Seo/SitemapTest.php
git commit -m "fix: align sitemap with public privacy route"
```

---

### Task 7: Phase-1 review gate and handoff to Pillar implementation planning

**Files:**
- Modify: `docs/seo/2026-10-05-current-state-audit.md`
- Create: `docs/seo/phase-1-review.md`

**Interfaces:**
- Consumes: all Phase-1 deliverables.
- Produces: a frozen recommendation set and the exact inputs required to write the Phase-2 Pillar implementation plan.

- [ ] **Step 1: Cross-check spec coverage**

Verify that every design family appears in the entity map or is explicitly deferred with a reason.

- [ ] **Step 2: Cross-check content safety**

Verify no `draft` or `experimental` concept is scheduled as an indexable Pillar. Verify every external adjacent concept has a relationship label.

- [ ] **Step 3: Cross-check cannibalization**

Verify every prioritized `cluster_id` has exactly one primary target URL.

- [ ] **Step 4: Run the Phase-1 code gate**

```bash
php artisan test tests/Feature/Seo
```

Then run the closest welcome/page/blog SEO tests identified in Task 1. Do not run the full repository suite unless Phase-1 code changed outside the SEO surface or CI policy requires it.

Expected: all selected tests PASS with zero failures.

- [ ] **Step 5: Write the review handoff**

`phase-1-review.md` must state:

- selected 8–12 Pillar candidates;
- top launch article clusters;
- deferred/unstable concepts;
- legacy-blog migration decisions;
- verified technical gaps fixed and remaining;
- Search Console baseline/date;
- exact recommended scope for Phase 2 and Phase 3.

- [ ] **Step 6: Commit**

```bash
git add docs/seo/2026-10-05-current-state-audit.md docs/seo/phase-1-review.md
git commit -m "docs: close Persian SEO phase 1 research gate"
```

---

## Self-review record

- **Spec coverage:** Phase 1 covers audience/language policy, entity architecture, foundational justice/rights layer, economic/governance/technology seed families, keyword research, legacy blog audit, indexability gate, content backlog, and measurement baseline. Public Pillar/content implementation is intentionally deferred to Phases 2–3.
- **Step scan:** research artifacts and the one code fix have checkable outputs; no Pillar implementation is smuggled into the research phase.
- **Type/name consistency:** canonical artifacts are `persian-topic-entity-map.md`, `persian-keyword-map.csv`, `persian-serp-research.md`, `blog-content-inventory.md`, `persian-content-backlog.md`, and `internal-linking-map.md` throughout.
- **Review Focus coverage:** semantic relationship labels are Task 2/3; cannibalization is Task 5/7; stability gate is Task 2/5/7; legacy URL safety is Task 4; sitemap drift is Task 6.
- **Proportion:** this plan is intentionally detailed enough for handoff but does not prescribe article prose or Phase-2 page implementation before research evidence exists.
