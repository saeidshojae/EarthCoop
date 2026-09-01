# Group Chat Unified Shell Design

## Goal
Move the Group Chat page onto EarthCoop's unified header/navigation components without forcing it into the generic page chrome, while giving chat an app-like identity that maximizes usable space on mobile and desktop.

## Current State
- `resources/views/groups/chat.blade.php` extends `layouts.chat`.
- `layouts.chat` duplicates header, navigation drawer, account dropdown placement, flash chrome, and hide/show behavior that already exist in the unified system.
- The chat page has specialized requirements that generic `layouts.unified` does not: fixed composer, group hero, overlays/modals, realtime runtime, no normal footer, and viewport-sensitive scrolling.
- The current chat header starts visible and reserves 64/72px of content space; its `IntersectionObserver` implementation hides/shows based on a sentinel rather than user scroll direction.

## Decision
Keep `layouts.chat` as a thin specialized shell for now, but remove its independently implemented site header/menu and reuse `components.header-unified` with a new `headerContext = chat`. This avoids a risky direct migration to generic `layouts.unified` while eliminating header/menu duplication.

## Chat Header Behavior
1. On initial entry to `/groups/chat/{group}`, the EarthCoop unified site header is hidden from the first rendered frame and reserves zero vertical space.
2. The Group Hero becomes the highest visible page chrome.
3. A meaningful downward gesture/scroll (decreasing `scrollY`) reveals the site header with a slide-down transition and increases the chat shell's top offset by the measured header height.
4. The first meaningful upward gesture/scroll (increasing `scrollY`) hides the site header again and collapses the top offset to zero.
5. Small jitter under a threshold does not toggle the header.
6. Behavior applies on both mobile/tablet and desktop.
7. While the unified navigation drawer, mobile account dropdown, desktop account dropdown, or another header-owned overlay is open, automatic hiding is suspended.
8. `prefers-reduced-motion` removes the animation but preserves state transitions.
9. Existing Group Hero expand/collapse, composer, modals, realtime runtime, Najm Hoda widget, permissions, and chat content behavior remain unchanged.

## Structure
- `layouts.chat` remains the HTML/body shell and page-specific asset boundary.
- `components.header-unified` receives `headerContext='chat'` and exposes stable data hooks/classes for the chat shell.
- `resources/js/group-chat-header-controller.js` owns direction-aware reveal/hide behavior. It does not own navigation menus themselves.
- `resources/js/group-chat-page.js` imports/boots the header controller only on Group Chat.
- Chat shell CSS is scoped under `body.chat-layout` / `[data-header-context="chat"]`.

## Initial State / No Flash
The server-rendered chat header receives a hidden chat-state class/data attribute. CSS therefore renders it translated above the viewport before JavaScript starts. The normal unified spacer is collapsed for chat context. JavaScript only changes state after a qualified gesture, preventing a visible header flash during page load.

## Direction Detection
- Track last `window.scrollY`.
- Ignore deltas smaller than 10px accumulated movement.
- `currentY < lastY` => downward gesture => reveal.
- `currentY > lastY` => upward gesture => hide.
- At `scrollY <= 0`, a downward overscroll/touch pull cannot be represented by negative scrollY on every browser, so touch `clientY` delta is additionally observed at the top boundary to reveal the header.
- Desktop users can reveal via wheel-up (negative `deltaY`) at or near the top; upward wheel hides again.

## Layout Offset
The controller measures the unified header with `getBoundingClientRect().height` and writes `--chat-site-header-height`. Revealed state sets the chat content top offset to this value; hidden state sets it to `0px`. This prevents the site header from covering the Group Hero.

## Compatibility
- No generic unified footer is introduced into chat.
- No route, controller, backend, permission, realtime, or polling behavior changes.
- Existing back-navigation remains supplied by `header-unified`.
- Existing mobile/desktop unified menus and account dropdowns are reused instead of duplicating their links.

## Testing
- Source contract: Chat shell includes `header-unified` with chat context and no duplicate legacy chat menu/header markup.
- JS unit/source contract: initial hidden state, direction threshold, touch/wheel top-boundary reveal, upward hide, suspension while menus are open.
- Existing Group Chat JavaScript/PHP regression suites.
- Production frontend build.
- Full Validation.
- Human UAT on mobile and desktop: initial hidden site header; pull/scroll down reveals; first upward gesture hides; menu/dropdown remains usable; Group Hero and composer remain aligned.

## Non-Goals
- Removing `layouts.chat` entirely in this change.
- Redesigning Group Hero or Group Info Panel.
- Changing chat navigation/content hierarchy beyond the site-header behavior.
- Changing realtime or backend behavior.
