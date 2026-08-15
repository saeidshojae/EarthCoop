# Najm Hoda User Memory & Context Roadmap

Status: Planned workstream. Not part of the current execution-boundary change.
Branch: `agent/najm-hoda-hardening`

## Current discovery

The backend is not actually stateless. `API/NajmHodaController` already:

- finds or creates a `Conversation` for chat;
- persists user messages and assistant messages;
- exposes an API to list the authenticated user's conversations;
- exposes an API to retrieve a conversation with ordered messages;
- supports archive and soft-delete style status changes.

Therefore the immediate continuity problem is likely in the global widget/front-end flow: it is not restoring/selecting the prior `conversation_id`, and it does not currently expose the existing conversation-list/history APIs to the user.

This is good news: the first memory milestone should reuse and harden the existing persistence layer rather than introduce duplicate conversation tables.

## Why this belongs in the architecture

Closing and reopening the widget should not erase useful conversational continuity, and Najm Hoda should be able to understand the page/module context in which a question is asked.

This work should be implemented after the execution boundary is stabilized so that remembered conversation/context cannot accidentally bypass capability, safety, approval, or execution controls.

## Workstream A — Conversation Continuity & Archive

Immediate goals:

- inspect the global widget implementation and how it manages `conversation_id`;
- restore an active/recent conversation when the widget is reopened, or let the user explicitly choose a previous conversation;
- add a user-visible conversation/history panel using the existing list/show/archive/delete APIs;
- ensure every conversation returned to the widget belongs to the authenticated user;
- add pagination and appropriate empty/loading/error states;
- preserve agent metadata where useful.

Backend hardening follow-ups:

- verify conversation ownership in `getOrCreateConversation`, not only in show/delete/archive endpoints;
- verify guest behavior and authenticated-user transition behavior;
- define conversation retention policy;
- add tests for cross-user access and conversation-id tampering.

Important distinction: persisted conversation history is not the same as long-term user memory.

## Workstream B — Page / Module Context Awareness

The widget should send structured page context with each message, for example:

```json
{
  "route_name": "groups.show",
  "module": "groups",
  "resource_type": "group",
  "resource_id": 123,
  "page_title": "..."
}
```

Najm Hoda can then answer questions such as "این بخش چیست؟" or "چطور این گروه را مدیریت کنم؟" using the actual page/module context.

Security constraints:

- Page context is informational input, not authorization.
- Resource IDs supplied by the browser must be re-authorized/re-resolved server-side.
- The widget must never infer permission to mutate a resource merely because the user is viewing its page.
- Do not trust browser-supplied permission arrays as authority.
- Any action request still passes through Capability Registry -> Safety -> Delegation/Approval -> Executor.

## Workstream C — Long-term User Understanding / Personalization

Only after conversation continuity and privacy controls exist, introduce a separate, bounded derived-memory layer. Possible uses:

- summarize recurring EarthCoop-related goals or preferences;
- remember preferred language and support style where appropriate;
- use prior resolved issues to avoid repetitive support;
- build bounded user context for better recommendations.

Do not simply send all historical messages to the model. Long-term memory should be compact, provenance-aware, confidence-scored, reviewable/expirable, and deletable.

Avoid opaque profiling. Sensitive inference should not be part of this layer.

## Integration order

1. Stabilize Interaction / Execution Boundary.
2. Audit the existing Conversation / ConversationMessage model, routes, ownership checks, and widget code.
3. Connect the widget to existing conversation list/show APIs and persist/restore `conversation_id`.
4. Add user-visible archive/history controls.
5. Add structured page/module context.
6. Add context retrieval into Agent prompts with token/recency limits.
7. Add carefully scoped long-term derived memory.
8. Add privacy/retention/deletion controls and security tests.

## Non-negotiable boundary

Memory and page awareness may improve understanding, but they must never grant execution authority. Execution authority remains capability- and policy-based.
