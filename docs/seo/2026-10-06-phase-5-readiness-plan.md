# Persian SEO — Phase 5 Readiness Plan

Date: 2026-10-06

Status: readiness/operations only. No public content or production behavior changes are authorized by this plan.

Baseline:
- Phase 4 merged to main at `20f4cf0b6599db3381819aa3dc85367d468f48a7`.
- Production UAT completed successfully on 2026-10-06.
- Eight Wave-1 Persian SEO article URLs were Live Tested as indexable and indexing requests were submitted on 2026-10-06.

## What Phase 5 is

Phase 5 is the evidence loop after publication:

1. observe actual Google Search Console indexing/query/impression data;
2. compare that data with the approved entity/intent map;
3. identify real opportunities, weak snippets, missing intent coverage or cannibalisation;
4. decide whether to:
   - improve an existing page;
   - change title/meta copy;
   - strengthen internal links;
   - publish a new Wave-2 article;
   - create a glossary target;
   - or deliberately do nothing.

Phase 5 is **not** permission to publish a second wave of articles before evidence exists.

## Entry gate

Do not begin content iteration merely because indexing was requested.

The operational entry gate is satisfied only when at least one of the following exists:

- one or more Wave-1 pages are actually indexed and accumulating impressions;
- Search Console exposes meaningful query/page data for a Wave-1 target;
- repeated search impressions reveal a distinct uncovered intent;
- a page accumulates enough impressions to evaluate CTR/snippet quality;
- evidence of cannibalisation or intent overlap appears.

## Minimum evidence set to record

For each Wave-1 article and relevant Pillar, capture:

- indexing status;
- last crawl / discovery state when visible;
- impressions;
- clicks;
- CTR;
- average position;
- top queries;
- top countries/devices only when materially useful;
- whether multiple EarthCoop URLs appear for the same dominant query;
- whether Google is selecting a different page than the intended canonical target.

## Decision rules

### Existing page improvement

Prefer improving an existing page when:

- the query intent clearly matches the page's current owner;
- impressions exist but title/meta CTR is weak;
- users are reaching the page for a subtopic already inside its scope;
- the page is thin relative to observed intent, without needing a new canonical target.

### Wave-2 article

Create a new article only when:

- the query cluster is materially distinct from the existing article/Pillar owner;
- repeated impressions or SERP research show standalone intent;
- the new article has a clear parent Pillar;
- it does not cannibalise an existing page;
- the concept is stable/publication-safe.

### Glossary target

Reconsider a glossary only when:

- a definitional query has repeated demand;
- the intent is narrower than a Pillar and not already owned by an article;
- a short durable definition page is more appropriate than a long article;
- the term is stable and has a clear internal-linking role.

### CTR iteration

Change title/meta only when:

- the page is indexed;
- it has enough impressions to make CTR meaningful;
- the query intent is relevant;
- the proposed copy remains truthful and does not overpromise.

### Do nothing

Do nothing when data is too sparse or ambiguous.

No SEO action is preferable to changing architecture based on noise.

## Wave-2 backlog format

Any future Wave-2 candidate must include:

`query_cluster | dominant_intent | current_owner | proposed_target | evidence | cannibalisation_check | stability | parent_pillar | action`

Allowed action values:

- `expand-existing`
- `new-article`
- `new-glossary`
- `meta-iteration`
- `internal-link`
- `defer`
- `reject`

## Authority / outreach track

Authority work should be separate from content production.

Potential Phase-5 authority actions may include:

- outreach to relevant cooperative, civic-tech, governance or research communities;
- earning contextual mentions/links from credible sites;
- citing external authoritative sources where the Phase-4 source map identified real generic claims;
- avoiding low-quality directory/link-scheme tactics.

No outreach target should be treated as endorsement unless the external party explicitly provides one.

## Cadence

Initial check:
- 2–3 days after indexing requests: verify crawl/indexing movement only.

Early signal review:
- about 7–14 days: inspect first impressions/queries if available.

Meaningful iteration:
- normally after enough impressions accumulate to distinguish signal from noise.

The cadence is evidence-driven; fixed calendar deadlines must not force changes when data is insufficient.

## Phase-5 operating principle

**Search Console is feedback, not command.**

A query appearing in Search Console does not automatically justify a new page. Every action still passes through:

1. intent fit;
2. entity ownership;
3. stability/publication safety;
4. cannibalisation check;
5. evidence strength.

## Current next step

Wait for the first meaningful Search Console indexing/impression data from Wave 1.

When data is available, record it against the eight articles and their Pillars before making any public content change.
