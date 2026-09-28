# M4 — Actor / Organization Boundary Design

**Date:** 2026-09-28  
**Baseline:** `main@c19e118d85bb04c106af0a2f91b0811f1aacef74`  
**Scope:** Stable actor/ownership boundary for API v1 and shared application services.  
**Status:** Written spec ready for user review before implementation planning.

## 1. Purpose

M4 freezes the minimum actor and ownership contract needed before Native Mobile so future Organization, Company, Shop and Marketplace work can be added without breaking the mobile API or duplicating authority rules.

M4 is not a Company implementation, Marketplace implementation, legal-entity registry, or Najm Bahar account-schema redesign. It creates a stable application boundary over concepts EarthCoop already partially supports: users, groups, group-owned projects/investments, legal-entity-style group accounts, Secretariat parties, and trusted system operations.

Target flow:

```text
Authenticated principal
        ↓
Requested/derived actor
        ↓
Actor resolution + representation authorization
        ↓
Resource policy + domain invariant
        ↓
Application command/query
```

A client may identify an actor only on operations that explicitly support multi-actor semantics. The server always resolves and authorizes that actor. Client input never creates authority.

## 2. Repository findings at baseline

### 2.1 Persistence already supports multi-owner Projects and Investments

`NajmBahar\Models\Project` stores `owner_type` / `owner_id` and uses `morphTo()`.

`NajmBahar\Models\Investment` stores `investor_type` / `investor_id` and uses `morphTo()`.

`Group` already exposes polymorphic Project and Investment relations. A canonical regression test already creates a Group-owned Project through `ProjectService`.

This foundation is retained.

### 2.2 API v1 still hard-codes user ownership

Current Project API v1:

- lists only `owner_type = User::class` + authenticated `user_id`;
- creates only through `ProjectApplicationService::createForUser()`;
- relies on `ProjectPolicy` checks that explicitly compare `owner_type` with `User::class`.

Therefore persistence is more capable than the stable mobile contract.

### 2.3 Ownership assumptions are repeated across domains

Direct user-only ownership checks also appear in Project/Investment policy and some Secretariat/Hoda/project paths.

M4 centralizes only the actor identity and owner-representation semantics required for stable boundaries. It does not authorize a broad authorization rewrite.

### 2.4 Najm Bahar already anticipates legal entities, but its account persistence is not ready to freeze

`Account.type` already includes `legal_entity`, and `AccountService::ensureLegalEntityAccountForGroup()` provisions a Group account using account-number conventions and `meta.group_id`.

However, account persistence remains partly user-centric, while the economic reference document is still being finalized and may change future Company/Organization/account rules.

M4 therefore does not normalize or migrate Najm Bahar account ownership. Actor identity becomes an anti-corruption boundary so later account persistence can change without changing the public actor contract.

### 2.5 Secretariat already distinguishes documentary parties from authenticated users

Secretariat parties may represent users, groups or external organization snapshots, and Secretariat office types already anticipate `legal_entity`.

This confirms the need to keep three concepts separate:

- authenticated principal;
- represented actor;
- resource owner/scope.

## 3. Goals

M4 must:

1. define one model-independent ActorReference contract;
2. distinguish principal from actor;
3. support user and group actors publicly, and trusted system actor internally;
4. reserve organization as a stable future actor type without inventing a fake Organization domain;
5. enforce representation authority server-side and fail closed;
6. keep Laravel model class names out of API identity;
7. make Project the first stable actor-aware API consumer;
8. provide one reusable owner-representation boundary for Project and semantically equivalent non-economic checks;
9. preserve current personal behavior by default;
10. keep M1–M3 contracts backward compatible;
11. avoid freezing economic rules that may change after the economic reference reconciliation.

## 4. Non-goals

M4 does not:

- create Company, Organization or Shop models;
- create Marketplace/catalog/order/fulfillment flows;
- define incorporation, directors, employees, commercial roles, tax identity or organization governance;
- migrate `najm_accounts` ownership columns;
- expose Group/Organization wallets through the M3 personal Najm Bahar API;
- change monetary policy, activation, membership fees, investment economics, idle tax, loans, auction rules or payment providers;
- change election eligibility, voting identity, delegation or responsibility rules;
- make every API endpoint actor-selectable;
- let administrators impersonate Groups merely because they are administrators;
- expose a public system-actor selector;
- broaden Investment authorization before the economic reference reconciliation;
- retire legacy polymorphic columns, legacy web controllers or rollback scaffolding.

## 5. Core terminology

### 5.1 Principal

The principal is the real authenticated user/session that made the request.

For Native API v1, the principal is resolved from the M1 bearer token + device session.

Principal identity is never replaced by actor identity. Audit/evidence must preserve the principal even when the action is performed for a Group.

### 5.2 Actor

The actor is the identity on whose behalf an actor-aware operation is performed.

Examples:

- personal Project: principal = user, actor = same user;
- Group Project: principal = elected/authorized Group Manager, actor = Group;
- trusted scheduler/system action: actor = EarthCoop system through a trusted server path.

### 5.3 Resource owner

The resource owner is the actor identity persisted or derived as owner of a resource.

Actor and owner are often equal during creation, but authorization remains separate: a principal may act for an owner only if representation and resource policy both allow it.

### 5.4 Scope

GovernanceArea, Location and similar topology objects are scope, not acting identities in M4.

Group is the acting identity because representation roles and responsibility live on Group membership/election state. GovernanceArea may be projected as Group context but cannot grant authority.

## 6. Stable actor types

Public actor-type strings are exactly:

- `user`
- `group`
- `organization`
- `system`

### 6.1 User actor

Backed by `App\Models\User`.

For public requests, a principal may resolve/represent only their own user actor. Another user's ID never becomes authority.

`users.is_system` identities are not public user actors.

### 6.2 Group actor

Backed by `App\Models\Group`.

M4 does not introduce a separate `governance` actor. Public, professional/sector, age/gender and other existing Group kinds share the same stable actor identity. Operation-specific domain policy may later restrict which Group kinds may perform a specific economic action without changing ActorReference.

A Group projection may include non-authoritative context such as name, governance area and dimension keys. Context never grants authority.

### 6.3 Organization actor

`organization` is a reserved stable type.

At M4 launch there is no authoritative Organization source domain. Therefore:

- public organization resolution returns `actor_not_supported`;
- organization actors do not appear in actor discovery;
- no placeholder Organization table/model is created;
- Company/Shop is not modeled as Group merely for M4.

A future Organization/Company subsystem may register a resolver/provider behind the same ActorReference shape.

### 6.4 System actor

Canonical system reference:

```json
{
  "type": "system",
  "id": "earthcoop"
}
```

Public/native requests cannot select or mint this actor. It exists only for explicit trusted server operations.

## 7. ActorReference contract

Minimum stable JSON shape:

```json
{
  "type": "group",
  "id": "123"
}
```

Rules:

- `type` is one of the four stable strings;
- `id` is always serialized as an opaque string;
- actor equality is `(type, id)`;
- clients must not infer table, numeric type or Laravel class from `id`;
- Laravel FQCNs never appear as actor identity in `/api/v1`;
- additive optional fields are allowed inside v1.

Actor discovery may return a richer projection:

```json
{
  "type": "group",
  "id": "123",
  "display_name": "...",
  "context": {
    "governance_area_id": 42
  },
  "permissions": {
    "can_represent": true
  }
}
```

These additional fields are informative only. Protected operations always reauthorize representation from current server state.

## 8. Internal application boundary

M4 introduces focused application-layer responsibilities. Exact PHP filenames are selected in the implementation plan, but these contracts are fixed.

### 8.1 ActorType

Closed enum/value set for user, group, organization and system.

### 8.2 ActorReference

Immutable value object containing actor type + opaque ID string. It contains no authorization.

### 8.3 ActorResolver

Responsibilities:

- convert supported domain models to ActorReference;
- resolve ActorReference to supported internal target;
- map legacy polymorphic model class + ID to public actor identity;
- fail closed on unsupported/unknown actor types;
- prevent persistence class names from leaking into transport.

M4 mappings:

```text
User::class  <-> user:<id>
Group::class <-> group:<id>
trusted system marker <-> system:earthcoop
organization:* -> unsupported until a real source domain exists
```

### 8.4 ActorRepresentationAuthorizationService

Responsibilities:

- determine whether a principal may represent a requested actor for the requested actor-aware operation;
- keep representation separate from resource policy;
- expose stable safe failure semantics while retaining useful internal denial evidence.

It is not a replacement for Laravel Policies.

Authorization order:

```text
principal authentication
  -> ActorReference validation/resolution
  -> representation authorization
  -> resource policy/domain permission
  -> business invariant
  -> command/query
```

## 9. Representation rules

### 9.1 User

A principal may represent `user:<id>` only when `<id>` is their own user ID.

There is no administrator exception.

### 9.2 Group

For M4 launch owner-level Project representation, all conditions must hold:

- active, non-expired Group membership exists;
- canonical/effective current role is Manager (`3`);
- principal is a real non-system user;
- the requested operation supports Group actors.

Inspector (`2`), Active (`1`), Observer (`0`), Guest (`4`) and Temporary Active (`5`) do not gain owner-level Group representation authority from those roles.

The service must reuse the canonical current membership/effective-role source, including existing expiry/temporary-role reconciliation. It must not copy stale membership logic into a new subsystem.

Global `is_admin` / `super-admin` power is not representation authority. Administrative review remains separate from acting in the Group's name.

### 9.3 Organization

Denied at M4 launch because no authoritative organization representation domain exists.

### 9.4 System

Denied on all public/native requests. Only trusted server code may construct/use system authority through an explicit trusted path.

## 10. Actor discovery API

M4 adds exactly:

```text
GET /api/v1/actors
```

It is authenticated by the existing M1 native/session boundary.

It returns:

- the principal's own user actor;
- Group actors the principal may currently represent under M4 rules.

It does not return:

- system actor;
- organization actors;
- Groups where the principal lacks Manager representation authority;
- unrelated actors by guessed ID.

Discovery is not a capability token. Every actor-aware mutation/read rechecks current authorization. M4 does not persist a global selected actor in session/device state.

## 11. No global act-as header

M4 does not add `X-Actor-ID`, `X-Act-As` or equivalent global actor state.

Reasons:

- voting and other constitutional actions remain personal;
- M3 personal wallet operations remain personal;
- global actor context creates privilege-confusion risk;
- future Company/Shop operations may have different representation rules;
- operation-specific actor input is explicit and auditable.

Only endpoints that explicitly support multi-actor semantics accept actor input.

## 12. Project: first stable actor-aware consumer

Project is the first consumer because persistence already supports User/Group ownership while API v1 is currently user-only.

### 12.1 Response

Every Project v1 serialization adds:

```json
{
  "owner_actor": {
    "type": "user",
    "id": "123"
  }
}
```

Existing response fields remain. Raw `owner_type` class names are not exposed.

### 12.2 Create

Existing create requests remain valid.

If `owner_actor` is omitted, owner actor is the authenticated principal's user actor.

If `owner_actor` is supplied:

1. validate ActorReference;
2. resolve it;
3. authorize principal representation;
4. invoke generalized Project application creation;
5. persist through existing polymorphic ownership.

Public M4 Project owners supported at launch:

- authenticated user's own actor;
- authorized Group actor.

`organization` and `system` Project creation through the public API are rejected.

### 12.3 Application service

`createForUser()` must no longer be the only stable application creation boundary.

The implementation introduces an actor-aware path conceptually equivalent to:

```text
createForActor(principal, actorRef, data)
```

`createForUser()` may remain as a compatibility wrapper that converts the User to ActorReference and delegates to the same semantic path.

Raw client IDs must never reach Project creation as trusted ownership authority.

### 12.4 List

Current no-filter behavior is preserved: `GET /api/v1/projects` returns the principal's personal Projects.

Actor-aware listing uses exactly:

```text
GET /api/v1/projects?filter[owner_actor]=group:123
```

`owner_actor` filter value grammar is exactly `<type>:<id>` where `<type>` is a stable actor type and `<id>` is the opaque actor ID without `:` at M4 launch.

The server parses the reference, reauthorizes representation, then maps it to legacy polymorphic storage.

`user:<other-user>` cannot be used to enumerate another user's private Projects.

### 12.5 View/update/submit/delete policy

Public approved Project visibility remains unchanged.

Owner-level update/submit/delete authorization asks the shared owner-representation boundary rather than assuming owner must be `User::class`.

Existing Project lifecycle constraints remain unchanged. M4 changes valid owner representation, not Project lifecycle/economics.

## 13. Shared owner-representation predicate

M4 provides one reusable predicate/service for:

```text
Does principal P currently have owner-level authority for ActorReference A?
```

Required launch consumers:

- Project policy/application/query boundary;
- Secretariat checks that are explicitly defined as Project-owner authority.

The predicate must not replace unrelated domain policies.

M4 does not use this predicate to broaden Investment mutation authority. Investment behavior remains under existing rules until the economic reference reconciliation.

## 14. Investment boundary

Investment is deliberately conservative in M4 because the economic reference document may change investment and legal-entity rules.

M4 may use ActorResolver to understand existing polymorphic identity for serialization/internal compatibility, but it does not:

- add a public investment API;
- grant new Group-investor permissions;
- change Investment payment/cancellation rules;
- change account-number/account-owner conventions;
- replace InvestmentPolicy with actor-aware mutation authority.

Existing Investment tests become regressions ensuring M4 does not accidentally alter economics.

A future economic reconciliation may add actor-aware investment commands using the same ActorReference without changing the actor contract.

## 15. Secretariat boundary

Secretariat keeps its richer party/snapshot model. `SecretariatParty` is not replaced by ActorReference.

M4 may use the shared Project-owner predicate only where Secretariat permission is semantically defined as authority of the owner of that Project.

External organization snapshots remain documentary snapshots; they do not become live `organization` actors. `legal_entity` office type alone never creates organization authority.

Existing confidentiality, ACL and correspondence rules remain authoritative.

## 16. Group/Governance boundary

Group is the M4 actor. GovernanceArea remains scope.

Rationale:

- representation roles live on Group membership;
- election/responsibility state is connected to Group;
- Group already owns Projects/Investments polymorphically;
- Group already maps to a legal-entity-style Najm Bahar account;
- a parallel GovernanceArea actor would duplicate authority.

Actor discovery may expose governance context, but `governance_area_id` is never authority.

## 17. Najm Bahar boundary

M3 personal endpoints remain principal/user-scoped:

- personal account/balance;
- personal transaction history;
- transfer source authority;
- participation-point activation;
- membership fee;
- scheduled-operation evidence as already contracted.

M4 does not expose Group or Organization financial accounts through these routes.

Future economic work may add separate actor-aware financial APIs after the economic reference document defines account ownership and representation rules. ActorReference is designed so that extension is additive.

## 18. Najm Hoda boundary

M4 preserves M2's authority constitution:

```text
user intent != AI proposal != server authority
```

If Hoda later proposes a Group-actor action:

- principal remains recorded;
- proposed actor is untrusted input until resolved;
- representation authorization runs server-side;
- capability/resource authorization still runs;
- required consent still runs;
- model output never mints actor authority.

Hoda should consume ActorReference/ActorResolver instead of learning Laravel polymorphic class names.

No new Hoda action is required to close M4.

## 19. Security requirements

### 19.1 Actor input is never authority

Client ActorReference only identifies a requested actor.

### 19.2 No class-name transport

Laravel FQCNs are persistence details, never public actor types.

### 19.3 No admin impersonation shortcut

Admin review power does not equal Group representation.

### 19.4 No public system actor

`system:earthcoop` cannot be selected through public input.

### 19.5 Organization fails closed

Until a real Organization domain exists, organization actor resolution is unsupported.

### 19.6 Authorization is live

Actor discovery is not cached authority. If Manager status expires/changes, the next protected actor-aware request must reflect current server state without token rotation.

### 19.7 Principal remains auditable

Actor-aware mutation evidence must distinguish at least:

- authenticated principal;
- represented actor;
- affected resource;
- request/idempotency identity where applicable.

A universal new audit table is not required if existing domain event/audit mechanisms can carry the evidence.

### 19.8 Idempotency is actor-sensitive

Existing M1 transport idempotency remains authoritative. Actor identity is part of normalized actor-aware mutation semantics/hash, so a replay key cannot be reused to switch actors.

## 20. Backward compatibility

M4 is additive inside `/api/v1`.

Existing personal Project clients remain valid because:

- `owner_actor` is optional on create;
- omitted actor defaults to current user;
- existing response fields remain;
- lifecycle, target scope and economic Project fields remain unchanged;
- idempotency semantics remain unchanged.

New clients should use ActorReference and must not rely on persistence owner classes.

## 21. Persistence strategy

M4 creates no Actor table and requires no ownership migration.

Existing persistence remains:

```text
Project.owner_type / owner_id
Investment.investor_type / investor_id
Najm account compatibility conventions
Secretariat party snapshots
```

ActorResolver is the anti-corruption layer between stable public actor identity and current storage.

A future Organization provider may map its real model to:

```text
organization:<opaque-id>
```

without changing clients.

## 22. Economic-reference compatibility rule

The economic reference document is being completed in parallel and may change Najm Bahar, Group, Company, Shop, Marketplace or investment rules.

M4 freezes identity/authority boundaries only, not economic policy.

Future economic changes should normally be one of:

1. **policy/internal change** — update domain/application logic; ActorReference unchanged;
2. **additive capability** — add actor-aware endpoint/field; existing actor contract unchanged;
3. **new actor source domain** — register Organization/Company provider behind ActorResolver; ActorReference unchanged.

Any proposal that changes the meaning of existing actor types requires explicit architecture review.

Before Native PoC, Architecture Gate includes a reconciliation between the final economic reference document and M3/M4. Expected changes are domain/additive adjustments rather than rebuilding the mobile actor foundation.

## 23. Error semantics

Actor errors use the existing API v1 envelope and these stable codes:

- `actor_reference_invalid`
- `actor_not_supported`
- `actor_not_found`
- `actor_representation_forbidden`
- `actor_operation_not_supported`

Where resource-enumeration policy requires concealment, inaccessible/not-found resources may still collapse to `404` consistently with M0.

## 24. Test strategy

Implementation is TDD-first with targeted M4 validation, then one repository Full Validation on the fixed final candidate.

### 24.1 ActorReference/Resolver

Prove:

- User maps to `user:<id>`;
- Group maps to `group:<id>`;
- trusted system maps to `system:earthcoop` internally;
- Laravel class names never appear in public ActorReference;
- organization/unknown actor resolution fails closed.

### 24.2 Representation

Prove:

- principal can represent self;
- cannot represent another user;
- active Manager role 3 can represent Group;
- Inspector 2, Active 1, Observer 0, Guest 4 and Temporary Active 5 cannot;
- inactive/expired manager cannot;
- admin without qualifying Group representation cannot impersonate Group;
- public client cannot select system actor.

### 24.3 Actor discovery

Prove:

- self actor returned;
- authorized managed Groups returned;
- non-representable Groups excluded;
- system/organization excluded;
- unrelated authority cannot be enumerated.

### 24.4 Project

Prove:

- current create request without `owner_actor` still creates personal Project;
- response includes `owner_actor=user:<self>`;
- Manager can create Group-owned Project;
- non-manager cannot;
- another-user actor cannot be used;
- organization/system cannot be used publicly;
- default list remains personal;
- `filter[owner_actor]=group:<id>` works only with current representation;
- public view semantics remain;
- update/submit/delete lifecycle constraints remain;
- Group-owned update/submit requires current valid representation;
- target Location/Governance scope remains independent of ownership;
- idempotent replay cannot change actor.

### 24.5 Regressions

Targeted gate includes:

- M1 Project/core API journey;
- Group membership/policy regressions;
- election regression proving Manager still votes personally;
- M2 Hoda authority regressions where Project context is affected;
- M3 personal Najm Bahar regressions unchanged;
- existing Investment economics/authorization regressions unchanged;
- Secretariat tests affected only by Project-owner predicate integration.

Full Validation runs once on the exact final M4 candidate before merge.

## 25. M4-A acceptance checkpoint

M4 closes when:

> Stable API/application ownership no longer assumes every actor-aware resource is permanently user-owned, while constitutionally personal operations remain personal and existing user clients remain backward compatible.

Required acceptance:

1. ActorReference is stable and class-name independent.
2. Principal and actor are separate in code/tests.
3. User + Group public actor concepts and trusted internal System actor exist; Organization is reserved/fail-closed.
4. Group representation is live, server-authorized and Manager-only for M4 owner-level Project actions.
5. `GET /api/v1/actors` safely returns representable actors.
6. Project API supports optional authorized Group ownership without breaking personal behavior.
7. Project owner policy no longer hard-codes User as the only representable owner.
8. Secretariat Project-owner checks use the shared predicate where semantically equivalent.
9. Investment economic/authorization behavior is unchanged by M4.
10. M3 personal Najm Bahar behavior is unchanged.
11. No Company/Shop/Marketplace or financial-schema migration is introduced.
12. Targeted M4 gate is green on fixed SHA.
13. Full Validation is green on the same final candidate before merge.

## 26. Deferred work

Deferred until later domain/economic work:

- real Organization/Company source domain;
- organization representatives/directors/employees;
- Company formation/KYC;
- Shop/Marketplace flows;
- Group/Organization mobile wallet APIs;
- Najm Bahar account ownership normalization;
- actor-aware Investment mutation/public API;
- organization/legal-entity Secretariat authority backed by a real source domain;
- operator proxy/impersonation workflow;
- legacy owner-column retirement.

These future systems build on ActorReference rather than being prerequisites for M4.

## 27. Relationship to M5 and Native PoC

M5 may use ActorReference in typed notification/deep-link payloads where a destination belongs to an actor, but device identity remains separate from actor identity.

Native clients must not need Laravel polymorphic class names, account metadata conventions, or future Company persistence.

Sequence:

```text
M3 Najm Bahar mobile contract — complete
        ↓
M4 Actor / Organization Boundary
        ↓
M5 Device / Push / Media / Realtime / Offline Foundation
        ↓
Economic reference reconciliation + Architecture Gate
        ↓
Native PoC
```

## 28. Implementation planning boundary

After this written spec is reviewed and approved, the implementation plan must be dependency-ordered and TDD-first:

1. ActorType/ActorReference/Resolver contracts;
2. representation authorization;
3. actor discovery API;
4. Project serialization + actor-aware create/list;
5. Project policy/owner predicate integration;
6. narrow Secretariat integration;
7. targeted cross-M1/M2/M3/economy regressions;
8. fixed-SHA M4 acceptance;
9. one Full Validation before merge.

Implementation occurs on a feature branch derived from the then-current approved baseline and never directly on `main`.
