# Group Control Center Capability Inventory

**Date:** 2026-08-27  
**Branch:** `agent/pre-main-ui-polish`  
**Purpose:** Characterize every currently visible group capability, search/filter contract, route target, and role gate before any UI control is moved or removed.

## Preservation rules

1. No current capability may be removed until its new destination exists and the relevant regression/UAT parity check passes.
2. Existing backend authorization/policies remain canonical. The redesign must not broaden permissions from the view layer.
3. Search/filter affordances are first-class capabilities. They must survive the migration with equivalent or better semantics.
4. Legacy Hero/Panel controls stay in place until the corresponding Control Center destination is proven.
5. `groups/{id}` remains available as the group dashboard; the frequent path from My Groups will move to Chat in a later task.

## Current surfaces

### Chat Hero — `resources/views/groups/partials/group_hero.blade.php`

The Hero currently duplicates its actions for mobile expanded state and desktop state. Current capabilities:

| Capability | Current hook / route | Current visibility | Planned destination |
|---|---|---|---|
| Open group panel | `data-chat-page-action="open-group-info"` | member in Chat | canonical Control Center CTA in Hero |
| Create post | `open-blog` | role != 5 | Content |
| Create poll | `open-poll` | role != 5 | Content |
| Participate in active election | `open-election` | election available + can participate | Governance (with contextual status in Content if useful) |
| Add/manage election | `open-election-admin` | roles 2/3 | Governance |
| Manage members | `manage-members` | role 3 | Members |
| Reports/moderation | `manage-reports` | role 3 | Governance |
| Group settings | `group-settings` | existing page condition | Governance |
| Leave group | `groups.logout` | member | Control Center footer/contextual action |
| Pinned-message count | stat chip | member | Content summary / keep only if useful |
| Post count | stat chip | member | Content summary or Group Dashboard |
| Poll count | stat chip | member | Content summary or Group Dashboard |
| Last activity | stat chip | member | Group Dashboard/contextual summary |

Hero identity/context that must remain available after simplification: avatar, group name, user role, membership status, member count, guest count when non-zero, location level, created date, short description.

### Legacy Group Info Panel — `resources/views/groups/partials/group_info_panel.blade.php`

Current top-level operational capabilities:

| Capability | Current control | Gate | Planned destination |
|---|---|---|---|
| Edit group | `open-group-edit` | non-level-10 + roles 2/3 | Governance |
| Add guest | `#addUserButton` | non-level-10 + roles 2/3 | Members |
| Manager chat request | `#addChatRequestButton` | non-level-10 + roles 2/3 | Members / Governance |
| Add election | `open-election-admin` | non-level-10 + roles 2/3 | Governance |
| Enable/disable session | `[data-session-toggle]` | non-level-10 + roles 2/3 | Governance |
| Manage session participation | `[data-session-admin-open]` | non-level-10 + roles 2/3 | Governance |
| Leave group | `groups.logout` | member | Control Center footer |

Current tabs and their target consolidation:

| Legacy tab | Existing content | New tab |
|---|---|---|
| `group` | user's related groups + group search | Tools / contextual group switcher; preserve search contract |
| `members` | active member list + profiles | Members |
| `admins` | managers/inspectors and governance identities | Members |
| `post` | posts | Content |
| `poll` | polls | Content |
| `election` | elections/candidates | Governance |
| `stats` | stats/reporting, manager-only | Governance / Group Dashboard as appropriate |

### Search/filter contracts that must survive

| Surface | Current input / selector | Exact current semantics | Migration requirement |
|---|---|---|---|
| Related groups | `#groupSearch` + `#searchType` | debounced 500ms; minimum 2 chars; `name` or `content`; server endpoint `/api/groups/search`; content search spans messages, blog titles, and poll questions; empty state + error state | Preserve name/content selector and server-backed semantics if group switcher stays in Control Center |
| Members | `#membersSearch` | debounced 200ms local filter over `data-name`, `data-role`, `data-email`; live `نمایش X از Y` count | Preserve name/role/email matching and live count |
| Managers/inspectors | `#searchManagers` | debounced 200ms local filter over `data-manager-search-text` | Preserve identity/name search across managers + inspectors |
| Posts | existing post search field in legacy panel | title/content-oriented search | Preserve within Content tab |
| Polls | existing poll search field in legacy panel | title/description-oriented search | Preserve within Content tab |
| Elections | existing election search field in legacy panel | candidate/election-oriented search | Preserve within Governance tab |
| Group Dashboard activities | `#activityFilter` | filter recent activity by all/messages/posts/polls/elections | Preserve on `groups/{id}` dashboard |

**Important:** Search controls are not cosmetic. They are capability parity gates. A legacy search control is not deleted until the replacement is present and verified.

## Group Dashboard — `resources/views/groups/show.blade.php`

Current dashboard characteristics to retain/reframe:

- group name/type
- role and membership status
- financial account number when membership active
- active-member count
- manager/inspector list
- recent activity aggregation (messages/posts/polls/elections) with `#activityFilter`
- Chat CTA (`groups.chat`) — copy should become «گفت‌وگوی گروه» / «بازگشت به گفت‌وگو»
- leave-group action (`groups.logout`)
- financial report shortcut (`groups.najm-bahar.reports`) for active membership
- group Najm Bahar dashboard shortcut (`groups.najm-bahar.dashboard`) currently shown to manager/inspector on this view
- Najm Hoda group panel shortcut (`groups.najm-hoda.panel`) currently shown to manager/inspector when global group assistant is enabled

The redesign may make additional tools discoverable from this dashboard, but it must not weaken their backend authorization.

## Independent tools and canonical routes

### Secretariat

The Secretariat is a real independent module under `app/Modules/Secretariat`. Group entry is:

- route: `secretariat.group`
- path: `/secretariat/groups/{group}`
- controller: `SecretariatDirectoryController::group`

The office-level routes remain policy-controlled under `/secretariat/offices/{office}`. The Control Center should expose only the group-directory entry; document/record access remains governed by Secretariat policies/controllers. Per approved product decision, this entry is discoverable to group members.

### Najm Hoda

Canonical group management entry already used by Group Dashboard:

- route: `groups.najm-hoda.panel`
- current UI gate: manager/inspector (`role` 2/3) plus global `najm-hoda.group_assistant.enabled`

Destination: Governance tab. If globally disabled, preserve a clearly disabled state rather than exposing a dead link.

### Najm Bahar

Canonical group finance entry already used by Group Dashboard:

- route: `groups.najm-bahar.dashboard`
- reporting route: `groups.najm-bahar.reports`

The group Najm Bahar UI already owns wallet/transfer/sub-account/report workflows; Chat must link into that system rather than rebuilding finance operations in the Control Center.

Destination label: **«حساب و امور مالی گروه — نجم بهار»** in Tools.

## Final target map

### Content
- create/browse posts
- create/browse polls
- content search/filter
- contextual pinned content shortcuts

### Members
- member list + profile navigation
- member search by name/role/email
- managers/inspectors + search
- member management for authorized manager
- add guest for authorized roles
- manager-chat request capability where currently authorized

### Governance
- active election / participation
- election creation/management
- election/candidate search
- session toggle + participation management
- reports/moderation
- settings + group edit
- stats/reporting where operational
- Najm Hoda for roles 2/3 when globally enabled

### Tools
- Group Dashboard (`groups.show`)
- Secretariat group directory (`secretariat.group`) for members; content remains policy-controlled
- Najm Bahar group finance dashboard (`groups.najm-bahar.dashboard`) according to existing backend authorization
- related-group switcher/search if retained inside Control Center

## Removal gate

Before deleting any legacy Hero or panel element, verify all four:

1. destination control exists;
2. route/action target is unchanged or intentionally mapped;
3. visibility/authorization parity is preserved;
4. relevant search/filter behavior still works.
