# Persian SEO Growth — Phase 2 Pillar Implementation Plan

Date: 2026-10-05

Base: `main@beaaf569a3f95e326c25556c7e42e4cca091c3a8`

Branch: `agent/persian-seo-phase2-pillars-20261005`

## Objective

Create the first six stable, indexable Persian Pillar pages that give EarthCoop's later blog/topic clusters durable canonical destinations, without mixing the blog reset into the same release.

## Approved first sub-batch

1. `/economy/` — اقتصاد آزاد مردمی
2. `/economy/glass/` — اقتصاد شیشه‌ای
3. `/governance/` — حکمرانی دموکراتیک و مشارکتی
4. `/governance/elections/` — انتخابات دائمی بدون نامزد
5. `/justice/` — عدالت، حق، آزادی مسئولانه و برابری بنیادین
6. `/commons/` — زمین، منابع مشترک و حق همگانی

Canonical URLs follow the project's current no-trailing-slash convention (`/economy`, `/economy/glass`, etc.) while user-facing route intent remains the same.

## Architectural decision

Do not model these Pillars as generic `/pages/{slug}` records. Their URL hierarchy and semantic ownership are stable application architecture, while their copy is version-controlled and grounded in the approved Entity Map.

Implement:

- `app/Support/Seo/PillarRegistry.php` as the source of truth for route metadata, titles, descriptions, breadcrumbs, related links, formal Docs references and stable Persian content.
- `app/Http/Controllers/Seo/PillarController.php` as one read-only renderer.
- `routes/seo-pillars.php` with six explicit public routes.
- `resources/views/seo/pillars/show.blade.php` as the shared presentation layer.
- `RouteServiceProvider` loads the isolated route file.
- `SitemapController` adds registry paths explicitly; no route-table introspection.

No database migration is required.

## SEO contract for every Pillar

Every page must:

- return HTTP 200;
- emit exactly one H1;
- emit one canonical on `https://earthcoop.ir`;
- emit `index,follow`;
- have a specific Persian title and meta description;
- emit valid `WebPage` and `BreadcrumbList` JSON-LD;
- link to at least one semantically related Pillar;
- link to the appropriate formal source in `https://docs.earthcoop.ir`;
- appear in the main sitemap;
- avoid keyword stuffing and false equivalence.

## Semantic truth constraints

- «اقتصاد آزاد مردمی» is EarthCoop's named proposed economic model. It combines legitimate private ownership/economic freedom with common rights in Earth/resources, transparency, anti-monopoly and accountability. It is not declared equivalent to laissez-faire capitalism or state economy.
- «اقتصاد شیشه‌ای» means transparent **and secure** economy: “bank transparent/lights on” plus “unbreakable/bullet-resistant glass”. Transparency does not erase legitimate financial privacy.
- EarthCoop's governance is participatory/democratic and multilevel. Do not imply every capability is already legally recognized or fully deployed everywhere.
- Formal election wording is «انتخابات دائمی بدون نامزد»: systemic, automatic, permanent/continuous and candidate-less at its stable core. Configurable parameter values are not frozen SEO claims.
- Liquid Democracy is comparison-only, never a synonym.
- Justice is framed by EarthCoop as putting each thing in its proper place and giving each right to its rightful holder. Do not claim this is the universally accepted definition.
- «آزادی مسئولانه» and «برابری بنیادین» are formal stable concepts. «آزادی عادلانه» and «برابری عادلانه» may be explanatory wording, not replacement formal terms.
- Earth/common-resource rights do not negate legitimate private ownership of the fruits of labor or lawfully created/acquired assets.
- Commons is adjacent external vocabulary; do not claim exact identity with EarthCoop's common-right mechanism.
- Natural-rights traditions may be compared later, but this release does not declare adoption of a particular natural-law school.

## Formal Docs destinations

Use stable public Docs references as authority links, principally:

- Constitution: `https://docs.earthcoop.ir/documents/co/`
- Charter: `https://docs.earthcoop.ir/documents/ch/`
- Executive/elections: `https://docs.earthcoop.ir/documents/ex/`
- Economy: `https://docs.earthcoop.ir/documents/econ/`

These links support official EarthCoop claims; external concepts remain comparison material rather than formal authority.

## Test-first sequence

1. Add `tests/Feature/Seo/PillarPagesTest.php` asserting the six routes, canonical/indexability contract, one H1, JSON-LD types, related-link presence and formal Docs links.
2. Extend `tests/Feature/Seo/SitemapTest.php` to require all six Pillar canonicals and uniqueness.
3. Only then implement registry/controller/routes/view/provider/sitemap integration.
4. Review the final branch diff for semantic overclaim, duplicate canonical ownership and accidental private-route exposure.
5. Open one PR and use one Full Validation run as the runtime gate because local GitHub checkout is unavailable in this environment.

## Out of scope for this phase

- deleting/replacing legacy blog rows;
- publishing the 15-article editorial wave;
- dedicated indexable Najm Hoda/Najm Bahar/Bahar pages;
- English/Arabic routes, hreflang or translated keyword targeting;
- category/tag archive policy changes;
- Search Console resubmission/index requests until production deployment.

## Success gate

Phase 2 first sub-batch is merge-ready only when:

- Full Validation passes on the exact PR HEAD;
- the six Pillars satisfy their test/metadata/sitemap contracts;
- a final source review finds no false equivalence or unstable parameter claim;
- the diff contains no unrelated application behavior changes.
