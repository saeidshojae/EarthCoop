# Private Messaging Professionalization — Phase 1 Design

Date: 2026-09-02
Branch: `agent/private-messaging-professionalization`
Base checkpoint: `39c9b4e6d0207ea7054a81a19605f964419de294`

## 1. Goal

Professionalize EarthCoop private messaging UX while preserving the existing request/conversation/realtime backend where it is already sound.

Phase 1 is intentionally limited to:

- professional responsive UI/UX for private conversations,
- professional request flow from member profile,
- received/sent request management,
- reliable text messaging,
- unread counts,
- read receipts,
- preserving existing authorization, realtime delivery, polling fallback, reactions and reporting without allowing them to dominate the UI.

Out of scope for Phase 1: file/image/video/audio attachments, voice messages, reply, forward, edit/delete, message search, and other advanced messenger features.

## 2. Product Information Architecture

The user-facing feature name becomes **«گفتگوهای خصوصی»**.

The existing `/chat-requests` route may remain for compatibility, but the UI should not present “chat requests” as the product name.

Top-level structure:

1. **گفتگوها**
2. **درخواست‌ها**

Inside **درخواست‌ها**:

- **دریافتی**
- **ارسالی**

Each request list may expose lightweight status filters:

- همه
- در انتظار
- پذیرفته‌شده
- ردشده

The current two-column received/sent Bootstrap layout is replaced by a single focused list controlled by the received/sent segmented control.

## 3. Conversations List UX

Each conversation row should show:

- counterparty avatar,
- member name,
- latest message preview,
- latest activity time,
- unread badge when unread messages exist,
- visual emphasis for unread conversations.

Ordering remains by latest message/activity.

Empty state should explain that private conversations begin through a member profile request.

Desktop uses a spacious messenger-style surface. Mobile uses a full-width conversation list and opens the selected conversation as a dedicated full-screen chat view.

## 4. Conversation Screen UX

Replace the current stacked-card presentation with one integrated messenger surface:

- stable conversation header at top,
- scrollable message timeline in the middle,
- sticky composer at bottom,
- responsive height based on viewport instead of the current fixed 500px message area.

Header includes avatar, member name, back/navigation affordance on mobile, and only status information that is actually backed by data.

Composer in Phase 1 contains only:

- auto-growing text field,
- send action.

No placeholder attachment or microphone controls are shown before those capabilities exist.

Message bubbles should group consecutive messages from the same sender where practical. Avatar duplication should be reduced. Reactions and reporting remain available as secondary interactions through hover/tap/overflow patterns instead of permanent visual clutter.

## 5. Profile → Request Flow

The existing permanently visible textarea-based request form on the member profile is replaced by a context-aware primary action in the profile action area.

CTA state machine:

- no prior relationship → **شروع گفتگو**,
- current user already sent pending request → **درخواست ارسال شده**,
- current user has received a pending request → **مشاهده درخواست**,
- accepted relationship → **ادامه گفتگو**,
- rejected prior request → **درخواست مجدد گفتگو**.

For a new or repeat request, clicking the CTA opens a focused modal/bottom sheet containing:

- target member identity,
- a short explanation of why an introductory request message is needed,
- request message textarea,
- cancel and submit actions.

Existing group-manager request context must remain functional and must not be broken by the profile redesign.

## 6. Request Management UX

Received request cards show:

- sender avatar and name,
- request timestamp,
- request message,
- status,
- primary **پذیرفتن** and secondary **رد کردن** actions while pending.

Accepting a request continues to create/reuse the private conversation and redirects directly to it.

Sent request cards show:

- receiver identity,
- submitted message,
- request time,
- explicit status,
- **ورود به گفتگو** when accepted,
- a controlled resend action when the previous request was rejected and backend rules allow it.

Rejected state should be visually calm and informative rather than presented as a large destructive alert.

## 7. Read/Unread Contract

Phase 1 adds a real read-state contract.

Recommended persistence:

- `private_messages.read_at` nullable timestamp for the current one-to-one model.

A message is unread when:

- sender is not the current user,
- `read_at` is null.

A conversation unread count is derived from unread messages belonging to that conversation and received by the current user.

When the receiver actively opens/views the conversation, unread incoming messages should be marked read. The update must be authorized against conversation membership.

Sender-side UI states:

- single neutral check = persisted/sent successfully,
- double emphasized check = read by the other participant.

Do not introduce a separate “delivered” state in Phase 1.

Read updates should propagate through realtime when Echo is available, with the current polling architecture remaining a fallback for eventual state synchronization.

## 8. Realtime and Fallback

Preserve the existing private channel authorization and message broadcast architecture.

Phase 1 adds a read-status event/payload sufficient for the sender UI and conversation list unread state to update without a page refresh.

If realtime is unavailable, polling must continue to fetch new messages and should also converge unread/read UI state.

The known typing-indicator UX bug, where the local user can activate their own remote typing indicator, should be corrected while touching this surface.

## 9. Backend Changes

Keep existing `ChatRequestController`, `PrivateChatController`, `PrivateConversation`, `PrivateMessage`, channel authorization and notification flow unless a targeted change is required.

Expected targeted backend additions:

- migration for read state,
- model cast/fill protection as appropriate,
- endpoint or controller action for marking messages read,
- unread-count aggregation for conversation/request shell,
- read-status broadcasting,
- formatted API payload fields for `read_at` / `is_read`,
- query updates to avoid N+1 or per-row unread queries.

Do not redesign attachments or media schema in this phase.

## 10. UI Implementation Boundaries

Private messaging pages remain under `layouts.unified` unless an existing project-wide layout contract requires otherwise.

The redesign should be localized to private messaging/profile request surfaces and should not rewrite unrelated global navigation or other module layouts.

Prefer dedicated private-messaging CSS/components over broad generic selectors such as `.btn`, `.btn-success`, or `.list-group-item` inside reusable partials.

The existing `chat_request.blade.php` partial currently mixes profile request UI, manager request UI, request inbox UI and generic styling. Phase 1 should split responsibilities enough to prevent profile polish from regressing group-manager request workflows.

## 11. Accessibility and Responsive Requirements

- RTL-first layout.
- Keyboard-accessible request modal and composer.
- Visible focus states.
- Adequate touch targets on mobile.
- Semantic labels for status and actions.
- No color-only indication of unread/read/request status.
- Long names and long messages must not break layout.
- Mobile conversation screen should use available viewport height without nested-page scroll traps.

## 12. Testing Strategy

Implementation must be contract/test-first.

Required coverage includes:

- sender can create a request with message,
- self-request rejected,
- duplicate/pending request behavior preserved,
- rejected request can follow existing resend contract,
- only authorized receiver/manager can accept or reject,
- accepting creates or reuses exactly one conversation,
- accepted request redirects to conversation,
- participant-only private message access remains enforced,
- text message max length and validation remain enforced,
- unread message remains unread before recipient opens conversation,
- recipient marks incoming messages read,
- sender cannot mark arbitrary other conversations read,
- read state is exposed in formatted message payload,
- unread counts are accurate,
- read broadcast authorization remains private,
- polling fallback still receives messages,
- profile CTA state matches relationship state,
- request lists render received/sent/status states correctly,
- responsive/source-contract tests cover the new messenger shell where the project already uses such tests.

## 13. UAT Gate

Phase 1 is not complete until two-user UAT proves:

1. User A opens User B profile and sends an introductory request.
2. User A sees it under sent requests as pending.
3. User B sees it under received requests.
4. User B accepts it and is taken into the conversation.
5. User A sees the resulting conversation without duplicate conversation creation.
6. A sends a text message; B receives it via realtime or fallback.
7. Before B views it, A sees sent/not-read state and B has an unread indicator.
8. When B views the conversation, unread count clears and A sees the read check state update.
9. Both desktop and mobile layouts remain usable and visually coherent.
10. Existing report/reaction/request authorization behaviors remain functional.

## 14. Safety / Scope Guardrails

- No merge to `main` during this work.
- Work stays on the isolated private-messaging branch.
- Preserve all economic-system, invitation, Najm Bahar, Stock, Najm Hoda, elections, secretariat and group-chat changes inherited from the base checkpoint.
- No advanced media/attachment work in Phase 1.
- No broad global-layout rewrite as part of this feature.
