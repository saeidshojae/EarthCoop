# EarthCoop Systemic Elections Rebuild — Baseline Audit

Branch: `agent/elections-systemic-rebuild`
Base checkpoint: `agent/integration-pre-elections-current-main`
Starting SHA: `2ec2b4003163a47960ef581271cad80efb99c39e`

## Scope

This document records the initial code-level audit before changing election behavior. The target is EarthCoop's permanent, automatic, no-candidate systemic election lifecycle while preserving a fail-closed integration path with Groups, Governance, Notifications and Najm Hoda Founder Ops.

## Current implementation observed

### Core entities

- `Election`: group-scoped record with `starts_at`, `ends_at`, `is_closed`, and `second_finish_time`.
- `Candidate`: election/user pair with a single nullable `accept_status` field.
- `Vote`: current controller writes an election, voter, selected user identifier, and position (inspector/manager).
- `GroupSetting`: level-specific settings for inspector count, manager count, election timing, threshold/status, and second election timing.

### Current voting flow

`ElectionController::submitVote()`:

1. Resolves a `GroupSetting` from location/group subtype.
2. Accepts arrays named `inspector` and `manager`.
3. Finds the first open election for the group.
4. Deletes all prior votes by the voter in that election.
5. Inserts new vote rows.

### Current finish flow

`ElectionController::finishElection()`:

1. Requires `manageSession` authorization.
2. Resolves a location-level `GroupSetting`.
3. Resets candidate `accept_status` values.
4. Counts inspector and manager votes independently.
5. Selects top N using `insperctor_count`/`manager_count`.
6. Marks selected candidates `accept_status = 1`.
7. Closes the election.
8. Publishes a group-feed `election_finished` update.

## Confirmed architectural problems

### E1 — `candidate_id` semantic mismatch

The voting controller stores selected **user IDs** in `votes.candidate_id`, then the finish flow compares those values to `candidates.user_id`. However `Candidate::votes()` treats `votes.candidate_id` as a foreign key to the candidate model. The column therefore has two incompatible meanings in current code.

Required rebuild direction: use a single canonical identity. Prefer a real candidate/election-choice record FK, or rename/normalize the schema if the constitutional model remains candidate-less and users are direct selectable persons.

### E2 — Election result and responsibility acceptance are conflated

The finish flow marks winners `accept_status = 1` immediately. This collapses two distinct states:

- the person ranked into a seat by election result;
- the person explicitly accepting the office/contract/responsibility.

Required rebuild direction: separate result/ranking from invitation and explicit acceptance/rejection lifecycle.

### E3 — Manual session-style completion conflicts with systemic elections

Election completion is currently a controller action protected by `manageSession`. The desired EarthCoop model is automatic and permanent: threshold detection, opening, scheduled close/result calculation, acceptance handling, representation activation, and future cycles must be system-driven and idempotent.

Required rebuild direction: move lifecycle transitions into canonical domain services/jobs; controller/API surfaces should request/read state, not own lifecycle rules.

### E4 — `finishElection()` loses subtype-specific settings

Vote submission resolves subtype settings (`_job`, `_experience`, `_age`, `_gender`), but `finishElection()` resolves only the base location level. Seat counts/timing therefore can diverge between vote collection and result calculation.

Required rebuild direction: one canonical ElectionRulesResolver used by every lifecycle stage.

### E5 — Current finish-state guard is ineffective

The finish method first sets every candidate `accept_status` to `null` and then checks the first candidate for non-null status. That check can no longer detect a prior state. It also indexes `candidates[0]` without a safe empty-election contract.

Required rebuild direction: explicit election state machine with guarded/idempotent transitions.

### E6 — Ballot invariants are not enforced in the controller

Current validation only checks that `inspector` and `manager` are nullable arrays. The controller itself does not establish canonical guarantees for:

- voter eligibility / active membership;
- selected-person eligibility in the same group;
- maximum ballot size per role;
- duplicate selections;
- selecting one person for incompatible seats on one ballot;
- election-open/time-window state;
- null/no-open-election handling.

Required rebuild direction: typed ballot command + canonical ballot validation service + database invariants where possible.

### E7 — Result calculation lacks tie/ranking contract

Top-N selection orders only by vote count. No deterministic tie policy, vacancy policy, decline/backfill policy, or immutable result snapshot is visible in the controller.

Required rebuild direction: persisted deterministic ranking/result snapshot and explicit tie/backfill rules.

### E8 — Group role / upper-level representation transition is not owned by the election domain

The current finish method only marks candidate status and closes the election. The constitutional lifecycle requires elected-and-accepted managers/inspectors to receive the correct group role and, where applicable, active representation in the next geographic/organizational level.

Required rebuild direction: a canonical Office/Representation assignment service with idempotent activation and revocation rules.

## Rebuild principles

1. Automatic, permanent, system-driven election lifecycle.
2. No self-declared candidacy requirement; eligible members are selectable according to EarthCoop rules.
3. Ballot and result semantics must be explicit and database-safe.
4. Ranking/election result must be separate from acceptance of responsibility.
5. Acceptance/rejection/timeout must support deterministic backfill.
6. Office assignment and upper-level representation must be explicit, reversible and audited.
7. Every lifecycle transition must be idempotent and evented.
8. Admin settings must feed one versioned/canonical rules resolver, not scattered `GroupSetting` reads.
9. Najm Hoda may observe/explain/recommend; rule changes remain approval-gated until the canonical election-rules boundary exists.
10. Full integration validation remains mandatory before any owner merge decision.

## Immediate next audit slices

- migrations/schema for `elections`, `candidates`, `votes`, `group_setting`;
- all election commands, scheduled jobs, events/listeners and notifications;
- candidate acceptance/rejection flow;
- group-role and upper-level representation side effects;
- admin election settings UI/controller;
- election UI/JS and ballot limits;
- existing tests and missing invariants.

No merge to `main` is authorized by this audit.