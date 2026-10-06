# EarthCoop Legacy Blog Content Inventory

Date: 2026-10-05

Status: Phase-1 migration gate. No legacy/test URL may be deleted or redirected solely from this repository inventory; live publication/index/backlink evidence must be checked first.

## Executive finding

Project decision: the existing blog material is test/demo content and may be replaced.

Repository evidence confirms that the main `EarthCoopBlogSeeder` is explicitly a demo-content reset: it truncates posts/comments/tag relationships and publishes **10 environmental/cooperation sample articles**, with randomized `views_count`. Those stored view numbers therefore cannot be treated as evidence of real audience demand.

A second console command, `blog:create-samples`, can also create generic sample blog posts. This means the repository alone cannot prove which sample set is currently present in the production database.

The live database is not available through the current tools and Search Console per-URL metrics are not connected. Therefore:

- live publication state = `unknown` unless a URL is separately observed;
- Search Console impressions/clicks/queries = `unknown`, **not zero**;
- backlink status = `unknown`, **not zero**;
- migration action remains `hold` until evidence is available.

This is deliberate: test status permits replacement, but does not justify discarding an indexed URL that may have accumulated search value.

## Repository-defined sample set: `EarthCoopBlogSeeder`

All entries below are created as `status=published`, with publish dates relative to seed execution. They are repository-defined candidates, not proof of the current production DB.

| Candidate URL | Sample title | Seed category | Known test/demo? | Live indexed? | Search value | Phase-3 action now |
| --- | --- | --- | --- | --- | --- | --- |
| `/blog/global-cooperation-for-climate` | چرا همکاری جهانی تنها راه مهار بحران اقلیمی است؟ | محیط زیست و اقلیم | Yes | unknown | unknown | `hold` |
| `/blog/renewables-2030` | انرژی‌های تجدیدپذیر 2030: خورشیدی، بادی و ذخیره‌سازی هوشمند | انرژی‌های تجدیدپذیر | Yes | unknown | unknown | `hold` |
| `/blog/circular-zero-waste` | اقتصاد چرخشی و صفرزباله: از طراحی تا اجرا | اقتصاد چرخشی و صفرزباله | Yes | unknown | unknown | `hold` |
| `/blog/resilient-cities-cooperation` | شهرهای تاب‌آور: نقش همکاری شهروندی در تاب‌آوری آب‌وهوایی | همکاری جهانی | Yes | unknown | unknown | `hold` |
| `/blog/regenerative-coops` | کشاورزی احیاگر تعاونی: خاک سالم، غذای سالم | نمونه‌های موفق | Yes | unknown | unknown | `hold` |
| `/blog/cooperation-in-crisis` | نمونه‌های موفق همکاری در بحران: از کووید تا سیل | نمونه‌های موفق | Yes | unknown | unknown | `hold` |
| `/blog/open-tech-for-sustainability` | فناوری باز برای پایداری: سنسورها، داده و شفافیت | همکاری جهانی | Yes | unknown | unknown | `hold` |
| `/blog/collaborative-water-management` | مدیریت مشارکتی آب: از حوضه تا مزرعه | محیط زیست و اقلیم | Yes | unknown | unknown | `hold` |
| `/blog/cooperative-carbon-neutrality` | اقتصاد تعاونی برای خنثی‌سازی کربن محلی | انرژی‌های تجدیدپذیر | Yes | unknown | unknown | `hold` |
| `/blog/100-day-climate-action` | برنامه ۱۰۰ روزه اقدام اقلیمی برای اعضای EarthCoop | محیط زیست و اقلیم | Yes | unknown | unknown | `hold` |

### Seed taxonomy

Categories created by this seeder:

- `environment-climate`
- `renewable-energy`
- `global-cooperation`
- `circular-economy`
- `success-stories`

Tags created include climate action, clean energy, global collaboration, circular economy, regenerative agriculture, water management, success cases, open tech, resilient cities and collective action.

These are demo taxonomy choices and are **not** the approved future Persian SEO taxonomy.

## Secondary sample generator: `blog:create-samples`

`app/Console/Commands/CreateSampleBlogPosts.php` creates additional generic sample content under categories such as:

- `technology`
- `sustainability`
- `cooperation`

Visible sample themes include:

- technology and a sustainable future;
- global cooperation for saving Earth;
- green economy;
- other generic cooperation/sustainability material in the command.

Because this command is additive and production execution history is not available, its generated URLs cannot be safely enumerated as live URLs from source code alone. Any future live inventory must query the production `blog_posts` table or an authoritative sitemap/export.

## Why the repository sample view counts are not evidence

`EarthCoopBlogSeeder` assigns `views_count` using a random number in a fixed range. `CreateSampleBlogPosts` also seeds preset view counts. These values are test fixtures/data decoration, not analytics. They must not influence SEO keep/remove decisions.

## Migration decision rules

Each **live** current post must eventually receive exactly one action:

- `keep-rewrite` — URL has real search/backlink value and its topic fits a future EarthCoop content cluster; rewrite it deeply without changing the URL.
- `replace-same-url` — URL semantics remain usable but content is wholly replaced.
- `redirect` — old URL has value, but a different future URL is the semantically correct canonical destination. Requires a specific destination.
- `remove-410-or-404` — test URL has no meaningful value and no relevant replacement. Do not redirect unrelated content merely to preserve a status code.
- `hold` — evidence incomplete.

### Redirect safety

No redirect is allowed to a semantically unrelated Pillar. For example, an old climate article should not be redirected to `/economy/` merely because `/economy/` is strategically important.

If a legacy climate/sustainability URL has real value and no close future article exists, the safer choices are same-URL rewrite into an accurate EarthCoop-relevant treatment, preservation until a relevant successor exists, or eventual removal.

## Current likely semantic reuse opportunities (not final actions)

These are **topic-fit observations only**, not redirect decisions:

- `global-cooperation-for-climate` could support the global-cooperation cluster if rewritten to be accurate, sourced and directly relevant to EarthCoop's local-to-global model.
- `open-tech-for-sustainability` could potentially support technology/open/auditable-tech content, but the current environmental framing is broader than the planned Najm Hoda/digital-governance cluster.
- `cooperation-in-crisis` may support cooperation/community resilience if future content strategy retains that topic.
- `collaborative-water-management` may relate to commons/shared-resource governance, but only if rewritten around a genuine user intent and credible sources.
- `cooperative-carbon-neutrality`, `renewables-2030`, `circular-zero-waste`, `regenerative-coops`, `resilient-cities-cooperation`, and `100-day-climate-action` are not first-wave SEO priorities under the approved EarthCoop identity/economy/governance architecture.

## Live evidence checklist required before deletion

For each production blog URL, collect when access is available:

`url | title | slug | status | published_at | category | tags | indexed_status | impressions_90d | clicks_90d | top_queries | backlink_observation | final_action | destination_if_redirect`

Minimum gate:

1. Export/query every currently published production `blog_posts` row.
2. Match each URL against Search Console pages/query data where available.
3. Check index status for URLs that appear in Search Console or sitemap.
4. Check backlinks using an available trustworthy source; mark unavailable evidence as `unknown`.
5. Only then replace `hold` with a destructive/migration action.

## Current conclusion

The content strategy may treat the old blog corpus as disposable **content**, but not yet as disposable **URLs**. Phase 3 should replace the demo editorial model with the approved EarthCoop topic clusters while preserving any URL equity that evidence shows to be real.
