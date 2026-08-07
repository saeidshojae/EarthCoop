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

Conversation ownership is now enforced at the database query boundary for chat continuation, history retrieval, archive and delete operations. Foreign and nonexistent conversation IDs return the same not-found behavior, reducing both unauthorized access and object-existence leakage.

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

### 6. Secure-by-default blocker still open

`najm-hoda.runtime.autonomy.permissioning_v2.enforce_apply_requires_delegation` currently defaults to `false`.

Recommended production default:

`NAJM_HODA_PERMISSIONING_V2_ENFORCE_APPLY=true`

Before full autonomy, the code/config default should also become fail-closed (`true`) so a missing environment variable cannot silently disable delegation enforcement.

## Tests added

- forged browser action controls are stripped;
- a user-provided apply flag cannot upgrade server propose authority;
- apply mode requires explicit server apply authority;
- blocked authorized actions do not reach the legacy or runtime orchestrator;
- authenticated users can write to their own conversation;
- authenticated users cannot write to another user's conversation.

## Remaining security work before production autonomy

1. Make delegation enforcement fail-closed by default.
2. Add explicit authorization factories/services that are the only code allowed to mint runtime action authority.
3. Add CSRF/session/Sanctum route review for all Najm Hoda mutation endpoints.
4. Add per-capability authorization tests (ticket, group, financial, content and admin scopes).
5. Upgrade the external escalation authentication from static token to HMAC + timestamp/replay protection if it remains externally exposed.
6. Add audit event for rejected forged action controls without logging sensitive payloads.
7. Run the full Najm Hoda suite in CI against the branch and inspect failures before any merge.
