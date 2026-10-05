# Persian SEO Phase 1 Review Gate

Date: 2026-10-05

Status: research/content architecture complete; technical hardening implemented on the isolated branch and awaiting/under CI verification before merge.

## Phase-1 outputs

- `docs/seo/2026-10-05-current-state-audit.md`
- `docs/seo/persian-topic-entity-map.md`
- `docs/seo/persian-keyword-map.csv`
- `docs/seo/persian-serp-research.md`
- `docs/seo/blog-content-inventory.md`
- `docs/seo/persian-content-backlog.md`
- `docs/seo/internal-linking-map.md`

Design/spec lineage:

- `docs/superpowers/specs/2026-10-05-persian-seo-growth-design.md`
- `docs/superpowers/plans/2026-10-05-persian-seo-growth-phase-1.md`

## Selected first-wave Pillar set

The recommended Phase-2 set is:

1. Home `/` — EarthCoop entity/mission semantic strengthening.
2. `/cooperative/` — cooperation/cooperative identity.
3. `/cooperative/global/` — global cooperation/global cooperative intent.
4. `/governance/` — participatory/democratic governance.
5. `/governance/elections/` — permanent/continuous candidate-less elections.
6. `/governance/local-to-global/` — local autonomy and multilevel governance.
7. `/economy/` — اقتصاد آزاد مردمی, EarthCoop's named economic model.
8. `/economy/glass/` — اقتصاد شیشه‌ای, transparent + secure economy.
9. `/economy/ownership/` — private ownership of labor + common resources + common ownership entitlement.
10. `/commons/` — Earth/shared resources/common-right framework.
11. `/justice/` — justice, rights, responsible freedom, foundational equality and dignity.
12. `/technology/` — digital governance/accountable technology.

This is the upper end of the intended 8–12 range. Phase 2 may ship these in sub-batches, but canonical ownership should follow this map.

## Deferred dedicated Pillars

Do not launch dedicated indexable Pillars yet for:

- `نجم هدا` — stable name/core role, evolving capability surface;
- `نجم‌بهار` — stable core financial-system identity, evolving implementation/legal details;
- `بهار` — stable internal monetary-unit identity, evolving creation/activation/valuation details;
- `اقتصاد مردمی` as a separate `/economy/people/` URL — first let `/economy/` own the bridge and split only if content/Search Console data justify it;
- `سرمایه‌گذاری مردمی` as a dedicated investment Pillar — requires tighter product/legal vocabulary before comparing with crowdfunding/securities concepts.

These may be mentioned at stable-core level inside approved Pillars and linked to formal Docs.

## External concepts: equivalence safety

The entity-map gate remains mandatory:

- Platform Cooperative — comparison, not synonym.
- Liquid Democracy — comparison, not synonym.
- Complementary Currency — comparison, not synonym for Bahar.
- Community Currency — comparison, not synonym for Bahar.
- Commons — adjacent, not exact identity with the EarthCoop common-right mechanism.
- Civic Tech — adjacent.
- Natural Rights — comparison; EarthCoop's foundational-rights language must not be silently assigned to a specific external natural-law school.
- Free Market — comparison; People's Free Economy is not laissez-faire capitalism.

## Top launch article clusters

Highest-value early articles are concentrated around:

- اقتصاد مردمی and the definition/differences of اقتصاد آزاد مردمی;
- Glass Economy: transparency + privacy + security;
- private ownership vs common resources and حق مالکانه همگانی;
- participatory governance and the participation/oversight/transparency chain;
- continuous/candidate-less elections and Liquid Democracy comparison;
- local self-governance and multilevel representation;
- justice, responsible freedom, foundational equality, dignity and land/resources;
- platform cooperative comparison.

The detailed 15-article initial editorial order is in `persian-content-backlog.md`.

## Legacy blog migration conclusion

The repository confirms demo/test blog generators and a 10-post environmental/cooperation seed set. The content may be replaced by project decision, but URL deletion remains gated because:

- current production DB rows are unavailable here;
- per-URL Search Console impressions/clicks are unavailable;
- backlink evidence is unavailable;
- seeded `views_count` is synthetic/random and cannot be treated as analytics.

Therefore every known legacy URL is currently `hold`. Phase 3 must obtain a live published-post export and Search Console/backlink evidence before destructive URL actions. Useful URLs may be rewritten in place or redirected only to semantically relevant successors.

## Verified technical gaps and branch fixes

### Gap 1: `/privacy` missing from sitemap

Evidence: route explicitly emits `index,follow` with canonical `/privacy`; sitemap static allowlist omitted it.

Branch change:

- regression assertion added to `SitemapTest`;
- `/privacy` added to explicit `SitemapController` allowlist.

### Gap 2: published managed pages in sitemap but `noindex`

Evidence: `SitemapController` includes every published `Page`, while `PageController` supplied title/description/canonical but not `seoRobots`; `layouts.unified` safely defaults unspecified pages to `noindex,nofollow`.

Branch change:

- published-page metadata test now requires `index,follow`;
- `PageController::show()` explicitly supplies `seoRobots = 'index,follow'` for published pages;
- unpublished pages remain 404.

This preserves the prior SEO-foundation policy that published managed pages are public/indexable and unpublished pages are excluded.

### Not changed: category/tag archives

Blog category/tag pages remain indexable by current controller metadata but omitted from the sitemap. This is not automatically changed in Phase 1. Phase 3 should decide whether each real taxonomy archive has enough value to stay indexable; thin archives should become `noindex,follow` rather than being added to the sitemap blindly.

### Later multilingual issue

`og:locale` remains hard-coded to `fa_IR`. This is correct enough for the Persian-first current surface and intentionally deferred until English/Arabic locale architecture is designed.

## Test/verification status

The execution container could not create a local checkout because it could not resolve GitHub directly. Therefore tests could not be run locally.

To preserve the user's constraint against repeated/wasteful CI cycles, the Phase-1 code changes were bundled into one branch with focused regression tests. CI on the PR is the verification gate. No merge should occur until the relevant SEO tests/required checks pass.

Targeted intended gate:

```bash
php artisan test tests/Feature/Seo
```

Relevant assertions now cover:

- canonical sitemap static routes including privacy;
- public published page indexability;
- unpublished page 404 behavior;
- public metadata contracts;
- exclusion of auth/private/thin routes.

## Phase-2 recommended scope

Phase 2 should implement **Pillar infrastructure + first sub-batch of high-priority Pillars**, not the blog reset simultaneously.

Recommended first implementation sub-batch:

1. `/economy/`
2. `/economy/glass/`
3. `/governance/`
4. `/governance/elections/`
5. `/justice/`
6. `/commons/`

Rationale: these form EarthCoop's most distinctive semantic core and give the later blog articles stable canonical destinations. The cooperation/local-to-global/technology Pillars can follow in the same Phase-2 program after the common page mechanism and content contract are proven.

Phase 3 should then reset the blog around the approved first editorial wave, using the live legacy-URL audit before deletion/redirect.

## Success gate before Phase 2 implementation

Phase 1 is ready to hand off when:

- branch CI passes the SEO regression surface;
- the Phase-1 PR review finds no semantic overclaim/cannibalization regression;
- no research artifact silently maps comparison concepts as exact equivalents;
- code changes remain limited to the two verified current-state SEO drifts.
