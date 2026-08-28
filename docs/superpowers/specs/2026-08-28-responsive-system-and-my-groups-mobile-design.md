# EarthCoop Responsive System + My Groups Mobile Design

## Status
Approved direction from founder conversation on 2026-08-28. This spec defines the responsive system to adopt incrementally across EarthCoop, with `resources/views/groups/index.blade.php` as the first reference implementation.

## Problem
EarthCoop currently contains many desktop-first pages that are merely compressed into narrow viewports. The resulting failure modes include excessive nested horizontal padding, multi-column tables forced into mobile widths, vertical word stacking, oversized headings, unnecessary card-within-card surfaces, and horizontal scrolling for entity lists that should instead become mobile-native cards.

The My Groups page is a clear example: `groups-page-shell`, `dashboard-content`, accordion content, and table cell padding compound until a large portion of a 360px viewport is consumed before content is rendered. The same five- or six-column desktop table is then rendered inside the remaining width.

## Design Principles

1. **Responsive means alternate presentation, not scaled desktop.** Mobile may use a different information hierarchy and component structure while preserving the same capabilities and data.
2. **Global tokens, local semantics.** Shared responsive spacing, typography, surface, and entity-list primitives should be reusable. Domain-specific lists such as groups, projects, users, tickets, elections, and finance may define their own mobile representation.
3. **No blanket table-to-card CSS.** True analytical/data tables may remain horizontally scrollable. Entity lists should use mobile cards/list rows when a card representation communicates the same data more clearly.
4. **Avoid global regressions.** Do not rewrite `.container`, all headings, or all tables site-wide without explicit opt-in classes. Shared responsive primitives must be additive and scoped.
5. **Mobile-first density.** On narrow screens, remove redundant nested surfaces and reduce gutters, title sizes, section padding, and decorative whitespace before shrinking content.
6. **RTL-native behavior.** Cards, avatars, metadata, chevrons, badges, and actions must be correct in RTL without relying on accidental left/right positioning.
7. **No horizontal scroll for entity lists.** A group list, user list, project list, or ticket list should not require side-scrolling to read core information.

## Responsive Foundation

Add an opt-in responsive utility layer, scoped by semantic classes rather than globally overriding Bootstrap/Tailwind containers.

### Page shell
- Desktop maximum width remains page-specific.
- Mobile page gutter: `12px` to `16px`.
- Tablet gutter: `20px` to `24px`.
- Desktop gutter may remain `24px+`.
- A mobile page should not accumulate more than one primary outer gutter plus one intentional component padding.

### Surfaces
Define a responsive surface contract:
- Desktop surface padding: typically `24px-40px` depending on page.
- Mobile surface padding: `12px-16px`.
- Mobile may remove outer border/shadow where a nested surface already provides hierarchy.
- Avoid card-inside-card-inside-card unless each level has a distinct interaction role.

### Typography
Use opt-in responsive title classes:
- Page title: desktop about `2rem-2.25rem`; mobile about `1.45rem-1.7rem`.
- Section title: desktop about `1.25rem-1.5rem`; mobile about `1.05rem-1.2rem`.
- Entity title: desktop `0.95rem-1.05rem`; mobile `0.9rem-1rem`, maximum two lines.
- Metadata: `0.72rem-0.82rem` on mobile.
- Never reduce entity title width so aggressively that Persian words break letter/word-by-word vertically.
- Apply `min-width: 0`, sensible `line-height`, and two-line clamping where appropriate.

### Mobile page hero/header
For pages that have an internal hero/page header:
- Compact height and vertical padding.
- One clear page title.
- Optional concise subtitle/metadata.
- Primary action(s) remain visible but may collapse to icon + short label.
- Decorative content that consumes width without adding navigational value is removed on mobile.

### Entity-list primitive
Entity cards/list rows on mobile should have:
- leading/right-side avatar or icon, fixed `48-56px` for primary entities;
- central flexible content with `min-width: 0`;
- title in one/two lines;
- compact metadata row below title;
- optional status badge integrated with metadata rather than allocated a full column;
- trailing/left-side chevron or action affordance;
- whole-card navigation when semantically safe;
- clear disabled/pending state when navigation is unavailable.

## My Groups Reference Implementation

### Desktop
Preserve the existing desktop information architecture:
- page title;
- category tabs;
- data tables;
- shared sidebar.

Desktop tables remain appropriate because there is enough horizontal space and comparative scanning is useful.

### Mobile layout
At mobile/tablet breakpoint:
- shared sidebar remains collapsed using its existing responsive behavior;
- main page gutter becomes `12-16px`;
- `.dashboard-content` loses desktop-sized `2.5rem` padding and uses mobile surface spacing;
- category navigation remains accordion-based, but accordion body padding is reduced;
- the table representation is hidden for mobile;
- a dedicated mobile group-list representation is shown.

### Mobile group card
Each group renders as a compact RTL card/list item.

Right side:
- group avatar using canonical `avatar_url` accessor where available;
- fallback with two-character group initials when no image exists.

Center:
- group name, no word stacking, max two lines;
- metadata directly below: role, status, member count;
- examples: `مدیر · فعال · ۲ عضو`, `ناظر · در انتظار تأیید · ۱۲ عضو`.

Left side:
- chevron/entry affordance when accessible;
- inactive/pending treatment when not accessible.

Interaction:
- accessible active group: entire card links to `groups.chat`;
- pending group: non-navigation card with visible pending status;
- inactive membership: visible inactive state and a secondary `بازگردانی` action.

### Group avatar contract
The mobile card must use the normalized Group avatar URL contract already introduced elsewhere in the application. Do not reconstruct storage paths ad hoc in this page.

### Managed groups
`table-managed.blade.php` must receive the same mobile card treatment. Its extra desktop Operations column should collapse into the whole-card entry affordance on mobile. No six-column mobile table.

### Filters
Specialty filters on mobile:
- render as wrapping compact chips when they fit;
- if the number of filters creates excessive vertical height, use an intentional horizontal chip rail only for filters, not the group list;
- active state remains visually clear.

### Accordion
- one top-level category open at a time as currently implemented;
- reduce nested padding;
- preserve specialty/exclusive sub-sections;
- avoid unnecessary double borders/backgrounds on mobile.

## Shared Implementation Boundary

The first phase introduces scoped shared responsive primitives and applies them to My Groups. It must **not** automatically restyle every EarthCoop page.

Candidate shared classes should cover:
- responsive page shell/gutter;
- responsive surface;
- responsive page title/section title;
- mobile entity list/card anatomy;
- mobile metadata/badge row;
- optional compact hero/page-header.

After My Groups is validated, other problematic pages can migrate to these primitives deliberately. Pages with domain-specific visual needs may compose the primitives rather than inherit a universal component.

## Accessibility and Interaction
- Preserve keyboard navigation for links/buttons.
- Do not make a disabled/pending card look clickable.
- Use meaningful `aria-label` where an icon-only affordance is used.
- Preserve readable contrast for active/pending/inactive badges in light and dark mode.
- Do not hide essential information exclusively behind hover.

## Dark Mode
The new mobile cards and responsive surfaces must support existing `body.dark-mode` behavior. Do not introduce fixed white backgrounds without corresponding dark-mode rules.

## Performance / Data Access
The responsive redesign must not add per-card database queries. Avatar, membership role/status, and member counts should reuse already-loaded data where possible. If current partials perform N+1 queries, optimization may be included only where directly required by the touched rendering path.

## Testing Strategy

Add regression/source-contract tests that verify at minimum:
- My Groups defines a mobile-native list/card representation for both basic and managed groups;
- mobile representation includes avatar, group name, role, status, and member count;
- active groups remain navigable to `groups.chat`;
- inactive memberships expose restore action;
- desktop tables remain available;
- mobile CSS does not require horizontal scrolling for the group entity list;
- mobile page/surface padding is explicitly reduced;
- page title typography is responsive;
- no global `.container`, all-table, or all-heading override is introduced as part of this slice.

Run the existing full integration validation after focused tests pass.

## Non-goals for This Slice
- Redesigning every EarthCoop page in one commit.
- Replacing the shared sidebar architecture.
- Rewriting all tables globally.
- Changing group membership/business rules.
- Changing desktop information architecture unless required for parity.

## Rollout
1. Build shared opt-in responsive primitives.
2. Convert My Groups basic and managed lists to mobile-native cards while preserving desktop tables.
3. Polish mobile spacing, typography, accordion density, dark mode, and avatar fallback.
4. Validate desktop parity and mobile UAT.
5. Use this implementation as the reference pattern for the next mobile-deficient pages, migrating them deliberately rather than through blanket CSS overrides.

## Implementation Progress

- **2026-08-28 19:16 +03:30 — STARTED.** Founder approved execution and explicitly required every milestone to be recorded so work can resume without duplication after interruptions/new chats.
- Isolated implementation branch: `agent/pre-main-ui-polish-responsive`.
- Baseline source branch: `agent/pre-main-ui-polish` at commit `cf239a34d57e4977657a54030c14bd17965bc388`.
- Design commit: `6ac031a7207a35be4e5d9fe28a7334a0722ed4c3`.
- Implementation plan: `docs/superpowers/plans/2026-08-28-responsive-system-my-groups-implementation.md`; plan commit `9afd22a4ca306ec5400dd55225361b548063e6fa`.
- Persistent recovery ledger: `docs/superpowers/progress/2026-08-28-responsive-my-groups-progress.md`.
- **Task 1 / TDD RED:** focused contract created at `54283983e13d755f416d855bc5782c3999c8564f`. Run `33187171198`, job `98903057580` produced the intended `5/5` failures before implementation.
- **Task 2 / foundation:** `public/Css/responsive-system.css` introduced at `9a6fca09f758a40db93429f549450d698c845972`; unified layout load at `ab8cd15b48c52688e0adbc655496a930f5cf2613`. Shared primitives are scoped and do not globally override `.container`, headings, or tables.
- **Task 3 / mobile group cards:** basic cards at `b6984034cfcd65a13b1b8b362b6247b0789ac07f`; managed cards at `82fb6224d89ce89bef8a74bfb64bf33ec0a78a5f`. Mobile cards use canonical avatar URL, title, role, status, member count, whole-card navigation where allowed, and restore action for inactive membership. Desktop tables remain intact.
- **Task 4 / page chrome:** local-scope responsive filter runtime added at `11c6de22fbbd38ad86ee65bd7d5664ca5b07c262`; layout integration at `ce65bc3700d454a263422a6c1cd1e67bc3ef07a5`; My Groups mobile spacing/typography/accordion/filter/dark-mode rules at `ab13a4c24ffba46986a2bbe51eadbd5c87406699`.
- **Focused GREEN:** run `33188114972`, job `98906298465` completed successfully after the first complete responsive slice.
- **Cascade root check:** a later review found that the generic `.ec-entity-list { display:grid }` could outrank Tailwind `lg:hidden` because the responsive stylesheet loads later than Vite. A new regression test was committed at `06a9ad98b5f6ea478a3d8df295b1c42fe5654da3`; run `33188338628`, job `98907059274` correctly went RED. Explicit `[data-mobile-group-list]` / `[data-desktop-group-table]` breakpoint ownership was then added at `353dba8a0a23289db46425881549a69682e4ee25`; focused run `33188444138`, job `98907421494` returned GREEN.
- **Performance root check:** existing list rendering used `users()->count()` per group. A regression contract requiring bulk counts was committed at `888616fa92ba7aa4e68dce77d65c105223251669`; run `33188539633`, job `98907755047` correctly went RED. Basic list bulk member counts + specialty relation loading were implemented at `b599590cf951aad627405cb20bb4d531331775a1`; managed bulk counts at `132d4be4e0dc0baccfeaa93fa0918ff38a938f9e`. Focused run `33188653223`, job `98908151038` returned GREEN.
- Accidental `docs/superpowers/specs/.keep` was removed at `36cebeaed2149dd6e19d061a431ab1315756cf87`.
- **Current state:** code slice is focused-contract GREEN. Full integration validation is still being observed on the final documentation/checkpoint head before founder UAT. No merge to `main` has occurred or is authorized.
