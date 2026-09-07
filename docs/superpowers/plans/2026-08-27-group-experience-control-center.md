# Group Experience Control Center Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** تبدیل مسیر استفاده از گروه‌ها به تجربه Chat-first با Group Control Center تب‌بندی‌شده، حفظ کامل capabilityها و searchهای فعلی، و بازتعریف `groups/{id}` به داشبورد گزارشی بدون تغییر غیرضروری backend.

**Architecture:** Chat نقطه ورود روزمره است؛ Hero سبک می‌شود و Control Center واحد روی همان `group_info_panel` فعلی ساخته می‌شود تا lifecycle و handlerهای موجود حفظ شوند. Control Center در موبایل Bottom Sheet و در دسکتاپ modal/panel عریض است؛ تب‌های محتوا، اعضا، حکمرانی و ابزارها یک component مشترک دارند. هر capability فعلی قبل از حذف از محل قدیمی باید در inventory مقصد، route و شرط visibility مشخص داشته باشد.

**Tech Stack:** Laravel Blade, PHP, Tailwind/Bootstrap/CSS موجود پروژه، JavaScript فعلی Group Chat، PHPUnit/feature tests، Node-based group-chat JS regression tests.

**Spec:** `docs/superpowers/specs/2026-08-27-group-experience-control-center-design.md` + `docs/superpowers/specs/2026-08-27-group-experience-control-center-search-amendment.md`

## Global Constraints

- هیچ capability، route، policy یا permission فعلی نباید در بازطراحی گم یا بی‌اجازه گسترده شود.
- مسیر روزمره «گروه‌های من → Chat» است.
- `groups/{id}` داشبورد وضعیت و گزارش گروه است، نه lobby قبل از Chat.
- تب‌های اصلی Control Center: محتوا، اعضا، حکمرانی، ابزارها.
- موبایل: Bottom Sheet حدود 85–90vh؛ دسکتاپ: پنل/Modal عریض؛ محتوای هر دو یکسان.
- دبیرخانه برای همه اعضا discoverable است؛ محتوای داخل تابع Policy موجود.
- نجم هدا فقط برای مدیر/بازرس قابل مشاهده است.
- نجم بهار به dashboard مالی واقعی گروه لینک می‌شود و capabilityهای کیف پول/انتقال/حساب فرعی/سوابق حفظ می‌شوند.
- search capabilityهای فعلی تب‌ها باید حفظ شوند و contextual باقی بمانند.
- هیچ merge به `main` انجام نمی‌شود.

---

### Task 1: Capability Inventory و Regression Contract

**Files:**
- Create: `docs/ux/GROUP_CONTROL_CENTER_CAPABILITY_MATRIX.fa.md`
- Modify: `tests/js/group-chat/source-contract.test.js`
- Test: `tests/js/group-chat/source-contract.test.js`

**Interfaces:**
- Consumes: کنترل‌های موجود در `resources/views/groups/partials/group_hero.blade.php`, `resources/views/groups/partials/group_info_panel.blade.php`, `resources/views/groups/show.blade.php`, routeهای گروه/Secretariat/Najm Hoda/Najm Bahar.
- Produces: ماتریس canonical با ستون‌های `capability`, `current_surface`, `final_tab`, `route_or_action`, `visibility_source`, `search_contract` که تمام Taskهای بعدی به آن استناد می‌کنند.

- [ ] **Step 1: inventory کامل همه کنترل‌ها و searchها را از source فعلی استخراج کن**

در ماتریس حداقل این موارد را ثبت کن: ایجاد پست، پست‌ها، ساخت/مرور نظرسنجی، انتخابات جاری، افزودن انتخابات، مدیریت اعضا، گزارش‌ها، تنظیمات، خروج، ویرایش گروه، افزودن مهمان، درخواست چت مدیران، نشست، مشارکت نشست، فهرست اعضا، مدیران/بازرسان، آمار، navigation گروه‌های مرتبط، داشبورد گروه، دبیرخانه، نجم هدا، نجم بهار، و تمام input/selectهای جست‌وجوی فعلی.

- [ ] **Step 2: failing source-contract test برای حفظ چهار تب و searchها بنویس**

```js
expect(panelSource).toContain('data-control-center-tab="content"');
expect(panelSource).toContain('data-control-center-tab="members"');
expect(panelSource).toContain('data-control-center-tab="governance"');
expect(panelSource).toContain('data-control-center-tab="tools"');
expect(panelSource).toContain('data-control-center-search="members"');
expect(panelSource).toContain('data-control-center-search="content"');
```

همچنین contract بنویس که لینک/کلیدهای Secretariat, Najm Hoda, Najm Bahar و Group Dashboard مقصد مشخص داشته باشند.

- [ ] **Step 3: تست را اجرا و قرمز بودن را تأیید کن**

Run: `npm test -- --runInBand tests/js/group-chat/source-contract.test.js` یا command canonical موجود در `package.json` برای group-chat tests.  
Expected: FAIL چون markup جدید هنوز وجود ندارد.

- [ ] **Step 4: فقط سند inventory را commit کن؛ هنوز UI را تغییر نده**

```bash
git add docs/ux/GROUP_CONTROL_CENTER_CAPABILITY_MATRIX.fa.md tests/js/group-chat/source-contract.test.js
git commit -m "test: define group control center capability contract"
```

---

### Task 2: مسیر «گروه‌های من → Chat»

**Files:**
- Modify: view/partialهایی که cards یا links صفحه «گروه‌های من» را تولید می‌کنند (پس از inventory با path دقیق ثبت‌شده)
- Test: feature/view test مرتبط با groups index یا یک regression test جدید در `tests/Feature/Groups/`

**Interfaces:**
- Consumes: route موجود `groups.chat` و شروط فعلی membership/location/specialty approval.
- Produces: کلیک اصلی روی گروه فعال مستقیماً به `groups/chat/{id}` می‌رود؛ مسیر `groups/{id}` همچنان از Control Center و dashboard shortcuts قابل دسترسی است.

- [ ] **Step 1: failing test بنویس که لینک primary گروه فعال route چت باشد**

```php
$response->assertSee(route('groups.chat', $group), false);
```

و برای گروه pending همان behavior فعلی عدم ورود را حفظ کن.

- [ ] **Step 2: تست را اجرا و failure فعلی را ثبت کن**

- [ ] **Step 3: فقط href/CTA اصلی cards را به `groups.chat` تغییر بده**

هیچ query، controller یا membership logic را تغییر نده.

- [ ] **Step 4: feature test و Group Chat regression suite را اجرا کن**

- [ ] **Step 5: commit**

```bash
git commit -am "ux: open active groups directly in chat"
```

---

### Task 3: Control Center Shell تطبیقی

**Files:**
- Modify: `resources/views/groups/partials/group_info_panel.blade.php`
- Modify: CSS مرتبط با `group-info-panel` در asset/source canonical فعلی
- Modify: JavaScript handler موجود باز/بسته‌شدن panel فقط در حد accessibility/state لازم
- Test: `tests/js/group-chat/source-contract.test.js`

**Interfaces:**
- Consumes: `data-chat-page-action="open-group-info"` و lifecycle موجود panel.
- Produces: همان `#groupInfoPanel` به‌عنوان Control Center با چهار tab، Bottom Sheet در mobile و modal/panel عریض در desktop.

- [ ] **Step 1: failing tests برای semantic tablist/dialog بنویس**

```js
expect(panelSource).toContain('role="dialog"');
expect(panelSource).toContain('role="tablist"');
expect(panelSource).toContain('aria-modal="true"');
```

- [ ] **Step 2: تست را قرمز اجرا کن**

- [ ] **Step 3: shell جدید را روی همان panel موجود بساز**

Header ثابت شامل avatar/name/close؛ tab bar با چهار دکمه؛ content region scrollable؛ footer contextual برای خروج از گروه. Mobile CSS باید bottom:0، border-radius بالا، max-height حدود 90dvh و safe-area padding داشته باشد. Desktop باید max-width مناسب، max-height viewport و center positioning داشته باشد.

- [ ] **Step 4: keyboard/focus behavior موجود را حفظ و Escape/close را verify کن**

- [ ] **Step 5: JS regression tests را سبز کن و commit کن**

```bash
git commit -am "ux: introduce adaptive group control center shell"
```

---

### Task 4: تب «محتوا» + جست‌وجوی contextual

**Files:**
- Modify: `resources/views/groups/partials/group_info_panel.blade.php`
- Modify: Group Chat panel JS/filter code canonical فعلی
- Test: `tests/js/group-chat/source-contract.test.js`

**Interfaces:**
- Consumes: blog/poll collections و actionهای `open-blog`, `open-poll`, election participation موجود.
- Produces: tab «محتوا» شامل actions و lists فعلی و search field مستقل با `data-control-center-search="content"`.

- [ ] **Step 1: failing test برای actionهای content و search field بنویس**

```js
expect(panelSource).toContain('data-chat-page-action="open-blog"');
expect(panelSource).toContain('data-chat-page-action="open-poll"');
expect(panelSource).toContain('data-control-center-search="content"');
```

- [ ] **Step 2: تست را قرمز اجرا کن**

- [ ] **Step 3: پست‌ها و نظرسنجی‌ها را از tabهای قدیمی به tab محتوا منتقل کن**

همان collection/rendering و handlerهای فعلی را reuse کن. محتوای hidden duplicated باقی نگذار.

- [ ] **Step 4: filtering contextual را پیاده/بازاستفاده کن**

Query فقط items همان tab را filter کند؛ clear کردن query همه items را برگرداند؛ empty-state قابل مشاهده باشد.

- [ ] **Step 5: tests + manual DOM smoke و commit**

```bash
git commit -am "ux: consolidate group content tools with scoped search"
```

---

### Task 5: تب «اعضا» + جست‌وجوی نام/نقش/ایمیل

**Files:**
- Modify: `resources/views/groups/partials/group_info_panel.blade.php`
- Modify: panel filtering JS canonical فعلی
- Test: `tests/js/group-chat/source-contract.test.js`

**Interfaces:**
- Consumes: member list، admins/inspectors، profile links، manage-members/add-guest actions.
- Produces: یک tab «اعضا» با search scoped و حفظ فیلتر حداقل نام/نقش/ایمیل.

- [ ] **Step 1: failing search contract بنویس**

```js
expect(panelSource).toContain('data-control-center-search="members"');
expect(panelSource).toContain('data-name=');
expect(panelSource).toContain('data-role=');
expect(panelSource).toContain('data-email=');
```

- [ ] **Step 2: تست قرمز**

- [ ] **Step 3: members/admins/inspectors و actionهای مجاز را در tab اعضا consolidate کن**

Permission conditions Blade موجود را عیناً preserve کن.

- [ ] **Step 4: search را روی name/role/email scope کن و empty-state اضافه کن**

- [ ] **Step 5: tests و commit**

```bash
git commit -am "ux: consolidate group members with scoped search"
```

---

### Task 6: تب «حکمرانی» + نجم هدا

**Files:**
- Modify: `resources/views/groups/partials/group_info_panel.blade.php`
- Modify: JS فقط اگر action wiring جدید لازم باشد
- Test: Group Chat source contracts + feature authorization tests موجود/جدید

**Interfaces:**
- Consumes: election/admin actions، session controls، reports، settings/edit، Najm Hoda group management route.
- Produces: governance tab با visibility فعلی و Najm Hoda فقط برای roleهای مدیر/بازرس.

- [ ] **Step 1: failing tests برای governance actions و role-gated Hoda بنویس**

Feature test باید برای عضو عادی absence و برای manager/inspector presence لینک نجم هدا را assert کند.

- [ ] **Step 2: tests قرمز**

- [ ] **Step 3: governance controls را بدون تغییر handler منتقل کن**

- [ ] **Step 4: Najm Hoda shortcut را با route canonical موجود و همان authorization backend اضافه کن**

- [ ] **Step 5: election/session/report regression suites + commit**

```bash
git commit -am "ux: centralize group governance and najm hoda access"
```

---

### Task 7: تب «ابزارها» — Dashboard / Secretariat / Najm Bahar

**Files:**
- Modify: `resources/views/groups/partials/group_info_panel.blade.php`
- Test: feature/view tests برای visibility و route destinations

**Interfaces:**
- Consumes: `groups.show`, group Secretariat route/policy, `groups.najm-bahar.*` dashboard route.
- Produces: سه tool card واضح با توضیح کوتاه و مقصد canonical.

- [ ] **Step 1: failing tests برای سه مقصد بنویس**

```php
$response->assertSee(route('groups.show', $group), false);
$response->assertSee(/* canonical group secretariat route */, false);
$response->assertSee(route('groups.najm-bahar.dashboard', $group), false);
```

اگر نام canonical route نجم بهار متفاوت است، از route ثبت‌شده در inventory استفاده کن؛ route جدید نساز مگر نبودن route اثبات شود.

- [ ] **Step 2: tests قرمز**

- [ ] **Step 3: tool cards را اضافه کن**

Dashboard برای همه اعضای مجاز؛ Secretariat برای همه اعضا discoverable؛ Najm Bahar فقط مطابق authorization موجود backend.

- [ ] **Step 4: verify کن که operations داخلی Najm Bahar دوباره داخل Chat duplicate نشده‌اند**

- [ ] **Step 5: tests و commit**

```bash
git commit -am "ux: add group dashboard secretariat and bahar tools"
```

---

### Task 8: Hero simplification بدون از دست دادن action

**Files:**
- Modify: `resources/views/groups/partials/group_hero.blade.php`
- Test: `tests/js/group-chat/source-contract.test.js`

**Interfaces:**
- Consumes: completed Control Center capability matrix.
- Produces: Hero سبک با identity/context و CTA «پنل گروه»؛ controls migrated دیگر duplicate نمی‌شوند.

- [ ] **Step 1: failing test بنویس که CTA پنل در mobile و desktop قابل دسترس باشد**

```js
expect(heroSource.match(/open-group-info/g).length).toBeGreaterThanOrEqual(2);
```

- [ ] **Step 2: بر اساس matrix هر action migrated را فقط پس از تأیید مقصد از Hero حذف کن**

- [ ] **Step 3: role/status/member-count/context را حفظ کن**

- [ ] **Step 4: sticky/reveal header behavior فعلی را regression-test کن**

- [ ] **Step 5: commit**

```bash
git commit -am "ux: simplify group chat hero around control center"
```

---

### Task 9: بازتعریف `groups/{id}` به Group Dashboard

**Files:**
- Modify: `resources/views/groups/show.blade.php`
- Test: feature/view test مرتبط با group show

**Interfaces:**
- Consumes: metrics/data موجود controller/view و shortcuts canonical.
- Produces: داشبورد گزارشی با CTA «گفت‌وگوی گروه»، shortcutهای Secretariat/Bahar/Hoda (role gated)، بدون تکرار عملیات روزمره Control Center.

- [ ] **Step 1: failing tests برای CTA و shortcuts بنویس**

- [ ] **Step 2: تست قرمز**

- [ ] **Step 3: CTA اصلی را `groups.chat` کن و copy را به «گفت‌وگوی گروه/بازگشت به گفت‌وگو» تغییر بده**

- [ ] **Step 4: dashboard cards را روی شاخص‌ها/گزارش‌ها متمرکز کن؛ deep management controls را duplicate نکن**

- [ ] **Step 5: Hoda visibility و Secretariat/Bahar destinations را verify کن**

- [ ] **Step 6: tests و commit**

```bash
git commit -am "ux: refocus group page as reporting dashboard"
```

---

### Task 10: حذف tabهای legacy فقط بعد از parity proof

**Files:**
- Modify: `resources/views/groups/partials/group_info_panel.blade.php`
- Modify: legacy panel CSS/JS فقط برای selectorsی که دیگر مصرف نمی‌شوند
- Modify: `docs/ux/GROUP_CONTROL_CENTER_CAPABILITY_MATRIX.fa.md`
- Test: JS + PHP regression suites

**Interfaces:**
- Consumes: completed matrix و همه Taskهای قبل.
- Produces: هیچ duplicated legacy tab/control باقی نمی‌ماند؛ هر capability و search وضعیت `preserved` دارد.

- [ ] **Step 1: matrix را خط‌به‌خط با source جدید reconcile کن**

هیچ ردیفی نباید destination خالی یا status نامشخص داشته باشد.

- [ ] **Step 2: regression test بنویس که legacy duplicate tab labels/selectors اصلی دیگر وجود نداشته باشند**

- [ ] **Step 3: dead markup/CSS/JS را فقط بعد از اثبات عدم مصرف حذف کن**

- [ ] **Step 4: Group Chat JS, Group PHP, Governance/Election, Secretariat, Najm Hoda, Najm Bahar targeted suites را اجرا کن**

- [ ] **Step 5: commit**

```bash
git commit -am "refactor: retire legacy group panel surfaces after parity"
```

---

### Task 11: Production Build + UAT Gate + Full Validation

**Files:**
- Modify: فقط در صورت bug اثبات‌شده در UAT
- Test: full project validation

**Interfaces:**
- Consumes: همه Taskهای قبل.
- Produces: release candidate قابل UAT روی `agent/pre-main-ui-polish` بدون merge به main.

- [ ] **Step 1: `npm run build` و asset manifest را verify کن**

- [ ] **Step 2: targeted automated suites را اجرا کن**

حداقل: Group Chat JS/PHP، Groups views، Elections/Governance، Secretariat، Najm Hoda group management، Najm Bahar group dashboard.

- [ ] **Step 3: UAT موبایل**

سناریو: My Groups → Chat → open Bottom Sheet → هر چهار tab → search محتوا → search اعضا → governance role controls → tools → Dashboard/Secretariat/Bahar → back to Chat → header/hero sticky behavior.

- [ ] **Step 4: UAT دسکتاپ**

همان سناریو با panel/modal، keyboard tabs، Escape، focus return و search.

- [ ] **Step 5: UAT role matrix**

حداقل عضو عادی، مدیر و بازرس؛ Najm Hoda باید فقط برای مدیر/بازرس باشد و Secretariat برای عضو discoverable بماند.

- [ ] **Step 6: Full Validation را روی head نهایی اجرا کن**

- [ ] **Step 7: فقط اگر همه gateها سبز و UAT تأیید شد، branch را برای تصمیم جداگانه merge-to-main آماده اعلام کن؛ merge نکن.**
