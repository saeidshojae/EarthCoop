# Native Bahar Participation Activation — Design

**Date:** 2026-10-07
**Status:** Phone-free design after repository audit
**Safety:** No live activation, production deployment or main merge.

## Scope

Add a Native Flutter flow to convert eligible participation points into Active Bahar by activating the user's own Dim Bahar.

This is a bucket conversion, not money creation.

Excluded:
- admin/monthly equal activation;
- project-investment activation;
- membership-fee activation side effect;
- group/legal-entity activation;
- manual authority metadata;
- offline queued activation;
- production/live activation.

## Eligibility contract

Extend GET `/api/v1/najm-bahar/activation/eligibility`:

```json
{
  "activation_contract_version": 1,
  "enabled": true,
  "source": "participation",
  "remaining_convertible_points": 350,
  "conversion_ratio_points_per_gol": 100,
  "max_convertible_points": 300,
  "max_activation_points": 300,
  "max_activation_gol": 3,
  "dim_available_gol": 10,
  "active_gol": 5,
  "policy_version_id": 12,
  "policy_version": 1,
  "policy_source": "versioned_policy"
}
```

Rules:
- read-only;
- all monetary/point values integer;
- `max_activation_points = max_activation_gol * conversion_ratio_points_per_gol`;
- policy version id may be null only when current policy source truly has no persisted version;
- no account id, user id, raw ledger rows or authority metadata.

## Native strict POST

Keep legacy POST compatible when `expected` is absent.

Native payload:

```json
{
  "source": "participation",
  "points": 300,
  "expected": {
    "activation_contract_version": 1,
    "policy_version_id": 12,
    "policy_version": 1,
    "conversion_ratio_points_per_gol": 100,
    "remaining_convertible_points": 350,
    "dim_available_gol": 10,
    "max_activation_gol": 3
  }
}
```

Strict rules:
- source exactly participation;
- points > 0;
- points must be an exact multiple of frozen ratio;
- points must be <= frozen and current `max_activation_points`;
- current policy version/id, ratio, remaining convertible points, Dim available and max activation Gol must exactly equal the expected snapshot;
- if any snapshot field changes: `activation_terms_changed` 409, zero mutation;
- if exact points exceed current eligibility: `activation_not_eligible` 409;
- no silent floor/rounding in native contract;
- legacy no-expected callers retain existing floor-to-whole-ratio behavior.

Response:
- source participation;
- requested_points;
- consumed_points;
- activated_gol;
- canonical transaction projection;
- current balance projection.

For strict Native success:
- `requested_points == consumed_points`;
- `activated_gol == requested_points / frozen ratio`;
- transaction must be completed and represent activation of the same amount.

## Idempotency and ambiguity

Reuse financial transport invariant:
- explicit Idempotency-Key;
- automatic transport retry disabled;
- repeated confirm coalesces one POST;
- same-intent retry uses byte-equivalent payload and same key;
- 23-hour client retry lifetime;
- no automatic POST after restart.

Add:
`GET /api/v1/najm-bahar/activation/by-idempotency/{key}`

Authenticated, current-user scoped, read-only.

It should resolve only a completed successful activation response belonging to route scope `api.v1.najm-bahar.activation.store`. It returns the same matchable receipt fields as POST. Otherwise 404 `not_found`.

The client must never infer success merely from changed Active/Dim balances.

## Native intent

`NajmBaharActivationIntent` freezes:
- points;
- expected snapshot;
- idempotency key.

No account/source picker is needed in v1. Destination is the user's own main account and source type is participation.

## UI

Separate card/action: «فعال‌سازی بهار از امتیاز مشارکت».

Flow:
1. GET eligibility.
2. If policy disabled, read-only explanation.
3. Show remaining participation points, conversion ratio, Dim available, max activatable Bahar.
4. User enters points, not Gol.
5. UI validates exact positive multiple of ratio and <= max activation points.
6. Review shows:
   - points consumed;
   - exact Active Bahar to be created from existing Dim;
   - Dim before/after;
   - Active before/after;
   - policy conversion ratio.
7. Explicit «تأیید و فعال‌سازی».
8. Known success shows receipt/tracking and refreshes wallet/policy best-effort.
9. Definite rejection reloads eligibility.
10. Unknown result freezes intent and offers «بررسی نتیجه» and «تلاش دوباره با همان درخواست».

Wording must state that activation does not mint new money; it converts existing Dim to Active.

## Session/lifecycle

Same financial boundaries as membership/transfer:
- logout/account change clears eligibility/intent/receipt;
- old async replies cannot publish into new session;
- restart never replays pending activation;
- no financial offline queue.

## Server tests

Prove:
- eligibility zero-write;
- contract version/policy ids/ratio/max points are integer-consistent;
- strict expected snapshot exact match;
- policy change after review => zero-mutation terms_changed;
- points change after review => zero-mutation terms_changed;
- Dim change after review => zero-mutation terms_changed;
- non-multiple points rejected in strict path;
- legacy no-expected rounding behavior remains unchanged;
- exact activation consumes exact points and moves exact Dim to Active without minting;
- idempotent replay single effect;
- same key/different body conflict;
- reconcile current-user/route scoped and zero-write;
- malformed/missing evidence cannot become success.

## Mobile tests

DTO/repository:
- strict eligibility decode;
- exact intent JSON;
- POST automatic retry disabled;
- reconcile GET only;
- strict receipt matching.

Controller:
- open never POSTs;
- invalid points never review/POST;
- confirm one POST;
- same-intent retry identical body/key;
- ambiguity + GET-only reconcile;
- 23-hour expiry;
- logout/session invalidation;
- restart no replay.

Widget:
- disabled policy no submit;
- exact ratio validation;
- confirmation math;
- no POST on open/cancel;
- unknown outcome frozen fields;
- large integers/RTL/narrow layout;
- success receipt.

## Final validation

Focused RED/GREEN during implementation. One final:
- activation contract suite;
- financial architecture gate;
- complete Flutter formatter/analyzer/tests;
- full server PHPUnit once;
- whole-change review.

Do not build another APK solely for this feature while phone access is unavailable. Bundle into the next consolidated signed candidate.
