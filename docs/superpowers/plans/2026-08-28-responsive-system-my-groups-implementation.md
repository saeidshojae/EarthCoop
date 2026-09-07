# EarthCoop Responsive System + My Groups Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Introduce a scoped responsive foundation for EarthCoop and make `My Groups` a professional mobile-native reference implementation without regressing desktop behavior.

**Architecture:** Add opt-in responsive primitives instead of global overrides, then render group entities as mobile cards while retaining desktop tables. The responsive layer defines page gutters, surfaces, title hierarchy, compact page headers/heroes, entity cards, metadata and no-horizontal-scroll behavior for entity lists. `My Groups` is the first consumer and remains the canonical reference for later page migrations.

**Tech Stack:** Laravel Blade, Tailwind/Bootstrap utilities already bundled via Vite, scoped CSS, Node source-contract tests, PHPUnit/full integration CI.

**Spec:** `docs/superpowers/specs/2026-08-28-responsive-system-and-my-groups-mobile-design.md`

## Global Constraints

- Work only on `agent/pre-main-ui-polish-responsive`; do not merge to `main`.
- Preserve desktop information architecture and current group business rules.
- Do not globally rewrite `.container`, all headings, or all tables.
- Mobile entity lists must not require horizontal scrolling.
- Group cards must use canonical `$group->avatar_url` when available.
- Pending/inactive groups must not look falsely clickable.
- Dark mode and RTL behavior are mandatory.
- No new per-card N+1 queries may be introduced.
- Every milestone updates this plan and the spec progress log before moving on.

---

## Progress Log

- 2026-08-28 19:16 +03:30 — Plan created from approved spec. Execution mode: inline. Branch isolated as `agent/pre-main-ui-polish-responsive`.

---

### Task 1: Add regression contract for responsive foundation and My Groups mobile representation

**Files:**
- Create: `tests/js/responsive/my-groups-mobile-contract.test.js`
- Read: `resources/views/groups/index.blade.php`
- Read: `resources/views/groups/partials/table-basic.blade.php`
- Read: `resources/views/groups/partials/table-managed.blade.php`
- Read: `public/Css/unified-styles.css`

**Interfaces:**
- Consumes: existing Blade partials and unified CSS.
- Produces: source contract that requires opt-in responsive primitives and mobile group cards while forbidding blanket global mobile rewrites.

- [ ] **Step 1: Write the failing source-contract test**

Create a Node test that asserts:

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');

const index = readFileSync('resources/views/groups/index.blade.php', 'utf8');
const basic = readFileSync('resources/views/groups/partials/table-basic.blade.php', 'utf8');
const managed = readFileSync('resources/views/groups/partials/table-managed.blade.php', 'utf8');
const responsive = readFileSync('public/Css/responsive-system.css', 'utf8');

// Shared opt-in foundation.
assert.match(responsive, /\.ec-page-shell/);
assert.match(responsive, /\.ec-page-title/);
assert.match(responsive, /\.ec-surface/);
assert.match(responsive, /\.ec-entity-list/);
assert.match(responsive, /\.ec-entity-card/);
assert.doesNotMatch(responsive, /(^|\})\s*\.container\s*\{/m);
assert.doesNotMatch(responsive, /(^|\})\s*(?:h1|h2|h3)\s*\{/m);
assert.doesNotMatch(responsive, /(^|\})\s*table\s*\{/m);

// My Groups consumes the foundation.
assert.match(index, /ec-page-shell/);
assert.match(index, /ec-surface/);
assert.match(index, /ec-page-title/);

// Both basic and managed groups have mobile-native cards and keep desktop tables.
for (const source of [basic, managed]) {
  assert.match(source, /data-mobile-group-list/);
  assert.match(source, /ec-entity-card/);
  assert.match(source, /group->avatar_url/);
  assert.match(source, /data-desktop-group-table/);
}

assert.match(basic, /groups\.relogout/);
assert.match(basic, /groups\.chat/);
assert.match(managed, /groups\.chat/);
```

- [ ] **Step 2: Run the focused test and verify RED**

Run:

```bash
node --test tests/js/responsive/my-groups-mobile-contract.test.js
```

Expected: FAIL because `public/Css/responsive-system.css` and mobile card markers do not yet exist.

- [ ] **Step 3: Record RED evidence in Progress Log**

Append the command, failing assertion/file, and commit SHA to this document and to the spec status log.

---

### Task 2: Introduce scoped responsive primitives

**Files:**
- Create: `public/Css/responsive-system.css`
- Modify: `resources/views/layouts/unified.blade.php`
- Test: `tests/js/responsive/my-groups-mobile-contract.test.js`

**Interfaces:**
- Consumes: existing CSS variables from `unified-styles.css`.
- Produces: `.ec-page-shell`, `.ec-surface`, `.ec-page-title`, `.ec-section-title`, `.ec-page-hero`, `.ec-entity-list`, `.ec-entity-card`, `.ec-entity-card__avatar`, `.ec-entity-card__body`, `.ec-entity-card__title`, `.ec-entity-card__meta`, `.ec-entity-card__status`, `.ec-entity-card__affordance`.

- [ ] **Step 1: Add the shared stylesheet to `layouts.unified`**

Insert after `unified-styles.css`:

```blade
<link rel="stylesheet" href="{{ asset('Css/responsive-system.css') }}">
```

- [ ] **Step 2: Implement opt-in responsive primitives**

The stylesheet must use scoped classes only. Minimum mobile behavior under `max-width: 767px`:

```css
.ec-page-shell { width: 100%; margin-inline: auto; }
.ec-surface { min-width: 0; }
.ec-page-title { margin: 0; }
.ec-entity-list { display: grid; gap: .625rem; min-width: 0; }
.ec-entity-card { min-width: 0; display: grid; grid-template-columns: 3.25rem minmax(0,1fr) auto; align-items: center; }
.ec-entity-card__avatar { width: 3.25rem; height: 3.25rem; }
.ec-entity-card__body { min-width: 0; }
.ec-entity-card__title { overflow-wrap: normal; word-break: normal; }
.ec-entity-card__meta { display: flex; flex-wrap: wrap; gap: .35rem .5rem; }

@media (max-width: 767px) {
  .ec-page-shell { padding-inline: .875rem; }
  .ec-surface { padding: .875rem; border-radius: 1rem; }
  .ec-page-title { font-size: clamp(1.45rem, 7vw, 1.7rem); line-height: 1.45; }
  .ec-section-title { font-size: 1.08rem; line-height: 1.55; }
  .ec-page-hero { padding: .875rem 1rem; min-height: auto; }
}
```

Include corresponding light/dark card colors and RTL-safe logical properties.

- [ ] **Step 3: Run focused test**

```bash
node --test tests/js/responsive/my-groups-mobile-contract.test.js
```

Expected: still FAIL on My Groups mobile-card requirements, but shared CSS assertions pass.

- [ ] **Step 4: Commit foundation**

Commit message:

```text
feat(responsive): add scoped mobile layout primitives
```

- [ ] **Step 5: Record milestone**

Update this plan and the spec progress log with commit SHA and focused test state.

---

### Task 3: Convert basic and managed group lists to mobile-native entity cards

**Files:**
- Modify: `resources/views/groups/partials/table-basic.blade.php`
- Modify: `resources/views/groups/partials/table-managed.blade.php`
- Test: `tests/js/responsive/my-groups-mobile-contract.test.js`

**Interfaces:**
- Consumes: shared `.ec-entity-*` primitives and existing group membership/access rules.
- Produces: desktop table + mobile card representations using the same group data semantics.

- [ ] **Step 1: Preserve desktop tables explicitly**

Wrap existing tables in:

```blade
<div class="data-table-container hidden md:block" data-desktop-group-table>
    ...existing table...
</div>
```

- [ ] **Step 2: Add mobile list to `table-basic`**

Add before/after the desktop wrapper:

```blade
<div class="ec-entity-list md:hidden" data-mobile-group-list>
    @forelse($groups as $group)
        {{-- reuse the same computed pivot/role/status/canAccess data --}}
        @if($canAccess)
            <a href="{{ route('groups.chat', $group) }}" class="ec-entity-card ec-entity-card--interactive">
                ...
            </a>
        @else
            <article class="ec-entity-card" aria-disabled="true">
                ...
            </article>
        @endif
    @empty
        <div class="ec-empty-state">{{ $emptyMessage }}</div>
    @endforelse
</div>
```

Card requirements:
- avatar: `$group->avatar_url`, fallback initials;
- title: group name, maximum two lines;
- metadata: `$roleText`, `$statusLabel`, member count;
- active: whole card navigates to `groups.chat`;
- inactive: secondary `groups.relogout` action;
- pending: no false navigation affordance.

- [ ] **Step 3: Add mobile list to `table-managed`**

Same visual anatomy, with active manager role and whole-card link to `groups.chat`. Desktop Operations column remains desktop-only.

- [ ] **Step 4: Run focused contract**

```bash
node --test tests/js/responsive/my-groups-mobile-contract.test.js
```

Expected: PASS.

- [ ] **Step 5: Commit mobile group cards**

Commit message:

```text
feat(groups): add mobile-native group cards
```

- [ ] **Step 6: Record milestone**

Update plan/spec progress logs with test output and commit SHA.

---

### Task 4: Apply responsive foundation to My Groups page chrome, typography, spacing, filters and accordions

**Files:**
- Modify: `resources/views/groups/index.blade.php`
- Modify: `public/Css/responsive-system.css` only when a shared primitive is genuinely reusable.
- Test: `tests/js/responsive/my-groups-mobile-contract.test.js`

**Interfaces:**
- Consumes: `.ec-page-shell`, `.ec-surface`, `.ec-page-title` and mobile cards.
- Produces: page-level professional mobile hierarchy with no nested desktop spacing.

- [ ] **Step 1: Opt the page into responsive primitives**

Use:

```blade
<div class="groups-page-shell ec-page-shell container mx-auto py-6">
...
<main class="dashboard-content ec-surface">
...
<h2 class="ec-page-title">...</h2>
```

- [ ] **Step 2: Add mobile-specific page rules**

At `max-width: 767px` enforce:
- outer gutter approximately `14px`;
- `.dashboard-content` padding `0` or a single `12-14px` surface layer, not `2.5rem`;
- page title around `1.5rem` and tighter margin;
- accordion item radius/padding reduced;
- accordion content around `.65rem-.8rem`;
- nested toggle section spacing reduced;
- filter chips wrap cleanly; only filter chips may intentionally scroll if necessary;
- no `overflow-x:auto` is needed for the mobile entity list.

- [ ] **Step 3: Verify desktop selectors remain intact**

Desktop tables and desktop tabs must still render at `md/lg` widths.

- [ ] **Step 4: Run focused test**

```bash
node --test tests/js/responsive/my-groups-mobile-contract.test.js
```

Expected: PASS.

- [ ] **Step 5: Commit page polish**

Commit message:

```text
feat(groups): polish My Groups mobile layout
```

- [ ] **Step 6: Record milestone**

Update plan/spec progress logs with exact responsive decisions and commit SHA.

---

### Task 5: Verify data/query behavior and full integration

**Files:**
- Inspect: group controller/service responsible for `groups.index` data.
- Modify only if the touched mobile rendering reveals direct N+1 queries in the current rendering path.
- Test: focused Node contract plus existing Group/Full Validation suites.

**Interfaces:**
- Consumes: final responsive implementation.
- Produces: verified checkpoint suitable for founder UAT.

- [ ] **Step 1: Inspect member-count and pivot access pattern**

Confirm mobile cards do not add queries beyond existing desktop rendering. If `users()->count()` already causes N+1, replace with controller eager `withCount('users')` and render `users_count` in both desktop and mobile representations, with regression coverage.

- [ ] **Step 2: Run focused Node test**

```bash
node --test tests/js/responsive/my-groups-mobile-contract.test.js
```

Expected: PASS, 0 failures.

- [ ] **Step 3: Run existing Group Chat JavaScript suite**

Run the repository's existing group-chat Node test command used by CI.

Expected: PASS.

- [ ] **Step 4: Run full integration validation**

Use `.github/workflows/integration-full-validation.yml` on the current branch/PR validation path.

Expected: all gates success.

- [ ] **Step 5: Record final checkpoint**

Update both documents with:
- final HEAD SHA;
- focused test result;
- Full Validation run ID/result;
- founder UAT items for 360px, 390px, 768px and desktop;
- explicitly note that no merge to `main` occurred.
