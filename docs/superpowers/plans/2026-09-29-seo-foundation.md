# EarthCoop SEO Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Establish `earthcoop.ir` as the canonical search origin, redirect `.net`, publish a safe dynamic sitemap, and emit consistent public-page metadata and structured data.

**Architecture:** A small canonical-URL support class provides fixed-origin URL generation to middleware, Blade metadata, and sitemap generation. A shared Blade SEO partial is consumed by the existing public head surfaces, while controllers/views supply page-specific values. Sitemap construction uses an explicit allowlist plus published `Page` and `Post` queries; it never introspects the route table.

**Tech Stack:** PHP 8.2+, Laravel 12 with the repository's legacy `Http\Kernel`, Blade, PHPUnit 11, XML.

**Spec:** `docs/superpowers/specs/2026-09-29-seo-foundation-design.md`

## Global Constraints

- Canonical origin is exactly `https://earthcoop.ir`.
- `earthcoop.net` and `www.earthcoop.net` permanently redirect to the matching `.ir` path and query.
- No third-party package and no database migration.
- No visual redesign or authenticated-flow behavior change.
- Sitemap content is allowlisted and limited to published public records.
- Metadata and JSON-LD must be safely escaped/encoded and must not trust the request host.
- Targeted tests run per task; the broader affected-area suite runs only at the final gate.

## Review Focus

- A malicious or unexpected `Host` header must never enter canonical URLs; Task 1 tests fallback to the fixed configured origin.
- Redirected `.net` URLs containing encoded paths and multiple query parameters must preserve the request target without producing an open redirect; Task 1 tests path/query preservation.
- Optional `pages` or `blog_posts` tables may be absent during early bootstrap; Task 4 tests a sitemap response without those tables.
- Page content containing HTML and quotes must yield plain escaped metadata, not markup or duplicate tags; Task 2 tests the fallback description.
- JSON-LD containing Persian text, slashes, and optional null fields must remain parseable JSON; Tasks 2 and 3 decode and assert the emitted payloads.

---

### Task 1: Canonical URL policy and `.net` redirect

**Files:**
- Create: `config/seo.php`
- Create: `app/Support/Seo/CanonicalUrl.php`
- Create: `app/Http/Middleware/RedirectToCanonicalDomain.php`
- Modify: `app/Http/Kernel.php`
- Test: `tests/Feature/Seo/CanonicalDomainTest.php`

**Interfaces:**
- Produces: `CanonicalUrl::origin(): string`, `CanonicalUrl::to(string $path = '/', array $query = []): string`, `CanonicalUrl::forRequest(Request $request): string`.
- Produces: global middleware `RedirectToCanonicalDomain::handle(Request $request, Closure $next): Response`.
- Consumes: `config('seo.canonical_origin')`, fixed to `https://earthcoop.ir` by default.

- [ ] **Step 1: Write the failing canonical-domain feature tests**

Add tests named:

- `test_net_root_redirects_permanently_to_ir()` asserting status 301 and `https://earthcoop.ir/`;
- `test_net_redirect_preserves_encoded_path_and_query()` asserting the exact target for `/pages/%D8%AF%D8%B1%D8%A8%D8%A7%D8%B1%D9%87?ref=one&lang=fa`;
- `test_www_net_redirects_but_ir_and_test_hosts_do_not_loop()`;
- `test_canonical_url_never_uses_an_untrusted_request_host()` asserting `evil.example` produces an `.ir` canonical URL.

- [ ] **Step 2: Run the focused test and observe RED**

Run: `php artisan test tests/Feature/Seo/CanonicalDomainTest.php`

Expected: FAIL because `CanonicalUrl`/middleware do not exist and `.net` returns the normal response instead of 301.

- [ ] **Step 3: Implement the minimal canonical URL support and middleware**

`config/seo.php` defines `canonical_origin`, `redirect_hosts`, default title/description, default image, and verified social URLs. `CanonicalUrl` normalizes the configured origin and always returns absolute `.ir` HTTPS URLs. The middleware compares only the lower-cased host to the configured redirect-host allowlist and builds the destination from the fixed origin plus the request path/query.

Register the middleware at the start of the global stack in `app/Http/Kernel.php` so duplicate content is redirected before sessions and controllers.

- [ ] **Step 4: Run the focused test and observe GREEN**

Run: `php artisan test tests/Feature/Seo/CanonicalDomainTest.php`

Expected: PASS, 4 tests.

- [ ] **Step 5: Commit Task 1**

```bash
git add config/seo.php app/Support/Seo/CanonicalUrl.php app/Http/Middleware/RedirectToCanonicalDomain.php app/Http/Kernel.php tests/Feature/Seo/CanonicalDomainTest.php
git commit -m "feat: enforce canonical EarthCoop domain"
```

### Task 2: Shared metadata contract for home and managed pages

**Files:**
- Create: `resources/views/components/seo-meta.blade.php`
- Modify: `resources/views/welcome.blade.php`
- Modify: `resources/views/layouts/unified.blade.php`
- Modify: `resources/views/layouts/master.blade.php`
- Modify: `app/Http/Controllers/PageController.php`
- Modify: `resources/views/pages/show.blade.php`
- Modify: `resources/views/pages/templates/about.blade.php`
- Modify: `resources/views/pages/templates/help.blade.php`
- Modify: `resources/views/pages/templates/cooperation.blade.php`
- Modify: `resources/views/pages/templates/contact.blade.php`
- Modify: `resources/views/pages/templates/faq.blade.php`
- Modify: `resources/views/terms.blade.php`
- Test: `tests/Feature/Seo/PublicMetadataTest.php`

**Interfaces:**
- Consumes: `CanonicalUrl::forRequest(Request): string` and values from `config/seo.php`.
- Produces: Blade component `<x-seo-meta ... />` with props `title`, `description`, `canonical`, `robots`, `type`, `image`, and `jsonLd`.
- Produces: view data keys `seoTitle`, `seoDescription`, `seoCanonical`, `seoRobots`, `seoType`, `seoImage`, `seoJsonLd` supported by the public layouts.

- [ ] **Step 1: Write failing public-metadata feature tests**

Cover:

- home emits exactly one description and canonical tag, canonical `.ir` URL, Open Graph/Twitter tags, and two decodable JSON-LD objects with types `Organization` and `WebSite`;
- a published managed page prefers translated meta title/description;
- a managed page without meta description derives a length-limited plain-text description from HTML containing Persian text and quotes;
- an unpublished page remains 404;
- terms emits one canonical and one description.

- [ ] **Step 2: Run the focused test and observe RED**

Run: `php artisan test tests/Feature/Seo/PublicMetadataTest.php`

Expected: FAIL because canonical/social/JSON-LD tags and the component contract are absent.

- [ ] **Step 3: Implement the shared Blade contract and page-specific data**

Create the component with safe default values and `@json` for each JSON-LD object. Replace the current standalone title/description declarations in the three public head surfaces with one component invocation. `PageController::show()` supplies translated/fallback metadata using `Str::limit(strip_tags(...), 160)` and the canonical support class. Each page template defines only its visible content; metadata comes from layout view data.

Home JSON-LD uses verified values already in configuration/footer and the official `public/icons/icon.svg`; omit unknown legal/address fields. Terms uses stable Persian title/description and `/terms` canonical.

- [ ] **Step 4: Run focused tests and observe GREEN**

Run: `php artisan test tests/Feature/Seo/PublicMetadataTest.php tests/Feature/Welcome/WelcomeCtaAndInvitationContractTest.php tests/Feature/CommunityStories/WelcomeCommunityStoriesTest.php`

Expected: PASS with no duplicate metadata and no welcome regression.

- [ ] **Step 5: Commit Task 2**

```bash
git add resources/views/components/seo-meta.blade.php resources/views/welcome.blade.php resources/views/layouts/unified.blade.php resources/views/layouts/master.blade.php app/Http/Controllers/PageController.php resources/views/pages resources/views/terms.blade.php tests/Feature/Seo/PublicMetadataTest.php
git commit -m "feat: add shared public SEO metadata"
```

### Task 3: Blog metadata and Article structured data

**Files:**
- Modify: `app/Modules/Blog/Controllers/BlogController.php`
- Modify: `app/Modules/Blog/Views/frontend/index.blade.php`
- Modify: `app/Modules/Blog/Views/frontend/show.blade.php`
- Modify: `app/Modules/Blog/Views/frontend/category.blade.php`
- Modify: `app/Modules/Blog/Views/frontend/tag.blade.php`
- Modify: `app/Modules/Blog/Views/frontend/search.blade.php`
- Test: `tests/Feature/Seo/BlogMetadataTest.php`

**Interfaces:**
- Consumes: public layout SEO view-data keys from Task 2 and `CanonicalUrl` from Task 1.
- Produces: published post `Article` JSON-LD and `noindex,follow` blog-search metadata.

- [ ] **Step 1: Write failing blog metadata tests**

Cover:

- published post emits its meta title/description, `.ir` canonical, `article` Open Graph type, and decodable `Article` JSON-LD;
- JSON-LD uses persisted publication/update timestamps, author name when present, and omits unavailable optional image rather than emitting invalid data;
- draft, archived, soft-deleted, and future-dated posts return 404;
- blog index/category/tag emit self-canonical URLs;
- blog search emits `noindex,follow` and canonicalizes to `/blog/search` without copying the query into the canonical URL.

- [ ] **Step 2: Run the focused test and observe RED**

Run: `php artisan test tests/Feature/Seo/BlogMetadataTest.php`

Expected: FAIL because blog views do not provide the shared metadata contract or Article JSON-LD.

- [ ] **Step 3: Implement blog metadata**

Supply metadata arrays from controller actions or narrowly scoped view sections following the Task 2 interface. Remove the existing description/keywords tags incorrectly placed in the styles stack. Build `Article` JSON-LD only for a published post and strip/limit fallback excerpts. Search gets `noindex,follow`; no other public blog page is marked noindex.

- [ ] **Step 4: Run the focused test and observe GREEN**

Run: `php artisan test tests/Feature/Seo/BlogMetadataTest.php`

Expected: PASS.

- [ ] **Step 5: Commit Task 3**

```bash
git add app/Modules/Blog/Controllers/BlogController.php app/Modules/Blog/Views/frontend tests/Feature/Seo/BlogMetadataTest.php
git commit -m "feat: add blog SEO metadata"
```

### Task 4: Dynamic sitemap and robots discovery

**Files:**
- Create: `app/Http/Controllers/Seo/SitemapController.php`
- Create: `app/Support/Seo/SitemapEntry.php`
- Create: `resources/views/seo/sitemap.blade.php`
- Modify: `routes/web.php`
- Modify: `public/robots.txt`
- Test: `tests/Feature/Seo/SitemapTest.php`

**Interfaces:**
- Consumes: `CanonicalUrl::to(string, array): string`, `Page::where('is_published', true)`, and `Post::published()`.
- Produces: `SitemapEntry::__construct(string $location, ?CarbonInterface $lastModified = null)`.
- Produces: `SitemapController::__invoke(): Response` at named route `seo.sitemap` (`GET /sitemap.xml`).

- [ ] **Step 1: Write failing sitemap and robots tests**

Cover:

- response status 200, `application/xml`, parseable XML, and only `.ir` locations;
- static home, terms, and blog-index entries appear once;
- published pages/posts appear with escaped URLs and meaningful `lastmod`;
- unpublished pages plus draft, archived, future-dated, and soft-deleted posts are absent;
- auth, registration, search, admin, API, invitation, and docs hash URLs are absent;
- sitemap still returns static entries when optional content tables are absent;
- `public/robots.txt` retains `User-agent: *`/empty `Disallow` and advertises only `https://earthcoop.ir/sitemap.xml`.

- [ ] **Step 2: Run the focused test and observe RED**

Run: `php artisan test tests/Feature/Seo/SitemapTest.php`

Expected: FAIL with route 404 and missing robots sitemap directive.

- [ ] **Step 3: Implement allowlisted sitemap and robots entry**

Add the route before broad dynamic slug routes. The controller creates static entries, then conditionally queries `pages` and `blog_posts` only when their tables exist. Order entries deterministically by location. The XML view emits escaped `<loc>` and optional W3C `<lastmod>` values. Append exactly one canonical Sitemap line to `public/robots.txt`.

- [ ] **Step 4: Run the focused test and observe GREEN**

Run: `php artisan test tests/Feature/Seo/SitemapTest.php`

Expected: PASS.

- [ ] **Step 5: Run the affected-area final gate**

Run: `php artisan test tests/Feature/Seo tests/Feature/Welcome tests/Feature/CommunityStories/WelcomeCommunityStoriesTest.php`

Expected: all tests PASS with zero failures.

Run: `php artisan route:list --path=sitemap`

Expected: one `GET|HEAD sitemap.xml` route named `seo.sitemap`.

Run: `git diff --check $(git merge-base main HEAD)..HEAD`

Expected: no output.

- [ ] **Step 6: Commit Task 4**

```bash
git add app/Http/Controllers/Seo/SitemapController.php app/Support/Seo/SitemapEntry.php resources/views/seo/sitemap.blade.php routes/web.php public/robots.txt tests/Feature/Seo/SitemapTest.php
git commit -m "feat: publish canonical sitemap"
```

### Task 5: Whole-branch verification and handoff

**Files:**
- Modify only if verification exposes a spec violation.

**Interfaces:**
- Consumes: all interfaces and tests from Tasks 1-4.
- Produces: reviewed branch ready for user-approved merge; no automatic merge or push.

- [ ] **Step 1: Run formatting on changed PHP files**

Run: `vendor/bin/pint --dirty`

Expected: exit 0; review any formatter changes.

- [ ] **Step 2: Re-run the affected-area gate**

Run: `php artisan test tests/Feature/Seo tests/Feature/Welcome tests/Feature/CommunityStories/WelcomeCommunityStoriesTest.php`

Expected: all tests PASS.

- [ ] **Step 3: Build a review package and perform one fresh whole-branch review**

Use the executing-plans review-package workflow against `git merge-base main HEAD..HEAD`, with special attention to host-header safety, redirect loops, duplicate metadata, unpublished-content leakage, and XML/JSON validity.

- [ ] **Step 4: Apply at most one TDD fix pass for Critical/Important findings**

For each accepted finding, add a reproducing test, observe RED, implement the minimal fix, observe GREEN, then rerun the affected-area gate. Record deferred Minor findings without changing code.

- [ ] **Step 5: Commit verified fixes, if any, and report readiness**

```bash
git add <only reviewed fix files>
git commit -m "fix: address SEO review findings"
```

Expected: clean branch, evidence-backed test report, explicit list of commits/files, and a request for approval before merging or pushing.
