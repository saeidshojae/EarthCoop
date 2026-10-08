# EarthCoop legal agreements — implementation checkpoint

Status: IN PROGRESS, do not merge into main before owner approval.

## Verified existing architecture (main, 2026-10-09)

- Membership terms: `terms` rows, hierarchical `parent_id` and editable `message`, administered by `Admin/RuleController`, rendered by `resources/views/terms.blade.php`.
- Financial terms: `najm_bahar_agreements` rows, hierarchical `parent_id`, `order`, `content`, managed by `Admin/NajmBaharController`, rendered by `resources/views/najm-bahar/agreement.blade.php`.
- Guest registration captures consent in the session then sets `users.terms_accepted_at` on account creation. Already-authenticated member flow uses `Profile/TermController`.
- Najm Bahar account creation sets `users.najm_bahar_agreement_accepted_at` inside its transaction.
- The 2026-10-08 three legal texts are currently static Markdown in `resources/legal`, with separate public view panels. No assumption may be made that historic users accepted those texts.
- Current update actions mutate the admin rows in-place; there is no immutable accepted-version reference.

## Target rules

1. Keep the existing two content-management experiences and parent/child editing.
2. Official publication freezes a **complete, immutable document snapshot**, including ordered sections, public links, language, version number, publisher and SHA-256 digest.
3. Only one explicitly published version per document and locale is current. Draft editing is separate; no mutation of a published snapshot.
4. Acceptance is a transactionally recorded reference to the exact published version(s); registered and guest flows must capture the identities displayed to the user, not whatever is latest at POST time.
5. No legal acceptance inferred from the prior timestamp-only columns, even when those fields remain populated.
6. No loss or rewrite of old `terms`, `najm_bahar_agreements`, `users` data. No auto-seeding into production or automatic republishing of founder materials.
7. Financial opt-in remains separate from membership acceptance; existing wallet behavior must not change.
8. Preserve official foundational document references through `config/docs-links.php`; do not quietly turn draft fundamental laws into automatically binding terms.
9. UI must explicitly show draft / published / archived states and never let the user accept a draft.
10. Rollouts must not break current registration or Najm Bahar account access before publication and migration are complete.

## Ordered checkpoints (test-first)

- [x] Inventory current public/admin/acceptance sources.
- [x] Additive schema foundation and corresponding schema regression test (not deployed).
- [ ] Add immutable snapshot publishing service and business-rule tests: state transition, digest, published immutability, current-version conflict, parent/child ordering.
- [ ] Add admin draft/review/publish/archive controls and tests, reusing two existing content-management interfaces.
- [ ] Import approved MD text as **non-destructive, reviewable draft source** via explicit admin action/command, with preview and idempotence; no silent migration on production.
- [ ] Switch public contract reading to currently published snapshots, retaining readable article accordions and canonical foundation links; hide duplicated static Markdown sections after cutover.
- [ ] Capture per-document/version acceptance in registration session + membership submission and financial onboarding, with tamper/staleness validation and no fabricated historical consent.
- [ ] Handle materially changed terms through controlled re-consent (separate non-blocking initial deployment and configurable rollout).
- [ ] Targeted PHP and responsive tests, then full CI once release candidate is ready.
- [ ] Manual UAT: new guest registration, existing member, Najm Bahar opt-in, editing a draft, publishing new version, verifying historical snapshot unchanged.
- [ ] Owner approval then PR merge (do not merge earlier).

## Notes

The newly added tables `legal_documents`, `legal_document_versions`, and `legal_document_acceptances` are additive only. Their presence alone does not activate publication or the new consent flow. Production requires a specific data migration/approval checkpoint.
