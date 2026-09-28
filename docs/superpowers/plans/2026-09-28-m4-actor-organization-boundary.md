# M4 Actor / Organization Boundary Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a stable principal/actor/resource-owner boundary so EarthCoop can safely support personal and Group-owned Projects in `/api/v1` now, while future Organization, Company, Shop and Marketplace domains can plug in without exposing Laravel persistence classes or redesigning the native API.

**Architecture:** M4 is an application-layer anti-corruption boundary over existing polymorphic ownership. It introduces stable `ActorReference` identities, live server-side representation authorization, actor discovery and actor-aware Project ownership. User-only constitutional actions and M3 personal wallet semantics stay personal. Existing Project/Group/Secretariat/Najm Bahar domains remain authoritative; M4 adds no Actor table, Company domain, Marketplace domain or Najm Bahar ownership migration.

**Tech Stack:** PHP 8.2+, Laravel 12, Laravel Sanctum 4, PHPUnit 11, existing M1 `/api/v1` envelope/device/idempotency middleware, Laravel Policies/Gates, current Group/Project/Secretariat/Najm Bahar services.

**Spec:** `docs/superpowers/specs/2026-09-28-m4-actor-organization-boundary-design.md`

## Global Constraints

- Approved baseline is `main@c19e118d85bb04c106af0a2f91b0811f1aacef74`; implementation branches from the approved spec+plan head and never edits `main` directly.
- Public actor types are exactly `user`, `group`, `organization`, `system`.
- Actor IDs are opaque strings. Public transport never exposes or accepts Laravel FQCNs as identity.
- Principal is always the authenticated real user. Actor identity never replaces principal identity in audit/idempotency evidence.
- Public user representation is self-only; no admin exception.
- Group owner-level representation is current, active, non-expired Manager role `3` only. Roles `0/1/2/4/5`, inactive/expired membership and admin-only status do not grant representation.
- `organization` is reserved and fail-closed until a real source domain exists. Do not create placeholder Organization/Company/Shop tables/models.
- `system:earthcoop` is trusted-server-only. Public/native clients cannot select or mint system authority.
- M4 adds exactly `GET /api/v1/actors`; no global act-as header, middleware or selected-actor session/device state.
- Existing Project create requests stay valid: omitted `owner_actor` means the authenticated user's actor.
- Actor-aware Project list syntax is exactly `GET /api/v1/projects?filter[owner_actor]=group:123`.
- Existing Project lifecycle, target Location/Governance scope, economics and admin review workflow remain authoritative.
- Ownership is immutable through the normal Project update endpoint in M4; `owner_actor` is creation-only.
- Investment economic/mutation authorization is unchanged. Do not broaden `InvestmentPolicy` or investment settlement semantics.
- M3 personal Bahar APIs remain principal/user-scoped; using a Group actor for Project work must never switch wallet/account context.
- Najm Bahar account ownership schema and current `legal_entity`/`meta.group_id` compatibility conventions are not migrated.
- Secretariat party/snapshot/ACL/confidentiality models remain intact; only semantically equivalent Project-owner checks consume the shared owner predicate.
- M2 Hoda authority constitution remains unchanged: proposal/model output never creates actor authority.
- M1 idempotency middleware remains authoritative. Because `owner_actor` is request body input, changing actor under the same idempotency key must conflict automatically through the existing request fingerprint.
- Group-owned Project lifecycle must not crash existing approve/reject/revision notification paths. The fix is notification routing only; it must not change lifecycle/economic rules.
- Use targeted RED/GREEN tests per task. Run repository Full Validation once, only on the fixed final candidate before merge.
- Do not merge final PR without explicit user approval after targeted and Full Validation evidence are green on the exact candidate.

## Review Focus

1. **Stale authority:** a Manager discovered earlier but downgraded/expired before mutation must be denied immediately without token rotation.
2. **Hostile actor input:** empty/numeric IDs, IDs with `:`, slashes/backslashes/FQCN-like strings, unknown types and nested non-scalar values must fail safely without class loading or guessed-resource lookup.
3. **Admin/representation confusion:** admin/super-admin review power must not become authority to create/update a Group-owned Project in that Group's name.
4. **Idempotency actor switching:** same key + different `owner_actor` must return existing M1 reuse conflict with no second Project or ownership change.
5. **Group-owner lifecycle compatibility:** approve/reject/revision of a Group-owned Project must not call `notify()` on Group; current Group managers receive owner notifications and non-managers do not.

---

## File Structure

### Actor boundary

- Create: `app/Services/Actors/ActorType.php`
- Create: `app/Services/Actors/ActorOperation.php`
- Create: `app/Services/Actors/ActorReference.php`
- Create: `app/Services/Actors/ActorBoundaryException.php`
- Create: `app/Services/Actors/ActorResolver.php`
- Create: `app/Services/Actors/ActorRepresentationAuthorizationService.php`
- Create: `app/Services/Actors/OwnerRepresentationService.php`
- Create: `app/Services/Actors/ActorDiscoveryService.php`

### Canonical Group membership/representation source

- Create: `app/Services/Groups/EffectiveGroupMembershipService.php`
- Modify: `app/Policies/Concerns/ResolvesGroupMembership.php`
- Reuse: `app/Services/TemporaryGroupRoleService.php`

### API v1 transport

- Create: `app/Http/Controllers/API/V1/ActorController.php`
- Modify: `app/Exceptions/ApiV1ExceptionRenderer.php`
- Modify: `app/Http/Controllers/API/V1/ProjectController.php`
- Modify: `routes/api-v1.php`

### Project and Secretariat integration

- Modify: `app/Services/Projects/ProjectApplicationService.php`
- Modify: `app/Policies/NajmBahar/ProjectPolicy.php`
- Create: `app/Services/Projects/ProjectOwnerNotificationService.php`
- Modify narrowly: `app/Modules/NajmBahar/Services/ProjectService.php`
- Modify narrowly: `app/Modules/Secretariat/Policies/SecretariatOfficePolicy.php`
- Keep unchanged: `app/Policies/NajmBahar/InvestmentPolicy.php` and Najm Bahar account ownership schema/services except regression execution.

### Tests / workflow

- Create: `tests/Unit/Actors/ActorReferenceTest.php`
- Create: `tests/Feature/Actors/ActorRepresentationAuthorizationTest.php`
- Create: `tests/Feature/Api/V1/ActorContractTest.php`
- Create: `tests/Feature/Api/V1/ProjectActorContractTest.php`
- Create: `tests/Feature/NajmBahar/ProjectOwnerNotificationTest.php`
- Create: `tests/Feature/Api/V1/ActorProjectMobileJourneyTest.php`
- Modify/add cases: `tests/Feature/Secretariat/SecretariatS5AuthorizationTest.php`
- Create: `.github/workflows/m4-actor-boundary-targeted.yml`
- Reuse regressions: M1 CoreJourney/transport, M2 Hoda authority/page context, M3 Bahar mobile/account/transaction, Group policy/temporary role, Project governance/lifecycle, Secretariat S5/S6, Investment controller/economy tests.

---

### Task 1: Freeze ActorReference, resolver and stable actor errors

**Files:**
- Create: `app/Services/Actors/ActorType.php`
- Create: `app/Services/Actors/ActorOperation.php`
- Create: `app/Services/Actors/ActorReference.php`
- Create: `app/Services/Actors/ActorBoundaryException.php`
- Create: `app/Services/Actors/ActorResolver.php`
- Modify: `app/Exceptions/ApiV1ExceptionRenderer.php`
- Test: `tests/Unit/Actors/ActorReferenceTest.php`
- Regression: `tests/Feature/Api/V1/TransportContractTest.php`

**Interfaces:**
- `enum ActorType: string { case User = 'user'; case Group = 'group'; case Organization = 'organization'; case System = 'system'; }`
- `enum ActorOperation: string { case ProjectOwner = 'project_owner'; }`
- `ActorReference::__construct(ActorType $type, string $id)`.
- M4 actor ID grammar is exactly `^[A-Za-z0-9][A-Za-z0-9._-]{0,190}$`; IDs are strings, not integers, and never contain `:`/slash/backslash/whitespace.
- `ActorReference::fromArray(array $value): ActorReference` requires string `type` + string `id`; additional discovery metadata is ignored as non-authoritative input rather than trusted.
- `ActorReference::parse(string $value): ActorReference` accepts exactly one `<type>:<id>` separator.
- `ActorReference::key(): string` returns `<type>:<id>`; `toArray()` returns only `type` and `id`.
- `ActorResolver::referenceFor(User|Group $model): ActorReference`; system User rows (`is_system=true`) are not public `user` actors.
- `ActorResolver::fromLegacyOwner(string $ownerType, int|string $ownerId): ActorReference` recognizes only `User::class` and `Group::class`; unknown FQCN fails closed.
- `ActorResolver::resolveModel(ActorReference $actor): User|Group` resolves public User/Group only. Missing row => actor-not-found; Organization => unsupported; System has no public model.
- `ActorResolver::systemReference(): ActorReference` returns exactly `system:earthcoop` for trusted server code.
- Stable codes/statuses: `actor_reference_invalid`/422, `actor_not_supported`/422, `actor_not_found`/404, `actor_representation_forbidden`/403, `actor_operation_not_supported`/422.
- `ApiV1ExceptionRenderer` handles `ActorBoundaryException` before generic status mapping and provides safe generic FA/EN/AR messages without echoing actor IDs/classes.

- [ ] **Step 1: Write failing pure tests** for `user:123`, UUID-like Group ID, array/string round-trip, numeric ID rejection, empty/extra-colon/slash/backslash/FQCN-like ID rejection, unknown type, system key and ignored client-supplied `permissions/context` metadata.
- [ ] **Step 2: Write a failing API renderer test** for each actor error code/status while preserving M1 request-id/content-language/envelope behavior.
- [ ] **Step 3: Run:** `php artisan test tests/Unit/Actors/ActorReferenceTest.php tests/Feature/Api/V1/TransportContractTest.php`  
  **Expected:** RED only because actor classes/error-rendering do not yet exist.
- [ ] **Step 4: Implement the actor primitives/resolver and minimal renderer branch. Do not instantiate classes from client strings or add DB schema.**
- [ ] **Step 5: Re-run the Task 1 command. Expected: GREEN.**
- [ ] **Step 6: Commit:** `feat(actor): add stable actor identity boundary`.

### Task 2: Centralize effective Group membership and live representation

**Files:**
- Create: `app/Services/Groups/EffectiveGroupMembershipService.php`
- Modify: `app/Policies/Concerns/ResolvesGroupMembership.php`
- Create: `app/Services/Actors/ActorRepresentationAuthorizationService.php`
- Create: `app/Services/Actors/OwnerRepresentationService.php`
- Test: `tests/Feature/Actors/ActorRepresentationAuthorizationTest.php`
- Regression: `tests/Feature/Admin/GroupRoleManagementTest.php` plus current GroupPolicy tests.

**Interfaces:**
- `EffectiveGroupMembershipService::__construct(TemporaryGroupRoleService $temporaryRoles)`.
- `current(User $user, Group $group): ?GroupUser` uses current `status=1` + membership-expiry rules and calls `restoreIfExpired()` before returning effective role.
- `currentForUser(User $user): Collection` returns current effective memberships for discovery using the same rules.
- `currentManagers(Group $group): Collection` returns current effective role-3 real users for owner notifications; no system users or inactive/expired memberships.
- Existing `ResolvesGroupMembership::membership()` delegates to `EffectiveGroupMembershipService::current()` so GroupPolicy and M4 use one canonical source.
- `ActorRepresentationAuthorizationService::allows(User $principal, ActorReference $actor, ActorOperation $operation): bool`.
- `authorize(User $principal, ActorReference $actor, ActorOperation $operation): void` throws stable actor exception on denial.
- User: self-only and non-system. Group ProjectOwner: current effective role exactly `3`, real non-system principal. Admin/super-admin does not bypass. Organization unsupported. Public System forbidden.
- `OwnerRepresentationService::referenceFor(string $ownerType, int|string $ownerId): ActorReference`.
- `allows(User $principal, string $ownerType, int|string $ownerId, ActorOperation $operation): bool` returns false for stale/unsupported legacy owner.
- `authorize(...)` propagates stable actor errors for command/query paths.

- [ ] **Step 1: Write failing tests** for self allowed, another user denied, Manager 3 allowed, roles 0/1/2/4/5 denied, inactive/expired membership denied, expired temporary-role restoration respected, system user denied, admin/super-admin without Manager membership denied.
- [ ] **Step 2: Add stale-authority test:** authorize Manager, downgrade/expire membership, authorize again in the same session and assert immediate denial.
- [ ] **Step 3: Add metadata-forgery test:** client-like `permissions.can_represent=true` in ActorReference input never changes server authorization.
- [ ] **Step 4: Run:** `php artisan test tests/Feature/Actors/ActorRepresentationAuthorizationTest.php tests/Feature/Admin/GroupRoleManagementTest.php` plus the existing GroupPolicy test file(s).  
  **Expected:** RED on missing shared services only; mature role tests remain otherwise valid.
- [ ] **Step 5: Implement `EffectiveGroupMembershipService`, refactor the existing policy concern to delegate to it, then implement representation/owner services. Do not call `GroupPolicy::manage()` for representation because admin policy authority is intentionally different.**
- [ ] **Step 6: Re-run focused Group/actor tests. Expected: GREEN.**
- [ ] **Step 7: Commit:** `feat(actor): authorize live group representation`.

### Task 3: Add safe actor discovery

**Files:**
- Create: `app/Services/Actors/ActorDiscoveryService.php`
- Create: `app/Http/Controllers/API/V1/ActorController.php`
- Modify: `routes/api-v1.php`
- Test: `tests/Feature/Api/V1/ActorContractTest.php`

**Interfaces:**
- Add exactly `GET /api/v1/actors` inside current `auth:sanctum` + `api.v1.device` group.
- `ActorDiscoveryService::for(User $principal): array` returns `['items' => [...]]`.
- First item: principal's own User actor. Then current representable Groups sorted ascending by Group ID.
- Item fields: `type`, `id`, `display_name`, `context`, `permissions`; `permissions.can_represent=true` is informative only.
- Group context may expose `governance_area_id`, `dimension_key`, `dimension_value_key`; context never grants authority.
- Exclude System, Organization, non-Manager Groups and unrelated actors.
- No global selected actor is stored anywhere.

- [ ] **Step 1: Write RED API tests** with real native session/device proving self first, Manager Group included, roles 0/1/2/4/5 excluded, system/organization excluded and unrelated Groups not enumerable.
- [ ] **Step 2: Add live-discovery test:** downgrade the Manager after first GET, repeat with same bearer/device, Group disappears.
- [ ] **Step 3: Run:** `php artisan test tests/Feature/Api/V1/ActorContractTest.php tests/Feature/Api/V1/NativeSessionContractTest.php tests/Feature/Api/V1/GroupContractTest.php`  
  **Expected:** RED because `/api/v1/actors` is missing.
- [ ] **Step 4: Implement service/controller/route using Task 2 membership source. No cache/token/session act-as state.**
- [ ] **Step 5: Re-run Task 3 command. Expected: GREEN.**
- [ ] **Step 6: Commit:** `feat(api): expose representable actors`.

### Task 4: Make Project reads actor-aware without changing personal defaults

**Files:**
- Modify: `app/Services/Projects/ProjectApplicationService.php`
- Modify: `app/Http/Controllers/API/V1/ProjectController.php`
- Test: `tests/Feature/Api/V1/ProjectActorContractTest.php`
- Regression: `tests/Feature/Api/V1/CoreJourneyTest.php`
- Regression: `tests/Feature/Api/V1/ElectionProjectNotificationContractTest.php`
- Regression: `tests/Feature/LocationGovernance/ProjectGovernanceScopeTest.php`

**Interfaces:**
- `ProjectApplicationService::ownerReference(Project $project): ActorReference` maps current legacy owner fields through ActorResolver.
- `serialize(Project $project): array` adds `owner_actor => ['type'=>..., 'id'=>...]`; existing keys stay unchanged; raw `owner_type` is not added.
- `ownedQueryFor(User $principal, ActorReference $owner): Builder` live-authorizes `ProjectOwner`, resolves existing User/Group model and returns only that owner query.
- No-filter `GET /api/v1/projects` remains principal's personal Projects only, even when they manage Groups.
- Exact filter: `filter[owner_actor]=<type>:<id>`; self-user allowed; another user denied; authorized Group allowed.
- Unknown/deleted owner and malformed/unsupported filter fail closed with actor errors and no internal class leakage.

- [ ] **Step 1: Write RED read tests** for additive `owner_actor`, no FQCN leak, personal default list, authorized Group filter, other-user denial, non-Manager denial, Organization/System failure and stale/deleted Group failure.
- [ ] **Step 2: Add hostile filter tests** for missing separator, extra colon, unknown type, FQCN-like/slash/backslash values.
- [ ] **Step 3: Run:** `php artisan test tests/Feature/Api/V1/ProjectActorContractTest.php tests/Feature/Api/V1/CoreJourneyTest.php tests/Feature/Api/V1/ElectionProjectNotificationContractTest.php tests/Feature/LocationGovernance/ProjectGovernanceScopeTest.php`  
  **Expected:** RED only on new actor-aware response/filter behavior.
- [ ] **Step 4: Implement owner serialization/query and thin controller parsing/pagination delegation. Do not change scope normalization or lifecycle/economic rules.**
- [ ] **Step 5: Re-run Task 4 command. Expected: GREEN.**
- [ ] **Step 6: Commit:** `feat(api): add actor-aware project reads`.

### Task 5: Add actor-aware Project creation and owner lifecycle authorization

**Files:**
- Modify: `app/Services/Projects/ProjectApplicationService.php`
- Modify: `app/Http/Controllers/API/V1/ProjectController.php`
- Modify: `app/Policies/NajmBahar/ProjectPolicy.php`
- Test: `tests/Feature/Api/V1/ProjectActorContractTest.php`
- Regression: Project core/election/location tests from Task 4.

**Interfaces:**
- `createForActor(User $principal, ActorReference $owner, array $data): Project` live-authorizes `ProjectOwner`, resolves User/Group model, normalizes Project scope once and delegates to canonical `ProjectService::createProject($ownerModel, $data)` plus current target/governance persistence.
- `createForUser(User $user, array $data): Project` remains compatibility wrapper delegating to self ActorReference.
- Project store accepts optional `owner_actor`; omitted => self. User can never supply another User, System or unresolved Organization as effective owner.
- `owner_actor` is creation-only. Project update validation explicitly marks `owner_actor` prohibited so ordinary update cannot transfer ownership silently.
- `ProjectPolicy::view()` checks current owner representation before existing approved/public/admin view paths.
- `update()`/`delete()` use `OwnerRepresentationService` and retain exact existing lifecycle status restrictions; submit remains gated through update.
- Admin review power does not become owner representation.

- [ ] **Step 1: Write RED create tests** for omitted self, explicit self, Manager Group owner, roles 0/1/2/4/5/nonmember denial, admin-without-Manager denial, other-user denial, Organization unsupported and System forbidden.
- [ ] **Step 2: Write RED lifecycle tests** proving Manager can privately view/update/submit valid Group-owned Project, role downgrade immediately revokes owner mutation, public approved view stays unchanged and delete status rule stays unchanged.
- [ ] **Step 3: Add ownership-immutability test:** PUT with any `owner_actor` returns validation error and original owner stays unchanged.
- [ ] **Step 4: Add principal/actor evidence assertion:** Group-owned Project persists `Group::class/id`, while `api_v1_idempotency_keys.actor_key` remains `user:<principal-id>`.
- [ ] **Step 5: Add idempotency actor-switch test:** same key + exact Group request replays single-effect; same key + different owner actor returns `409 idempotency_key_reused`, no second Project/no ownership change.
- [ ] **Step 6: Run:** `php artisan test tests/Feature/Api/V1/ProjectActorContractTest.php tests/Feature/Api/V1/CoreJourneyTest.php tests/Feature/Api/V1/ElectionProjectNotificationContractTest.php tests/Feature/LocationGovernance/ProjectGovernanceScopeTest.php`  
  **Expected:** RED before generalized create/policy implementation.
- [ ] **Step 7: Implement generalized create, creation-only owner input and shared policy predicate. Do not rewrite `ProjectService` lifecycle/economics in this task.**
- [ ] **Step 8: Re-run Task 5 command. Expected: GREEN.**
- [ ] **Step 9: Commit:** `feat(projects): support authorized group ownership`.

### Task 6: Make existing Project owner notifications safe for Group owners

**Files:**
- Create: `app/Services/Projects/ProjectOwnerNotificationService.php`
- Modify narrowly: `app/Modules/NajmBahar/Services/ProjectService.php`
- Test: `tests/Feature/NajmBahar/ProjectOwnerNotificationTest.php`
- Regression: existing Najm Bahar Project notification/lifecycle tests.

**Interfaces:**
- `ProjectOwnerNotificationService::__construct(EffectiveGroupMembershipService $memberships)`.
- `recipients(Project $project): Collection`:
  - User owner => that User only;
  - Group owner => current real effective Managers from `currentManagers($group)`;
  - missing/unsupported legacy owner => empty collection, never dynamic class loading.
- `send(Project $project, Illuminate\Notifications\Notification $notification): void` uses Laravel Notification delivery to resolved User recipients; it never calls `notify()` on Group.
- Replace only the three direct owner notification sites in existing Project lifecycle (`approved`, `rejected`, `revision_requested`) with this service.
- No Project status, review, economic, approval or rejection rule changes.

- [ ] **Step 1: Write RED notification tests** using `Notification::fake()` proving User-owner behavior stays one-user, Group-owner approve/reject/revision do not crash, all current Managers receive, roles 0/1/2/4/5 do not receive, expired/downgraded Managers do not receive.
- [ ] **Step 2: Run:** `php artisan test tests/Feature/NajmBahar/ProjectOwnerNotificationTest.php` plus existing Project notification/lifecycle tests.  
  **Expected:** RED on Group owner because current code calls `$project->owner->notify()`.
- [ ] **Step 3: Implement notification routing and replace only those direct lifecycle notification calls.**
- [ ] **Step 4: Re-run Task 6 command. Expected: GREEN with unchanged User-owner notification behavior.**
- [ ] **Step 5: Commit:** `fix(projects): route group owner notifications to managers`.

### Task 7: Integrate only equivalent Secretariat Project-owner authority

**Files:**
- Modify: `app/Modules/Secretariat/Policies/SecretariatOfficePolicy.php`
- Modify/add cases: `tests/Feature/Secretariat/SecretariatS5AuthorizationTest.php`
- Regression: relevant Secretariat S5/S6 authorization/retrieval tests.

**Interfaces:**
- Group-scope office rules remain exactly current: membership views; Manager manages; Inspector/Manager inspect.
- For `scope_type=najm_bahar_project`, replace only the private user-only owner predicate with `OwnerRepresentationService::allows(..., ActorOperation::ProjectOwner)`.
- Public approved Project read-only visibility continues through `$user->can('view', $project)`.
- Top-level admin Secretariat powers remain admin powers, not actor representation.
- SecretariatParty, external organization snapshots, `legal_entity` office type, ACL/confidentiality/correspondence remain unchanged/default-deny where currently defined.

- [ ] **Step 1: Write RED Group-owned Project-office cases:** current Manager can manage/inspect; Active/Inspector cannot gain Project-owner management merely from Group role; role downgrade revokes live; public approved visibility remains read-only.
- [ ] **Step 2: Add regression that Group-scoped office Inspector behavior and existing admin Secretariat behavior remain unchanged.
- [ ] **Step 3: Run:** `php artisan test tests/Feature/Secretariat/SecretariatS5AuthorizationTest.php` plus affected S5/S6 tests.  
  **Expected:** RED only on current user-only Project-owner check.
- [ ] **Step 4: Inject/use shared owner predicate in Project-scope branch only.**
- [ ] **Step 5: Re-run Task 7 command. Expected: GREEN.**
- [ ] **Step 6: Commit:** `refactor(secretariat): share project owner authority`.

### Task 8: Add native M4 acceptance journey and targeted CI gate

**Files:**
- Create: `tests/Feature/Api/V1/ActorProjectMobileJourneyTest.php`
- Create: `.github/workflows/m4-actor-boundary-targeted.yml`

**Acceptance journey:**
- Establish M1 bearer/device session as a real Manager.
- Discover self + managed Group; non-representable Group absent.
- Create Group-owned Project with idempotency key; exact replay is single-effect.
- Group filter returns it; default Project list remains personal only.
- Non-manager cannot owner-read/mutate it.
- Downgrade Manager in same session; next owner mutation denied live.
- Create ordinary personal Project with omitted owner actor; M1 behavior remains intact.
- Personal M3 Bahar account remains the principal's account after Group actor use.
- Existing election regression still proves Manager votes personally.
- Group-owned Project can pass existing review status transitions without notification crash (Task 6 regression remains green).

**Targeted workflow suites:**
- `tests/Unit/Actors/ActorReferenceTest.php`
- `tests/Feature/Actors/ActorRepresentationAuthorizationTest.php`
- `tests/Feature/Api/V1/ActorContractTest.php`
- `tests/Feature/Api/V1/ProjectActorContractTest.php`
- `tests/Feature/Api/V1/ActorProjectMobileJourneyTest.php`
- `tests/Feature/NajmBahar/ProjectOwnerNotificationTest.php`
- `tests/Feature/Api/V1/CoreJourneyTest.php`
- `tests/Feature/Api/V1/ElectionProjectNotificationContractTest.php`
- `tests/Feature/Api/V1/NajmHodaAuthorityContractTest.php`
- `tests/Feature/NajmHoda/PageContextResolverTest.php`
- `tests/Feature/Api/V1/NajmBaharMobileJourneyTest.php`
- `tests/Feature/Api/V1/NajmBaharAccountContractTest.php`
- `tests/Feature/Api/V1/NajmBaharTransactionContractTest.php`
- `tests/Feature/NajmBahar/InvestmentControllerTest.php`
- `tests/Feature/Secretariat/SecretariatS5AuthorizationTest.php` + affected S5/S6 tests
- existing Group policy/temporary-role tests
- relevant Project governance/lifecycle tests.

- [ ] **Step 1: Write the RED end-to-end M4 journey** with all assertions above, especially no global actor state leaking into personal wallet/election semantics.
- [ ] **Step 2: Run the journey and fix only implementation gaps; do not weaken mature economic/governance tests.**
- [ ] **Step 3: Create targeted workflow using current M2/M3 PHP/MySQL/bootstrap conventions and concurrency cancellation.**
- [ ] **Step 4: Run targeted workflow on a fixed commit; record exact run ID + head SHA and require every listed suite GREEN.**
- [ ] **Step 5: Diff audit forbidden scope:** no Actor/Organization/Company/Shop DB migration; no Najm account ownership migration; no InvestmentPolicy expansion; no global act-as header/state; no M3 wallet switching.
- [ ] **Step 6: Commit:** `test(api): add M4 actor boundary acceptance gate`.

### Task 9: Fixed-SHA final review, Full Validation and merge boundary

**Files:**
- No product-code changes after candidate is fixed unless a failing gate proves a defect; any fix creates a new candidate SHA and repeats targeted evidence.
- PR metadata may change without changing validated commit.

- [ ] **Step 1: Self-review branch against every Spec section, Review Focus item and Acceptance Traceability item; inspect diff for accidental economic/election/Hoda/account-schema/marketplace scope growth.**
- [ ] **Step 2: Run M4 targeted workflow on final candidate SHA and record exact evidence. Do not proceed on cancelled/partial evidence.**
- [ ] **Step 3: Open/update Draft PR to `main` with scope, authority/security invariants, non-goals, economic-reference compatibility, targeted run ID and exact head SHA.**
- [ ] **Step 4: Trigger official repository Full Validation once on that exact candidate. Require all jobs GREEN; record run ID, exact SHA, PHPUnit totals/artifact when available.**
- [ ] **Step 5: Re-check `main` drift/merge base after Full Validation. If `main` moved materially, reconcile before claiming merge-ready and revalidate only when the resulting candidate changes.**
- [ ] **Step 6: Stop at merge boundary and report exact evidence. Do not merge until explicit user approval.**

---

## Acceptance Traceability

- Stable class-independent ActorReference and stable actor errors: Task 1.
- Principal/actor separation and Manager-only live representation: Task 2.
- Safe `GET /api/v1/actors`: Task 3.
- Project `owner_actor` serialization/filter with personal defaults: Task 4.
- Group-owned Project create/update/submit/delete policy and actor-sensitive idempotency: Task 5.
- Existing Group-owned Project lifecycle notifications remain functional: Task 6.
- Narrow Secretariat Project-owner integration only: Task 7.
- Investment behavior unchanged: Global Constraints + Task 8 regression/diff audit.
- M2 Hoda authority unchanged: Global Constraints + Task 8 Hoda regressions.
- M3 personal Bahar behavior unchanged: Global Constraints + Task 8 journey/regressions.
- No Company/Shop/Marketplace/financial-schema implementation: Global Constraints + Task 8 diff audit.
- Fixed-SHA targeted + one Full Validation: Tasks 8–9.

## Self-Review Notes

- Spec coverage checked: all M4 acceptance items map to Tasks 1–9.
- Type/signature consistency checked: all later Project/Secretariat tasks consume Task 1 `ActorReference`/resolver and Task 2 owner-representation interfaces.
- Review Focus coverage checked: stale authority (Tasks 2/3/5), hostile input (Tasks 1/4), admin confusion (Tasks 2/5), idempotency actor switch (Task 5), Group-owner lifecycle notification compatibility (Task 6).
- Scope proportion checked: no Company/Organization provider, actor DB migration, financial-schema change, Investment authority expansion or global actor middleware is planned.
- Additional repository finding incorporated: current Project lifecycle directly calls `$project->owner->notify()`, which is unsafe for Group owners; Task 6 fixes only delivery routing so the newly exposed Group ownership path does not break existing lifecycle.

## Execution Notes

- At execution start, create an isolated worktree/feature branch from the approved spec+plan head; never implement directly on `main`.
- Use strict TDD: observe RED, minimal GREEN, targeted regression, commit.
- Prefer the smallest task-specific test command. Full Validation is reserved for Task 9.
- If a mature test conflicts with this plan, investigate root cause before editing either side. Existing constitutional/economic invariants remain authoritative unless the approved Spec explicitly changes them.
- If the economic reference document is finalized during M4, compare it with M4 non-goals. Only identity/authority clarifications that preserve the approved actor contract may enter this branch; economic capability changes remain a separate reconciliation after M4/M5.