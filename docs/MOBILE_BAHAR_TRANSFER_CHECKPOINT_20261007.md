# Native Bahar Transfer Checkpoint — 2026-10-07

## Status

**Software complete / automated final gates green / physical UAT and deployment not executed.**

The first native external Najm Bahar transfer flow is complete on isolated branch `agent/mobile-bahar-transfer-20261007`.

Frozen product code before the workflow-only evidence trigger:
`05106f816123ed0fe196b03a8f312cbe8a4067da`

Final evidence head (same product code plus workflow-only rerun comment):
`84799fc942fb1f2e81ceaf8bb7b6d8bfdf125c36`

No `main` merge, production deployment, live transfer or new signed APK was performed.

## Implemented contract

- Read-only `GET /api/v1/najm-bahar/transfers/capability`.
- External transfer is fail-closed when the global threshold is unmet or no owned canonical active subaccount has spendable Active Bahar.
- Source projection is ownership-scoped, canonical-mirror-only and reservation-aware.
- No GET creates a missing mirror or mutates financial state.
- Exact `GET /api/v1/najm-bahar/transfers/destination` normalizes slash/dash input and returns a minimal identity projection plus a 10-minute opaque encrypted destination token.
- Missing, inactive, unmirrored, unsupported and internal/same-owner destinations do not become an external transfer.
- Strict native `POST /api/v1/najm-bahar/transfers` binds:
  - contract version 1;
  - exact selected source id/number;
  - exact frozen reservation-aware source availability;
  - exact destination account/token;
  - positive integer Gol amount;
  - Active-only external transfer.
- Legacy no-`expected` API behavior is preserved for existing callers.
- Policy, threshold, ownership, destination and availability are re-evaluated at POST.
- Cross-owner Dim remains prohibited.
- Financial POST disables automatic transport retry.
- Same intent uses the same idempotency key/body; controller retry lifetime is 23 hours.
- No financial offline queue and no POST replay on restart.
- Read-only `GET /api/v1/najm-bahar/transfers/by-idempotency/{key}` reconciles an ambiguous result using the authenticated user + exact transfer route scope.
- Mobile receipt must match completed status, amount, Active bucket, outgoing direction and exact canonical counterparty.
- Session/logout invalidates source/destination/frozen intent and suppresses delayed old-session publication.
- Native UI includes source subaccount selection, exact destination preview, exact amount, optional description, review/confirmation, unknown-result reconcile/retry and known receipt.
- Dim has no external-transfer affordance in the native UI.
- Internal redistribution and scheduled transfer remain separate future intents.

## Review findings closed

Whole-change review covered the 21-file PR surface and the approved design/plan. Three Important findings were closed regression-first:

1. **Canonical reservation availability** — capability now uses the existing canonical mirror and `ActiveBaharReservationService::availableActive()`, bounded by the subaccount Active balance.
2. **Inactive destination mirror** — destination preview/verification requires an active canonical mirror and fails closed otherwise.
3. **No spendable source** — capability is disabled with `no_eligible_source` unless at least one source has positive spendable Active.

No remaining Critical or Important finding was identified after the fixes and final rerun.

## Final automated evidence

Final workflow run: `37595959108`

### Server

Job: `112708524537` — **SUCCESS**

- Financial mutation architecture: **1 test / 1 assertion**, green.
- Native transfer contracts: **21 tests / 211 assertions**, green.
- Full server suite: **2439 tests / 13938 assertions**, **47 PHPUnit deprecations**, **2 skipped**, green.
- Server artifact ID: `11471280518`
- Server artifact digest: `sha256:6798873903f3faef4d33f0e41c6ba2dfb3973290eab1f80be0eb028807cd63a7`
- Evidence retention expiry: 2026-11-06.

### Mobile

Job: `112708524514` — **SUCCESS**

- Formatter: **148 files / 0 changed**.
- Analyzer: **No issues found**.
- Complete Flutter suite: **311 tests passed**.
- Mobile artifact ID: `11470503698`
- Mobile artifact digest: `sha256:308bd0fcbfa78f1676a0c9181f676ecf109d022ba332f1f2a84a64b5dcc55882`
- Evidence retention expiry: 2026-11-06.

## Deliberately open

- No compatible transfer API has been deployed to production.
- No live or test monetary transfer has been made.
- No physical Android/iOS transfer acceptance has been executed.
- Signed Android +18 predates this transfer feature and therefore is **not** a transfer-capable delivery candidate.
- Do not rebuild merely to inspect this feature while a phone is unavailable. Package one later consolidated signed candidate after additional phone-free work is complete.
- No `main` merge or FTP/host publication is authorized by this checkpoint.
