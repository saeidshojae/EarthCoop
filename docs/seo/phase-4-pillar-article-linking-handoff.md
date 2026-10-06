# Persian SEO — Phase 4 Pillar ↔ Article Linking Handoff

Date: 2026-10-06

## Scope

Close the first post-publication internal-linking gap for the eight approved Persian SEO articles by adding curated reverse links from each owning Pillar to its published supporting article(s).

## Design

- Keep `PillarRegistry` focused on stable Pillar copy, metadata, related Pillars and Docs references.
- Use `PillarArticleRegistry` as the small source of truth for curated supporting article links.
- `PillarController` resolves the supporting links by Pillar key.
- The shared Pillar view renders a dedicated «مقالات مرتبط» section only when curated articles exist.
- Do not query arbitrary latest blog posts; links are intentional and tied to the approved SEO ownership map.

## First-wave mappings

- `/economy` → `people-economy-explained`
- `/economy` → `free-market-without-monopoly`
- `/economy/glass` → `financial-transparency-and-privacy`
- `/governance` → `participatory-governance-beyond-voting`
- `/governance/elections` → `continuous-elections-explained`
- `/cooperative` → `platform-cooperative-and-earthcoop`
- `/justice` → `earth-in-earthcoop-justice`
- `/economy/ownership` → `private-property-and-common-resources`

## Safety

This change adds links only. It does not rewrite Pillar copy, mutate blog rows, change canonical URLs, alter sitemap behavior, or remove legacy content.

## Verification

`PillarPagesTest` now requires every approved owning Pillar to emit the expected `/blog/{slug}` link. Final integration must not merge until Full Validation is green on the exact final HEAD and the diff is re-reviewed.
