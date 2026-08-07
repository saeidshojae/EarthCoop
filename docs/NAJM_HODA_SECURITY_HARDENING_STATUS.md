# Najm Hoda Security Hardening Status

Branch: `agent/najm-hoda-hardening`

## Security posture introduced in this sprint

### 1. Browser input is not execution authority

Chat execution controls are treated as untrusted by default. The following values are stripped before interaction classification unless trusted server code supplies a real `NajmHodaRuntimeActionAuthority` object:

- `requested_action`
- `capability_action`
- `action_input`
- `action_priority`
- `action_reason`
- `goals`
- `trusted_apply_request`
- forged `runtime_action_authority`

A browser JSON payload cannot construct the PHP authority object.

### 2. Apply authority is server-only

`trusted_apply_request=true` from user input has no authority. Apply permission comes only from `NajmHodaRuntimeActionAuthority::apply(...)`, created by trusted server-side code after authorization.

The runtime Capability Registry, Safety Gate, executor rules and existing delegation/approval mechanisms still run after this boundary.

### 3. Actor identity cannot be spoofed

For authorized runtime actions, the actor ID comes from the server authority object. If the authority represents a system action with no actor ID, a user-supplied `user_id` is removed.

### 4. Conversation IDOR protection

Conversation ownership is enforced at the database query boundary for chat continuation, history retrieval, archive and delete operations. Foreign and nonexistent conversation IDs return the same not-found behavior, reducing both unauthorized access and object-existence leakage.

A second defense remains in `ConversationMessage`: authenticated user-role messages cannot be created for a conversation owned by another user.

Conversation listing filters are also validated and `per_page` is capped at 50 to prevent unbounded client-controlled pagination.

### 5. External escalation endpoint hardening

The external Najm Hoda escalation endpoint now:

- fails closed when the shared secret is missing;
- uses `hash_equals` for constant-time secret comparison;
- never logs any portion of the provided secret;
- passes only validated input to the integration service;
- caps transcript size at 50,000 characters;
- remains protected by the existing route rate limit.

Longer term, HMAC-signed requests with timestamp/replay protection are preferable to a static shared bearer secret.

### 6. Delegation cannot be proven by untrusted context

Role and group delegation matching no longer trusts `role_slugs`, `group_ids`, or similar values supplied in plan/context data. Role and group membership must be verified against the real user relationships in the database.

### 7. Apply mode fails closed

Capability planning can enter `apply` only when all of the following are true:

- apply was explicitly requested by trusted server code;
- the capability risk is low;
- low-risk apply is enabled;
- permissioning v2 is enabled;
- apply delegation enforcement is enabled.

If permissioning/delegation enforcement is absent or disabled, the capability remains in `propose` mode rather than becoming more permissive.

### 8. Resource-level authorization

Delegation and resource access are treated as separate requirements.

`set_ticket_needs_review` now passes through `NajmHodaResourceAuthorizationService` before it can enter the runtime. The actor must be one of:

- the ticket owner (`user_id`);
- the user represented by the ticket email;
- the assigned operator (`assignee_id`);
- an admin/super-admin.

An unrelated actor receives the same generic `resource_not_accessible` denial, avoiding resource-existence disclosure.

This pattern should be extended to group, financial, content and admin capabilities as those capabilities become executable.

## Tests added or hardened

- forged browser action controls are stripped;
- a user-provided apply flag cannot upgrade server propose authority;
- apply mode requires explicit server apply authority;
- blocked authorized actions do not reach the legacy or runtime orchestrator;
- authenticated users can write to their own conversation;
- authenticated users cannot write to another user's conversation;
- context-provided role/group claims cannot satisfy delegation;
- apply requests remain propose when delegation enforcement is unavailable;
- ticket owner and ticket assignee pass resource authorization;
- unrelated users fail ticket resource authorization;
- resource authorization denial stops the runtime before orchestration;
- GameDay restores pre-existing approval state and cannot leave synthetic overdue approvals behind;
- GameDay executor traffic is tagged and excluded from operational governance KPIs without removing audit/evidence events;
- governance distinguishes missing samples (`no_data`) from real KPI breaches.

## Verified automated security / readiness closure

On 2026-08-07 the hardening branch demonstrated a clean automated software-level posture:

- Composer configuration validation passes;
- production Composer advisory audit is blocking and passes;
- the unused Doctrine DBAL / abandoned Doctrine Cache dependency chain has been removed;
- clean database migrations pass;
- user-import boundary tests pass;
- the full Najm Hoda regression suite passes;
- two controlled GameDay cycles pass all scenarios;
- strict production-readiness returns `GO` with `0` blockers and `0` warnings;
- deployment is now gated by both the safety/regression/security job and the strict readiness job before FTP can start.

This is CI/software-release evidence, not proof of the target server's live operational configuration.

## Remaining security work before expanding production autonomy

1. Add explicit authorization factories/services that are the only code allowed to mint runtime action authority.
2. Add CSRF/session/Sanctum route review for all Najm Hoda mutation endpoints.
3. Extend resource authorization to group capabilities after the concurrent group work stabilizes.
4. Define and test stricter authorization for any future financial, content-publish and admin capabilities before they can become executable.
5. Upgrade external escalation authentication from static token to HMAC + timestamp/replay protection if it remains externally exposed.
6. Add audit events for rejected forged action controls and resource authorization denials without logging sensitive payloads.
7. Verify target-server production configuration, scheduler, queue workers, persistent cache, provider credentials and runtime evidence before enabling or expanding live autonomous apply.
