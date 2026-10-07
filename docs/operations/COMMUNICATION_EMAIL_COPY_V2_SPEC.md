# EarthCoop Communication Email Copy v2 — Proposed Content Specification

**Status:** Draft for content approval before runtime implementation.  
**Implementation rule:** Publish as new immutable template versions; do not mutate historical v1 content.

## 1. onboarding.welcome — v2 proposal

### Mission
Turn completed registration into a confident first session.

### Proposed subject
`{{display_name}} عزیز، به EarthCoop خوش آمدید`

### Proposed preheader
`ثبت‌نام شما کامل شده؛ این سه قدم برای شروع پیشنهاد می‌شود.`

### Proposed body copy

**{{display_name}} عزیز، خوش آمدید.**

ثبت‌نام شما در EarthCoop کامل شده است. از اینجا به بعد، حساب شما فقط یک پروفایل نیست؛ عضویت شما به گروه‌ها، مشارکت‌های محلی و تخصصی و ابزارهای تصمیم‌گیری EarthCoop متصل می‌شود.

**برای شروع، این سه قدم را پیشنهاد می‌کنیم:**

**۱. پروفایل و مکان خود را مرور کنید**  
اطلاعات پروفایل و مکان، مبنای قرارگرفتن شما در گروه‌ها و حوزه‌های مرتبط است.  
[تکمیل و بررسی پروفایل]({{profile_url}})

**۲. گروه‌های خود را ببینید**  
EarthCoop بر اساس مکان، حوزه فعالیت و ویژگی‌های عضویت، شما را به گروه‌های مرتبط متصل می‌کند.  
[مشاهده گروه‌های من]({{groups_url}})

**۳. مشارکت‌های باز را بررسی کنید**  
انتخابات، نظرسنجی‌ها و سایر مشارکت‌های جاری را از بخش مشارکت‌های من دنبال کنید.  
[مشاهده مشارکت‌های من]({{participation_url}})

اگر می‌خواهید قبل از فعالیت، با منطق و اصول EarthCoop آشنا شوید، از راهنمای شروع و اسناد مرجع استفاده کنید:  
[EarthCoop چیست؟]({{getting_started_url}})

**CTA اصلی:** `شروع فعالیت در EarthCoop` → `{{home_url}}`

در صورت نیاز به راهنمایی، از مسیر پشتیبانی داخل EarthCoop استفاده کنید.

### Required v2 variables
- `display_name`
- `home_url`
- `profile_url`
- `groups_url`
- `participation_url`
- `getting_started_url`

Optional:
- `governance_url`
- `najm_bahar_url`
- `communication_preferences_url`

### Content note
The onboarding definition of EarthCoop must remain concise and must not substitute for the constitution/docs.

---

## 2. auth.email_verification — v2 proposal

### Subject
`کد تأیید ایمیل EarthCoop`

### Body
سلام،

برای تأیید این آدرس ایمیل در EarthCoop، کد زیر را وارد کنید:

**{{code}}**

این کد **۵ دقیقه** اعتبار دارد.

این کد را در اختیار شخص دیگری قرار ندهید. اگر شما این فرایند را آغاز نکرده‌اید، این پیام را نادیده بگیرید.

### CTA
None required. User is expected to return to the already-open verification screen.

### Variables
- `code`

---

## 3. auth.password_reset — v2 proposal

### Subject
`کد بازیابی رمز عبور EarthCoop`

### Body
سلام،

برای ادامه فرایند بازیابی رمز عبور، کد زیر را وارد کنید:

**{{code}}**

این کد **۵ دقیقه** اعتبار دارد و نباید در اختیار شخص دیگری قرار گیرد.

اگر شما درخواست بازیابی رمز عبور نداده‌اید، این پیام را نادیده بگیرید؛ رمز عبور شما بدون انجام ادامه فرایند تغییر نخواهد کرد.

### Variables
- `code`

---

## 4. auth.invitation_issued — v2 proposal

### Mission
Tell an applicant that their invitation-code request was approved and give the clear next step.

### Subject
`درخواست شما تأیید شد — کد دعوت EarthCoop`

### Body
سلام،

درخواست شما برای دریافت کد دعوت EarthCoop تأیید شد.

**کد دعوت:** `{{code}}`

این کد تا **{{expire_at}}** معتبر است.

برای ادامه، ثبت‌نام را آغاز کنید و کد بالا را در مرحله مربوط وارد کنید.

**CTA:** `شروع ثبت‌نام` → `{{registration_url}}`

اگر پیش از ثبت‌نام می‌خواهید با EarthCoop آشنا شوید:  
[راهنمای آشنایی با EarthCoop]({{learn_more_url}})

### Variables
- `code`
- `expire_at`
- `registration_url`
- `learn_more_url`

---

## 5. membership.member_invitation — v2 proposal

### Subject
`دعوت برای پیوستن به EarthCoop`

### Body
سلام،

{{inviter_name}} شما را برای پیوستن به EarthCoop دعوت کرده است.

برای ثبت‌نام از کد زیر استفاده کنید:

**{{code}}**

این کد تا **{{expire_at}}** معتبر است.

**CTA:** `شروع ثبت‌نام` → `{{registration_url}}`

اگر هنوز با EarthCoop آشنا نیستید:  
[EarthCoop چیست؟]({{learn_more_url}})

اگر تمایلی به پیوستن ندارید، نیازی به انجام کاری نیست و می‌توانید این پیام را نادیده بگیرید.

### Variables
- `inviter_name`
- `code`
- `expire_at`
- `registration_url`
- `learn_more_url`

### Product dependency
Only expose `inviter_name` if member-originated invitation policy explicitly permits it.

---

## 6. auth.invitation_rejected — v2 proposal

### Subject
`نتیجه بررسی درخواست کد دعوت EarthCoop`

### Body
سلام،

درخواست شما برای دریافت کد دعوت بررسی شد و در این مرحله تأیید نشد.

{{admin_note_text}}

اگر امکان ثبت درخواست دوباره یا مسیر پیگیری وجود دارد، فقط در صورت وجود واقعی آن فرایند، لینک زیر نمایش داده شود:

`{{next_step_text}}`  
`{{next_step_url}}`

### Variables
- `admin_note_text`
- optional `next_step_text`
- optional `next_step_url`

### Content policy
Do not say “رد شدید” or imply misconduct unless an authoritative reviewed reason explicitly supports that claim.

---

## 7. reports.member.weekly — v2 proposal

### Subject logic
Default:
`هفته شما در EarthCoop — {{period_start}} تا {{period_end}}`

Optional dynamic subject when safe:
`هفته شما در EarthCoop — {{open_actions_count}} مشارکت باز`

### Body
**{{display_name}} عزیز، این خلاصه هفته شماست.**

از {{period_start}} تا {{period_end}}:

- گروه‌های مرتبط شما: **{{groups_count}}**
- انتخابات باز: **{{open_elections_count}}**
- نظرسنجی‌های باز: **{{open_polls_count}}**
- اعلان‌های خوانده‌نشده: **{{unread_notifications_count}}**

{{action_summary_text}}

**CTA:** `مشاهده مشارکت‌های من` → `{{participation_url}}`

لینک‌های فرعی:
- [گروه‌های من]({{groups_url}})
- [داشبورد]({{dashboard_url}})
- [تنظیمات ارتباطی]({{preferences_url}})

### Variables
Existing metrics plus:
- `participation_url`
- `groups_url`
- `dashboard_url`
- `preferences_url`
- optional derived `action_summary_text`
- optional `open_actions_count`

---

## 8. reports.manager.weekly — v2 proposal

### Subject
`گزارش هفتگی مدیریت EarthCoop — {{display_name}}`

### Body
**{{display_name}} عزیز، خلاصه هفته شما و مسئولیت‌های مدیریتی‌تان آماده است.**

### مشارکت شخصی
- گروه‌های شما: **{{groups_count}}**
- انتخابات باز: **{{open_elections_count}}**
- نظرسنجی‌های باز: **{{open_polls_count}}**

### مسئولیت مدیریتی
- گروه‌های تحت مدیریت: **{{managed_groups_count}}**
- انتخابات باز در این گروه‌ها: **{{open_elections_in_managed_groups_count}}**
- نظرسنجی‌های باز در این گروه‌ها: **{{open_polls_in_managed_groups_count}}**

اگر موردی نیازمند اقدام مدیریتی است، آن را در پنل مربوط بررسی کنید.

**CTA:** `بررسی مسئولیت‌های مدیریتی` → `{{management_url}}`

Secondary:
- `{{participation_url}}`
- `{{preferences_url}}`

### Variables
Existing metrics plus:
- `management_url`
- `participation_url`
- `preferences_url`

---

## 9. reports.inspector.weekly — v2 proposal

### Subject
`گزارش هفتگی بازرسی EarthCoop — {{display_name}}`

### Body
**{{display_name}} عزیز، خلاصه هفته شما و حوزه‌های تحت بازرسی آماده است.**

### مشارکت شخصی
- گروه‌های شما: **{{groups_count}}**
- انتخابات باز: **{{open_elections_count}}**
- نظرسنجی‌های باز: **{{open_polls_count}}**

### حوزه بازرسی
- گروه‌های تحت بازرسی: **{{inspected_groups_count}}**
- انتخابات باز در این گروه‌ها: **{{open_elections_in_inspected_groups_count}}**
- نظرسنجی‌های باز در این گروه‌ها: **{{open_polls_in_inspected_groups_count}}**

**CTA:** `بررسی حوزه‌های بازرسی` → `{{inspection_url}}`

Secondary:
- `{{participation_url}}`
- `{{preferences_url}}`

### Variables
Existing metrics plus:
- `inspection_url`
- `participation_url`
- `preferences_url`

---

## 10. support.ticket_created — v2 proposal

### Subject
`تیکت {{tracking_code}} ثبت شد — {{ticket_subject}}`

### Body direction
Your support request has been registered successfully.

Show:
- tracking code;
- subject;
- status;
- priority if useful;
- created time;
- original message preview.

**Primary CTA:** `مشاهده تیکت` → ticket URL

Operational note:
«پاسخ تیم پشتیبانی در EarthCoop ثبت می‌شود و در صورت فعال بودن اعلان ایمیلی، برای شما ایمیل نیز ارسال خواهد شد.»

Current Production-safe footer:
«برای ادامه گفتگو، تیکت را در EarthCoop باز کنید.»

### Content correction
Keep “do not reply directly to this email” until inbound provider integration is enabled.

---

## 11. support.ticket_reply — v2 proposal

### Subject
`پاسخ جدید به تیکت {{tracking_code}}`

### Body
**پاسخ جدیدی برای تیکت شما ثبت شده است.**

تیکت: **{{tracking_code}} — {{ticket_subject}}**

{{reply_body}}

**CTA:** `مشاهده و پاسخ به تیکت` → `{{ticket_url}}`

### Required Production-safe notice
«برای پاسخ، تیکت را در EarthCoop باز کنید. پاسخ مستقیم به این ایمیل در حال حاضر به تیکت شما اضافه نمی‌شود.»

### Critical change
Remove the current promise that email Reply will become a ticket comment until inbound provider integration is actually active and verified.

---

## 12. contact.reply — v2 proposal

### Subject
`پاسخ EarthCoop به پیام شما: {{subject}}`

### Body
سلام {{contact_name}}،

در پاسخ به پیام شما با موضوع **{{subject}}**:

{{reply_body}}

اگر همچنان به راهنمایی نیاز دارید، می‌توانید از مسیر تماس/پشتیبانی EarthCoop پیام جدیدی ارسال کنید.

**Secondary link:** `{{contact_url}}`

### Variables
- `contact_name`
- `subject`
- `reply_body`
- `contact_url`

### Boundary
Do not mention a Ticket unless the ContactMessage was actually converted to one.

---

## 13. faq.answer — v2 proposal

### Subject
`پاسخ به پرسش شما: {{title}}`

### Body
سلام،

برای پرسش شما با عنوان **{{title}}** پاسخ ثبت شد:

{{answer}}

{{published_faq_link_block}}

اگر پاسخ برای مسئله شما کافی نیست، از مسیر پشتیبانی EarthCoop استفاده کنید.

### Variables
- `title`
- `answer`
- optional `published_faq_url`
- optional `support_url`

### Formatting note
Need an explicit policy for multiline/rich FAQ answer rendering; do not casually inject arbitrary HTML.

---

## 14. najm_bahar.project_assigned — v2 proposal

### Subject
`پروژه «{{project_title}}» برای بررسی به شما ارجاع شد`

### Body
سلام {{recipient_name}}،

پروژه زیر برای بررسی به شما ارجاع شده است:

- پروژه: **{{project_title}}**
- دسته‌بندی: **{{category}}**
- سرمایه مورد نیاز: **{{required_capital}}**
- توضیح ارجاع: {{assignment_note}}

**CTA:** `بررسی پروژه` → `{{project_url}}`

اگر فرایند پروژه در آینده deadline/accept/decline رسمی دارد، فقط پس از وجود آن قابلیت در سیستم به ایمیل اضافه شود.

### Variables
Current variables are sufficient for v2 copy.

---

## 15. support.ticket_internal_alert — v2 proposal

### Mission
Internal operational alert, not consumer communication.

### Suggested structure
- source of escalation;
- ticket tracking code;
- subject;
- priority/assignee if authoritative;
- direct admin URL.

### CTA
`بازکردن تیکت در پنل مدیریت`

### Note
Keep compact. No onboarding/footer-heavy treatment.

---

## 16. admin.manual_custom

### Guidance
No fixed v2 copy. It is an envelope.

Admin authoring UI should eventually require:

- subject;
- classification;
- audience;
- primary CTA optional;
- preview;
- sender identity;
- safe content body.

It should inherit the shared EarthCoop email shell.

---

## 17. system.scheduled_smoke_test

No redesign required.

Keep diagnostic:
- what was tested;
- which layer succeeded;
- timestamp/run identifier if useful.

Never style it as a normal member email.

---

## 18. Shared footer copy proposals

### Required/security
«این پیام به دلیل یک اقدام امنیتی یا حساب کاربری برای شما ارسال شده است.»

### Operational/configurable
«این پیام بر اساس فعالیت یا تنظیمات ارتباطی حساب EarthCoop شما ارسال شده است.»  
[تنظیمات ارتباطی]({{preferences_url}})

### Support
«این پیام مربوط به یک درخواست پشتیبانی یا تماس با EarthCoop است.»

## 19. Implementation dependencies to resolve later

Before v2 publication, implementation must inventory exact canonical named routes for:

- member home/dashboard;
- profile;
- groups;
- participation;
- governance/location if used;
- getting started/docs;
- communication preferences;
- registration;
- support/contact;
- manager responsibilities;
- inspector responsibilities;
- Najm Bahar project detail.

No URL should be hard-coded before this inventory is complete.

## 20. Approval checklist for v2 rollout

Before implementation:

- approve this copy direction;
- resolve canonical routes;
- decide shared email layout strategy;
- coordinate with active Temporal work;
- implement context additions;
- publish new immutable versions;
- preview all templates;
- test Gmail/mobile;
- smoke Welcome, Verification, Invitation, Weekly Report, Ticket Created/Reply, Contact Reply and Najm Bahar assignment where Production paths exist.
