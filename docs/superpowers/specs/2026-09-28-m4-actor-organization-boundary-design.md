# M4 — Actor / Organization Boundary Design

**Date:** 2026-09-28  
**Baseline:** `main@c19e118d85bb04c106af0a2f91b0811f1aacef74`  
**Scope:** Stable actor/ownership boundary for API v1 and shared application services.  
**Status:** Design approved in conversation; pending written-spec review before implementation planning.

## 1. Purpose

M4 freezes the minimum actor and ownership contract needed before Native Mobile so future Organization, Company, Shop and Marketplace work can be added without breaking the mobile API or duplicating authority rules.

M4 is **not** a Company implementation, Marketplace implementation, legal-entity registry, or Najm Bahar account-schema redesign. It creates a stable application boundary over concepts EarthCoop already partially supports: users, groups, group-owned projects/investments, legal-entity-style group accounts, Secretariat parties, and trusted system operations.

The target rule is:

```text
Authenticated principal
        │
        ▼
Requested/derived actor
        │
        ▼
Actor resolution + representation authorization
        │
        ▼
Resource policy + domain invariant
        │
        ▼
Application command/query
```

The client may identify the actor it wants to act for only on operations that support multi-actor ownership. The server always resolves and authorizes that actor. Client input never creates authority.

## 2. Why M4 is needed now

The repository already contains polymorphic ownership, but transport and policies are inconsistent.

### 2.1 Project and investment models are already multi-owner

`NajmBahar\Models\Project` stores `owner_type` / `owner_id` and uses `morphTo()`.

`NajmBahar\Models\Investment` stores `investor_type` / `investor_id` and uses `morphTo()`.

`Group` exposes polymorphic project and investment relations.

This is a strong foundation and must be adapted rather than replaced.

### 2.2 API v1 still hard-codes user ownership

The current Project API:

- lists only `owner_type = User::class` + authenticated `user_id`;
- creates only through `ProjectApplicationService::createForUser()`;
- relies on `ProjectPolicy` rules that explicitly compare `owner_type` to `User::class`.

This means the persistence model is more capable than the stable mobile contract.

### 2.3 Authorization repeats user-only ownership assumptions

Direct `User::class` ownership checks also exist in places such as:

- `ProjectPolicy`;
- `InvestmentPolicy`;
- Secretariat project-office ownership checks;
- some Najm Hoda project-context projections;
- legacy/web Najm Bahar project/account paths.

M4 must centralize only the ownership/representation predicate required for stable boundaries. It must not perform unrelated refactors.

### 2.4 Najm Bahar already has a legal-entity concept, but its persistence is not ready to be generalized

`Account.type` already recognizes `legal_entity`, and `AccountService::ensureLegalEntityAccountForGroup()` provisions group economic accounts using `meta.group_id`. Existing financial services recognize `user`, `legal_entity`, and `system` account types.

However, the main account schema still has a user-centric `user_id`, while group legal-entity identity is carried through account number conventions and metadata. The economic reference document is still being reconciled and may change future Company/Organization/account rules.

Therefore M4 must **not** normalize or migrate Najm Bahar account ownership. It provides an actor adapter boundary so that a later economic reconciliation can change account persistence without breaking API actor identity.

### 2.5 Secretariat already distinguishes parties from authenticated users

Secretariat parties can represent users, groups, external organizations and snapshots. Secretariat office types already anticipate `legal_entity` and other future scopes.

This reinforces the architectural need to distinguish:

- who authenticated;
- whom an action represents;
- who owns or scopes a resource.

## 3. Goals

M4 must:

1. define one public, model-independent actor reference contract;
2. distinguish authenticated principal from represented actor;
3. support user, group and trusted-system actors now;
4. reserve an organization actor type without inventing a fake organization domain;
5. enforce representation authority server-side and fail closed;
6. prevent Laravel model class names from becoming public API identity;
7. make Project the first stable API consumer of actor-aware ownership;
8. provide reusable ownership predicates for Project/Investment/Secretariat integration where appropriate;
9. preserve existing user-only behavior by default;
10. keep M1–M3 contracts backward compatible;
11. remain compatible with future economic-document changes by avoiding premature financial/schema commitments.

## 4. Explicit non-goals

M4 does **not**:

- create a Company model or registration workflow;
- create a Shop model or Marketplace subsystem;
- define legal incorporation, beneficial ownership, licenses, tax identity, directors, employees or commercial roles;
- define organization membership or organization governance;
- migrate `najm_accounts` ownership columns;
- expose group/legal-entity Najm Bahar accounts through the M3 personal wallet API;
- change monetary policy, activation, fees, investment economics, idle tax, loans, auction semantics or payment providers;
- change election eligibility, voting identity, delegation or responsibility rules;
- make every API endpoint actor-selectable;
- allow global administrators to impersonate groups merely because they are administrators;
- expose a public system-actor selector;
- retire legacy polymorphic columns, legacy web controllers or rollback scaffolding.

## 5. Core terminology

### 5.1 Principal

The **principal** is the real authenticated user/session that made the request.

For Native API v1 this is the user resolved from the valid bearer token and device session established in M1.

Principal identity is never replaced by actor identity. Audit records must preserve the principal even when the principal acts for a group.

### 5.2 Actor

The **actor** is the identity on whose behalf a multi-actor operation is performed.

Examples:

- Saeed creates a personal project: principal = Saeed, actor = Saeed/user.
- An elected group manager creates a group project: principal = that manager, actor = the group.
- A trusted scheduler performs a system operation: actor = EarthCoop system; there is no client-granted system authority.

### 5.3 Resource owner

The **resource owner** is the actor identity persisted or derived as owner of a resource.

Actor and resource owner are often equal during creation, but they are separate concepts. A principal may view/manage a resource owned by another actor only if resource authorization permits it.

### 5.4 Scope

GovernanceArea, Location and similar topology objects are **scope**, not principals and not independently acting legal identities in M4.

A group may carry governance scope context, but the group is the actor because authority and roles are attached to Group membership/election state.

## 6. Stable actor types

M4 freezes these public type identifiers:

```text
user
 group
 organization
 system
```

Whitespace above is illustrative only; actual enum values are exactly:

- `user`
- `group`
- `organization`
- `system`

### 6.1 User actor

Backed by `App\Models\User`.

Public requests may resolve only the authenticated principal's own user actor. A client cannot act as another user by supplying another ID.

System identities stored in `users` are not converted into public user actors.

### 6.2 Group actor

Backed by `App\Models\Group`.

A Group may represent a public/governance group, professional/sector group, age/gender group, or other existing group domain. M4 does not create a separate `governance` actor type.

Where useful, actor projections may include non-authoritative context such as:

- group name;
- governance area ID;
- dimension keys;
- the principal's representation role.

That context never grants authority by itself.

### 6.3 Organization actor

`organization` is a **reserved stable contract type**.

At M4 launch there is no authoritative Organization source domain. Therefore:

- public organization actor resolution fails closed;
- organization actors are not returned from actor discovery;
- no Organization row/table is created merely to satisfy M4;
- no Company/Shop is modeled as a Group by convention.

A later Organization/Company subsystem may register a provider behind the same actor contract without changing API actor shape.

### 6.4 System actor

The system actor represents trusted EarthCoop application/system authority.

Canonical internal/public reference identity is:

```json
{
  "type": "system",
  "id": "earthcoop"
}
```

Public clients cannot select or mint this actor. It is resolved only through trusted server paths explicitly marked as system operations.

System actor support does not turn `users.is_system` rows into public authenticated actors.

## 7. Public ActorReference contract

The minimum stable actor reference is:

```json
{
  "type": "group",
  "id": "123"
}
```

Rules:

- `type` is one of the stable actor-type strings;
- `id` is always serialized as an opaque string;
- clients must not infer database table, numeric type or Laravel class from `id`;
- Laravel FQCNs such as `App\\Models\\User` and `App\\Models\\Group` never appear as actor identity in `/api/v1`;
- unknown fields may be added additively inside v1;
- actor equality is `(type, id)`, not display name.

A richer projection may be returned by discovery endpoints:

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

`display_name`, `context`, and `permissions` are informative projections. Authorization is re-evaluated on every protected operation.

## 8. Internal architecture

M4 introduces an application-layer actor package/boundary. Exact PHP filenames may be refined during implementation planning, but responsibilities are fixed.

### 8.1 ActorType

A closed enum/value set for:

- user;
- group;
- organization;
- system.

### 8.2 ActorReference

An immutable value object containing:

- actor type;
- opaque ID string.

It contains no authorization by itself.

### 8.3 ActorResolver

Responsibilities:

- convert supported existing domain models into `ActorReference`;
- resolve an `ActorReference` to an internal supported actor target;
- map legacy polymorphic model class + ID to public actor type + ID;
- reject unsupported/unknown/disabled actor types fail-closed;
- hide persistence class names from transport.

M4 model mappings:

```text
User::class  <-> user:<id>
Group::class <-> group:<id>
trusted system marker <-> system:earthcoop
organization:* -> unresolved until source domain exists
```

### 8.4 ActorRepresentationAuthorizationService

Responsibilities:

- answer whether a principal may represent a requested actor for a supported operation;
- keep representation separate from resource policy;
- preserve explicit denial reasons internally for audit/tests while returning stable safe API errors.

It is not a replacement for Laravel Policies. It is an earlier boundary.

Authorization flow:

```text
principal authenticated
  -> requested actor parses
  -> actor exists/resolves
  -> principal may represent actor
  -> resource policy/domain rule
  -> command
```

## 9. Representation authorization rules

### 9.1 User

A principal may represent `user:<id>` only when `<id>` is that principal's own ID.

No administrator exception.

### 9.2 Group

At M4 launch, a principal may represent a Group for owner-level economic/project actions only when all of the following are true:

- an active non-expired group membership exists;
- the effective group role is Manager (`3`);
- the principal is a real non-system user;
- the domain operation supports group actors.

Inspector (`2`), Active (`1`), Observer (`0`), Guest (`4`) and Temporary Active (`5`) do not gain owner-level group representation authority merely from those roles.

Representation must use the canonical group-membership/effective-role source. It must not implement a second stale copy of role-expiry logic.

Global `is_admin` / `super-admin` authority is **not** representation authority. Administrative review powers remain separate. If an administrator must later perform an explicit audited proxy action, that requires a separate operator/proxy design, not silent group impersonation.

### 9.3 Organization

Denied at M4 launch because no authoritative source-domain membership/representation policy exists.

### 9.4 System

Denied for all public/native requests. Only trusted internal server code may construct/resolve system authority through an explicit trusted path.

## 10. Actor discovery API

M4 adds an authenticated read-only API such as:

```text
GET /api/v1/actors
```

The endpoint returns actor identities the current principal may currently represent for launch-scope actor-aware actions.

Minimum result:

- the principal's own user actor;
- manager-authorized group actors.

It must not return:

- system actor;
- unresolved organization actors;
- groups where the user is only inspector/active/observer/guest/temporary-active;
- arbitrary actors discoverable by guessed IDs.

The endpoint is a convenience/discovery projection, not a capability token. Every later mutation reauthorizes representation.

No actor selection is persisted as global session state in M4.

## 11. No global actor header

M4 deliberately does **not** introduce a global `X-Actor-ID`, `X-Act-As`, or similar header applied to all API requests.

Reasons:

1. many EarthCoop actions are constitutionally personal, including voting and personal wallet operations;
2. a global actor header would make accidental privilege propagation more likely;
3. it would force unrelated endpoints to reason about actors unnecessarily;
4. operation-specific actor input is clearer and safer;
5. future Company/Shop actions may require domain-specific representation constraints.

Actor input is accepted only by endpoints that explicitly support multi-actor semantics.

## 12. Project as the first stable actor-aware API consumer

Project is the correct first consumer because its persistence already supports User and Group polymorphic owners while API v1 currently supports only User ownership.

### 12.1 Project serialization

Project responses add an additive stable field:

```json
{
  "owner_actor": {
    "type": "user",
    "id": "123"
  }
}
```

Existing fields are not removed or renamed.

The response must not expose `owner_type` Laravel class names.

### 12.2 Project creation

Existing request bodies remain valid.

If `owner_actor` is omitted:

```text
owner_actor = authenticated principal's user actor
```

This preserves the current M1 mobile journey without client changes.

If `owner_actor` is supplied:

1. validate ActorReference shape;
2. resolve actor;
3. authorize principal representation;
4. call a generalized Project application command/service;
5. persist through the existing polymorphic owner relation.

For M4 launch, supported public project owners are:

- authenticated user actor;
- authorized group actor.

Organization and system project creation through the public API are rejected.

### 12.3 Project application service

`createForUser()` must not remain the only stable application boundary.

Implementation should introduce a generalized actor-aware creation path while preserving `createForUser()` as a compatibility wrapper if existing web/tests benefit from it.

Conceptually:

```text
createForActor(principal, actorRef, data)
createForUser(user, data) -> compatibility wrapper to user actor
```

The application service must not trust raw actor IDs without resolver/representation authorization.

### 12.4 Project listing

Current behavior with no actor filter remains backward compatible: return the authenticated user's personal projects.

M4 adds an explicit whitelisted actor filter for actor-aware listing. The implementation plan should choose one canonical syntax consistent with M0 filtering conventions; recommended semantic shape:

```text
filter[owner_actor]=group:123
```

The server parses the reference, reauthorizes representation, then queries the mapped legacy polymorphic owner fields.

A client cannot list another user's private projects by supplying `user:<other-id>`.

### 12.5 Project view/update/submit

Resource authorization must become actor-aware without weakening current public-project visibility.

Owner-level update/submit/delete permissions should ask a centralized owner-representation predicate rather than hard-coding `User::class`.

Status constraints remain unchanged:

- edit only where existing lifecycle permits;
- delete only where existing lifecycle permits;
- submit only where existing lifecycle permits.

M4 changes **who can validly represent an owner**, not project lifecycle or economics.

## 13. Shared ownership predicate

M4 needs one reusable service/predicate for questions such as:

```text
Does principal X have owner-level authority for resource owner actor Y?
```

This should be used where direct user-only ownership assumptions block existing polymorphic semantics.

Initial integration targets:

- `ProjectPolicy`;
- owner-side checks in `InvestmentPolicy`;
- Secretariat project-office ownership check if it is semantically the same owner predicate;
- Project API queries/application service.

The implementation must avoid a broad replacement of all authorization code. Domain-specific permissions remain domain-specific.

## 14. Investment boundary

Investment already has polymorphic investor identity, but broad investment/mobile expansion is not an M4 goal.

M4 should:

- make shared owner/investor identity conversion use ActorReference where needed;
- remove only user-only authorization assumptions that conflict with already-supported Group investors/owners;
- keep investment economics and payment flows unchanged;
- not add a new public investment API solely for M4.

If existing investment services distinguish User vs Group for account-number lookup, they remain compatibility adapters until the economic reconciliation decides the future account model.

## 15. Secretariat boundary

Secretariat must retain its richer party/snapshot model. M4 does not replace `SecretariatParty` with ActorReference.

Instead:

- when a Secretariat permission is truly based on Project owner authority, it may consume the shared ownership predicate;
- external organization snapshots remain snapshots and do not become live `organization` actors;
- `legal_entity` office types do not create organization authority without a source domain;
- current Secretariat confidentiality/ACL rules remain authoritative.

This prevents M4 from collapsing documentary-party identity into application authority.

## 16. Group/Governance boundary

Group is the M4 actor. GovernanceArea remains scope.

Reasons:

- representation roles live in Group membership;
- elections and Manager/Inspector responsibility are connected to Group;
- Group already owns Projects/Investments polymorphically;
- Group already maps to a legal-entity-style Najm Bahar account;
- introducing a separate GovernanceArea actor would duplicate authority resolution.

Actor discovery may include governance context, but a `governance_area_id` cannot be presented by a client as proof of authority.

## 17. Najm Bahar boundary

M3 personal wallet endpoints remain principal/user scoped.

M4 must **not** change these launch contracts merely because Group legal-entity accounts exist:

- `/api/v1/najm-bahar/account` remains the authenticated user's account;
- personal transaction history remains owner scoped;
- transfer source resolution remains server-owned;
- activation remains personal/policy-backed;
- membership fee remains personal under the existing M3 contract.

Group/Organization economic accounts may receive a future separate actor-aware API after the economic reference document defines their rules.

The actor abstraction must make that future extension possible without changing the actor identity shape.

## 18. Najm Hoda boundary

Najm Hoda already separates user intent, AI proposal and server authority. M4 must preserve that constitution.

If Hoda later proposes an action for a group actor:

- the authenticated principal remains recorded;
- proposed actor identity is untrusted until resolved;
- representation authorization runs server-side;
- capability/resource authorization still runs;
- consent requirements still apply;
- model output cannot mint actor authority.

M4 should expose reusable ActorReference/resolver services to Hoda rather than teaching Hoda Laravel class-name ownership conventions.

No new Hoda action is required merely to close M4.

## 19. Security requirements

M4 must satisfy all of the following.

### 19.1 No actor spoofing

Client-supplied actor references are identifiers, never authority.

### 19.2 No class-name transport

Laravel FQCNs are persistence details and must not become public actor types.

### 19.3 No administrator impersonation shortcut

Admin review authority does not equal group representation authority.

### 19.4 No system actor from client input

Public requests cannot select `system:earthcoop` to bypass policy.

### 19.5 Organization fails closed

Until a real Organization source domain and representation policy exist, organization actor resolution is denied.

### 19.6 Actor discovery is not authorization caching

Every mutation and protected actor-scoped read rechecks current representation authority. A user who stops being Manager loses group representation without waiting for a token/session refresh.

### 19.7 Principal auditability

Where an actor-aware mutation is persisted/audited, enough evidence must remain to distinguish:

- authenticated principal;
- represented actor;
- affected resource;
- request/idempotency identity where applicable.

M4 does not require a universal new audit table if existing domain audit/event mechanisms can carry this evidence.

### 19.8 Idempotency remains actor-sensitive

Existing M1 idempotency stays authoritative. An idempotency replay must not be reusable to change actor identity. Actor identity is part of the normalized request semantics/hash for actor-aware mutations.

## 20. Backward compatibility

M4 is additive inside `/api/v1`.

Existing personal Project clients continue to work because:

- `owner_actor` request field is optional;
- omitted owner defaults to the authenticated user;
- no existing required response field is removed;
- current lifecycle, target scope and project economic fields remain unchanged;
- existing idempotency behavior remains unchanged.

New clients should use actor references rather than relying on persistence owner classes.

## 21. Persistence strategy

M4 requires **no actor table and no mandatory ownership migration**.

Existing persistence remains:

```text
Project.owner_type / owner_id
Investment.investor_type / investor_id
Najm account compatibility conventions
Secretariat party snapshots
```

ActorResolver is the anti-corruption layer between stable public actor identity and current persistence identity.

A future Organization subsystem may later add a real organization table/model/provider. At that point the resolver can add:

```text
OrganizationModel::class <-> organization:<opaque-id>
```

without changing client actor shape.

## 22. Economic-document compatibility rule

The economic reference document is being completed in parallel and may change Najm Bahar, Group, Company, Shop, Marketplace or investment rules.

M4 therefore freezes only **identity and authority boundaries**, not economic policy.

Future economic changes should fall into one of three classes:

1. **Policy/internal change** — update domain/application service; actor API unchanged.
2. **Additive capability** — add a new actor-aware endpoint/field; existing actor API unchanged.
3. **New source actor domain** — register Organization/Company provider behind ActorResolver; ActorReference shape unchanged.

Any future proposal that would require changing the meaning of existing actor types must trigger explicit architecture review rather than silent reuse.

Before Native PoC, the planned architecture gate must include a reconciliation pass between the final economic reference document and M3/M4. The expected outcome is additive/domain adjustment, not rebuilding the actor/mobile foundation.

## 23. Error behavior

Actor failures use stable API error semantics through the existing v1 envelope.

Recommended stable error codes for implementation planning:

- `actor_reference_invalid` — malformed type/id;
- `actor_not_supported` — known type not currently enabled, such as organization at M4 launch;
- `actor_not_found` — safe to reveal only where enumeration policy permits;
- `actor_representation_forbidden` — principal cannot represent actor;
- `actor_operation_not_supported` — valid actor but operation is intentionally personal/user-only.

Sensitive resources may collapse not-found/forbidden into `404` where existing enumeration policy requires concealment.

## 24. Testing strategy

Implementation must be test-first and use targeted M4 gates before one final repository Full Validation.

### 24.1 ActorReference/resolver unit/contract tests

Prove:

- User model maps to `user:<id>`;
- Group model maps to `group:<id>`;
- system uses `system:earthcoop` internally;
- Laravel class names never appear in serialized references;
- unknown/organization-unbacked actor resolution fails closed.

### 24.2 Representation authorization tests

Prove:

- principal may represent self;
- principal cannot represent another user;
- active Manager role 3 may represent Group;
- Inspector 2 cannot;
- Active 1 cannot;
- Observer 0 cannot;
- Guest 4 cannot;
- Temporary Active 5 cannot;
- expired/inactive manager membership cannot;
- global admin without qualifying group representation cannot silently impersonate Group;
- public client cannot select system actor.

### 24.3 Actor discovery tests

Prove:

- self actor is returned;
- eligible managed groups are returned;
- non-representable groups are excluded;
- system/organization are excluded;
- discovery cannot enumerate unrelated group authority.

### 24.4 Project compatibility tests

Prove:

- legacy/current personal create request without `owner_actor` still creates User-owned project;
- response includes `owner_actor=user:<self>`;
- Manager can create a Group-owned project with `owner_actor=group:<id>`;
- non-manager cannot create Group-owned project;
- another user's actor cannot be used;
- organization/system cannot be used through public Project creation;
- personal list default remains personal;
- actor-filtered group list requires current representation authority;
- view/update/submit lifecycle constraints remain unchanged;
- group-owned update/submit succeeds only through valid representation;
- target Location/Governance scope remains independent of actor ownership;
- idempotent replay cannot switch actor identity.

### 24.5 Regression tests

Targeted M4 workflow should include at minimum:

- M1 API v1 core journey Project tests;
- Group policy/membership regression coverage;
- election regression showing Manager still votes personally;
- M2 Najm Hoda authority regressions affected by owner context;
- M3 Najm Bahar regressions proving personal wallet/account contracts remain unchanged;
- Project/Investment/Secretariat tests touched by shared ownership predicate.

Full Validation runs once on the fixed final M4 candidate before merge.

## 25. M4 checkpoint

M4 closes only when **M4-A Ownership Compatibility** is proven:

> Stable API/application ownership no longer assumes every actor-aware resource is permanently user-owned, while constitutionally personal operations remain personal and existing user clients remain backward compatible.

Concrete acceptance criteria:

1. ActorReference contract is stable and class-name independent.
2. Principal and actor are separate in code and tests.
3. User, Group and internal System actor concepts exist; Organization is reserved/fail-closed.
4. Group representation is server-authorized and manager-only at launch.
5. `/api/v1/actors` safely discovers representable actors.
6. Project API supports optional authorized group ownership without breaking personal behavior.
7. Project ownership policies no longer hard-code User ownership as the only owner authority.
8. Relevant Investment/Secretariat owner predicates use the shared boundary where semantically equivalent.
9. Personal M3 Najm Bahar API behavior is unchanged.
10. No Company/Shop/Marketplace or financial-schema migration is introduced.
11. Targeted M4 regression gate is green on a fixed SHA.
12. Repository Full Validation is green on that exact candidate before merge.

## 26. Deferred work after M4

Explicitly deferred:

- real Organization/Company source domain;
- organization representatives/directors/employees;
- Company formation/registration/KYC;
- Shop/Marketplace catalog/order/fulfillment flows;
- organization/group mobile wallet APIs;
- Najm Bahar account ownership normalization;
- broad investment/mobile investment API;
- legal-entity Secretariat authority beyond a real source domain;
- operator proxy/impersonation workflow;
- legacy owner column retirement.

These are future domains built **on** the M4 actor contract, not prerequisites for M4.

## 27. Relationship to M5 and Native PoC

M4 feeds M5 and the Native architecture gate.

M5 may use ActorReference in typed notification/deep-link payloads where a destination/resource belongs to an actor, but device identity remains separate from actor identity.

The Native client must not need to understand Laravel polymorphic class names, group-account metadata or future Company persistence. It should understand only stable actor references and capability/resource responses.

The planned sequence remains:

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

After this written spec is reviewed and approved, the implementation plan must be dependency-ordered and TDD-first. It should begin with ActorReference/resolver/representation tests, then actor discovery, then Project adaptation, then narrow policy integrations, then regression/acceptance gates.

Implementation must happen on a feature branch derived from the then-current approved baseline; it must not modify `main` directly.
