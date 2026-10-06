# Persian SEO — Phase 2 Wave 2 Handoff

Date: 2026-10-05

Base: `main@d4c4faf2f192278e1107623abc16727c6da801a1`

Branch: `agent/persian-seo-phase2-wave2-clean-20261005`

## Scope implemented

This wave extends the existing `PillarRegistry` / `PillarController` / shared Pillar view architecture created in Phase 2 Wave 1.

New indexable Persian Pillars:

- `/cooperative` — تعاون نوین، با platform cooperative as comparison only;
- `/cooperative/global` — تعاون جهانی، from local communities to global cooperation;
- `/governance/local-to-global` — حکمرانی از محله تا جهان، without equating the model to federalism/confederalism;
- `/economy/ownership` — مالکیت خصوصی مشروع، منابع مشترک و حق مالکانه همگانی;
- `/technology` — حکمرانی دیجیتال / فناوری مدنی at stable-principle level; Najm Hoda capability details remain gated.

Home semantic-hub treatment:

- Persian home title now owns the brand + top semantic themes: ارث‌کوپ، تعاون جهانی، حکمرانی مشارکتی، اقتصاد آزاد مردمی;
- default description names justice, participatory governance, People's Free Economy and common rights in Earth/resources;
- Persian Welcome footer links directly to `/cooperative`, `/economy`, `/governance`, `/justice` without changing English/Arabic navigation.

## Tests added/extended

- `tests/Feature/Seo/PillarPagesTest.php`
  - metadata/canonical/JSON-LD/docs/related-link contract for all new Pillars;
  - semantic-safety checks for platform cooperative, federalism, ownership and Najm Hoda capability claims.
- `tests/Feature/Seo/SitemapTest.php`
  - all eleven published Pillars must appear exactly once.
- `tests/Feature/Seo/PublicMetadataTest.php`
  - home must expose the four core topic links and semantic terms.

## Semantic guardrails preserved

- platform cooperative is adjacent/comparison terminology, not an EarthCoop synonym;
- global cooperation does not claim EarthCoop replaces states or international organizations;
- multilevel governance is not declared identical to federalism or confederalism;
- common rights do not abolish legitimate private ownership of the fruits of labor;
- common-entitlement rates/formulas remain unpublished until stabilized;
- Najm Hoda has only stable-core public wording; capabilities still evolving are explicitly not frozen as SEO claims.

## Still deferred

Dedicated indexable pages for:

- `/technology/najm-hoda`
- `/economy/najm-bahar`
- `/economy/bahar`
- `/economy/people`
- `/economy/investment`

remain deferred under `docs/seo/persian-content-backlog.md` until their public claim matrices / demand justify separation.

## Release gate

Do not merge until one Full Validation run passes on the exact PR HEAD and the final PR diff contains no unrelated application behavior changes.

After this wave, the next planned SEO work is Phase 3: replace the demo blog content with the approved Persian topic-cluster editorial program, after a final legacy-URL/index/backlink check.
