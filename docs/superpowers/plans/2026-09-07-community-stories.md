# Community Stories Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** جایگزینی testimonialهای ساختگی Welcome با داستان‌های واقعی، رضایت‌محور، قابل بررسی و Featured.

**Architecture:** یک موجودیت مستقل CommunityStory منبع canonical داستان‌های عمومی است. Welcome فقط داستان‌های approved/published/featured دارای رضایت فعال را می‌خواند و در نبود داده، empty-state صادقانه نمایش می‌دهد. ارسال عضو و moderation ادمین از داده‌های posts/comments/private messages جدا می‌مانند.

**Tech Stack:** Laravel 9/PHP 8.2، Blade، Eloquent، PHPUnit/Feature tests، Tailwind/Vite موجود پروژه.

**Spec:** `docs/superpowers/specs/2026-09-07-community-stories-design.md`

## Global Constraints
- هیچ testimonial ساختگی یا filler در Welcome مجاز نیست.
- انتشار عمومی فقط با رضایت صریح عضو و approval مدیریتی انجام می‌شود.
- Welcome حداکثر سه داستان Featured واقعی نمایش می‌دهد.
- withdrawn/rejected/pending یا بدون رضایت هرگز عمومی نمی‌شود.
- FA/EN/AR برای UI و empty-state حفظ می‌شود.
- هیچ merge به `main` انجام نشود.

---

### Task 1: CommunityStory domain + eligibility scope

**Files:**
- Create: `database/migrations/*_create_community_stories_table.php`
- Create: `app/Models/CommunityStory.php`
- Create: `database/factories/CommunityStoryFactory.php`
- Test: `tests/Feature/CommunityStories/CommunityStoryPublicationContractTest.php`

**Interfaces:**
- Produces: `CommunityStory::welcomeFeatured()` که فقط داستان‌های واجد شرایط عمومی را برمی‌گرداند.

- [ ] **Step 1: Write failing tests** برای approved/published/featured/consent و exclusion وضعیت‌های pending/rejected/withdrawn.
- [ ] **Step 2: Run** `php artisan test tests/Feature/CommunityStories/CommunityStoryPublicationContractTest.php` و RED را ثبت کن.
- [ ] **Step 3: Implement migration/model/factory** با status، consent، featured و published metadata.
- [ ] **Step 4: Run همان test** و GREEN را ثبت کن.
- [ ] **Step 5: Commit** `feat: add consent-aware community stories domain`.

### Task 2: Welcome real-data + honest empty state

**Files:**
- Modify: controller/route data provider فعلی Welcome که `$testimonials` را می‌سازد.
- Modify: `resources/views/partials/testimonials-section.blade.php`
- Modify: `resources/lang/fa/langWelcome.php`
- Modify: `resources/lang/en/langWelcome.php`
- Modify: `resources/lang/ar/langWelcome.php`
- Test: `tests/Feature/CommunityStories/WelcomeCommunityStoriesTest.php`

**Interfaces:**
- Consumes: `CommunityStory::welcomeFeatured()`.
- Produces: حداکثر سه داستان واقعی یا empty-state، بدون filler.

- [ ] **Step 1: Write failing tests**: zero stories => empty-state؛ one/two => همان تعداد؛ four => سه Featured؛ default names/quotes قدیمی absent.
- [ ] **Step 2: Run targeted test** و RED را ثبت کن.
- [ ] **Step 3: Remove `$defaultTestimonials` و placeholder avatar logic** و grid را برای ۱/۲/۳ کارت responsive کن.
- [ ] **Step 4: Add FA/EN/AR copy** برای «صدای جامعه»، empty-state و CTA «به جمع نخستین اعضا بپیوندید».
- [ ] **Step 5: Run targeted tests** و GREEN را ثبت کن.
- [ ] **Step 6: Commit** `feat: show authentic community stories on welcome`.

### Task 3: Voluntary member submission + consent

**Files:**
- Create: request/controller/routes/views متناسب با الگوی فعلی profile برای community stories.
- Test: `tests/Feature/CommunityStories/CommunityStorySubmissionTest.php`

**Interfaces:**
- Produces: submission با `status=pending` و `consent_publication_at` فقط پس از consent صریح.

- [ ] **Step 1: Write failing tests** برای auth، validation، explicit consent، optional public profile fields و pending status.
- [ ] **Step 2: Run targeted test** و RED را ثبت کن.
- [ ] **Step 3: Implement minimal submission UI/controller** با preview اطلاعات عمومی.
- [ ] **Step 4: Run targeted tests** و GREEN را ثبت کن.
- [ ] **Step 5: Commit** `feat: add voluntary community story submissions`.

### Task 4: Admin moderation + Featured selection

**Files:**
- Create/modify: admin controller/routes/views مطابق admin layout موجود.
- Test: `tests/Feature/CommunityStories/CommunityStoryModerationTest.php`

**Interfaces:**
- Consumes: pending CommunityStory.
- Produces: approved/rejected، publish state و `is_featured` فقط برای رکورد مجاز.

- [ ] **Step 1: Write failing tests** برای admin authorization، approve/reject، featured guard و جلوگیری از انتشار بدون consent.
- [ ] **Step 2: Run targeted test** و RED را ثبت کن.
- [ ] **Step 3: Implement moderation queue/actions** بدون تغییر layout ادمین.
- [ ] **Step 4: Run targeted tests** و GREEN را ثبت کن.
- [ ] **Step 5: Commit** `feat: moderate and feature community stories`.

### Task 5: Consent withdrawal + final regression

**Files:**
- Modify: member CommunityStory flow.
- Test: `tests/Feature/CommunityStories/CommunityStoryConsentWithdrawalTest.php`
- Modify/add: CI contract فقط اگر gate فعلی این suite را پوشش ندهد.

**Interfaces:**
- Produces: withdrawal که فوراً story را از eligibility عمومی خارج می‌کند.

- [ ] **Step 1: Write failing test** که story منتشرشده پس از withdrawal از Welcome حذف شود.
- [ ] **Step 2: Run targeted test** و RED را ثبت کن.
- [ ] **Step 3: Implement withdrawal** با status/consent state مناسب و audit metadata.
- [ ] **Step 4: Run CommunityStories suite، Welcome tests و full relevant regression**.
- [ ] **Step 5: Run Vite build و responsive/contracts**.
- [ ] **Step 6: Commit** `test: close community stories regression gate`.
