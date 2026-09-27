# M2 Najm Hoda Stable Mobile Contract Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expose Najm Hoda through stable `/api/v1` conversation, capability, proposal, consent/apply and evidence contracts while preserving the existing server-only execution authority and resource-authorization boundaries.

**Architecture:** M2 does not rebuild Najm Hoda and does not make trusted services call EarthCoop over localhost HTTP. `/api/v1` controllers are thin adapters over shared in-process application/runtime services. Browser/model context remains untrusted; executable authority is minted only by trusted server code after resource authorization and explicit consent. A small durable mobile action-lifecycle store is added because the current autonomy approval queue is cache/TTL based and is not a durable client contract. Existing unversioned Najm Hoda routes remain compatibility surfaces and are not retired in M2.

**Tech Stack:** PHP 8.2+, Laravel 12, Laravel Sanctum 4, PHPUnit 11, M1 `/api/v1` request/envelope/device/idempotency contracts, existing Najm Hoda runtime services and event bus.

**Spec:** `docs/superpowers/specs/2026-09-27-m0-api-constitution-mobile-readiness-design.md`

## Global Constraints

- M1 validated implementation boundary is `24f0e09c22c2caebee0affbe72b06037cd8533ac`; do not weaken its envelope, bearer/device, idempotency or authorization contracts.
- Stable client namespace is `/api/v1/*`; existing `/api/najm-hoda/*` and web routes remain compatibility surfaces.
- Client context, model output, page hints, `trusted_apply_request`, role strings and requested actor IDs never mint execution authority.
- `NajmHodaRuntimeActionAuthority` remains a server-only value object; mobile JSON never deserializes into it.
- Page/group/resource context used for authorization is re-resolved on the server.
- Conversation access remains owner-scoped and must not disclose another user's conversation existence.
- Current baseline has public `welcome` only; chat and conversation routes are authenticated. M2 therefore does not invent an anonymous-conversation claim subsystem.
- Apply mutations use M1 `Idempotency-Key` plus durable domain replay/evidence.
- Ordinary chat remains answer mode unless an explicit registered capability is requested.
- Full autonomy/self-extension, broad Marketplace/Company automation and unrestricted financial execution are not M2 blockers.
- No Product/UX frozen-backlog work and no legacy route retirement.
- Use targeted Najm Hoda tests during implementation; Full Validation only at the M2 checkpoint.

## Review Focus

1. Forged `runtime_action_authority`, `trusted_apply_request`, actor ID or page context from a native client must never turn a proposal into execution.
2. A valid capability against a resource the actor does not own/manage must fail closed without leaking resource existence.
3. Retrying consent/apply with the same idempotency key must return the same outcome/evidence and must not duplicate side effects.
4. Conversation/proposal/evidence IDs from another user must resolve as inaccessible, not as a distinguishable authorization leak.
5. Cache eviction/restart must not erase a mobile proposal/consent/evidence record that the API already returned as durable.

---

## File Structure

### Stable M2 application boundary

- Create: `app/Services/NajmHoda/Api/NajmHodaConversationService.php` — owner-scoped conversation create/read/list/message persistence and shared orchestration entrypoint.
- Create: `app/Services/NajmHoda/Api/NajmHodaCapabilityQueryService.php` — stable capability projection from `NajmHodaCapabilityRegistry`.
- Create: `app/Services/NajmHoda/Runtime/NajmHodaRuntimeAuthorityFactory.php` — sole launch-scope factory for `NajmHodaRuntimeActionAuthority` after capability/resource/consent checks.
- Create: `app/Services/NajmHoda/Api/NajmHodaActionApplicationService.php` — durable propose/consent/apply orchestration.
- Create: `app/Services/NajmHoda/Api/NajmHodaEvidenceService.php` — stable owner-scoped evidence projection.
- Create: `app/Models/NajmHodaAction.php` and migration — durable proposal/consent/apply lifecycle record; do not rely on the cache-only autonomy approval queue as the API source of truth.

### v1 transport

- Create: `app/Http/Controllers/API/V1/NajmHodaConversationController.php`
- Create: `app/Http/Controllers/API/V1/NajmHodaCapabilityController.php`
- Create: `app/Http/Controllers/API/V1/NajmHodaActionController.php`
- Create: `app/Http/Resources/API/V1/NajmHodaConversationResource.php`
- Create: `app/Http/Resources/API/V1/NajmHodaCapabilityResource.php`
- Create: `app/Http/Resources/API/V1/NajmHodaActionResource.php`
- Modify: `routes/api-v1.php`

### Existing seams to reuse, not duplicate

- `NajmHodaExecutionService`, `NajmHodaInteractionBoundaryService`, `NajmHodaCapabilityRegistry`, `NajmHodaResourceAuthorizationService`, `NajmHodaRuntimeActionAuthority`, `NajmHodaPageContextResolver`, `RuntimeEventBus`.
- Existing private-group/Secretariat/escalation services where their current contracts are in launch scope.
- `NajmHodaAutonomyApprovalService` may remain an internal/human-approval mechanism, but its cache storage is not the durable v1 lifecycle record.

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

**Interfaces:**
- `list(User $actor, array $filters, int $perPage = 20): LengthAwarePaginator`
- `get(User $actor, int $conversationId): Conversation`
- `startOrGet(User $actor, ?int $conversationId, ?string $agentType = null): Conversation`
- `appendUserMessage(Conversation $conversation, string $message): ConversationMessage`
- `appendAssistantMessage(Conversation $conversation, string $message, string $agent): ConversationMessage`

- [ ] **Step 1: Write owner-scope parity tests** for own list/show, cross-user concealment, `per_page <= 50`, and legacy-controller parity.
- [ ] **Step 2: Run the new test plus existing conversation tests; confirm RED only for the missing service seam.**
- [ ] **Step 3: Extract the smallest service from `NajmHodaController`; keep current orchestration semantics.**
- [ ] **Step 4: Re-run targeted tests and confirm GREEN.**
- [ ] **Step 5: Commit** `feat(hoda): extract conversation application boundary`.

### Task 2: Add stable v1 conversation/chat adapters

**Files:**
- Create controller/resource listed above; modify `routes/api-v1.php`.
- Test: `tests/Feature/Api/V1/NajmHodaConversationContractTest.php`

**Interfaces:**
- `GET /api/v1/najm-hoda/conversations`
- `POST /api/v1/najm-hoda/conversations`
- `GET /api/v1/najm-hoda/conversations/{conversation}`
- `POST /api/v1/najm-hoda/conversations/{conversation}/messages`
- Message execution delegates to `NajmHodaExecutionService::executeChat(NajmHodaOrchestrator $orchestrator, string $message, array $context = []): array` after server-owned context preparation.

- [ ] **Step 1: Write RED tests** for M1 envelope/request ID, bearer-only access, owner scope, server-resolved context and no cookie/CSRF dependency.
- [ ] **Step 2: Confirm RED because v1 Hoda routes do not exist.**
- [ ] **Step 3: Implement thin adapters; strip/ignore client authority fields rather than forwarding them.**
- [ ] **Step 4: Run v1 tests plus existing Hoda chat/execution regressions; confirm GREEN.**
- [ ] **Step 5: Commit** `feat(api): expose Najm Hoda conversations in v1`.

### Task 3: Freeze capability and proposal schemas

**Files:**
- Create capability query/controller/resources listed above; modify `routes/api-v1.php`.
- Test: `tests/Feature/Api/V1/NajmHodaCapabilityContractTest.php`

**Interfaces:**
- `GET /api/v1/najm-hoda/capabilities`
- `GET /api/v1/najm-hoda/capabilities/{action}`
- Capability fields: `action`, `version`, `enabled`, `risk`, `default_mode`, `human_approval_required`, `required_input`, `optional_input`, `output`.
- Proposal fields: `id`, `action`, `contract_version`, `risk`, `mode`, `input`, `expected_output`, `consent_required`, `status`, `created_at`; never serialize authority.

- [ ] **Step 1: Write RED tests** for registry projection, unknown action `404`, disabled action, stable machine codes and absence of authority internals.
- [ ] **Step 2: Confirm RED.**
- [ ] **Step 3: Implement projection from `NajmHodaCapabilityRegistry::contract()`/`validateInput()` without copying config.**
- [ ] **Step 4: Run `CapabilityRegistryTest` and `InteractionBoundaryTest`; confirm GREEN.**
- [ ] **Step 5: Commit** `feat(api): freeze Najm Hoda capability contract`.

### Task 4: Introduce server-only authority minting and deny-by-default protected resource authorization

**Files:**
- Create: `app/Services/NajmHoda/Runtime/NajmHodaRuntimeAuthorityFactory.php`
- Modify: `app/Services/NajmHoda/Runtime/NajmHodaResourceAuthorizationService.php`
- Test: `tests/Feature/Api/V1/NajmHodaAuthorityContractTest.php`

**Interfaces:**
- `propose(User $actor, string $action, array $input, string $source): NajmHodaRuntimeActionAuthority`
- `apply(User $actor, string $action, array $input, string $source, string $consentEvidenceId): NajmHodaRuntimeActionAuthority`
- Factory validates capability + concrete resource before calling `NajmHodaRuntimeActionAuthority::propose/apply`.
- Protected launch-scope actions receive explicit resource rules; unknown protected resources do not fall through to allow.

- [ ] **Step 1: Write RED security tests** for forged authority/apply flags, wrong-user resources, missing actor, and valid owner/manager cases.
- [ ] **Step 2: Confirm RED on missing factory/protected rules.**
- [ ] **Step 3: Implement factory and explicit resource rules using existing policies/services.**
- [ ] **Step 4: Run `ExecutionBoundaryTest` and relevant resource policy regressions; confirm GREEN.**
- [ ] **Step 5: Commit** `feat(hoda): mint runtime authority server side`.

### Task 5: Add durable proposal, consent, idempotent apply and evidence lifecycle

**Files:**
- Create: `app/Models/NajmHodaAction.php`
- Create: `database/migrations/<timestamp>_create_najm_hoda_actions_table.php`
- Create: `NajmHodaActionApplicationService`, `NajmHodaEvidenceService`, v1 action controller/resource.
- Modify: `routes/api-v1.php`
- Test: `tests/Feature/Api/V1/NajmHodaActionContractTest.php`

**Interfaces:**
- Durable row minimum: public UUID, `user_id`, `conversation_id`, `action`, contract version/risk/mode, normalized input/hash, status, consent timestamps/evidence, apply idempotency/evidence, result/error codes, created/updated timestamps.
- `POST /api/v1/najm-hoda/actions/proposals` — validate/classify, persist proposal, no execution.
- `POST /api/v1/najm-hoda/actions/{action}/consent` — actor-owned durable consent; M1 idempotency required.
- `POST /api/v1/najm-hoda/actions/{action}/apply` — M1 idempotency required; Task 4 is the only authority mint path.
- `GET /api/v1/najm-hoda/actions/{action}/evidence` — owner/authorized actor only.
- Status: `proposed|consented|applied|blocked|failed`.

- [ ] **Step 1: Write RED lifecycle tests** for propose-not-execute, consent ownership, required consent, replay-safe apply, changed-payload `409`, blocked resource and durable evidence after cache clear.
- [ ] **Step 2: Confirm RED.**
- [ ] **Step 3: Add migration/model and application/evidence services; keep internal cache approval separate from durable API state.**
- [ ] **Step 4: Execute only after Task 4 authority creation; store stable result/evidence identifiers, not serialized authority.**
- [ ] **Step 5: Run M1 idempotency plus Hoda runtime/chaos regressions; confirm GREEN.**
- [ ] **Step 6: Commit** `feat(api): add durable Najm Hoda action lifecycle`.

### Task 6: Pin the current authenticated-conversation boundary

**Files:**
- Test: `tests/Feature/Api/V1/NajmHodaConversationContractTest.php`
- No new guest/claim service or migration.

**Interfaces:**
- Baseline public Hoda surface is `welcome`; chat/conversations require authentication.
- M2 preserves that boundary. Anonymous→authenticated conversation continuity is `not_applicable` for this launch baseline and can only be reopened by a later product decision/spec change.

- [ ] **Step 1: Add regression proving v1 conversation/chat routes return v1 `401 unauthenticated` without a bearer session.**
- [ ] **Step 2: Add regression proving public welcome behavior remains separate from conversation ownership.**
- [ ] **Step 3: Run the conversation contract and existing entry-policy tests; confirm GREEN.**
- [ ] **Step 4: Record `anonymous_conversation_continuity=not_applicable` in M2 checkpoint evidence; no implementation commit is needed if tests already pass.**

### Task 7: M2-C non-browser mobile acceptance and checkpoint

**Files:**
- Create: `tests/Feature/Api/V1/NajmHodaMobileJourneyTest.php`
- Modify only defects required by the accepted M2 contract.

**Interfaces:**
- Bearer client: list/start conversation → ordinary chat → inspect capability → create proposal → inspect consent → consent → apply authorized launch-scope action → retrieve durable evidence.

- [ ] **Step 1: Write the end-to-end acceptance before fixing integration gaps.**
- [ ] **Step 2: Assert ordinary chat cannot execute, forged authority/context cannot execute, unauthorized resource blocks and accepted apply is idempotent/auditable.**
- [ ] **Step 3: Fix only M2 integration gaps; do not pull M3 financial APIs or full autonomy into M2.**
- [ ] **Step 4: Run `tests/Feature/Api/V1/NajmHoda*` plus impacted `tests/Feature/NajmHoda`.**
- [ ] **Step 5: Run Full Validation once on one fixed SHA.**
- [ ] **Step 6: Record M2-A/M2-B/M2-C evidence and commit** `docs(hoda): record M2 mobile contract gate`.

## M2 Definition of Done

- **M2-A Authority Boundary:** no client/model field can mint apply authority; only the server factory can construct it after checks.
- **M2-B Resource Safety:** every executable launch-scope concrete resource has an explicit authorization rule and deny-by-default coverage.
- **M2-C Mobile Contract:** bearer client can chat, inspect capabilities/proposals, consent, apply an authorized action exactly once and retrieve durable auditable evidence through stable `/api/v1` schemas.
- Existing web/unversioned Hoda consumers remain compatible.
- Anonymous conversation continuity is explicitly not applicable to the current authenticated-chat baseline.
- Full autonomy/self-extension remains outside the blocker set.