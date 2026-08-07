# Najm Hoda User Memory & Context Roadmap

Status: Planned workstream. Not part of the current execution-boundary change.
Branch: `agent/najm-hoda-hardening`

## Why this belongs in the architecture

The global Najm Hoda widget currently behaves primarily as a stateless chat surface. Closing and reopening the widget should not erase the user's useful conversational continuity, and Najm Hoda should be able to understand the page/module context in which a question is asked.

This work should be implemented after the execution boundary is stabilized so that remembered conversation/context cannot accidentally bypass capability, safety, approval, or execution controls.

## Workstream A — Conversation Memory & Archive

Goals:

- Persist chat conversations per authenticated user.
- Allow the user to view, reopen, search, and optionally delete/archive prior conversations.
- Restore recent conversation context when a conversation is reopened.
- Keep raw conversation history separate from long-term derived user memory.
- Introduce retention/privacy controls before using conversation history for personalization.

Suggested data model:

- `najm_hoda_conversations`
  - id
  - user_id
  - title
  - status
  - started_at
  - last_message_at
- `najm_hoda_messages`
  - id
  - conversation_id
  - role
  - agent
  - content
  - metadata
  - created_at
- optional later: `najm_hoda_user_memory`
  - user_id
  - memory_type
  - fact/value
  - confidence
  - source_conversation_id
  - reviewed/expired flags

Important constraint: long-term memory should not simply copy every chat message. It should store only useful, bounded, reviewable derived context.

## Workstream B — Page / Module Context Awareness

The widget should send structured page context with each message, for example:

```json
{
  "route_name": "groups.show",
  "module": "groups",
  "resource_type": "group",
  "resource_id": 123,
  "page_title": "...",
  "user_permissions": ["..."]
}
```

Najm Hoda can then answer questions such as "این بخش چیست؟" or "چطور این گروه را مدیریت کنم؟" using the actual page/module context.

Security constraints:

- Page context is informational input, not authorization.
- Resource IDs from the browser must be re-authorized server-side.
- The widget must never infer permission to mutate a resource merely because the user is viewing its page.
- Any action request still passes through Capability Registry -> Safety -> Delegation/Approval -> Executor.

## Workstream C — User Understanding / Personalization

Only after persistent conversation and privacy controls exist:

- summarize recurring goals/preferences relevant to EarthCoop usage;
- remember preferred language and support style where appropriate;
- use prior resolved issues to avoid repetitive support;
- build bounded user context for better recommendations.

Avoid opaque profiling. Derived memory should have provenance, confidence, retention, and a deletion path.

## Integration order

1. Stabilize Interaction / Execution Boundary.
2. Persist conversations and messages.
3. Add user-visible conversation archive.
4. Add structured page/module context.
5. Add context retrieval into Agent prompts.
6. Add carefully scoped long-term derived memory.
7. Add privacy/retention controls and tests.

## Non-negotiable boundary

Memory and page awareness may improve understanding, but they must never grant execution authority. Execution authority remains capability- and policy-based.
