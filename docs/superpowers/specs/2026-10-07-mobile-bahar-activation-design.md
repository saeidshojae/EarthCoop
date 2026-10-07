# Native Participation Activation — Design

**Date:** 2026-10-07
**Scope:** Native Flutter participation-points → Active Bahar activation. Phone-free implementation only.

## Safety goal

The user must confirm one exact conversion:
**N participation points are consumed to activate exactly G Gol of the user's existing Dim balance.**

No amount is minted. No hidden flooring is allowed in the strict native path.

## Read contract

Keep `GET /api/v1/najm-bahar/activation/eligibility`, extend it with:

```json
{
  "activation_contract_version": 1,
  "enabled": true,
  "source": "participation",
  "remaining_convertible_points": 350,
  "conversion_ratio_points_per_gol": 100,
  "max_convertible_points": 300,
  "max_activation_gol": 3,
  "dim_available_gol": 10,
  "active_gol": 5,
  "policy_version_id": 42,
  "policy_version": 7,
  "policy_source": "versioned_policy"
}
```

### Zero-write activation policy projection

The activation eligibility service must not call a legacy helper that migrates/saves Najm Bahar amount fields. It may:
- read an effective versioned policy; or
- read legacy activation fields directly from Setting without writing.

Relevant legacy fields are only:
- reputation_conversion_enabled
- reputation_to_gol_ratio

No membership amount normalization is needed to answer activation eligibility.

## Native confirmation model

The native UI lets the user choose an exact activation amount in Gol/Bahar. The intent derives:

`points = amount_gol × conversion_ratio_points_per_gol`

Constraints:
- amount_gol is positive integer;
- amount_gol <= max_activation_gol;
- derived points <= max_convertible_points;
- derived points is exact; strict native path never floors a non-multiple request.

Confirmation shows:
- exact participation points consumed;
- exact Bahar/Gol activated;
- current Dim available;
- ratio (points per Gol);
- policy/version source.

## Strict POST

Legacy body remains supported:

```json
{"source":"participation","points":250}
```

Native body:

```json
{
  "source": "participation",
  "points": 200,
  "expected": {
    "activation_contract_version": 1,
    "remaining_convertible_points": 350,
    "conversion_ratio_points_per_gol": 100,
    "max_convertible_points": 300,
    "max_activation_gol": 3,
    "dim_available_gol": 10,
    "policy_version_id": 42,
    "policy_version": 7,
    "policy_source": "versioned_policy"
  }
}
```

When `expected` is present:
- exact expected key set only;
- source must be participation;
- points must be a positive exact multiple of current ratio;
- recompute zero-write eligibility before mutation;
- every expected field must exactly match current server projection;
- requested points must map to amount_gol <= max_activation_gol;
- mismatch => `activation_terms_changed` 409 with zero point consumption and zero money mutation;
- insufficient exact points => `activation_not_eligible`;
- insufficient Dim => `insufficient_dim`;
- policy disabled remains fail-closed.

The server response must return:
- requested_points;
- consumed_points;
- activated_gol;
- canonical transaction projection;
- balance projection.

Strict native success requires requested_points == consumed_points.

## Reconciliation

Add:

`GET /api/v1/najm-bahar/activation/by-idempotency/{key}`

Authenticated and user-scoped. It returns a confirmed activation receipt only when:
- a UserPointConversion exists for current user + exact request key;
- status is applied;
- transaction evidence for conversion_key exists and belongs to the user's account.

Otherwise `not_found`.

No mutation and no replay occurs on reconciliation.

## Flutter intent/state

Create typed DTOs:
- ActivationEligibility
- ActivationIntent
- ActivationReceipt

ActivationIntent freezes:
- exact eligibility snapshot;
- exact amountGol;
- exact derived points;
- idempotency key.

Financial POST uses `allowAutomaticRetry=false`.

Controller states:
- loading
- ready
- reviewing
- submitting
- confirmed
- definiteRejected
- outcomeUnknown

Rules mirror membership/transfer:
- no POST on open/review/cancel;
- confirm coalesces;
- unknown outcome freezes intent;
- explicit same-intent retry only;
- 23h monotonic expiry;
- reconcile GET only;
- restart never replays POST;
- session/logout clears state and suppresses delayed publication;
- known success remains success if refresh fails.

## UI

New section under Najm Bahar:
- if policy disabled: explanatory read-only state;
- show remaining convertible points, ratio, max exact activation and Dim balance;
- exact amount input in Bahar with max two decimals, converted to integer Gol;
- dynamically show exact points to be consumed;
- review confirmation;
- no activation button when max_activation_gol == 0;
- known receipt;
- unknown-result controls «بررسی نتیجه» and «تلاش دوباره با همان درخواست».

## Tests

Server:
- eligibility zero-write on versioned and legacy settings paths;
- contract version + policy_version_id shape;
- strict expected snapshot mismatch zero-mutation;
- native non-multiple points rejected rather than floored;
- exact ratio conversion;
- point race and Dim race fail closed;
- replay single effect;
- reconciliation current-user scoped and read-only;
- malformed/foreign key not found;
- no minting and exact balance invariant.

Mobile:
- strict DTO integer validation;
- exact points derivation;
- no automatic financial retry;
- no POST open/cancel;
- one confirm one POST;
- unknown outcome/reconcile/same intent;
- 23h expiry;
- session/logout;
- Persian RTL/narrow/large values;
- policy disabled/zero eligibility;
- exact receipt validation.

## Final gate

Focused RED/GREEN during implementation. Once stable:
- financial mutation architecture;
- activation contract suite;
- complete Flutter formatter/analyzer/tests;
- full server PHPUnit once;
- one whole-change review;
- checkpoint update.

Do not build another signed APK solely for activation while no phone is available.
