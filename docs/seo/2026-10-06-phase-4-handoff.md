# Persian SEO — Phase 4 Handoff

Date: 2026-10-06

Branch: `agent/seo-phase4-audit-plan-20261006`

PR: #208

## Completion status

Phase 4 implementation is complete pending the final integration gate.

Completed work:

- inverse article -> Pillar ownership lookup without a duplicate mapping table;
- Article JSON-LD enrichment with Persian language, EarthCoop publisher/site identity, and approved Pillar `about` relationships;
- no fabricated Pillar ownership for legacy/non-SEO posts;
- curated sibling related-article selection for SEO-owned posts;
- preserved category/recent fallback for ordinary legacy posts;
- explicit sitemap regression coverage for all eight Wave-1 articles;
- external-source research map for generic/comparison claims;
- no public article body was changed during the external-source research task.

## Glossary gate

### Current decision

**Deferred.**

No glossary route, page, migration, tag, sitemap entry or public glossary content is created in Phase 4.

### Reason

The approved Phase-1 query/entity material does not currently contain a stable query target whose `target_type` is actually assigned to a glossary page.

Creating glossary URLs now would therefore invent SEO targets without evidence and could cannibalise an existing Pillar or article.

### Revisit trigger

Glossary implementation may be reconsidered only when Search Console and/or renewed SERP research identifies a stable term that:

1. has meaningful search demand or repeated relevant impressions;
2. has intent distinct from the existing Pillar and article targets;
3. is stable enough to define publicly;
4. can be explained without exposing draft/configurable EarthCoop mechanics;
5. has a clear canonical owner and internal-linking role.

Until then, entity reinforcement should continue through Pillars, approved articles, structured data and curated internal links.

## External-source pass

Research output:

`docs/seo/phase-4-external-source-map.md`

The map separates:

- generic/external claims suitable for independent sources;
- EarthCoop-specific definitions and rules that must remain sourced to official EarthCoop Docs;
- optional contextual citations that must not imply external endorsement of EarthCoop.

Public article copy should be changed only in a separately reviewed content pass.

## Search Console / Phase 5 boundary

All eight Wave-1 article URLs were Live Tested as indexable and submitted for indexing on 2026-10-06.

Phase 5 should not infer success merely from submission. It should use actual Google Search Console evidence after sufficient crawl/indexing/impression time, including:

- indexed/not-indexed status;
- impressions;
- queries;
- clicks/CTR;
- page-level performance;
- evidence of intent overlap or cannibalisation.

## Final integration gate

Before merge:

1. verify the exact final HEAD;
2. compare the branch with current `main` and resolve any staleness safely;
3. review the final diff for scope hygiene;
4. confirm PR review threads are resolved;
5. require one successful Full Validation on the exact final HEAD;
6. merge only that validated HEAD.

No Production data migration or Seeder execution is required for the Phase-4 code changes themselves.
