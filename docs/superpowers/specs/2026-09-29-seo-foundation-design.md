# EarthCoop SEO Foundation Design

## Context

EarthCoop's public Laravel application is reachable at `https://earthcoop.ir`, while `earthcoop.net` serves the same product. The current public site is crawlable, but it has no sitemap, the home page lacks core metadata, and the public Blade layouts expose inconsistent SEO behavior. Public Google results currently do not surface EarthCoop pages.

This change establishes a minimal, maintainable SEO foundation without adding a package, changing the database schema, or altering private application behavior.

## Goals

- Make `earthcoop.ir` the single canonical search origin.
- Permanently redirect `earthcoop.net` requests to the same path and query on `earthcoop.ir`.
- Publish a valid dynamic sitemap containing only explicitly approved public content.
- Provide consistent title, description, canonical, Open Graph, Twitter Card, and structured-data output for public pages.
- Ensure drafts, private routes, authentication routes, and search-result pages never enter the sitemap.
- Keep the implementation testable with focused Laravel feature tests.

## Non-goals

- Search Console or Bing account automation.
- Content strategy, backlink acquisition, keyword campaigns, or analytics installation.
- Database migrations or a new admin SEO interface.
- Rewriting page copy or changing the visual design.
- Adding authenticated/member pages to search indexes.
- Changing the independently hosted documentation application at `docs.earthcoop.ir`.

## Canonical-domain policy

`https://earthcoop.ir` is the canonical origin.

Requests whose host is exactly `earthcoop.net` or `www.earthcoop.net` receive a permanent redirect to `https://earthcoop.ir` with the original path and query string preserved. Requests already using `earthcoop.ir` continue normally. Local/test hosts are not redirected, so development and automated tests remain usable.

The redirect is implemented as focused HTTP middleware and covered by host-specific feature tests. It must not redirect command-line execution, internal URL generation, or unrelated configured hosts.

## Shared metadata architecture

A reusable Blade partial/component owns the common public metadata contract:

- page title;
- meta description;
- `robots` directive;
- absolute canonical URL on `https://earthcoop.ir`;
- Open Graph type, title, description, URL, image, and locale;
- Twitter card, title, description, and image;
- optional JSON-LD payloads encoded with Laravel/Blade's safe JSON facilities.

The three existing public head surfaces (`welcome`, `layouts.unified`, and `layouts.master`) consume the shared contract. Existing page-specific values override defaults. Authenticated and administrative layouts are outside this change.

Defaults use EarthCoop's current Persian identity and the existing official logo/icon asset. Pages may supply a more specific image where one already exists. The implementation must avoid emitting duplicate description or canonical tags.

## Page-specific behavior

### Home page

The home page emits:

- its current localized title;
- a concise Persian description based on the current public value proposition;
- canonical `https://earthcoop.ir/`;
- `Organization` JSON-LD for EarthCoop;
- `WebSite` JSON-LD for the public website.

Structured data includes only verified public details already present in the site or configuration. It must not invent a postal address, legal registration identifier, or unsupported social profile.

### Managed public pages

Published `Page` records emit their translated meta title when present (otherwise their translated page title), their translated meta description when present (otherwise a length-limited value derived from safely stripped translated content), and their absolute canonical URL. Unpublished pages continue to return 404 and are excluded from the sitemap.

### Blog

The blog index, category pages, tag pages, and published posts receive canonical metadata. Search-result URLs are `noindex,follow` and are excluded from the sitemap.

Published post pages emit `Article` JSON-LD using persisted title, description/excerpt, publication/update timestamps, author display name when available, canonical URL, and featured image when available. Draft, archived, soft-deleted, or future-dated posts remain inaccessible through the public controller and absent from the sitemap.

### Terms

The public terms page receives a stable title, description, and canonical URL and is included in the sitemap.

## Dynamic sitemap

`GET /sitemap.xml` returns an XML response with UTF-8 content type and URLs on the canonical `https://earthcoop.ir` origin.

The sitemap uses an explicit allowlist:

- home page;
- terms page;
- blog index;
- each published managed `Page`;
- each currently published blog `Post`.

Category and tag archives are omitted initially to avoid thin or empty archive URLs. Search, registration, login, invitation, authenticated areas, admin areas, API routes, and documentation hash routes are excluded.

Dynamic records use their most meaningful available update timestamp for `lastmod`. Static entries may omit `lastmod` rather than publish a misleading value. XML values are escaped by the view/serializer.

The implementation must tolerate absent optional tables during early deployment or test bootstrap, matching the project's existing defensive schema checks where appropriate.

## robots.txt

The existing permissive rules remain. The file adds:

`Sitemap: https://earthcoop.ir/sitemap.xml`

Only the canonical sitemap is advertised because `.net` redirects to `.ir`.

## Error handling and safety

- Unknown or unpublished slugs remain 404.
- Sitemap generation never exposes private routes by introspecting the entire route table.
- Missing optional images fall back to the existing official EarthCoop icon.
- Metadata text is escaped; JSON-LD uses safe JSON encoding.
- The canonical redirect preserves path and query but always targets HTTPS and the fixed approved host.
- No user-supplied host header is copied into canonical URLs.

## Testing

Focused feature tests cover:

1. `.net` and `www.earthcoop.net` redirect permanently to the matching `.ir` URL with query preservation.
2. `.ir` and test hosts do not loop or redirect.
3. Home response contains one description, one canonical, social metadata, and valid Organization/WebSite JSON-LD.
4. Sitemap returns valid XML and canonical `.ir` URLs.
5. Published pages and posts are included.
6. Draft, archived, future-dated, soft-deleted, and unpublished content is excluded.
7. Auth, search, admin, API, and private routes are absent.
8. Published blog posts expose canonical metadata and valid Article JSON-LD.
9. Blog search responses are marked `noindex,follow`.
10. `robots.txt` advertises only the canonical sitemap.

The final gate runs these targeted tests plus the closest existing welcome, page, and blog tests. A full suite is reserved for the final integration gate because the repository's CI is long-running.

## Deployment and Search Console

After deployment:

1. Verify that both HTTP and HTTPS variants resolve as intended.
2. Verify `.net` redirects to the exact `.ir` path.
3. Validate `/robots.txt` and `/sitemap.xml` publicly.
4. Verify the `earthcoop.ir` Domain property in Google Search Console using the DNS record already installed.
5. Submit `https://earthcoop.ir/sitemap.xml` and `https://docs.earthcoop.ir/sitemap.xml`.
6. Add `earthcoop.net` as a separate Domain property only for ownership/redirect monitoring; do not submit a duplicate sitemap for it.

## Success criteria

- Every supported public page emits one consistent canonical SEO contract.
- `.net` cannot serve a duplicate indexable copy of the application.
- The canonical sitemap contains all and only approved public content.
- Targeted regression tests pass without requiring a database migration or third-party SEO package.
- No visual or authenticated workflow regression is introduced.
