# Current Elections Center Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** تبدیل مسیر «انتخابات جاری» به مرکز عملیاتی واحد برای انتخابات سیستمی و درون‌گروهی، با eligibility واقعی، CTA دقیق، UI/UX mobile-first و انتقال چرخه‌های تاریخی به صفحه مستقل «تاریخچه انتخابات من».

**Architecture:** یک read-model read-only جدید داده‌های دو موتور مستقل `Election` و `Poll(main_type=0)` را از طریق adapterهای جداگانه به قرارداد UI مشترک نرمال می‌کند. Controller فقط snapshot این سرویس را به view می‌دهد؛ Blade هیچ قانون eligibility یا lifecycle را دوباره پیاده نمی‌کند. مسیر تاریخچه مستقل باقی می‌ماند و صفحه نظرسنجی نیز فقط `main_type=1` را می‌گیرد.

**Tech Stack:** Laravel 12 / PHP 8.2+, Eloquent, Blade, Bootstrap/Tailwind utility classes موجود در `layouts.unified`, PHPUnit feature/contract tests, MySQL-compatible test environment.

**Spec:** `docs/superpowers/specs/2026-09-07-current-elections-center-design.md`

## Global Constraints

- هیچ merge یا تغییر مستقیم روی `main` انجام نشود.
- branch اجرا: `agent/current-elections-center`.
- دو موتور انتخابات در دامنه/دیتابیس ادغام نشوند؛ فقط read model مشترک ساخته شود.
- قوانین ثبت رأی موجود تغییر نکنند؛ UI باید مجوز backend را بازتاب دهد، نه قانون جدید بسازد.
- انتخابات سیستمی جاری = lifecycleهای non-terminal: `scheduled`, `open`, `closed`, `tallying`, `awaiting_acceptance`, `appointing`.
- انتخابات سیستمی تاریخی = `filled`, `exhausted`, `cancelled`.
- انتخابات درون‌گروهی = `Poll.main_type = 0`؛ نظرسنجی عادی = `Poll.main_type = 1`.
- eligibility انتخابات سیستمی از snapshot/service canonical موجود؛ eligibility انتخابات داخلی از `PollPolicy::vote()` + active/not-expired.
- صفحه جدید mobile-first و card-first باشد؛ جدول عریض primary layout نباشد.
- بازدید صفحات read-only هیچ mutation، seed یا enrollment ناخواسته ایجاد نکند.
- تاریخچه انتخابات سیستمی فعلی نباید از بین برود.

---

### Task 1: قفل کردن قرارداد read model و رفتار جاری/تاریخی

**Files:**
- Create: `app/Services/Elections/CurrentElectionCenterService.php`
- Create: `app/Services/Elections/SystemicElectionCurrentItemAdapter.php`
- Create: `app/Services/Elections/InternalElectionCurrentItemAdapter.php`
- Create: `tests/Feature/Elections/CurrentElectionCenterServiceTest.php`

**Interfaces:**
- Produces: `CurrentElectionCenterService::forUser(\App\Models\User $user): array`
- Output keys: `systemic`, `internal`, `summary`.
- Every item exposes: `source_type`, `source_id`, `group_id`, `group_name`, `title`, `status_key`, `status_label`, `is_current`, `eligible`, `eligibility_label`, `has_voted`, `can_vote_now`, `can_edit_vote`, `starts_at`, `ends_at`, `deadline_label`, `primary_action`, `secondary_action`, `priority_bucket`.

- [ ] **Step 1: Write failing service tests**

Create tests covering at minimum:

```php
public function test_systemic_non_terminal_cycles_are_current_and_terminal_cycles_are_excluded(): void
{
    // Arrange one member group with Open, AwaitingAcceptance and Filled elections.
    // Assert service returns Open + AwaitingAcceptance, not Filled.
}

public function test_systemic_cta_uses_real_voter_eligibility_snapshot(): void
{
    // Arrange an open election with voter_eligible=true for user A and false for user B.
    // Assert A => can_vote_now=true and primary action vote/edit.
    // Assert B => can_vote_now=false and primary action detail-only.
}

public function test_internal_elections_are_loaded_from_main_type_zero_only(): void
{
    // Arrange active internal election main_type=0 and active poll main_type=1.
    // Assert only main_type=0 appears in internal items.
}

public function test_internal_eligibility_matches_poll_policy_and_expiry(): void
{
    // Active participating member => eligible/can_vote_now.
    // Inactive/forbidden membership or expired poll => cannot vote.
}
```

- [ ] **Step 2: Run test to verify RED**

Run:

```bash
php artisan test tests/Feature/Elections/CurrentElectionCenterServiceTest.php
```

Expected: FAIL because service/adapters do not exist.

- [ ] **Step 3: Implement minimal systemic adapter**

`SystemicElectionCurrentItemAdapter` must:

```php
public function itemsFor(User $user): Collection
```

- query only elections whose group contains the user;
- include only `ElectionLifecycleStatus` cases where `isTerminal() === false`;
- eager-load group, user votes and eligibility snapshot needed for the current user;
- never call enrollment/mutation helpers;
- derive `eligible` from existing snapshot rows/canonical eligibility read path;
- `can_vote_now = lifecycle === Open && eligible`;
- `has_voted` from current `Vote` rows for that election/voter;
- `can_edit_vote = can_vote_now && has_voted`;
- action label is `ثبت رأی`, `ویرایش رأی`, or `مشاهده جزئیات` accordingly;
- detail action routes to canonical election portal; vote action routes to the canonical group ballot surface.

- [ ] **Step 4: Implement minimal internal adapter**

`InternalElectionCurrentItemAdapter` must:

```php
public function itemsFor(User $user): Collection
```

- query `Poll::where('main_type', 0)` only in user-related groups;
- keep active/non-expired items only;
- evaluate `$user->can('vote', $poll)` and combine with `is_active` / `isExpired()` exactly as the vote endpoint does;
- derive `has_voted` from `PollVote`;
- generate vote/detail actions without mutating the poll.

- [ ] **Step 5: Implement aggregator and deterministic sorting**

`CurrentElectionCenterService::forUser()` returns:

```php
[
    'systemic' => $systemicItems,
    'internal' => $internalItems,
    'summary' => [
        'action_required' => $count,
        'related' => $count,
        'total' => $count,
    ],
]
```

Sort `action_required` before `related`; then nearest deadline; then newest source id.

- [ ] **Step 6: Run targeted service tests**

```bash
php artisan test tests/Feature/Elections/CurrentElectionCenterServiceTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/Elections/CurrentElectionCenterService.php app/Services/Elections/SystemicElectionCurrentItemAdapter.php app/Services/Elections/InternalElectionCurrentItemAdapter.php tests/Feature/Elections/CurrentElectionCenterServiceTest.php
git commit -m "feat(elections): add unified current election read model"
```

---

### Task 2: تفکیک route/controller «انتخابات جاری» از «تاریخچه انتخابات من»

**Files:**
- Modify: `app/Http/Controllers/Profile/HistoryController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Elections/CurrentElectionRoutesTest.php`

**Interfaces:**
- Existing `history.election` remains the user-facing current elections route for compatibility.
- New route: `history.election-history` -> independent history page.
- `HistoryController::election(CurrentElectionCenterService $service)` renders current center.
- `HistoryController::electionHistory()` owns the old lifecycle/history query.

- [ ] **Step 1: Write failing routing/controller tests**

```php
public function test_history_election_route_renders_current_center_contract(): void
{
    $this->actingAs($user)
        ->get(route('history.election'))
        ->assertOk()
        ->assertViewIs('history.election');
}

public function test_election_history_route_preserves_terminal_and_non_terminal_cycles(): void
{
    $this->actingAs($user)
        ->get(route('history.election-history'))
        ->assertOk()
        ->assertViewIs('history.election-history');
}
```

Also assert the current route no longer passes the legacy `$currentElections` history collection.

- [ ] **Step 2: Run RED**

```bash
php artisan test tests/Feature/Elections/CurrentElectionRoutesTest.php
```

Expected: FAIL because new history route/view method does not exist.

- [ ] **Step 3: Move legacy history query**

Move the existing lifecycle-driven `Election::query()->whereHas('group.users', ...)` + history eager-loads from `election()` into `electionHistory()` without changing its semantics.

- [ ] **Step 4: Wire current center**

`election()` must call:

```php
$snapshot = $currentElectionCenterService->forUser(auth()->user());
return view('history.election', compact('snapshot'));
```

- [ ] **Step 5: Add route**

Keep:

```php
Route::get('history/election', [HistoryController::class, 'election'])->name('history.election');
```

Add:

```php
Route::get('history/election-history', [HistoryController::class, 'electionHistory'])->name('history.election-history');
```

- [ ] **Step 6: Run route tests and route boot**

```bash
php artisan test tests/Feature/Elections/CurrentElectionRoutesTest.php
php artisan route:list --name=history.election
```

Expected: PASS and both routes visible.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Profile/HistoryController.php routes/web.php tests/Feature/Elections/CurrentElectionRoutesTest.php
git commit -m "refactor(elections): separate current center from election history"
```

---

### Task 3: ساخت UI/UX حرفه‌ای mobile-first برای مرکز انتخابات جاری

**Files:**
- Replace/Modify: `resources/views/history/election.blade.php`
- Create: `tests/Feature/Elections/CurrentElectionCenterViewTest.php`
- Create: `tests/Feature/Elections/CurrentElectionCenterResponsiveContractTest.php`

**Interfaces:**
- Consumes `$snapshot['systemic']`, `$snapshot['internal']`, `$snapshot['summary']` only.
- Blade must not query models or recompute eligibility.

- [ ] **Step 1: Write failing content contract test**

Assert rendered page contains:

```text
انتخابات جاری
انتخابات سیستمی
انتخابات درون‌گروهی
تاریخچه انتخابات من
نیازمند اقدام
```

and per-card semantic hooks such as:

```html
data-current-election-center
data-election-type="systemic"
data-election-type="internal"
data-election-card
data-primary-election-action
```

- [ ] **Step 2: Write failing responsive source contract**

Read the Blade source and assert it contains protections equivalent to:

```css
.election-center-page,
.election-center-grid,
.election-card { min-width: 0; }

.election-card__actions { flex-wrap: wrap; }

@media (max-width: 640px) {
  .election-card__actions { flex-direction: column; }
  .election-card__actions a { width: 100%; }
}
```

Also assert there is no primary data table with a large fixed `min-width`.

- [ ] **Step 3: Run RED**

```bash
php artisan test tests/Feature/Elections/CurrentElectionCenterViewTest.php tests/Feature/Elections/CurrentElectionCenterResponsiveContractTest.php
```

- [ ] **Step 4: Implement header and summary**

Use `layouts.unified`. Header includes title, short explanatory copy, two compact counters (`action_required`, `related`) and a secondary link to `history.election-history`.

- [ ] **Step 5: Implement accessible two-tab navigation**

Use buttons/anchors with clear active state and `aria` semantics. Tabs must not require a wide viewport; on narrow screens they remain inside the page width.

- [ ] **Step 6: Implement reusable card markup**

Each card shows group, election type badge, title/cycle, status badge, eligibility label, vote state, deadline, one primary CTA and optional secondary CTA. Primary CTA wording comes from service output; the Blade does not derive it.

- [ ] **Step 7: Implement empty states per tab**

Systemic and internal tabs each show their own empty state so one empty source does not hide the other.

- [ ] **Step 8: Mobile polish**

At <=640px:

- single-column cards;
- compact spacing and typography;
- CTA stack full width;
- badges wrap safely;
- no horizontal page overflow;
- no card child can force parent width;
- target Samsung Galaxy S8+ width (~360px CSS viewport).

- [ ] **Step 9: Run view and responsive tests**

```bash
php artisan test tests/Feature/Elections/CurrentElectionCenterViewTest.php tests/Feature/Elections/CurrentElectionCenterResponsiveContractTest.php
```

Expected: PASS.

- [ ] **Step 10: Commit**

```bash
git add resources/views/history/election.blade.php tests/Feature/Elections/CurrentElectionCenterViewTest.php tests/Feature/Elections/CurrentElectionCenterResponsiveContractTest.php
git commit -m "feat(elections): redesign current elections center mobile first"
```

---

### Task 4: حفظ تاریخچه انتخابات و دسترسی مستقل از منوی مشارکت‌ها

**Files:**
- Create: `resources/views/history/election-history.blade.php`
- Modify: `resources/views/partials/sidebar-unified.blade.php`
- Modify: `resources/views/partials/nav-bar.blade.php` only if that legacy navigation is still active in runtime.
- Create: `tests/Feature/Elections/ElectionHistoryNavigationTest.php`

**Interfaces:**
- History view consumes legacy `$currentElections` query result unchanged.
- Sidebar under «مشارکت» exposes both current and history destinations.

- [ ] **Step 1: Write failing history-preservation test**

Create an open and a filled systemic election related to user and assert `history.election-history` renders both, including current ballot/offer/appointment information already present in the legacy view.

- [ ] **Step 2: Write failing navigation test**

Assert unified sidebar contains both labels/routes:

```text
انتخابات جاری -> history.election
تاریخچه انتخابات من -> history.election-history
```

- [ ] **Step 3: Run RED**

```bash
php artisan test tests/Feature/Elections/ElectionHistoryNavigationTest.php
```

- [ ] **Step 4: Move old Blade content into history view**

Copy the current lifecycle/history card content from the pre-redesign `resources/views/history/election.blade.php` into `resources/views/history/election-history.blade.php`, renaming title/header to «تاریخچه انتخابات من». Preserve portal, offer and appointment links.

- [ ] **Step 5: Add sidebar item under Participation**

Place history directly adjacent to current elections; active state must use `request()->routeIs('history.election-history')` independently.

- [ ] **Step 6: Run navigation/history tests**

```bash
php artisan test tests/Feature/Elections/ElectionHistoryNavigationTest.php tests/Feature/Elections/CurrentElectionRoutesTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/views/history/election-history.blade.php resources/views/partials/sidebar-unified.blade.php resources/views/partials/nav-bar.blade.php tests/Feature/Elections/ElectionHistoryNavigationTest.php
git commit -m "feat(elections): preserve election history as separate participation page"
```

---

### Task 5: تمیز کردن «نظرسنجی‌های جاری» و جلوگیری از duplication انتخابات داخلی

**Files:**
- Modify: `app/Http/Controllers/Profile/HistoryController.php`
- Modify only if needed for copy: `resources/views/history/poll.blade.php`
- Create: `tests/Feature/Elections/CurrentPollSeparationTest.php`

**Interfaces:**
- `HistoryController::poll()` returns only `Poll.main_type = 1` related to user's groups.
- `history.election` owns `Poll.main_type = 0` current items.

- [ ] **Step 1: Write failing separation test**

Arrange one active `main_type=0` election and one active `main_type=1` poll in the same group.

Assert:

```php
$this->get(route('history.poll'))->assertSee($normalPollQuestion)->assertDontSee($internalElectionQuestion);
$this->get(route('history.election'))->assertSee($internalElectionQuestion);
```

- [ ] **Step 2: Run RED**

```bash
php artisan test tests/Feature/Elections/CurrentPollSeparationTest.php
```

- [ ] **Step 3: Add canonical filter**

In `poll()` add:

```php
->where('main_type', 1)
```

while preserving group membership, eager loads and ordering.

- [ ] **Step 4: Run separation test**

```bash
php artisan test tests/Feature/Elections/CurrentPollSeparationTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Profile/HistoryController.php resources/views/history/poll.blade.php tests/Feature/Elections/CurrentPollSeparationTest.php
git commit -m "fix(participation): separate internal elections from current polls"
```

---

### Task 6: Regression gate، UAT قرارداد و PR ایزوله

**Files:**
- Create or Modify: `.github/workflows/current-elections-center-gate.yml` only if no existing reusable feature gate can cover the suite.
- Test existing: `tests/Feature/Elections/*`
- Test existing: relevant Group Chat/Poll tests.

**Interfaces:**
- No production deployment.
- PR base stays the parent feature branch, not `main`.

- [ ] **Step 1: Run targeted new suite**

```bash
php artisan test \
  tests/Feature/Elections/CurrentElectionCenterServiceTest.php \
  tests/Feature/Elections/CurrentElectionRoutesTest.php \
  tests/Feature/Elections/CurrentElectionCenterViewTest.php \
  tests/Feature/Elections/CurrentElectionCenterResponsiveContractTest.php \
  tests/Feature/Elections/ElectionHistoryNavigationTest.php \
  tests/Feature/Elections/CurrentPollSeparationTest.php
```

Expected: PASS.

- [ ] **Step 2: Run entire elections feature suite**

```bash
php artisan test tests/Feature/Elections
```

Expected: PASS with no lifecycle/ballot regression.

- [ ] **Step 3: Run poll/group-chat regression subset**

Run the existing poll vote/policy/group chat tests that cover `PollController`, `VotePollRequest`, rendering and vote toggling.

Expected: PASS.

- [ ] **Step 4: Verify route/application boot**

```bash
php artisan route:list --name=history
php artisan about
```

Expected: successful boot and both election routes registered.

- [ ] **Step 5: Review branch diff**

Compare `agent/current-elections-center` against `agent/participation-credit-regulation`. Confirm only intended services, controller/routes, views, navigation, tests/docs and optional feature gate changed.

- [ ] **Step 6: Open/update Draft PR**

Open Draft PR:

```text
head: agent/current-elections-center
base: agent/participation-credit-regulation
```

Body must state explicitly: no merge to `main`, current/history separation, two election engines, canonical eligibility, mobile-first UX, and validation results.

- [ ] **Step 7: Manual UAT checklist**

Verify at minimum:

1. 360px/Galaxy S8+ width: no horizontal overflow.
2. One systemic open eligible election: «ثبت رأی».
3. Same election after vote: «ویرایش رأی».
4. Systemic open but non-eligible: detail-only.
5. Internal active election: appears only in internal tab.
6. Normal poll: appears only in «نظرسنجی‌های جاری».
7. Filled systemic election: absent from current center, present in history.
8. Both tabs empty independently render useful empty states.
9. «تاریخچه انتخابات من» accessible from header and sidebar.

- [ ] **Step 8: Final checkpoint**

Record final commit SHA, exact test counts/assertions and CI run status before declaring the feature complete.
