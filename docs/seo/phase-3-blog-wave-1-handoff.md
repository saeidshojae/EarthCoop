# Persian SEO — Phase 3 Blog Wave 1 Handoff

Date: 2026-10-05

## Scope

Publish the first eight deep Persian SEO articles through the existing Blog subsystem without deleting, truncating, redirecting, archiving, or otherwise mutating unrelated production blog content.

## TDD evidence

RED was verified on commit `cf3a9287d0d8c501fa1bda371ebff51739a38928` through Full Validation run `#3845` / workflow run `37358504011`.

The full suite reached the new contract test and failed for the intended missing implementation:

`Target class [Database\Seeders\PersianSeoBlogSeeder] does not exist.`

At that RED checkpoint the suite summary was one failed test, seven skipped tests, and 1366 passing tests (8510 assertions). Earlier regression gates were green; the failure was isolated to the new SEO blog contract.

## Approved taxonomy

- `economy-ownership` — اقتصاد و مالکیت
- `governance-elections` — حکمرانی و انتخابات
- `cooperation-community` — تعاون و جامعه
- `justice-commons` — عدالت و منابع
- `technology-transparency` — فناوری و شفافیت

## First article wave

1. `people-economy-explained` → `/economy`
2. `free-market-without-monopoly` → `/economy`
3. `financial-transparency-and-privacy` → `/economy/glass`
4. `participatory-governance-beyond-voting` → `/governance`
5. `continuous-elections-explained` → `/governance/elections`
6. `platform-cooperative-and-earthcoop` → `/cooperative`
7. `earth-in-earthcoop-justice` → `/justice`
8. `private-property-and-common-resources` → `/economy/ownership`

Every article must contain substantive Persian explanatory content, its owning Pillar link, and a link to the production Docs Center. Proprietary/adjacent concepts retain the semantic safety rules from the approved SEO design: platform cooperative and liquid democracy are comparison concepts, not synonyms for EarthCoop.

## Production safety

`PersianSeoBlogSeeder` is additive/idempotent and owns only the five approved taxonomy slugs and eight approved article slugs. It must not truncate any blog table. It updates official rows by stable slug, preserves each article's original `published_at` on re-run, and leaves unrelated rows untouched.

The older `EarthCoopBlogSeeder` remains a demo/local reset seeder and is not used for this production-safe content wave.

Legacy/demo URLs remain on `hold` until live production publication/Search Console/backlink evidence is available; this wave performs no destructive legacy cleanup.

## Integration gate

Do not merge the Phase 3 Wave 1 PR until a Full Validation run succeeds on the exact final implementation HEAD and the final diff is reviewed for scope hygiene.
