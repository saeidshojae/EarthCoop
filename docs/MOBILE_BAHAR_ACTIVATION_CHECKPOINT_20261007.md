# Native Bahar Participation Activation Checkpoint — 2026-10-07

## Status

**Software complete / automated server and mobile final gates green / physical UAT and deployment not executed.**

The first native participation-point activation flow is complete on isolated branch `agent/mobile-bahar-activation-20261007`.

Server final evidence source:
`2dbc210dfd3ec51d14df4fc8125140503704958f`

Pinned final mobile product source:
`82b1097aa621fef9b16e9cf4e03f6443e0c8b224`

Current workflow-only branch head:
`a508bc62b769a233e3201cf1463beae0ef6e8f12`

The commits between the server evidence source and pinned mobile source modify only mobile/workflow files; no server implementation changed after the full server gate.

No `main` merge, production deployment, live activation, FTP publication or new signed APK was performed.

## Implemented contract

- Read-only `GET /api/v1/najm-bahar/activation/eligibility` now exposes activation contract version 1, exact policy identity/source, integer participation capacity, exact ratio, Dim/Active amounts, and exact max activation points/Gol.
- Eligibility is zero-write, including legacy-settings fallback; it does not migrate or normalize monetary settings merely because a native client performs a GET.
- Native strict POST keeps legacy compatibility when `expected` is absent.
- Strict Native activation binds:
  - contract version 1;
  - source `participation`;
  - exact current policy version/id/source;
  - exact conversion ratio;
  - exact remaining convertible points;
  - exact max convertible/activation points and Gol;
  - exact current Dim availability.
- Native points must be a positive exact multiple of the frozen ratio; no silent flooring is allowed.
- Stale policy/points/Dim terms fail closed with `activation_terms_changed` and zero financial/point mutation.
- Legacy no-`expected` callers retain existing whole-ratio flooring behavior.
- Activation consumes exact participation points and converts existing Dim to Active; total Bahar does not increase.
- Financial POST uses an explicit idempotency key and disables automatic transport retry in the native repository.
- Same-intent retry preserves the same frozen payload/key with a 23-hour client lifetime.
- No financial offline queue and no POST replay on restart.
- Read-only `GET /api/v1/najm-bahar/activation/by-idempotency/{key}` reconciles ambiguous results, scoped to the authenticated user and applied activation conversion.
- Native receipt validation requires exact requested/consumed points, activated Gol, completed internal transaction and matching amount.
- Session/logout invalidates eligibility/frozen intent/receipt publication and suppresses delayed old-session responses.
- Native UI shows exact participation→Dim→Active math, review/confirmation, unknown-result reconciliation/retry, and success tracking.
- UI explicitly explains that activation does **not mint new money**; it converts existing Dim to Active.

## Whole-change review

A separate read-only review covered the activation server consent boundary, point consumption, monetary conversion, idempotency/reconciliation, session lifecycle, DTO consistency and UI math.

Review checks included:
- eligibility read path does not write or migrate policy/settings;
- policy identity and ratio are bound in strict native consent;
- points and Dim are re-evaluated inside the financial transaction before consumption;
- stale terms roll back conversion identity/point consumption;
- exact-multiple native requests do not use legacy flooring;
- transaction evidence is matched rather than inferring success from balances;
- reconciliation is GET-only and current-user scoped;
- known success is not downgraded by best-effort refresh failure;
- no automatic transport retry or restart replay exists.

No remaining Critical or Important finding was identified in this review pass.

One design nuance is intentional: when participation conversion policy is disabled, the existing API returns `activation_disabled` rather than a normal `enabled=false` eligibility object. The native UI renders that as a read-only disabled-policy explanation and provides no activation submit path, so this remains fail-closed and consistent with the existing server contract.

## Final automated evidence

### Server

Final server workflow run: `37606644938`  
Job: `112743635660` — **SUCCESS**

- Activation contract: **13 tests / 154 assertions**, green.
- Financial mutation architecture: **1 test / 1 assertion**, green.
- Full server suite: **2446 tests / 14012 assertions**, **47 PHPUnit deprecations**, **2 skipped**, green.
- Server artifact ID: `11475502952`
- Server artifact digest: `sha256:6b41a38e08523d6c363ef4f313990850b53eb3958a345c871ef4a42a4276e119`
- Evidence retention expiry: 2026-11-06.

The combined run's mobile job failed only at strict formatting before analyzer/tests. Canonical formatting was then committed and verified separately; this was not a product-logic failure.

### Mobile

Pinned final mobile workflow run: `37607152360`  
Job: `112745312911` — **SUCCESS**

- Formatter: **154 files / 0 changed**.
- Analyzer: **No issues found**.
- Complete Flutter suite: **336 tests passed**.
- Mobile artifact ID: `11475168436`
- Mobile artifact digest: `sha256:c30bad491415b0ac3e732be8303f1b7e3e84cbbbbd636b3f9a7946c1a5f6f6b7`
- Evidence retention expiry: 2026-11-06.

## Deliberately open

- No compatible activation API has been deployed to production.
- No real/test activation has been executed.
- No physical Android/iOS activation acceptance has been executed.
- Signed Android +18 predates both external transfer and activation features; it is not a delivery candidate for these newer flows.
- Do not build another APK solely for activation while a phone is unavailable. Bundle additional phone-free work first, then create one consolidated signed candidate.
- No `main` merge, FTP/host publication, provider activation or production financial action is authorized by this checkpoint.
