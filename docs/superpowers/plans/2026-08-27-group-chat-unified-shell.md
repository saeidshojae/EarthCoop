# Group Chat Unified Shell Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reuse EarthCoop's unified site header/navigation in Group Chat while keeping a specialized chat shell whose site header starts hidden, reveals on downward pull/scroll, and hides on the first meaningful upward gesture on mobile and desktop.

**Architecture:** Keep `layouts.chat` as a thin app-shell boundary, replace its duplicate header/menu with `components.header-unified` using `headerContext='chat'`, and move chat-only reveal/hide behavior into a dedicated frontend controller. The Group Hero, composer, realtime runtime, modals, permissions, and backend remain unchanged.

**Tech Stack:** Laravel 9 Blade, Vite, Alpine.js, vanilla JavaScript, Node test runner, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-08-27-group-chat-unified-shell-design.md`

## Global Constraints

- No merge to `main`.
- No backend, permission, realtime, polling, Group Hero, composer, modal, or Group Info Panel behavior changes.
- The unified site header is hidden from the first rendered frame on Group Chat and reserves zero space until revealed.
- The behavior applies on mobile/tablet and desktop.
- Reuse existing unified navigation/account components; do not recreate their links in Chat.
- Auto-hide is suspended while a unified header menu/dropdown is open.
- Respect `prefers-reduced-motion`.

---

### Task 1: Lock the unified-shell source contract

**Files:**
- Modify: `tests/js/group-chat/source-contract.test.js`
- Modify: `resources/views/layouts/chat.blade.php`
- Modify: `resources/views/components/header-unified.blade.php`

**Interfaces:**
- Consumes: existing `components.header-unified` Blade component.
- Produces: chat context marker `data-header-context="chat"`, shell class `chat-site-header-hidden`, and no duplicate `.chat-mini-header` / `.chat-menu-sidebar` markup in `layouts.chat`.

- [ ] **Step 1: Write failing source-contract assertions**

Add assertions that `layouts.chat` includes:

```js
assert.match(layoutSource, /header-unified[^\n]*headerContext[^\n]*chat/);
assert.doesNotMatch(layoutSource, /class="chat-mini-header"/);
assert.doesNotMatch(layoutSource, /class="chat-menu-sidebar"/);
```

and that `header-unified.blade.php` recognizes chat context:

```js
assert.match(headerSource, /\$isChatHeader\s*=\s*\$headerContext\s*===\s*'chat'/);
assert.match(headerSource, /chat-site-header-hidden/);
```

- [ ] **Step 2: Run the focused JS source-contract test**

Run:

```bash
node --test tests/js/group-chat/source-contract.test.js
```

Expected: FAIL because the legacy Chat header/menu still exists and chat context is not recognized.

- [ ] **Step 3: Make `header-unified` chat-aware**

Add:

```php
$isChatHeader = $headerContext === 'chat';
```

Render the header with `chat-site-header-hidden` only for chat context and emit stable `data-chat-site-header` marker. Keep existing welcome/default behavior unchanged.

- [ ] **Step 4: Thin `layouts.chat`**

Replace the legacy standalone header/menu/observer with:

```blade
@include('components.pwa-splash')
@include('components.header-unified', ['headerContext' => 'chat'])
```

Keep chat-specific body, flash messages, content wrapper, scripts, and Najm Hoda widget. Remove only duplicate legacy header/menu CSS/markup/observer JS.

- [ ] **Step 5: Re-run focused source-contract tests**

Run:

```bash
node --test tests/js/group-chat/source-contract.test.js
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add tests/js/group-chat/source-contract.test.js resources/views/layouts/chat.blade.php resources/views/components/header-unified.blade.php
git commit -m "refactor(chat): reuse unified site header shell"
```

---

### Task 2: Add direction-aware Chat header controller

**Files:**
- Create: `resources/js/group-chat-header-controller.js`
- Modify: `resources/js/group-chat-page.js`
- Modify: `tests/js/group-chat/source-contract.test.js`
- Modify: `resources/views/layouts/chat.blade.php`

**Interfaces:**
- Consumes: `[data-chat-site-header]`, `.chat-content-wrapper`, existing unified drawer/dropdown DOM.
- Produces: `createGroupChatHeaderController(options)` and CSS state class `chat-site-header-hidden`.

- [ ] **Step 1: Write failing controller/source tests**

Require source evidence for:

```js
createGroupChatHeaderController
CHAT_HEADER_GESTURE_THRESHOLD
wheel
touchstart
touchmove
scroll
chat-site-header-hidden
```

and verify `group-chat-page.js` imports and boots the controller.

- [ ] **Step 2: Run focused JS tests**

Run:

```bash
node --test tests/js/group-chat/source-contract.test.js
```

Expected: FAIL because the controller does not exist.

- [ ] **Step 3: Implement minimal controller**

Create a controller with:

```js
export const CHAT_HEADER_GESTURE_THRESHOLD = 10;
export function createGroupChatHeaderController({
  header = document.querySelector('[data-chat-site-header]'),
  content = document.querySelector('.chat-content-wrapper'),
  win = window,
} = {}) { /* ... */ }
```

Required behavior:
- Initial state remains hidden.
- Measure header height and write `--chat-site-header-height`.
- Meaningful decreasing `scrollY` reveals.
- Meaningful increasing `scrollY` hides.
- `wheel` with negative delta reveals; positive delta hides.
- At top boundary, downward `touchmove` reveals.
- Upward touch hides.
- Ignore deltas below 10px.
- Do not hide while `#site-header-mobile-menu`, `.mobile-account-dropdown`, or `#user-dropdown-menu` is visibly open.
- Return `destroy()` removing listeners.

- [ ] **Step 4: Add shell CSS**

Scope under `body.chat-layout`:

```css
body.chat-layout header.site-header-unified[data-header-context="chat"] {
    transition: transform .25s ease, opacity .25s ease;
}
body.chat-layout header.site-header-unified[data-header-context="chat"].chat-site-header-hidden {
    transform: translateY(-100%) !important;
    opacity: 0;
    pointer-events: none;
}
body.chat-layout > .site-header-spacer { height: 0 !important; }
body.chat-layout .chat-content-wrapper {
    padding-top: var(--chat-site-header-offset, 0px) !important;
    transition: padding-top .25s ease;
}
@media (prefers-reduced-motion: reduce) {
    body.chat-layout header.site-header-unified[data-header-context="chat"],
    body.chat-layout .chat-content-wrapper { transition: none !important; }
}
```

Controller sets `--chat-site-header-offset` to measured height only when revealed.

- [ ] **Step 5: Boot from Group Chat entry**

Import controller in `resources/js/group-chat-page.js` and initialize once after DOM readiness; register cleanup in existing Group Chat lifecycle where available.

- [ ] **Step 6: Run focused Group Chat JS tests**

Run:

```bash
node --test tests/js/group-chat/source-contract.test.js
```

Expected: PASS.

- [ ] **Step 7: Run production build**

Run:

```bash
npm run build
```

Expected: successful Vite build with Group Chat page entry.

- [ ] **Step 8: Commit**

```bash
git add resources/js/group-chat-header-controller.js resources/js/group-chat-page.js resources/views/layouts/chat.blade.php tests/js/group-chat/source-contract.test.js
git commit -m "feat(chat): add app-like unified header gestures"
```

---

### Task 3: Regression and UAT gate

**Files:**
- No production files unless a regression is proven.

**Interfaces:**
- Consumes: Tasks 1-2 complete.
- Produces: validated checkpoint ready for human UAT.

- [ ] **Step 1: Run Group Chat PHP regression suite**

```bash
php artisan test tests/Feature/GroupChat
```

Expected: PASS.

- [ ] **Step 2: Run Group Chat JS regression suite**

```bash
node --test tests/js/group-chat/*.test.js
```

Expected: PASS.

- [ ] **Step 3: Run production frontend build**

```bash
npm run build
```

Expected: PASS.

- [ ] **Step 4: Trigger/check Full Validation**

Expected: every regression gate and Full Project PHPUnit PASS.

- [ ] **Step 5: Human UAT on mobile and desktop**

Verify:
1. On initial Chat entry, Group Hero is at the top and EarthCoop site header is not visible.
2. Pull/scroll downward reveals the unified site header without covering Group Hero.
3. First meaningful upward gesture hides it again.
4. Hamburger navigation remains fully usable while open.
5. Account dropdown remains fully usable while open.
6. Group Hero expand/collapse still works.
7. Composer remains positioned correctly.
8. Post/Poll/Election modals still open correctly.
9. Back navigation from the unified header follows the existing history behavior.

- [ ] **Step 6: Record final checkpoint**

Do not merge to `main`; report exact branch/commit/CI/UAT state.
