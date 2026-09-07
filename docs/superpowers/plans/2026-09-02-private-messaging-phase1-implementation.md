# Private Messaging Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a mobile-first professional private messaging experience for EarthCoop with request management, reliable text messaging, unread counts, and read receipts while preserving existing request/realtime/report/reaction behavior.

**Architecture:** Keep the existing ChatRequest/PrivateConversation/PrivateMessage domain and private broadcast channel. Add a minimal `read_at` contract, unread aggregation, a read-status endpoint/event, then rebuild the request/list/conversation/profile surfaces as mobile-first messenger UI under `layouts.unified` without introducing media features.

**Tech Stack:** Laravel/PHP 8.2, Blade, existing Bootstrap/Vite frontend, Laravel broadcasting/Echo, PHPUnit, MySQL.

**Spec:** `docs/superpowers/specs/2026-09-02-private-messaging-professionalization-design.md`

## Global Constraints

- Work only on `agent/private-messaging-professionalization`; never merge to `main`.
- Mobile is the primary design target; desktop is an enhancement of the same responsive shell.
- User-facing wording uses «گفتگو» rather than «چت» where the UI is redesigned.
- Phase 1 has text messages only; no file/image/audio/video/reply/edit/delete/search work.
- Keep `layouts.unified`; do not rewrite unrelated global navigation/layouts.
- Preserve group-manager chat-request behavior, reactions, reporting, realtime, and polling fallback.
- Every production behavior change starts with a failing test/contract.

---

### Task 1: Read/Unread persistence and authorization contract

**Files:**
- Create: `database/migrations/2026_09_02_000001_add_read_at_to_private_messages_table.php`
- Create: `tests/Feature/PrivateMessagingReadStateTest.php`
- Modify: `app/Models/PrivateMessage.php`
- Modify: `app/Http/Controllers/PrivateChatController.php`
- Modify: `routes/web.php`

**Interfaces:**
- Produces `PrivateMessage::$casts['read_at'] = 'datetime'`.
- Produces authorized POST route `private-chats.read` for a `PrivateConversation`.
- Produces controller action `markRead(PrivateConversation $conversation): JsonResponse`.
- Formatted message payload exposes `read_at` and `is_read`.

- [ ] Write RED tests proving `read_at` is absent from the current contract, recipient can mark only incoming messages in their own conversation read, sender/non-participant cannot mutate arbitrary messages, and formatted payload reports read state.
- [ ] Verify RED in CI/PHPUnit before writing production code.
- [ ] Add nullable indexed `read_at` timestamp and model cast.
- [ ] Add membership-authorized `markRead()` that updates only messages whose `sender_id != currentUserId` and `read_at IS NULL`.
- [ ] Add the POST route inside the existing authenticated private-chat route group.
- [ ] Add `read_at`/`is_read` to `formatMessage()`.
- [ ] Verify targeted tests GREEN and commit `feat(private-chat): add read state contract`.

### Task 2: Unread aggregation and conversation-list contract

**Files:**
- Modify: `tests/Feature/PrivateMessagingReadStateTest.php`
- Modify: `app/Http/Controllers/PrivateChatController.php`
- Modify: `app/Http/Controllers/ChatRequestController.php`
- Modify: `resources/views/private-chats/index.blade.php`
- Modify: `resources/views/chat-requests/partials/body.blade.php`

**Interfaces:**
- Each conversation exposed to the view has `unread_count` without N+1 per-row message queries.
- Current-user unread total can be displayed in the private-messaging shell.

- [ ] Write RED tests for 0/1/multiple unread messages, excluding the current user's own messages and already-read messages.
- [ ] Verify RED.
- [ ] Add constrained unread count aggregation to conversation queries.
- [ ] Render unread badge and unread visual state in conversation rows.
- [ ] Verify GREEN and commit `feat(private-chat): expose unread conversation counts`.

### Task 3: Mobile-first «گفتگوهای خصوصی» shell and requests IA

**Files:**
- Create: `tests/Feature/PrivateMessagingUiContractTest.php`
- Modify: `resources/views/chat-requests/index.blade.php`
- Modify: `resources/views/chat-requests/partials/body.blade.php`
- Modify: `app/Http/Controllers/ChatRequestController.php`

**Interfaces:**
- Top-level shell: `گفتگوها | درخواست‌ها`.
- Requests sub-navigation: `دریافتی | ارسالی` plus compact status filters.
- Query contract adds a direction selector such as `box=received|sent` while preserving existing `section` compatibility.

- [ ] Write RED source/render tests for the new Persian labels, single focused list, direction selector, and absence of the old two-column `col-lg-6` request layout.
- [ ] Verify RED.
- [ ] Extend controller filtering for request direction without breaking AJAX partial responses.
- [ ] Rebuild request partial as one mobile-first list with touch targets >=44px, calm status chips, relative/compact metadata, accept/reject/continue actions.
- [ ] Add desktop max-width/polish only after the mobile layout is complete.
- [ ] Verify GREEN and commit `feat(private-chat): rebuild private messaging request shell`.

### Task 4: Mobile-first professional conversation screen

**Files:**
- Modify: `tests/Feature/PrivateMessagingUiContractTest.php`
- Modify: `resources/views/private-chats/show.blade.php`
- Modify: `app/Http/Controllers/PrivateChatController.php`

**Interfaces:**
- Integrated viewport shell with sticky header, scrollable timeline, sticky composer.
- No fake presence text; header only displays backed information.
- Sent messages render single-check when persisted and double-check when `read_at` is present.

- [ ] Write RED contracts forbidding fixed `max-height: 400px/500px` chat timeline behavior and requiring mobile viewport shell markers, sticky composer, read receipt marker, and text-only composer.
- [ ] Verify RED.
- [ ] Rebuild CSS/markup mobile-first using `min-height`/`100dvh` compatible sizing and safe-area padding for the bottom composer.
- [ ] Keep desktop within a centered messenger surface without changing the global unified layout.
- [ ] Render grouped message presentation where adjacent sender identity need not repeat.
- [ ] Move reaction/report actions to secondary tap/overflow presentation.
- [ ] Remove the local-user typing-indicator activation bug while preserving Echo whisper behavior.
- [ ] Verify GREEN and commit `feat(private-chat): deliver mobile-first conversation surface`.

### Task 5: Read receipt realtime/fallback convergence

**Files:**
- Create: `app/Events/PrivateMessagesRead.php`
- Modify: `tests/Feature/PrivateMessagingReadStateTest.php`
- Modify: `app/Http/Controllers/PrivateChatController.php`
- Modify: `resources/views/private-chats/show.blade.php`
- Verify: `routes/channels.php`

**Interfaces:**
- Private broadcast event `.private-messages.read` on existing `private-chat.{conversation}` channel.
- Payload contains conversation id and IDs/read timestamp sufficient to update sender ticks.

- [ ] Write RED event/payload/privacy contract tests.
- [ ] Verify RED.
- [ ] Broadcast read update after authorized mark-read.
- [ ] Listen in the conversation page and update sent-message receipt state without refresh.
- [ ] Ensure polling response also returns `is_read`, so no-Echo sessions converge.
- [ ] Verify GREEN and commit `feat(private-chat): synchronize read receipts`.

### Task 6: Professional profile request CTA without manager regression

**Files:**
- Modify: `tests/Feature/PrivateMessagingUiContractTest.php`
- Create: `resources/views/chat-requests/partials/profile-action.blade.php`
- Modify: `resources/views/profile/profile-member.blade.php`
- Modify: `resources/views/chat_request.blade.php`

**Interfaces:**
- Profile state-aware CTA: شروع گفتگو / درخواست ارسال شده / مشاهده درخواست / ادامه گفتگو / درخواست مجدد گفتگو.
- New/retry CTA opens an accessible modal on desktop and bottom-sheet presentation on mobile.
- Group manager cards/inbox keep their existing functional request forms/authorization.

- [ ] Write RED contracts for CTA states, accessible dialog markup, mobile bottom-sheet marker/styles, and removal of global `.btn`/`.list-group-item` overrides from reusable request partial.
- [ ] Verify RED.
- [ ] Extract profile-specific action UI from manager-specific request rendering.
- [ ] Implement state-aware CTA/modal and keep existing backend POST contract (`description`, optional `request_to_group`).
- [ ] Verify existing `ChatRequestFlowTest` remains GREEN.
- [ ] Commit `feat(private-chat): professionalize profile conversation requests`.

### Task 7: Validation and UAT gate

**Files:**
- Modify tests only if a genuine regression contract is discovered; do not weaken assertions to make CI pass.

- [ ] Run targeted `PrivateMessagingReadStateTest`, `PrivateMessagingUiContractTest`, and `ChatRequestFlowTest`.
- [ ] Run full PHPUnit validation and frontend build/source-contract validation used by the project.
- [ ] Review mobile widths first: 320, 360, 390/393, 412/430; then tablet and desktop.
- [ ] Perform two-user UAT: request → accept → send → unread → view → read tick, using realtime where available and polling fallback otherwise.
- [ ] Check reactions/reporting and group-manager request flow for regression.
- [ ] Record final checkpoint only after tests and UAT evidence are green; do not merge to `main`.
