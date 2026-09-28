# M4 Actor / Organization Boundary Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a stable principal/actor/resource-owner boundary to EarthCoop so `/api/v1` can safely support personal and Group-owned Projects today while future Organization, Company, Shop and Marketplace domains can plug in without exposing Laravel persistence classes or forcing a mobile API redesign.

**Architecture:** M4 is an application-layer anti-corruption boundary over existing polymorphic ownership. It introduces stable `ActorReference` identities, live server-side representation authorization, actor discovery, and actor-aware Project ownership while preserving user-only constitutional actions and M3 personal wallet semantics. Existing `Project.owner_type/owner_id`, Group membership and Secretariat policies remain source-domain authorities; no Actor table, Company domain, or Najm Bahar ownership migration is introduced.

**Tech Stack:** PHP 8.2+, Laravel 12, Laravel Sanctum 4, PHPUnit 11, existing M1 `/api/v1` envelope/device/idempotency middleware, Laravel Policies/Gates, current Group/Project/Secretariat/Najm Bahar services.

**Spec:** `docs/superpowers/specs/2026-09-28-m4-actor-organization-boundary-design.md`

## Global Constraints

- Approved baseline is `main@c19e118d85bb04c106af0a2f91b0811f1aacef74`; implementation branches from the approved spec/plan head, never directly modifies `main`.
- Public actor types are exactly `user`, `group`, `organization`, `system`.
- Public actor IDs are opaque strings. Laravel FQCNs such as `App\\Models\\User` and `App\\Models\\Group` must never be public actor identity.
- Principal is always the authenticated real user. Actor identity never replaces principal identity in audit/idempotency evidence.
- Public user representation is self-only; there is no admin exception.
- M4 Group owner-level representation is live, active, non-expired Manager role `3` only. Inspector `2`, Active `1`, Observer `0`, Guest `4`, Temporary Active `5`, inactive/expired memberships and admin-only status do not grant Group representation.
- `organization` is reserved and fail-closed until a real source domain exists. Do not create a placeholder Organization/Company/Shop model or table.
- `system:earthcoop` is trusted-server-only. Public/native clients cannot select or mint system authority.
- M4 adds exactly `GET /api/v1/actors` for discovery. It does not add global `X-Actor-ID`, `X-Act-As`, selected-actor session state, or equivalent middleware.
- Project create remains backward compatible: omitted `owner_actor` means authenticated user's own actor.
- Actor-aware Project list syntax is exactly `GET /api/v1/projects?filter[owner_actor]=group:123`; filter grammar is `<type>:<id>` and M4 IDs contain no `:`.
- Existing Project lifecycle, target Location/Governance scope, economics and review workflow are unchanged.
- Investment mutation/economic authorization is unchanged. Do not modify `InvestmentPolicy` to grant actor-aware Group powers in M4.
- M3 personal account, balance, history, transfer, activation, membership-fee and scheduled-operation APIs remain principal/user-scoped.
- Najm Bahar account ownership schema and `legal_entity`/`meta.group_id` compatibility conventions are not migrated in M4.
- Secretariat's party/snapshot model remains intact; only Project-owner checks that are semantically owner authority may consume the shared owner predicate.
- M2 Hoda authority constitution remains unchanged: model/proposal output never creates actor authority.
- Existing M1 idempotency middleware remains authoritative. Because `owner_actor` is request input, its normalized body is part of the request fingerprint; same key + changed actor must conflict.
- Use targeted RED/GREEN tests per task. Do not run repository Full Validation after each task. Run one Full Validation only on the fixed final M4 candidate before merge.
- Do not merge the final PR without an explicit merge decision after fixed-SHA targeted and Full Validation evidence is green.

## Review Focus

1. **Authority becomes stale between discovery and mutation:** a Manager returned by `GET /api/v1/actors` who is downgraded/expired before Project create/update must be denied on the next request without token rotation.
2. **Malformed/hostile actor identity:** empty IDs, IDs containing `:`, FQCN-looking values, unknown actor types and non-scalar nested values must fail safely without class loading or cross-resource lookup.
3. **Admin/representation confusion:** `is_admin` or `super-admin` may retain administrative review privileges but must not become authority to create/update a Group-owned Project in the Group's name.
4. **Idempotency actor switching:** replaying one `Idempotency-Key` with a different `owner_actor` must return the existing M1 reuse conflict and must not create a second Project or change ownership.
5. **Unsupported/stale legacy owner:** an unknown polymorphic owner class, deleted/nonexistent Group, or unsupported organization/system owner on a public actor-aware operation must fail closed without leaking internal class names or broadening access.

---

## File Structure

### Actor boundary

- Create: `app/Services/Actors/ActorType.php` — backed enum for exact stable actor type strings.
- Create: `app/Services/Actors/ActorOperation.php` — operation enum; M4 launch value is `project_owner`.
- Create: `app/Services/Actors/ActorReference.php` — immutable actor type + opaque string ID, public serialization and filter parsing.
- Create: `app/Services/Actors/ActorBoundaryException.php` — safe application exception carrying stable actor error code/status/details, not user-controlled authority.
- Create: `app/Services/Actors/ActorResolver.php` — User/Group/legacy polymorphic mapping plus trusted system reference; Organization remains unsupported.
- Create: `app/Services/Actors/ActorRepresentationAuthorizationService.php` — live principal→actor representation checks.
- Create: `app/Services/Actors/OwnerRepresentationService.php` — shared predicate that maps legacy owner columns to ActorReference then evaluates representation.
- Create: `app/Services/Actors/ActorDiscoveryService.php` — self + currently representable Groups projection.

### Canonical Group membership source

- Create: `app/Services/Groups/EffectiveGroupMembershipService.php` — one reusable active/non-expired membership lookup and current effective-role restoration.
- Modify: `app/Policies/Concerns/ResolvesGroupMembership.php` — delegate existing policy membership lookup to the new service; preserve current GroupPolicy behavior.
- Reuse unchanged: `app/Services/TemporaryGroupRoleService.php`.

### API v1 transport

- Create: `app/Http/Controllers/API/V1/ActorController.php`.
- Modify: `app/Exceptions/ApiV1ExceptionRenderer.php` — emit stable actor codes from `ActorBoundaryException` through the existing envelope.
- Modify: `routes/api-v1.php` — add `GET /actors`; no global act-as middleware/header.
- Modify: `app/Http/Controllers/API/V1/ProjectController.php` — optional `owner_actor`, exact owner filter, thin transport mapping only.

### Project/Secretariat application and authorization

- Modify: `app/Services/Projects/ProjectApplicationService.php` — actor-aware create/query/serialization with `createForUser()` compatibility wrapper.
- Modify: `app/Policies/NajmBahar/ProjectPolicy.php` — shared owner-representation predicate for private owner visibility and lifecycle-constrained owner mutations.
- Modify narrowly: `app/Modules/Secretariat/Policies/SecretariatOfficePolicy.php` — replace only user-only Project-owner predicate with shared owner representation.
- Reuse unchanged: `app/Modules/NajmBahar/Services/ProjectService.php` — remains canonical Project lifecycle/persistence service.
- Keep unchanged: `app/Policies/NajmBahar/InvestmentPolicy.php` and Najm Bahar account schema/services except regression coverage.

### Tests and targeted gate

- Create: `tests/Unit/Actors/ActorReferenceTest.php`.
- Create: `tests/Feature/Actors/ActorRepresentationAuthorizationTest.php`.
- Create: `tests/Feature/Api/V1/ActorContractTest.php`.
- Create: `tests/Feature/Api/V1/ProjectActorContractTest.php`.
- Create: `tests/Feature/Api/V1/ActorProjectMobileJourneyTest.php`.
- Modify/add cases: `tests/Feature/Secretariat/SecretariatS5AuthorizationTest.php`.
- Reuse as regressions: `tests/Feature/Api/V1/CoreJourneyTest.php`, `tests/Feature/Api/V1/ElectionProjectNotificationContractTest.php`, `tests/Feature/Api/V1/NajmHodaAuthorityContractTest.php`, `tests/Feature/Api/V1/NajmBaharMobileJourneyTest.php`, `tests/Feature/Api/V1/NajmBaharAccountContractTest.php`, `tests/Feature/Api/V1/NajmBaharTransactionContractTest.php`, `tests/Feature/NajmBahar/InvestmentControllerTest.php`, Group policy/role tests and relevant Project scope tests.
- Create: `.github/workflows/m4-actor-boundary-targeted.yml` — narrow task/acceptance gate with concurrency cancellation; repository Full Validation remains the final gate only.

---

### Task 1: Freeze ActorReference, resolver and stable actor error semantics

**Files:**
- Create: `app/Services/Actors/ActorType.php`
- Create: `app/Services/Actors/ActorOperation.php`
- Create: `app/Services/Actors/ActorReference.php`
- Create: `app/Services/Actors/ActorBoundaryException.php`
- Create: `app/Services/Actors/ActorResolver.php`
- Modify: `app/Exceptions/ApiV1ExceptionRenderer.php`
- Test: `tests/Unit/Actors/ActorReferenceTest.php`
- Test/Regression: `tests/Feature/Api/V1/TransportContractTest.php`

**Interfaces:**
- `enum ActorType: string { case User = 'user'; case Group = 'group'; case Organization = 'organization'; case System = 'system'; }`
- `enum ActorOperation: string { case ProjectOwner = 'project_owner'; }`
- `ActorReference::__construct(ActorType $type, string $id)`; ID is trimmed, non-empty, contains no `:`, and is serialized as string.
- `ActorReference::fromArray(array $value): ActorReference` accepts exactly the semantic fields `type` and `id`; malformed values throw `ActorBoundaryException::invalidReference()`.
- `ActorReference::parse(string $value): ActorReference` parses exactly one `<type>:<id>` separator and rejects empty/extra-colon forms.
- `ActorReference::key(): string` returns `<type>:<id>`.
- `ActorReference::toArray(): array` returns `['type' => <stable string>, 'id' => <opaque string>]`.
- `ActorResolver::referenceFor(User|Group $model): ActorReference`.
- `ActorResolver::fromLegacyOwner(string $ownerType, int|string $ownerId): ActorReference` recognizes only `User::class` and `Group::class`; unknown FQCNs fail closed.
- `ActorResolver::resolveModel(ActorReference $actor): User|Group` resolves only public model-backed User/Group actors; missing row => `actor_not_found`; Organization => `actor_not_supported`; System has no public Eloquent model.
- `ActorResolver::systemReference(): ActorReference` returns exactly `system:earthcoop` for trusted internal callers.
- Stable actor error codes/statuses in M4: invalid reference `actor_reference_invalid`/422; unsupported provider/type operation `actor_not_supported`/422; missing actor `actor_not_found`/404; forbidden representation `actor_representation_forbidden`/403; valid actor on unsupported operation `actor_operation_not_supported`/422.
- `ApiV1ExceptionRenderer` recognizes `ActorBoundaryException` before generic status mapping and retains existing request ID, locale and envelope behavior.

- [ ] **Step 1: Write RED `ActorReferenceTest` cases** asserting exact `user:123`, `group:<uuid-like-string>`, array serialization, empty/extra-colon/non-scalar/FQCN-looking malformed values, unknown type, exact system key, and no Laravel FQCN in serialized output.
- [ ] **Step 2: Add a RED transport-level renderer assertion** that an `ActorBoundaryException` rendered inside `/api/v1` preserves `error.code=actor_reference_invalid`, correct HTTP status and existing M1 envelope/request-id headers without changing generic validation/authorization behavior.
- [ ] **Step 3: Run only the new unit test plus `TransportContractTest`; confirm RED is caused by missing actor classes/renderer support, not unrelated fixtures.**
- [ ] **Step 4: Implement the five actor primitives/resolver and minimal exception-renderer branch; do not add models, tables, routes or class-name reflection from client strings.**
- [ ] **Step 5: Re-run the Task 1 tests and confirm GREEN.**
- [ ] **Step 6: Commit** `feat(actor): add stable actor identity boundary`.

### Task 2: Centralize effective Group membership and live representation authorization

**Files:**
- Create: `app/Services/Groups/EffectiveGroupMembershipService.php`
- Modify: `app/Policies/Concerns/ResolvesGroupMembership.php`
- Create: `app/Services/Actors/ActorRepresentationAuthorizationService.php`
- Create: `app/Services/Actors/OwnerRepresentationService.php`
- Test: `tests/Feature/Actors/ActorRepresentationAuthorizationTest.php`
- Regression: existing Group policy/temporary-role tests including `tests/Feature/Admin/GroupRoleManagementTest.php`

**Interfaces:**
- `EffectiveGroupMembershipService::__construct(TemporaryGroupRoleService $temporaryRoles)`.
- `EffectiveGroupMembershipService::current(User $user, Group $group): ?GroupUser` reproduces the current active-membership conditions (`status=1`, not expired/terminal), calls `restoreIfExpired()`, and returns the refreshed effective role.
- `EffectiveGroupMembershipService::currentForUser(User $user): Collection` returns current effective memberships for discovery; each result must pass the same active/expiry semantics as `current()`.
- `ResolvesGroupMembership::membership()` delegates to `EffectiveGroupMembershipService::current()` so GroupPolicy and M4 representation share one source instead of duplicating expiry logic.
- `ActorRepresentationAuthorizationService::allows(User $principal, ActorReference $actor, ActorOperation $operation): bool`.
- `ActorRepresentationAuthorizationService::authorize(User $principal, ActorReference $actor, ActorOperation $operation): void` throws stable ActorBoundaryException on denial.
- User rule: only principal's own non-system user actor.
- Group rule for `ProjectOwner`: current membership role exactly `3`; principal must be non-system. Admin/super-admin does not bypass this rule.
- Organization rule: `actor_not_supported`; System on public representation path: `actor_representation_forbidden`.
- `OwnerRepresentationService::referenceFor(string $ownerType, int|string $ownerId): ActorReference` delegates to resolver.
- `OwnerRepresentationService::allows(User $principal, string $ownerType, int|string $ownerId, ActorOperation $operation): bool` returns false on unsupported/stale legacy owner rather than broadening access.
- `OwnerRepresentationService::authorize(...)` is available for command/query boundaries that need stable actor errors.

- [ ] **Step 1: Write RED representation tests** for self allowed, another user denied, Manager `3` allowed, roles `0/1/2/4/5` denied, inactive membership denied, expired membership denied/restored according to current temporary-role baseline, system user denied, and admin/super-admin without Manager membership denied.
- [ ] **Step 2: Add the Review Focus stale-authority test:** discover/authorize Manager once, mutate the membership to non-Manager/expired, call authorization again without token/session changes, and assert immediate denial.
- [ ] **Step 3: Run the new representation tests plus existing GroupPolicy/temporary-role tests; confirm RED only on the missing shared services/refactor.**
- [ ] **Step 4: Implement `EffectiveGroupMembershipService`; change the existing policy concern to delegate to it before implementing actor representation. Preserve existing GroupPolicy semantics exactly.**
- [ ] **Step 5: Implement representation and owner-predicate services using Task 1 resolver/reference; never call `GroupPolicy::manage` for representation because admin policy power is intentionally different.**
- [ ] **Step 6: Re-run targeted Group/actor tests and confirm GREEN.**
- [ ] **Step 7: Commit** `feat(actor): authorize live group representation`.

### Task 3: Add safe actor discovery without session-wide act-as state

**Files:**
- Create: `app/Services/Actors/ActorDiscoveryService.php`
- Create: `app/Http/Controllers/API/V1/ActorController.php`
- Modify: `routes/api-v1.php`
- Test: `tests/Feature/Api/V1/ActorContractTest.php`

**Interfaces:**
- Add exactly `GET /api/v1/actors` inside existing `auth:sanctum` + `api.v1.device` group.
- No mutation/idempotency middleware is needed because discovery is read-only.
- `ActorDiscoveryService::for(User $principal): array` returns `['items' => [...]]`.
- First item is always principal's own user actor for a valid non-system native principal.
- Group items are only memberships whose current effective role is Manager `3`; deterministic order is ascending Group ID after self.
- Minimum item projection: `type`, `id`, `display_name`, `context`, `permissions`.
- User context is an empty object/array; Group context may include `governance_area_id`, `dimension_key`, `dimension_value_key`; these are informational only.
- `permissions.can_represent` is `true` for returned entries but is never consumed as authority on later requests.
- Do not return system, organization, non-Manager Groups or unrelated Groups.

- [ ] **Step 1: Write RED API tests** using a real native session/device: self appears first; Manager Group appears; Inspector/Active/Observer/Guest/Temporary Active Groups do not; system/organization never appear; another user's Manager Group cannot be enumerated.
- [ ] **Step 2: Add live-authority regression:** call discovery as Manager, downgrade membership, call discovery again with the same bearer token/device and assert the Group disappears.
- [ ] **Step 3: Confirm RED because `/api/v1/actors` does not exist.**
- [ ] **Step 4: Implement discovery service/controller/route using Task 2 membership service; do not persist selected actor in token, device, cache or request middleware.**
- [ ] **Step 5: Run `ActorContractTest`, native session contract tests and Group contract tests; confirm GREEN.**
- [ ] **Step 6: Commit** `feat(api): expose representable actors`.

### Task 4: Make Project serialization and listing actor-aware while preserving personal defaults

**Files:**
- Modify: `app/Services/Projects/ProjectApplicationService.php`
- Modify: `app/Http/Controllers/API/V1/ProjectController.php`
- Test: `tests/Feature/Api/V1/ProjectActorContractTest.php`
- Regression: `tests/Feature/Api/V1/CoreJourneyTest.php`
- Regression: `tests/Feature/Api/V1/ElectionProjectNotificationContractTest.php`
- Regression: `tests/Feature/LocationGovernance/ProjectGovernanceScopeTest.php`

**Interfaces:**
- Inject `ActorResolver` and `ActorRepresentationAuthorizationService` into the Project application/query boundary.
- `ProjectApplicationService::ownerReference(Project $project): ActorReference` maps legacy `owner_type/owner_id` through ActorResolver.
- `ProjectApplicationService::serialize(Project $project): array` adds `owner_actor => ['type' => ..., 'id' => ...]`; existing response keys remain unchanged and raw `owner_type` is not added.
- `ProjectApplicationService::ownedQueryFor(User $principal, ActorReference $owner): Builder` first authorizes `ActorOperation::ProjectOwner`, maps actor to the existing User/Group model class+ID, and returns only that actor's Project query.
- `GET /api/v1/projects` with no `filter[owner_actor]` keeps exact current semantics: authenticated user's personal Projects only, even when the user manages Groups.
- `GET /api/v1/projects?filter[owner_actor]=group:123` parses via `ActorReference::parse()`, live-authorizes representation, then queries that Group's legacy polymorphic ownership.
- `filter[owner_actor]=user:<self>` is allowed and equivalent to personal ownership; another user is denied.

- [ ] **Step 1: Write RED Project read tests** asserting `owner_actor` on personal Project responses, no FQCN leak, no-filter personal-only behavior, authorized Group filter, another-user filter denial, non-Manager Group filter denial, organization/system filter failure and unknown/deleted Group fail-closed behavior.
- [ ] **Step 2: Add malformed filter cases** for missing separator, extra `:`, unknown type and FQCN-looking filter values; assert stable actor errors and no DB/class lookup driven by FQCN input.
- [ ] **Step 3: Run new test + current Project core/location tests; confirm RED only for missing actor-aware serialization/filter path.**
- [ ] **Step 4: Implement owner serialization and query method, then make ProjectController delegate pagination query construction to it; leave project scope normalization/lifecycle untouched.**
- [ ] **Step 5: Re-run Task 4 and current CoreJourney/location Project regressions; confirm GREEN and unchanged default response fields except additive `owner_actor`.**
- [ ] **Step 6: Commit** `feat(api): add actor-aware project reads`.

### Task 5: Add actor-aware Project creation and owner lifecycle authorization

**Files:**
- Modify: `app/Services/Projects/ProjectApplicationService.php`
- Modify: `app/Http/Controllers/API/V1/ProjectController.php`
- Modify: `app/Policies/NajmBahar/ProjectPolicy.php`
- Test: `tests/Feature/Api/V1/ProjectActorContractTest.php`
- Regression: `tests/Feature/Api/V1/ElectionProjectNotificationContractTest.php`
- Regression: `tests/Feature/Api/V1/CoreJourneyTest.php`

**Interfaces:**
- `ProjectApplicationService::createForActor(User $principal, ActorReference $owner, array $data): Project` live-authorizes `ProjectOwner`, resolves User/Group model, normalizes Project scope exactly once, then calls canonical `ProjectService::createProject($ownerModel, $data)` and current target/governance persistence.
- Existing `ProjectApplicationService::createForUser(User $user, array $data): Project` remains as a compatibility wrapper and delegates to `createForActor($user, $resolver->referenceFor($user), $data)`.
- ProjectController accepts optional nested `owner_actor`. Omitted means authenticated user's ActorReference. Supplied value is parsed by ActorReference and never trusted as ownership authority.
- Public Project creation supports only self User and authorized Group actors. Organization returns unsupported; System/public system selection is forbidden.
- `ProjectPolicy::view()` recognizes current owner representation before existing approved/public/admin visibility checks.
- `ProjectPolicy::update()` and `delete()` use `OwnerRepresentationService` plus the exact existing lifecycle status constraints; no admin representation shortcut is added.
- Existing submit endpoint continues to authorize through `update`, so the same live owner representation applies.
- Existing ProjectService lifecycle/economic validation is unchanged.

- [ ] **Step 1: Write RED create tests** for omitted actor personal creation, explicit self actor, Manager-created Group Project, Inspector/Active/nonmember denial, admin-without-Manager denial, another-user denial, organization unsupported and public system forbidden.
- [ ] **Step 2: Write RED owner lifecycle tests** proving Manager can view/update/submit a Group-owned draft/rejected Project only where existing lifecycle permits; role downgrade between create and update immediately denies; public approved view remains unchanged; delete policy status constraint remains unchanged.
- [ ] **Step 3: Add principal/actor evidence assertions:** after Group Project create, Project row owner is Group while M1 idempotency row `actor_key` remains `user:<principal-id>`.
- [ ] **Step 4: Add idempotency actor-switch test:** create with key K and Group A, replay exact request => single effect/replayed response; resend key K with Group B/self => `409 idempotency_key_reused`, no second Project and no ownership change.
- [ ] **Step 5: Run new Project actor tests plus current Project/election core regressions; confirm RED before implementation.**
- [ ] **Step 6: Implement generalized creation and policy owner predicate with no ProjectService economic/lifecycle rewrite.**
- [ ] **Step 7: Re-run Task 5 tests and Project core regressions; confirm GREEN.**
- [ ] **Step 8: Commit** `feat(projects): support authorized group ownership`.

### Task 6: Integrate only semantically equivalent Secretariat Project-owner authority

**Files:**
- Modify: `app/Modules/Secretariat/Policies/SecretariatOfficePolicy.php`
- Modify/add cases: `tests/Feature/Secretariat/SecretariatS5AuthorizationTest.php`
- Regression: other Secretariat S5/S6 policy/retrieval tests affected by office policy

**Interfaces:**
- Keep existing Group-scope office behavior exactly as-is: Group membership determines view; Manager manages; Inspector/Manager inspect according to existing Secretariat policy.
- For `scope_type = najm_bahar_project`, replace private `isUserProjectOwner()` semantics with shared `OwnerRepresentationService::allows(..., ActorOperation::ProjectOwner)`.
- Public approved Project read-only Secretariat visibility continues through `$user->can('view', $project)`.
- Global administrator checks at the top of Secretariat policy remain administrative powers; they are not fed into ActorRepresentationAuthorizationService and do not create Group representation.
- External organization snapshots, `legal_entity` office type and unknown scopes remain default-deny for non-admins.

- [ ] **Step 1: Write RED Secretariat cases** for a Group-owned Project office: current Manager can manage/inspect; Active and Inspector cannot gain Project-owner management merely through Group role; public-approved read visibility remains read-only; role downgrade revokes Project-owner management live.
- [ ] **Step 2: Add regression proving Group-scoped office Inspector behavior is unchanged and admin's existing Secretariat administration remains unchanged without implying Group actor representation elsewhere.**
- [ ] **Step 3: Run the focused Secretariat authorization suite and confirm RED only on the user-only Project-owner predicate.**
- [ ] **Step 4: Inject/use `OwnerRepresentationService` in the Project-scope branch only; do not replace SecretariatParty, ACL, confidentiality or correspondence logic.**
- [ ] **Step 5: Run S5/S6 authorization/retrieval regressions and confirm GREEN.**
- [ ] **Step 6: Commit** `refactor(secretariat): share project owner authority`.

### Task 7: Add the native M4 acceptance journey and targeted CI gate

**Files:**
- Create: `tests/Feature/Api/V1/ActorProjectMobileJourneyTest.php`
- Create: `.github/workflows/m4-actor-boundary-targeted.yml`
- Reuse regression suites listed below; do not alter mature tests just to make the gate green.

**Interfaces / acceptance journey:**
- Native principal establishes M1 bearer/device session.
- `GET /api/v1/actors` returns self + one managed Group and excludes non-representable Groups.
- Manager creates a Group-owned Project with `owner_actor=group:<id>` and M1 `Idempotency-Key`.
- Exact replay is single-effect.
- Group filter returns the Project; default Project list remains personal only.
- A second user/non-manager cannot list/update/submit the Group Project as owner.
- Manager is downgraded in the same session; next actor-aware owner request is immediately denied.
- Principal creates a normal personal Project with no `owner_actor`; current M1 journey remains valid.
- M3 personal Najm Bahar account endpoint still returns principal's personal account and does not switch to Group legal-entity account because a Group actor was previously used.
- Election regression still proves Manager keeps personal voting rights; acting for a Group never changes voting principal.

**Targeted workflow commands/suites:**
- `tests/Unit/Actors/ActorReferenceTest.php`
- `tests/Feature/Actors/ActorRepresentationAuthorizationTest.php`
- `tests/Feature/Api/V1/ActorContractTest.php`
- `tests/Feature/Api/V1/ProjectActorContractTest.php`
- `tests/Feature/Api/V1/ActorProjectMobileJourneyTest.php`
- `tests/Feature/Api/V1/CoreJourneyTest.php`
- `tests/Feature/Api/V1/ElectionProjectNotificationContractTest.php`
- `tests/Feature/Api/V1/NajmHodaAuthorityContractTest.php`
- `tests/Feature/Api/V1/NajmBaharMobileJourneyTest.php`
- `tests/Feature/Api/V1/NajmBaharAccountContractTest.php`
- `tests/Feature/Api/V1/NajmBaharTransactionContractTest.php`
- `tests/Feature/NajmBahar/InvestmentControllerTest.php`
- `tests/Feature/Secretariat/SecretariatS5AuthorizationTest.php` and affected S5/S6 tests
- existing Group policy/temporary-role tests
- relevant `ProjectGovernanceScopeTest`/Project lifecycle tests.

- [ ] **Step 1: Write the M4 end-to-end RED acceptance journey** with all assertions above, including no global actor state leaking from Group Project action into personal wallet/election behavior.
- [ ] **Step 2: Run the journey locally/CI against the current branch; fix only real implementation gaps discovered by the acceptance contract, never relax mature domain invariants.**
- [ ] **Step 3: Create the targeted workflow using the same PHP/MySQL/bootstrap conventions as current M2/M3 targeted workflows and enable concurrency cancellation for superseded branch runs.**
- [ ] **Step 4: Run the full M4 targeted workflow on a fixed commit and confirm every listed task/regression is GREEN. Record run ID + exact head SHA.**
- [ ] **Step 5: Audit diff for forbidden scope:** no Actor/Organization/Company/Shop DB migration; no Najm account ownership migration; no InvestmentPolicy authority expansion; no global act-as header/middleware; no M3 wallet actor switching.
- [ ] **Step 6: Commit** `test(api): add M4 actor boundary acceptance gate`.

### Task 8: Fixed-SHA final review, Full Validation and merge boundary

**Files:**
- No product-code changes after the final candidate is fixed unless a failing gate proves a defect; any defect fix creates a new candidate SHA and repeats targeted verification.
- PR metadata may be updated without changing the validated commit.

**Interfaces / evidence:**
- Candidate branch contains the approved Spec, this Plan and implementation only.
- Branch must be based on the expected M3-merged lineage or consciously reconciled with any later `main` movement before final validation.
- M4 acceptance means all 13 acceptance items in the Spec are satisfied on one exact SHA.

- [ ] **Step 1: Self-review the complete branch against every Spec section and this Plan's Review Focus; inspect diff for accidental economic, election, Hoda, account-schema or marketplace scope expansion.**
- [ ] **Step 2: Run the M4 targeted workflow on the final candidate SHA and record exact run ID/results. Do not proceed on partial/cancelled evidence.**
- [ ] **Step 3: Open/update a Draft PR to `main` with scope, security invariants, non-goals, targeted evidence and economic-reference compatibility notes.**
- [ ] **Step 4: Trigger the repository's official Full Validation once on that exact final candidate. Confirm all jobs GREEN and record run ID, exact SHA, PHPUnit totals when available, and artifact evidence.**
- [ ] **Step 5: Re-check branch/main drift after Full Validation. If `main` moved, inspect merge base and affected files before claiming the old validation is merge-ready; rebase/merge and revalidate only if material drift requires it.**
- [ ] **Step 6: Stop at the merge boundary and report exact evidence. Do not merge until the user explicitly approves merge of the validated PR.**

---

## Acceptance Traceability

- Stable class-name-independent ActorReference: Tasks 1, 3, 4.
- Principal/actor separation: Tasks 2, 5, 7.
- User + Group public actors, internal System, reserved Organization: Tasks 1–3.
- Manager-only live Group representation: Tasks 2, 3, 5, 7.
- `GET /api/v1/actors`: Task 3.
- Actor-aware Project create/list/serialization with personal backward compatibility: Tasks 4–5.
- Project owner policy no longer User-only: Task 5.
- Narrow Secretariat Project-owner integration: Task 6.
- Investment behavior unchanged: Global Constraints + Task 7 regression gate.
- M3 personal Bahar behavior unchanged: Global Constraints + Task 7 acceptance/regressions.
- No Company/Shop/Marketplace/financial-schema implementation: Global Constraints + Task 7 diff audit.
- Actor-sensitive idempotency and principal audit: Task 5.
- Fixed-SHA targeted + one Full Validation: Tasks 7–8.

## Execution Notes

- At execution start, use an isolated worktree/feature branch from the approved spec+plan head; never implement directly on `main`.
- Use strict TDD for every task: first observe the expected RED, then minimal GREEN, then targeted regression, then commit.
- Prefer the smallest test command that proves the current task. Do not launch Full Validation until Task 8.
- If a mature test conflicts with the plan, investigate root cause before editing either side. Existing constitutional/economic invariants win unless the approved Spec explicitly changes them.
- If the economic reference document is finalized while M4 is in progress, compare it against the M4 non-goals. Only identity/authority clarifications that do not redefine the approved actor contract may enter this branch; economic capability changes remain a separate reconciliation after M4/M5 as specified.