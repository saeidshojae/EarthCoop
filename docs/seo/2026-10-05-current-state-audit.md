# EarthCoop SEO Current-State Audit

Date: 2026-10-05

Audit base: `9f916d80be35925ec6f51b2fac0670cee9b64438`

Purpose: freeze the current public/indexable SEO surface before Persian topic-cluster work changes URLs, content, or indexability.

## Executive findings

The technical SEO foundation is present and generally coherent: `https://earthcoop.ir` is the configured canonical origin; `robots.txt` is permissive and advertises the canonical sitemap; the home page emits Organization + WebSite JSON-LD; blog posts emit Article JSON-LD; blog search is `noindex,follow`; and the sitemap uses an explicit allowlist plus published managed pages/posts rather than route-table introspection.

Two verified indexability/sitemap gaps exist in the current code:

1. `/privacy` explicitly emits `index,follow` and has a stable canonical URL, but it is absent from the static sitemap allowlist.
2. Published managed pages under `/pages/{slug}` are included in the sitemap, but `PageController::show()` does not set `seoRobots`; the `layouts.unified` fallback is `noindex,nofollow`. Therefore the current code can advertise published managed pages in the sitemap while instructing crawlers not to index them. This conflicts with the prior SEO-foundation intent that published managed pages be public/indexable.

A third item is intentional but must be revisited during the blog reset: blog category/tag archives default to `index,follow`, have canonical URLs, and are omitted from the sitemap by design to avoid thin/empty archives. They should not automatically be added to the sitemap; Phase 3 should decide whether sufficiently useful archives remain indexable or become `noindex,follow`.

## Canonical and shared metadata baseline

- Canonical origin: `https://earthcoop.ir` from `config/seo.php`.
- Alternate `.net` hosts are configured for canonical redirect behavior by the existing SEO foundation.
- Shared public metadata component: `resources/views/components/seo-meta.blade.php`.
- Shared component emits title, description, robots, canonical, Open Graph, Twitter Card, optional JSON-LD.
- `og:locale` is currently hard-coded to `fa_IR`. This is acceptable for the Persian-first current surface but is a known multilingual-readiness issue for the later English/Arabic phase.
- `resources/views/layouts/unified.blade.php` and the comparable shared public layout default unspecified pages to `noindex,nofollow`. This is a safe default, but every intentionally indexable controller/view must opt in explicitly.

## Route/type inventory

| Public route/type | Current robots behavior | Canonical source | Sitemap membership | Structured data | Current SEO role | Audit status |
| --- | --- | --- | --- | --- | --- | --- |
| `/` | `index,follow` via `<x-seo-meta>` default on the dedicated welcome view | `CanonicalUrl::to('/')` | Yes, explicit static entry | `Organization`, `WebSite` | Home / brand + future top-level semantic hub | Healthy baseline |
| `/terms` | Explicit `index,follow` route data | Shared component/request canonical; title + description supplied by view sections | Yes, explicit static entry | None | Utility/legal | Healthy baseline |
| `/privacy` | Explicit `index,follow` route data | Explicit `https://earthcoop.ir/privacy` via config | **No** | None | Utility/legal | **Gap: indexable route missing from sitemap** |
| `/blog` | `index,follow` from `BlogController::metadata()` default | `CanonicalUrl::to('/blog')` | Yes, explicit static entry | None | Supporting-content hub | Healthy baseline; copy/IA will be rebuilt later |
| `/blog/{slug}` published post | `index,follow` from metadata default | `CanonicalUrl::to('/blog/{slug}')` | Yes, published posts only | `Article` | Supporting content | Healthy technical baseline; legacy content requires audit |
| `/blog/category/{slug}` | `index,follow` from metadata default | `CanonicalUrl::to('/blog/category/{slug}')` | No, intentionally omitted | None | Archive/supporting navigation | Intentional omission; reassess thinness after blog reset |
| `/blog/tag/{slug}` | `index,follow` from metadata default | `CanonicalUrl::to('/blog/tag/{slug}')` | No, intentionally omitted | None | Archive/supporting navigation | Intentional omission; reassess thinness after blog reset |
| `/blog/search` | Explicit `noindex,follow` | Canonical `/blog/search` | No | None | Utility/search results | Healthy baseline |
| `/pages/{slug}` published managed page | **Falls back to `noindex,nofollow` because controller does not set `seoRobots`** | `CanonicalUrl::to('/pages/{slug}')` | **Yes, all published pages** | None | Existing public reference/page content; potential future pillar source | **Gap: sitemap says public while robots says noindex** |
| unpublished `/pages/{slug}` | 404 | N/A | No | None | Exclude | Healthy baseline |
| `/login` and password/auth pages | Shared-layout safe default unless a view explicitly overrides it; no index opt-in found in SEO code search | Request canonical may still render, but robots blocks indexing | No | None | Exclude | Correct policy: noindex/excluded |
| `/register` and registration steps | Shared-layout safe default unless explicitly overridden; no index opt-in found | Request canonical may render | No | None | Exclude | Correct policy: noindex/excluded |
| admin/member/private areas | Not part of public SEO allowlist; shared-layout/admin behavior is not opted into public indexing | Not relevant to public SEO | No | None expected | Exclude | Correct policy |
| `/sitemap.xml` | XML resource, not a content page | N/A | N/A | N/A | Discovery utility | Returns XML through dedicated controller |
| `/elections/guideline` | Public stable reference route exists, but no explicit `index,follow` opt-in was found during this audit | Depends on view/layout | No | Not established | Reference/utility | Treat as non-index target until explicitly reviewed; do not infer indexability from public reachability |

## Sitemap behavior

`app/Http/Controllers/Seo/SitemapController.php` currently builds the sitemap from:

- `/`
- `/terms`
- `/blog`
- each `Page` where `is_published = true`
- each blog `Post` returned by the model's `published()` scope

It sorts the final entries and emits `application/xml; charset=UTF-8`.

It intentionally does **not** introspect the full route table. That policy should be preserved.

The existing `tests/Feature/Seo/SitemapTest.php` asserts:

- valid XML and canonical `https://earthcoop.ir` URLs;
- `/`, `/terms`, and `/blog` are present;
- published pages/posts are present with `lastmod`;
- unpublished/draft/future/deleted content is absent;
- login, register, admin, API, invitation, blog search, and docs-host URLs are absent;
- `robots.txt` advertises only the canonical sitemap.

The test currently does **not** assert `/privacy`, which explains why the sitemap omission is not caught.

## Robots baseline

`public/robots.txt` currently contains a permissive rule for all user agents and exactly one sitemap declaration:

`Sitemap: https://earthcoop.ir/sitemap.xml`

There is no robots.txt-level block preventing public crawl.

## Metadata/structured-data baseline

### Home

The dedicated welcome view constructs:

- canonical home URL;
- absolute default image;
- `Organization` JSON-LD with name, URL, logo, configured social profiles;
- `WebSite` JSON-LD with Persian language marker.

The current localized home title is brand-oriented rather than keyword/topic-cluster-oriented; rewriting it is deliberately deferred until the Persian keyword map is approved.

### Managed pages

`PageController::show()` supplies:

- translated meta title with translated page-title fallback;
- translated meta description with cleaned/limited page-content fallback;
- stable canonical `/pages/{slug}`.

It does **not** supply `seoRobots`, causing the `noindex,nofollow` fallback described above.

### Blog

`BlogController` supplies canonical metadata for index/category/tag/post pages. Post pages add `Article` JSON-LD with headline, description, publication/modification dates, canonical `mainEntityOfPage`, author when available, and featured image when available. Search results are explicitly `noindex,follow`.

## Search Console baseline (operator-observed, dated)

These are dated observations from Google Search Console during the 2026-10-05 audit and are not permanent expected counts:

- Domain property: `earthcoop.ir`.
- `https://earthcoop.ir/sitemap.xml`: status **Success**, 18 discovered pages.
- `https://docs.earthcoop.ir/sitemap.xml`: status **Success**, 35 discovered pages.

The documentation sitemap belongs to the separate Docs SEO surface and should remain separate from the main-site sitemap.

## Current content architecture baseline

The current site has SEO infrastructure but does not yet have the approved Entity + Topic Cluster content architecture. In particular:

- home metadata is broad/brand-oriented;
- no approved first-wave Persian Pillar set has yet been published;
- the blog is treated by project decision as legacy/test content until its per-URL audit is complete;
- existing managed pages may contain useful public material, but they cannot currently function as indexable Pillars because of the robots mismatch;
- docs remain the legal/reference layer and should not be duplicated verbatim into main-site/blog SEO content.

## Phase-1 technical findings to carry forward

### Finding A — privacy sitemap omission

Severity: low-to-medium technical drift.

Decision: add `/privacy` to the explicit static sitemap allowlist with a regression test in the Phase-1 technical-hardening task.

### Finding B — published managed pages are sitemap-listed but noindex

Severity: high within the current public-content architecture because it creates contradictory discovery/indexing signals.

Decision required in the technical-hardening task: preserve the prior approved SEO-foundation policy that **published managed pages intended for public publication are indexable**, and make that intent explicit in controller metadata with regression coverage. Do not solve this by blindly removing all published pages from the sitemap; first preserve the distinction between published and unpublished content already encoded in the model/controller/sitemap policy.

### Finding C — category/tag archive policy

Severity: not an immediate defect.

Decision: defer to the blog reset. Keep them out of the sitemap for now. After real content/taxonomy exists, decide archive-by-archive whether it merits indexation; thin archives should become `noindex,follow` rather than being promoted merely because they are public.

### Finding D — multilingual metadata readiness

`og:locale` is hard-coded to `fa_IR`. This is not a Phase-1 blocker because Persian is the only current SEO language target, but the later EN/AR architecture must make locale metadata route/content-aware and implement hreflang/canonical policy deliberately.

## Verification limitation in this execution environment

The Phase-1 plan calls for:

`php artisan test tests/Feature/Seo/SitemapTest.php`

The current execution container cannot resolve `github.com`, so a local repository checkout/worktree could not be created and the command could not be executed here without spending a remote CI run. To avoid blind code changes or unnecessary CI consumption, this audit is based on direct inspection of the branch source and the existing test contract. The targeted test must be run before any Phase-1 code fix is claimed complete.

This limitation does not change the code-level findings above; it only means runtime verification remains an explicit gate for the later code-changing task.
