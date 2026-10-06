# EarthCoop Persian SEO Growth — Phase 4 Implementation Plan

Date: 2026-10-06

Audit source: `docs/seo/2026-10-06-phase-4-current-state-audit.md`

Baseline: `main@a2db18d1df8a3deaaadac7e5cf3c4d4ab206ad4a`

## Goal

Complete the evidence-backed parts of Phase 4 without inventing new SEO targets:

1. make first-wave article entity ownership machine-readable;
2. make related-content selection for approved SEO articles semantically curated;
3. preserve existing generic blog behavior as fallback;
4. record but defer glossary creation until query evidence exists;
5. prepare a separate trust/citation pass for external concepts.

## Global constraints

- No direct changes to `main`.
- TDD for every behavior change.
- Keep current canonical URLs and sitemap behavior unchanged.
- Do not mutate Production blog rows as part of Tasks 1–3.
- Do not create SEO tags or glossary URLs without an approved target.
- Do not infer equivalence between EarthCoop and external concepts.
- Run targeted tests during development; reserve Full Validation for the final exact HEAD.

---

## Task 1 — Make Pillar/article ownership reusable in both directions

### Files

- Modify: `app/Support/Seo/PillarArticleRegistry.php`
- Test: `tests/Feature/Seo/PillarPagesTest.php` or a new focused registry test

### Contract

The existing registry already maps:

`pillar key -> curated article label/path[]`

Add safe inverse lookup capabilities without duplicating mappings:

- article slug/path -> owning Pillar key;
- optional article -> curated sibling article links.

### RED

Add focused tests proving:

- each of the eight article slugs resolves to exactly one owning Pillar;
- unknown/legacy slugs return no ownership;
- no article is owned by more than one Pillar.

### GREEN

Implement the smallest inverse lookup over the existing registry.

Do not introduce a second independent mapping table.

---

## Task 2 — Expand Article JSON-LD conservatively

### Files

- Modify: `app/Modules/Blog/Controllers/BlogController.php`
- Modify/use: `app/Support/Seo/PillarArticleRegistry.php`
- Read/use: `app/Support/Seo/PillarRegistry.php`
- Test: `tests/Feature/Seo/BlogMetadataTest.php`

### RED

For an approved SEO article, assert the JSON-LD includes:

- `@type: Article`;
- `inLanguage: fa`;
- a truthful EarthCoop `publisher` object;
- `isPartOf` pointing to the EarthCoop website/blog identity;
- `about` pointing to the owning Pillar canonical URL and approved Pillar public name.

For an ordinary legacy/non-SEO blog article:

- Article JSON-LD remains valid;
- no fabricated Pillar `about` relationship is emitted.

Existing tests for headline, author, date and optional image must remain green.

### GREEN

Build the enrichment from existing canonical URL + Pillar registries.

Do not hard-code eight separate schema payloads in the controller.

### Safety

- No external `sameAs` identifiers in this task.
- No schema claim for concepts not in approved stable Pillars.
- No canonical changes.

---

## Task 3 — Curate “related articles” for SEO-owned posts

### Files

- Modify: `app/Modules/Blog/Controllers/BlogController.php`
- Modify/use: `app/Support/Seo/PillarArticleRegistry.php`
- Test: new focused blog related-content feature test

### RED

Prove:

1. an approved SEO article prefers curated sibling articles from its owning Pillar;
2. the current article is excluded;
3. only published matching rows are rendered;
4. ordinary/legacy posts still use the current category/recent fallback;
5. missing curated siblings do not cause errors or empty the generic site unexpectedly.

### GREEN

For SEO-owned posts:

- resolve curated sibling slugs;
- query only published matching posts;
- preserve registry order.

For non-SEO posts:

- retain current same-category/recent behavior.

### Note

Single-article Pillars may legitimately have no curated sibling in Wave 1. Do not fabricate cross-cluster links just to fill a card grid.

---

## Task 4 — Structured-data and internal-linking regression review

### Files

- Test/read:
  - `tests/Feature/Seo/BlogMetadataTest.php`
  - `tests/Feature/Seo/PillarPagesTest.php`
  - `tests/Feature/Seo/PersianSeoBlogSeederTest.php`
  - sitemap tests
- No production code unless a real regression is found.

### Targeted verification

Run the smallest relevant SEO tests first.

Expected invariants:

- eight Wave-1 articles still contain owning Pillar + Docs links;
- Pillars still link back to all eight articles;
- published articles remain in sitemap;
- non-public articles remain inaccessible and excluded;
- canonical URLs remain unchanged;
- enriched JSON-LD parses as valid JSON.

---

## Task 5 — External-source trust pass (research/content only)

### Inputs

- `docs/seo/persian-topic-entity-map.md`
- `docs/seo/persian-serp-research.md`
- the eight published article bodies

### Output

Create:

`docs/seo/phase-4-external-source-map.md`

For each first-wave article record:

- generic/external claims needing independent evidence;
- 0–3 recommended authoritative sources;
- EarthCoop-specific claims that must continue to cite Docs;
- “no external citation needed” where appropriate.

Do not modify public article copy until this map is reviewed.

---

## Task 6 — Glossary gate

### Output

Add a short decision section to the Phase-4 handoff:

- current status: deferred;
- reason: no approved `target_type=glossary` query target;
- trigger for future implementation: Search Console/SERP evidence for a stable term whose intent is not already owned by a Pillar/article.

No route, page, migration or sitemap entry is created in this task.

---

## Task 7 — Final integration gate

Before merge:

1. review final diff for scope hygiene;
2. verify exact final HEAD;
3. run one Full Validation;
4. inspect failures rather than rerunning blindly;
5. merge only after exact-head success.

## Expected Phase-4 completion state

After Tasks 1–7:

- human-visible and machine-readable article→Pillar ownership are aligned;
- Pillar→article reverse links remain intact;
- first-wave related-content links are curated where evidence exists;
- legacy blog behavior is preserved;
- glossary remains intentionally deferred, not forgotten;
- external-source improvements have a reviewed evidence map;
- Search Console Phase 5 can use real impressions/query data without Phase-4 architecture drift.
