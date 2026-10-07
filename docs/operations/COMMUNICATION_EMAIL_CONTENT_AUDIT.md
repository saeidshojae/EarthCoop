# EarthCoop Email Content & Design Audit — 2026-10-06

**Scope:** Content/design audit only. No renderer, template model, migration, queue, scheduler, Temporal, or Production runtime code is changed by this document.

## 1. Executive conclusion

EarthCoop's Communication Center delivery architecture is now substantially more mature than the content carried by many of its templates.

The current system stores template bodies as HTML and the delivery adapter sends them with `Mail::html()`. `CommunicationTemplateRenderer` currently performs safe-ish declared-variable interpolation but does **not** parse Markdown.

Therefore the professional target should not be “emails must be Markdown.” The target should be:

1. simple, maintainable source content;
2. a shared EarthCoop email visual/layout system;
3. controlled HTML output compatible with major email clients;
4. a plain-text alternative where practical;
5. purpose-specific copy, one clear primary action, and useful contextual links;
6. immutable version publication so historical emails remain reproducible.

Markdown may later be accepted as an authoring format and compiled into controlled HTML, but switching template bodies to raw Markdown without a rendering/layout layer would be a regression.

## 2. Current rendering contract

Current flow:

`CommunicationTemplateVersion.body` → `CommunicationTemplateRenderer` variable replacement → `EmailDeliveryAdapter` → `Mail::html()`

Current renderer behavior:

- declared `{{variable}}` replacement;
- missing required variables fail closed;
- unknown context variables fail closed;
- structured context may be retained for audit but is not interpolated;
- no Markdown conversion;
- no shared global email shell;
- no automatic CTA component;
- no automatic footer/preference block;
- no plain-text generation.

This means visual quality currently depends on every individual template/view implementing its own HTML.

## 3. Content design principles proposed for EarthCoop

Every outward-facing email should follow these rules unless its mission requires otherwise:

- **One mission per email.** The recipient should understand the reason for the email within the first screen.
- **One primary CTA.** Secondary links are allowed, but only one action should visually dominate.
- **Human Persian, not administrative Persian.** Short sentences, explicit verbs, minimal jargon.
- **EarthCoop identity should be consistent.** Use `EarthCoop` consistently; avoid mixed `Earth Coop` unless a legal/product naming decision says otherwise.
- **Security mail stays short.** Verification/password codes should not become onboarding newsletters.
- **Onboarding mail can teach.** Welcome should orient a new member and reduce first-session confusion.
- **Operational reports should prioritize action over statistics.** Numbers should lead to useful next steps.
- **Support mail should never promise unsupported capabilities.**
- **Transactional mail should explain why it was received.**
- **Optional/eligible operational mail should expose communication preferences where policy allows.**
- **Links should be generated from named routes/context builders, not hard-coded production URLs.**
- **Email content should not silently redefine EarthCoop law, governance, economy, or product behavior.** Educational wording must link to authoritative documents/pages where possible.

## 4. Shared email design system — recommended target

A single reusable shell should eventually provide:

- EarthCoop wordmark/logo area;
- preheader text;
- message category/eyebrow;
- heading;
- content body;
- primary CTA button;
- optional compact secondary-links block;
- security/notice callout;
- footer with EarthCoop identity;
- “why you received this” line;
- communication-preferences link only where suppression is legally/product-appropriate;
- support/contact link;
- accessible text contrast and RTL layout;
- mobile-friendly width around 560–640px;
- email-client-safe inline/table-oriented styling as needed.

The shared shell should avoid depending on web fonts, external JS, hover-only behavior, or CSS unsupported by common email clients.

## 5. Template inventory and audit

### 5.1 `onboarding.welcome` — critical content upgrade

**Current subject:** `به ارث‌کوپ خوش آمدید {{display_name}}`

**Current body:** essentially one sentence: membership is complete.

**Current context:** `display_name`, `email`, `profile_url`.

**Audit:** Technically valid but substantially below the onboarding mission. The recipient has just completed registration and is at the highest-information-need moment. This email should become a compact “first-day orientation,” not a marketing message.

**Recommended mission:** answer four questions quickly:

1. What did I just join?
2. What should I do first?
3. Where are my automatically-created groups and participation surfaces?
4. Where can I understand EarthCoop's principles/rules before acting?

**Recommended primary CTA:** “ورود به EarthCoop” or “شروع فعالیت”.

**Recommended secondary actions:**

- complete/review profile and location;
- open “گروه‌های من”;
- open “مشارکت‌های من”;
- read a concise “EarthCoop چیست؟” / constitution / docs start page;
- understand Najm Bahar only through an authoritative introductory page if that page exists and is ready.

**Recommended additional context variables (route-generated, not hard-coded):**

- `home_url` or canonical member dashboard URL;
- `profile_url`;
- `groups_url`;
- `participation_url`;
- `governance_url` where appropriate;
- `getting_started_url` or docs start URL;
- `communication_preferences_url`;
- optionally `najm_bahar_url` only when the member-facing page is stable.

**Proposed content structure:**

- greeting by name;
- one paragraph explaining that registration is complete;
- one short paragraph defining EarthCoop in product terms;
- “سه قدم برای شروع” with 3 concise actions;
- one main CTA;
- “برای شناخت بهتر” with 2–4 authority links;
- concise support/footer.

**Important:** Welcome should not make legal/economic claims that are not already represented in authoritative EarthCoop documents.

### 5.2 `auth.email_verification`

**Current:** clear 6-digit verification code, 5-minute expiry.

**Audit:** Appropriate to remain short. It should not be turned into onboarding content.

**Recommended improvements:**

- subject can clarify purpose: “کد تأیید ایمیل EarthCoop”;
- visually emphasize the code;
- state 5-minute validity;
- add “اگر شما این درخواست را آغاز نکرده‌اید، این پیام را نادیده بگیرید”;
- do not add unnecessary CTA if code entry happens in the already-open browser;
- optionally mention never to share the code.

**Priority:** medium.

### 5.3 `auth.password_reset`

**Current:** clear reset code, 5-minute validity, ignore-if-not-requested notice.

**Audit:** Already close to professional transactional copy.

**Recommended improvements:**

- consistent EarthCoop naming;
- stronger security language: do not share the code;
- explain that ignoring the message leaves the password unchanged;
- optional support link if repeated unsolicited resets occur.

**Priority:** low/medium.

### 5.4 `auth.invitation_issued`

**Current:** request approved, code shown, expiry shown.

**Audit:** Functional but does not explain what the code is for or the next action clearly enough.

**Recommended mission:** “Your request for an invitation was approved; here is the code and the next step.”

**Recommended variables:**

- `code`;
- human-formatted `expire_at`;
- `registration_url` / `start_url`;
- optional `learn_more_url`.

**Primary CTA:** “شروع ثبت‌نام”.

**Priority:** high.

### 5.5 `membership.member_invitation`

**Current:** “برای پیوستن به EarthCoop از کد زیر استفاده کنید” plus code/expiry.

**Audit:** Missing the inviter context and a concise explanation of what the recipient is being invited to.

**Recommended variables if domain state supports them safely:**

- `inviter_name`;
- `code`;
- `expire_at`;
- `registration_url`;
- `learn_more_url`.

**Recommended copy:** Explain that a member invited the recipient, EarthCoop registration requires the code, and the recipient is free to ignore the invitation.

**Priority:** high.

### 5.6 `auth.invitation_rejected`

**Current:** direct statement that request was rejected plus optional admin note.

**Audit:** Tone is too abrupt for a trust-sensitive onboarding edge case.

**Recommended copy rules:**

- neutral, respectful wording;
- avoid implying wrongdoing unless the admin note explicitly states a verified reason;
- clearly distinguish “not approved at this time” from permanent exclusion if product policy allows reapplication;
- include next-step/support guidance only if an actual process exists.

**Priority:** high.

### 5.7 `reports.member.weekly`

**Current:** date range plus one-line counts for groups, elections, polls, unread notifications.

**Audit:** Data is present but it is not an actionable report.

**Recommended structure:**

- “هفته شما در EarthCoop”;
- 3–4 metric cards/rows;
- explicit callouts only when count > 0, e.g. “۲ انتخابات باز نیازمند توجه شماست”;
- primary CTA to participation/dashboard;
- secondary link to groups;
- communication-preferences link because weekly reports are operational/user-configurable.

**Recommended context additions:**

- `dashboard_url`;
- `groups_url`;
- `participation_url`;
- `preferences_url`.

**Priority:** high.

### 5.8 `reports.manager.weekly`

**Current:** member metrics plus managed-group metrics.

**Audit:** Useful raw data but does not distinguish “personal participation” from “management attention.”

**Recommended structure:**

1. your personal participation;
2. responsibilities needing management attention;
3. open elections/polls in managed groups;
4. primary CTA to management/governance task surface.

Avoid sending a dense spreadsheet in email.

**Priority:** high.

### 5.9 `reports.inspector.weekly`

**Current:** member metrics plus inspected-group metrics.

**Audit:** Same issue as manager report. Should frame the role section around oversight/inspection responsibilities without implying findings that the system has not actually computed.

**Recommended structure:** personal summary + inspection scope + open processes requiring review + CTA.

**Priority:** high.

### 5.10 `support.ticket_created`

**Current rendering:** a dedicated Blade email already contains a styled header, tracking code, subject/status/priority/category/date, original message and “مشاهده تیکت” button.

**Audit:** Much more mature than most templates, but it duplicates its own visual design instead of using a shared EarthCoop email shell.

**Critical content issue:** footer says “لطفاً به این ایمیل پاسخ ندهید.” This is correct for current Production because inbound Mailgun email processing is dormant.

**Recommended improvements:**

- keep tracking code prominent;
- primary CTA remains “مشاهده تیکت”;
- explain where future replies will appear;
- remove redundant detail if mobile readability suffers;
- normalize `Earth Coop` → `EarthCoop`;
- migrate to shared design shell later.

**Priority:** medium.

### 5.11 `support.ticket_reply`

**Current rendering:** dedicated Blade email with ticket info, responder/date, reply text, CTA.

**Critical correctness defect in current copy:** it currently says:

“شما می‌توانید با پاسخ دادن به این ایمیل، پاسخ خود را برای تیم پشتیبانی ارسال کنید...”

But Production does **not** have Mailgun/`MAILGUN_SECRET` configured, so inbound email reply processing is dormant. This promise is currently false in Production.

**Required content correction:** until inbound email provider integration is explicitly activated and smoke-tested, the email must instruct the user to open the ticket in EarthCoop to reply. It should not invite direct email reply.

This is the highest-priority content defect found in the audit.

**Primary CTA:** “مشاهده و پاسخ به تیکت”.

**Priority:** urgent.

### 5.12 `contact.reply`

**Current template:** subject wrapper + dynamically generated admin reply HTML.

**Audit:** The admin's reply is delivered, but the surrounding communication lacks context.

**Recommended additions:**

- greeting when contact name is safely available;
- mention the original contact subject;
- response body;
- link to contact/support entry point if further help is needed;
- avoid implying a formal Ticket exists unless it was actually converted.

**Priority:** medium/high.

### 5.13 `support.ticket_internal_alert`

**Current:** internal subject and rendered HTML supplied by caller.

**Audit:** Internal operational message. It should stay compact and action-oriented, not receive consumer onboarding styling.

**Recommended fields:** tracking code, subject, source (e.g. Najm Hoda escalation), assignee/priority if authoritative, and direct admin URL.

**Priority:** medium.

### 5.14 `faq.answer`

**Current:** subject “پاسخ به پرسش: ...” and answer in one paragraph.

**Audit:** Functional but can be clearer.

**Recommended content:**

- thank recipient for the question;
- restate question/title;
- answer;
- optional link to the published FAQ if it was published;
- support/contact path for unresolved issue.

Be careful with answer formatting: current interpolation into `<p>{{answer}}</p>` treats the answer as raw string interpolation; multiline/rich formatting needs an explicit safe policy rather than ad hoc HTML.

**Priority:** medium.

### 5.15 `najm_bahar.project_assigned`

**Current:** recipient greeting, project title, category, required capital, assignment note, direct project URL.

**Audit:** Structurally good. Needs a clearer reason for receiving the email, responsibility expectation, and CTA wording.

**Recommended primary CTA:** “بررسی پروژه”.

**Potential content additions only if authoritative data exists:**

- assignment role;
- review due date;
- expected action;
- conflict-of-interest/decline path.

Do not invent deadlines or obligations that the project workflow does not enforce.

**Priority:** medium.

### 5.16 `admin.manual_custom`

**Current:** subject + arbitrary rendered HTML passed by admin.

**Audit:** This is not a normal curated template; it is an administrative delivery envelope. It should use the shared EarthCoop shell but must not force one content structure.

**Recommendation:** admin editor should eventually offer safe content blocks/preview and clear classification/audience information.

**Priority:** medium.

### 5.17 `system.scheduled_smoke_test`

**Current:** internal scheduler/queue health message.

**Audit:** Not a user-facing content target. Keep plain and diagnostic.

**Priority:** no content redesign required.

## 6. Highest-priority findings

### P0 — Correct a false support promise

`resources/views/emails/ticket-reply.blade.php` promises direct reply-by-email, but inbound email provider integration is not active in Production. Until Mailgun (or another inbound provider) is configured and tested, copy must direct users back to the ticket page.

### P1 — Rebuild Welcome as real onboarding

Welcome is the largest content opportunity. Current context is too small for the desired onboarding mission, so implementation should expand the context builder with canonical route-generated links before publishing v2.

### P1 — Upgrade weekly reports from statistics to actions

Reports should tell members/managers/inspectors what deserves attention and where to act, not merely list counts.

### P1 — Improve invitations

Both invitation templates need a clear registration CTA and human explanation of EarthCoop/next step.

### P2 — Establish a shared visual shell

Ticket emails already show a stronger visual standard than database-only templates, but visual implementation is fragmented. A shared shell is the scalable fix.

## 7. Markdown recommendation

### Decision recommendation

Do **not** replace the current HTML bodies with raw Markdown as a quick change.

A better staged architecture is:

1. define shared email layout/components first;
2. decide whether template source format is:
   - controlled HTML;
   - Markdown compiled to sanitized HTML;
   - or structured content blocks rendered to HTML;
3. always deliver rendered HTML;
4. generate a plain-text alternative where feasible.

For EarthCoop admin editing, Markdown can be attractive because it is easier to write and safer than arbitrary HTML. If adopted, it should be a declared template content format with a controlled converter and sanitizer, not inferred from body text.

## 8. Versioning strategy

Professional copy changes should create **new immutable published template versions**, not mutate historical published versions.

Recommended rollout:

- publish `onboarding.welcome` v2;
- publish improved auth/invitation versions;
- publish weekly report v2 versions;
- update support Blade emails/shared shell;
- publish `najm_bahar.project_assigned` v2 if copy/context changes;
- preserve all v1 versions for audit/reproducibility.

## 9. Proposed implementation sequence after audit approval

### Phase A — correctness and content contract

1. Fix Ticket Reply copy so it does not promise inactive inbound-email reply.
2. Create a formal Email Content Style Guide.
3. Map canonical URLs/routes required by each template.
4. Expand context builders only with authoritative, stable links/data.

### Phase B — shared email design shell

1. Introduce a reusable RTL EarthCoop email layout.
2. Define primary CTA, info/callout, metric row, footer and preference-link components.
3. Verify Gmail/mobile rendering.
4. Add plain-text strategy.

### Phase C — content v2 publication

1. Welcome v2.
2. Invitation v2.
3. Weekly reports v2.
4. Verification/password refinement.
5. Contact/support/FAQ refinement.
6. Najm Bahar project assignment v2.

### Phase D — optional Markdown authoring

Only after the shared shell and safe rendering policy are proven, consider allowing Markdown as an admin authoring format.

## 10. Temporal concurrency rule

Temporal/Time-System work is already underway elsewhere. Content implementation must avoid editing Temporal-owned files concurrently.

Safe content-design work now:

- copy specification;
- route/link inventory;
- email visual system design;
- new documentation;
- content review.

Potentially conflicting implementation work to coordinate first:

- `CommunicationTemplateRenderer`;
- template/version model/schema;
- scheduled report context/time fields;
- any migration that touches communication scheduling/time semantics.

A content implementation branch should be based on the latest `main` after the relevant Temporal checkpoint.

## 11. Definition of done for professional email content

The content/design upgrade is complete when:

- every active user-facing template has a documented mission and audience;
- every email has a clear subject and primary action where applicable;
- Welcome provides a concise but meaningful onboarding path;
- weekly reports are actionable;
- security email remains short and safe;
- support copy matches actual Production capabilities;
- EarthCoop naming/tone/layout are consistent;
- canonical links are generated rather than hard-coded;
- historical template versions remain immutable;
- new content versions pass preview/rendering tests and Production smoke checks;
- no email claims a capability or rule that EarthCoop does not actually provide.
