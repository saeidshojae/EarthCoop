# Persian SEO — Phase 4 Current-State Audit

Date: 2026-10-06

Baseline: `main@a2db18d1df8a3deaaadac7e5cf3c4d4ab206ad4a`

## Scope

Audit the remaining Phase 4 work defined by the approved Persian SEO roadmap:

- internal linking;
- glossary/entity reinforcement;
- structured-data expansion.

This audit is evidence-first. It does not introduce new public concepts, routes, glossary pages, redirects, tags, or schema claims without support from the Phase-1 entity/topic map and current implementation.

## Verified already complete

### Technical SEO foundation

- `earthcoop.ir` is the canonical origin.
- Public metadata infrastructure emits canonical, robots, Open Graph/Twitter data and JSON-LD through the shared SEO component.
- `/sitemap.xml` is dynamic and includes all currently published blog posts.
- Published blog posts are indexable; draft, archived, future and deleted posts are not publicly accessible.
- Blog search is `noindex,follow`.
- Blog post canonical URLs are self-canonical.

### Article structured data baseline

`BlogController::show()` currently emits one `Article` JSON-LD object containing:

- `headline`;
- `description`;
- `datePublished`;
- `dateModified`;
- `mainEntityOfPage`;
- `author` when available;
- `image` when a featured image exists.

This baseline is covered by `BlogMetadataTest`.

### First-wave content linking

The eight Phase-3 Persian SEO articles:

- are published;
- contain one owning Pillar link;
- contain an official Docs Center link;
- have non-empty SEO title/description/excerpt;
- are substantive rather than thin copy.

Phase 4 checkpoint PR #206 added the reverse direction:

`Pillar -> curated supporting article`

through `PillarArticleRegistry`, so the first-wave architecture now supports:

`Pillar -> Article -> Pillar -> Docs`

for all eight approved articles.

### Production verification

The eight first-wave article URLs were Live Tested in Google Search Console on 2026-10-06 and reported as available to Google / indexable. Indexing requests were submitted for all eight URLs. This is an operational observation, not a guarantee of eventual indexing.

## Gap A — Article related-content selection is not semantic-source-of-truth driven

Current `BlogController::show()` selects `relatedPosts` as:

- same category;
- not the current post;
- newest four.

This is reasonable as a generic blog fallback, but it is not the approved Entity + Topic Cluster ownership model.

### Risk

For SEO-owned articles, category recency can eventually surface:

- newly added posts that share a broad category but have a different dominant intent;
- content that should not be treated as a semantic sibling;
- future legacy/imported posts if they reuse an approved category slug.

The current five SEO categories reduce this risk today, but there is no contract guaranteeing the relationship remains semantically curated.

### Recommendation

Keep the current query as a fallback for ordinary/legacy posts, but allow approved SEO articles to resolve curated semantic related links from an explicit registry.

Do not remove the current fallback until production/Search Console evidence justifies a broader content migration.

## Gap B — Machine-readable entity ownership is implicit, not explicit

Human-visible first-wave articles link to their owning Pillars, but current `Article` JSON-LD does not express the relationship between an article and its owning EarthCoop concept/Pillar.

Current JSON-LD also does not explicitly include `inLanguage`, `publisher`, or `isPartOf`.

### Recommendation

For approved Persian SEO articles, enrich the existing `Article` object conservatively with stable, truthful properties:

- `inLanguage: fa`;
- `publisher`: EarthCoop organization identity;
- `isPartOf`: EarthCoop website/blog identity;
- `about`: the owning stable Pillar concept, using the canonical Pillar URL and approved public name.

Do not add speculative external entity identifiers, Wikipedia/Wikidata IDs, or equivalence claims.

Do not change the canonical target because of schema; structured data must describe the existing page, not create an alternate SEO target.

## Gap C — Glossary is architecturally allowed but not research-approved

The Phase-1 schema allows `target_type=glossary`, and Phase 4 was described as including “glossary/entity reinforcement”.

However, the current repository contains no actual query/intent target assigned to a glossary URL and no approved glossary route/content backlog.

### Decision

Do **not** create a glossary yet.

A glossary should only be introduced when one or more Phase-1/Phase-5 query clusters justify a stable glossary target without cannibalizing existing Pillars or articles.

Entity reinforcement in the current Phase 4 should therefore happen through:

- existing Pillars;
- article-to-Pillar ownership;
- curated related content;
- conservative structured data.

## Gap D — Generic/external concept evidence is not yet systematically surfaced

The approved SEO design distinguishes three evidence classes:

1. independent authoritative sources for generic external concepts;
2. official EarthCoop documents for EarthCoop rules/definitions;
3. explicit EarthCoop analysis for EarthCoop's own model/comparisons.

The first eight articles reliably link to EarthCoop Pillars and official Docs, but a systematic external-source citation layer has not yet been implemented for generic concepts such as platform cooperatives or external economic/governance comparisons.

### Recommendation

Handle this as a content-research subtask, not as an automatic link injector.

For each article that makes material claims about external concepts:

- identify 1–3 authoritative sources;
- add citations only where they improve trust/clarity;
- keep EarthCoop-specific claims sourced to EarthCoop Docs;
- avoid citation clutter and low-authority SEO link farms.

## Non-gaps / things not to change now

- Do not delete legacy blog posts or categories yet.
- Do not create tags solely for SEO.
- Do not re-submit or replace the working sitemap.
- Do not add a glossary without a justified query target.
- Do not add FAQ/HowTo schema merely to create Search Console “enhancements”.
- Do not change canonical URLs for the eight published articles.
- Do not rewrite published Pillar definitions as part of this phase.
- Do not expose unstable Najm Bahar/Bahar/Najm Hoda parameters as fixed SEO claims.

## Priority order

### P0 — preserve current indexability
No change required; current first-wave pages are crawlable/indexable.

### P1 — structured-data entity reinforcement
Small, testable change to the existing Article JSON-LD using approved Pillar ownership.

### P1 — curated semantic related-content contract
Use explicit first-wave topic ownership for SEO articles while preserving generic fallback behavior.

### P2 — external-source trust pass
Research and add authoritative external citations only where applicable.

### Deferred — glossary
Wait for demand/query evidence and a non-cannibalizing target design.

## Release discipline

All code changes remain:

- branch-only;
- TDD-first;
- targeted tests before broad validation;
- one final Full Validation on the exact final HEAD;
- no direct writes to `main`;
- no Production data mutation for Phase 4 code changes.
