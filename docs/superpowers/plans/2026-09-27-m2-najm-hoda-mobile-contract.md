# M2 Najm Hoda Stable Mobile Contract Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expose Najm Hoda through stable `/api/v1` conversation, capability, proposal, consent/apply and evidence contracts while preserving the existing server-only execution authority and resource-authorization boundaries.

**Architecture:** M2 does not rebuild Najm Hoda and does not make trusted services call EarthCoop over localhost HTTP. The `/api/v1` controllers are thin adapters over shared in-process application/runtime services. Browser/model context remains untrusted; executable authority is minted only by trusted server code after resource authorization and explicit consent. Existing unversioned Najm Hoda routes remain compatibility surfaces and are not retired in M2.

**Tech Stack:** PHP 8.2+, Laravel 12, Laravel Sanctum 4, PHPUnit 11, M1 `/api/v1` request/envelope/device/idempotency contracts, existing Najm Hoda runtime services and event bus.

**Spec:** `docs/superpowers/specs/2026-09-27-m0-api-constitution-mobile-readiness-design.md`

## Global Constraints

- M1 validated implementation boundary is `24f0e09c22c2caebee0affbe72b06037cd8533ac`; do not weaken its envelope, bearer/device, idempotency or authorization contracts.
- Stable client namespace is `/api/v1/*`; existing `/api/najm-hoda/*` and web routes remain compatibility surfaces.
- Client context, model output, page hints, `trusted_apply_request`, role strings and requested actor IDs never mint execution authority.
- `NajmHodaRuntimeActionAuthority` remains a server-only value object; mobile JSON never deserializes into it.
- Page/group/resource context used for authorization is re-resolved on the server.
- Conversation access remains owner-scoped and must not disclose another user's conversation existence.
- Apply mutations are idempotent through the M1 `Idempotency-Key` transport contract plus domain-level replay/evidence where needed.
- Ordinary chat remains answer mode unless an explicit registered capability is requested.
- Full autonomy/self-extension, broad Marketplace/Company automation and unrestricted financial execution are not M2 blockers.
- No Product/UX frozen-backlog work and no legacy route retirement.
- Use targeted Najm Hoda tests during implementation; Full Validation only at the M2 checkpoint.

## Review Focus

1. Forged `runtime_action_authority`, `trusted_apply_request`, actor ID or page context from a native client must never turn a proposal into execution.
2. A valid capability against a resource the actor does not own/manage must fail closed without leaking resource existence.
3. Retrying consent/apply with the same idempotency key must return the same outcome/evidence and must not duplicate side effects.
4. Conversation IDs from another user must resolve as inaccessible, not as a distinguishable authorization leak.
5. Unknown future capability fields may be ignored by v1 clients, but unknown capability/action names must never execute by default.

---

## File Structure

### Stable M2 application boundary

- Create: `app/Services/NajmHoda/Api/NajmHodaConversationService.php` — owner-scoped conversation create/read/list/message persistence and shared orchestration entrypoint.
- Create: `app/Services/NajmHoda/Api/NajmHodaCapabilityQueryService.php` — stable capability/contract projection from `NajmHodaCapabilityRegistry`.
- Create: `app/Services/NajmHoda/Runtime/NajmHodaRuntimeAuthorityFactory.php` — sole launch-scope factory that returns `NajmHodaRuntimeActionAuthority` after actor/resource/consent checks.
- Create: `app/Services/NajmHoda/Api/NajmHodaActionApplicationService.php` — propose/consent/apply orchestration and evidence normalization.
- Create: `app/Services/NajmHoda/Api/NajmHodaEvidenceService.php` — stable audit/evidence projection from runtime events/action outcome.
- Create only if anonymous launch scope is confirmed by existing runtime: `app/Services/NajmHoda/Api/NajmHodaConversationClaimService.php` — signed/opaque anonymous-to-member handoff without trusting client actor identity.

### v1 transport

- Create: `app/Http/Controllers/API/V1/NajmHodaConversationController.php`
- Create: `app/Http/Controllers/API/V1/NajmHodaCapabilityController.php`
- Create: `app/Http/Controllers/API/V1/NajmHodaActionController.php`
- Create: `app/Http/Resources/API/V1/NajmHodaConversationResource.php`
- Create: `app/Http/Resources/API/V1/NajmHodaCapabilityResource.php`
- Create: `app/Http/Resources/API/V1/NajmHodaActionResource.php`
- Modify: `routes/api-v1.php`

### Existing runtime seams to reuse, not duplicate

- `app/Services/NajmHoda/Runtime/NajmHodaExecutionService.php`
- `app/Services/NajmHoda/NajmHodaInteractionBoundaryService.php`
- `app/Services/NajmHoda/Runtime/NajmHodaCapabilityRegistry.php`
- `app/Services/NajmHoda/Runtime/NajmHodaResourceAuthorizationService.php`
- `app/Services/NajmHoda/Runtime/NajmHodaRuntimeActionAuthority.php`
- `app/Services/NajmHoda/Context/NajmHodaPageContextResolver.php`
- `app/Services/NajmHoda/Runtime/RuntimeEventBus.php`
- Existing group/private command services, Secretariat helpers and escalation service where their current contracts are in launch scope.

### Tests

- Create: `tests/Feature/Api/V1/NajmHodaConversationContractTest.php`
- Create: `tests/Feature/Api/V1/NajmHodaCapabilityContractTest.php`
- Create: `tests/Feature/Api/V1/NajmHodaAuthorityContractTest.php`
- Create: `tests/Feature/Api/V1/NajmHodaActionContractTest.php`
- Create: `tests/Feature/Api/V1/NajmHodaMobileJourneyTest.php`
- Modify/add existing `tests/Feature/NajmHoda/*` only when extracting a shared service requires parity coverage.

---

### Task 1: Extract an owner-scoped conversation application service

**Files:**
- Create: `app/Services/NajmHoda/Api/NajmHodaConversationService.php`
- Modify minimally: `app/Http/Controllers/API/NajmHodaController.php`
- Test: `tests/Feature/Api/V1/NajmHodaConversationContractTest.php`
- Regression: existing Najm Hoda conversation/controller tests.

**Interfaces:**
- Produces `list(User $actor, array $filters, int $perPage = 20): LengthAwarePaginator`.
- Produces `get(User $actor, int $conversationId): Conversation` with owner scoping.
- Produces `startOrGet(User $actor, ?int $conversationId, ?string $agentType = null): Conversation`.
- Produces `appendUserMessage(Conversation $conversation, string $message): ConversationMessage` and `appendAssistantMessage(...)`.
- Existing unversioned controller delegates to the same service after extraction.

- [ ] **Step 1: Write failing owner-scope parity tests** asserting own list/show works, another user's ID is inaccessible without existence disclosure, `per_page` remains bounded at 50, and legacy controller behavior remains owner-scoped.
- [ ] **Step 2: Run the new test plus current Najm Hoda conversation tests and confirm RED only for the missing service seam.**
- [ ] **Step 3: Extract the smallest conversation service from `NajmHodaController`; do not change orchestration semantics.**
- [ ] **Step 4: Re-run targeted tests and confirm GREEN.**
- [ ] **Step 5: Commit** `feat(hoda): extract conversation application boundary`.

### Task 2: Add stable v1 conversation/chat adapters

**Files:**
- Create: `app/Http/Controllers/API/V1/NajmHodaConversationController.php`
- Create: `app/Http/Resources/API/V1/NajmHodaConversationResource.php`
- Modify: `routes/api-v1.php`
- Test: `tests/Feature/Api/V1/NajmHodaConversationContractTest.php`

**Interfaces:**
- `GET /api/v1/najm-hoda/conversations`
- `POST /api/v1/najm-hoda/conversations`
- `GET /api/v1/najm-hoda/conversations/{conversation}`
- `POST /api/v1/najm-hoda/conversations/{conversation}/messages`
- Chat endpoint delegates to `NajmHodaExecutionService::executeChat(NajmHodaOrchestrator $orchestrator, string $message, array $context = []): array` after server-owned conversation/context preparation.

- [ ] **Step 1: Write RED contract tests** for M1 envelope/request ID, bearer-only access, owner scope, server-resolved context, success/error normalization, and no cookie/CSRF dependency.
- [ ] **Step 2: Confirm RED because v1 Hoda routes do not exist.**
- [ ] **Step 3: Implement thin controller/resource adapters using Task 1 and existing execution service; never pass client authority fields through.**
- [ ] **Step 4: Run v1 contract plus existing `tests/Feature/NajmHoda` chat/execution regressions; confirm GREEN.**
- [ ] **Step 5: Commit** `feat(api): expose Najm Hoda conversations in v1`.

### Task 3: Freeze capability and proposal schemas

**Files:**
- Create: `app/Services/NajmHoda/Api/NajmHodaCapabilityQueryService.php`
- Create: `app/Http/Controllers/API/V1/NajmHodaCapabilityController.php`
- Create: `app/Http/Resources/API/V1/NajmHodaCapabilityResource.php`
- Create: `app/Http/Resources/API/V1/NajmHodaActionResource.php`
- Modify: `routes/api-v1.php`
- Test: `tests/Feature/Api/V1/NajmHodaCapabilityContractTest.php`

**Interfaces:**
- `GET /api/v1/najm-hoda/capabilities`
- `GET /api/v1/najm-hoda/capabilities/{action}`
- Public stable capability fields: `action`, `version`, `enabled`, `risk`, `default_mode`, `human_approval_required`, `required_input`, `optional_input`, `output`.
- Proposal projection fields: `proposal_id`, `action`, `contract_version`, `risk`, `mode`, `input`, `expected_output`, `consent_required`, `status`, `created_at`; never expose a serialized authority object.

- [ ] **Step 1: Write RED tests** pinning registry projection, unknown action `404`, disabled action non-executable, stable machine codes and absence of authority internals.
- [ ] **Step 2: Confirm RED.**
- [ ] **Step 3: Implement query/resource adapters on `NajmHodaCapabilityRegistry::contract()` and `validateInput()` without copying capability config.**
- [ ] **Step 4: Run v1 tests plus `CapabilityRegistryTest` and `InteractionBoundaryTest`; confirm GREEN.**
- [ ] **Step 5: Commit** `feat(api): freeze Najm Hoda capability contract`.

### Task 4: Introduce a server-only runtime authority factory and expand resource authorization

**Files:**
- Create: `app/Services/NajmHoda/Runtime/NajmHodaRuntimeAuthorityFactory.php`
- Modify: `app/Services/NajmHoda/Runtime/NajmHodaResourceAuthorizationService.php`
- Test: `tests/Feature/Api/V1/NajmHodaAuthorityContractTest.php`
- Regression: `tests/Feature/NajmHoda/ExecutionBoundaryTest.php`, relevant group/content/financial policy tests.

**Interfaces:**
- `propose(User $actor, string $action, array $input, string $source): NajmHodaRuntimeActionAuthority`
- `apply(User $actor, string $action, array $input, string $source, string $consentEvidenceId): NajmHodaRuntimeActionAuthority`
- Factory calls capability validation and resource authorization before constructing `NajmHodaRuntimeActionAuthority::propose/apply`.
- `NajmHodaResourceAuthorizationService::authorize(?int $actorId, string $action, array $input = []): array` remains the concrete resource gate and gains explicit launch-scope rules rather than defaulting protected actions to allow.

- [ ] **Step 1: Write RED security tests** proving forged JSON authority/apply flags fail, wrong-user group/content/ticket/resource IDs fail closed, missing actor fails, and authorized owner/manager cases pass.
- [ ] **Step 2: Confirm RED on missing factory/protected resource rules.**
- [ ] **Step 3: Implement the factory and explicit launch-scope resource rules; no generic admin bypass beyond existing policy semantics.**
- [ ] **Step 4: Run new tests plus `ExecutionBoundaryTest`/resource regressions; confirm GREEN.**
- [ ] **Step 5: Commit** `feat(hoda): mint runtime authority server side`.

### Task 5: Add proposal, consent, idempotent apply and evidence application service

**Files:**
- Create: `app/Services/NajmHoda/Api/NajmHodaActionApplicationService.php`
- Create: `app/Services/NajmHoda/Api/NajmHodaEvidenceService.php`
- Create only if no adequate existing persistence table/model can carry the stable lifecycle: minimal proposal/consent persistence migration/model under the Najm Hoda domain.
- Create: `app/Http/Controllers/API/V1/NajmHodaActionController.php`
- Modify: `routes/api-v1.php`
- Test: `tests/Feature/Api/V1/NajmHodaActionContractTest.php`

**Interfaces:**
- `POST /api/v1/najm-hoda/actions/proposals` — classify/validate and return proposal, no execution.
- `POST /api/v1/najm-hoda/actions/{proposal}/consent` — records explicit actor consent; idempotency required.
- `POST /api/v1/najm-hoda/actions/{proposal}/apply` — idempotency required; obtains authority only through Task 4 factory.
- `GET /api/v1/najm-hoda/actions/{proposal}/evidence` — owner/authorized actor only.
- Stable outcome: `status` in `proposed|consented|applied|blocked|failed`, `executed`, `reason_code`, `result`, `evidence`, `request_id`.

- [ ] **Step 1: Write RED lifecycle tests** for propose-not-execute, consent ownership, no apply without consent where required, replay-safe apply, same key/different payload `409`, blocked resource, and evidence/audit retrieval.
- [ ] **Step 2: Confirm RED.**
- [ ] **Step 3: Implement minimal lifecycle persistence/application service, using `NajmHodaExecutionService`/cross-module orchestrator only after Task 4 authority minting.**
- [ ] **Step 4: Verify replay does not duplicate protected side effects and runtime event evidence is stable.**
- [ ] **Step 5: Run M1 idempotency tests plus Najm Hoda runtime/chaos regressions; confirm GREEN.**
- [ ] **Step 6: Commit** `feat(api): add auditable Najm Hoda action lifecycle`.

### Task 6: Resolve anonymous-to-authenticated continuity only if it exists in launch scope

**Files:**
- Audit first: existing anonymous Najm Hoda web/API routes, conversation ownership columns and any guest/session token model.
- Create if required: `app/Services/NajmHoda/Api/NajmHodaConversationClaimService.php`
- Create if required: migration/model for one-time opaque claim token hash + expiry.
- Modify: v1 conversation controller/routes.
- Test: `tests/Feature/Api/V1/NajmHodaConversationContractTest.php`

**Interfaces:**
- If anonymous conversation is a real supported launch flow: issue a one-time opaque claim credential that contains no actor authority; after login, claim succeeds only after server validation and permanently binds the conversation to the authenticated user.
- If repository audit shows anonymous conversation is not an actual supported launch flow, record that fact and do not invent a new guest subsystem in M2.

- [ ] **Step 1: Perform repository audit and write the failing/decision test before implementation.**
- [ ] **Step 2: If needed, implement minimal one-time claim; otherwise record explicit non-applicability in M2 checkpoint evidence.**
- [ ] **Step 3: Verify claim replay, expired token, wrong logged-in user and guessed token all fail safely.**
- [ ] **Step 4: Commit only if implementation is required.**

### Task 7: M2-C non-browser mobile acceptance and checkpoint

**Files:**
- Create: `tests/Feature/Api/V1/NajmHodaMobileJourneyTest.php`
- Modify only defects required by the accepted M2 contract.

**Interfaces:**
- Bearer client: list/start conversation → send ordinary chat → inspect capabilities → create explicit proposal → inspect consent requirement → consent → apply authorized launch-scope action → retrieve evidence.

- [ ] **Step 1: Write end-to-end acceptance before fixing integration gaps.**
- [ ] **Step 2: Assert ordinary chat cannot accidentally execute; forged authority/context cannot execute; unauthorized resource action blocks; accepted action is idempotent/auditable.**
- [ ] **Step 3: Fix only M2 integration gaps; do not pull M3 financial APIs or full autonomy into M2.**
- [ ] **Step 4: Run `tests/Feature/Api/V1/NajmHoda*` and impacted `tests/Feature/NajmHoda` suites.**
- [ ] **Step 5: Run Full Validation once on one fixed SHA.**
- [ ] **Step 6: Record M2-A/M2-B/M2-C evidence and commit** `docs(hoda): record M2 mobile contract gate`.

## M2 Definition of Done

- **M2-A Authority Boundary:** no client/model field can mint apply authority; only server factory can construct authority after checks.
- **M2-B Resource Safety:** every executable launch-scope action with a concrete resource has an explicit resource-authorization rule and deny-by-default test.
- **M2-C Mobile Contract:** bearer client can chat, inspect capabilities/proposals, consent, apply an authorized action once, and retrieve auditable evidence through stable `/api/v1` schemas.
- Existing web/unversioned Hoda consumers remain compatible.
- Full autonomy/self-extension remains outside the blocker set.