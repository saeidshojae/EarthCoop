# EarthCoop Email Content & Design Style Guide

**Status:** Proposed standard for all user-facing Communication Center email content.  
**Scope:** Copy, information hierarchy, CTA behavior, visual/content consistency.  
**Runtime note:** This document does not define or change renderer implementation.

## 1. Brand voice

EarthCoop email should sound:

- clear, calm and respectful;
- civic and cooperative, not corporate-salesy;
- practical and action-oriented;
- concise enough to scan on mobile;
- explanatory when the user needs orientation;
- restrained in claims: no promise beyond what the system actually supports.

Avoid:

- exaggerated enthusiasm;
- slogans in transactional/security mail;
- vague phrases such as «برای تجربه بهتر کلیک کنید»;
- unexplained internal terminology;
- legal/economic claims that are not backed by an authoritative EarthCoop page;
- mixed naming such as `Earth Coop` / `EarthCoop` in the same product.

Preferred product name in copy: **EarthCoop**.

## 2. Email mission model

Every email must declare exactly one primary mission:

1. **Security / required** — verify, reset, access.
2. **Onboarding** — help a new member understand where to start.
3. **Operational action** — tell the user what requires attention.
4. **Status / confirmation** — confirm that an action was registered.
5. **Support** — keep the user informed and guide the next support action.
6. **Report** — summarize what changed and point to relevant action.
7. **Invitation** — explain why the recipient was contacted and how to proceed.

If a draft appears to have two unrelated missions, split it.

## 3. Subject line rules

Subjects should:

- be understandable without opening the message;
- include EarthCoop only when it improves recognition or security;
- avoid decorative emoji in security/transactional messages;
- avoid all-caps;
- avoid generic subjects such as «اطلاعیه مهم».

Examples:

- خوب: «کد تأیید ایمیل EarthCoop»
- خوب: «پاسخ جدید به تیکت TK-12345678»
- خوب: «هفته شما در EarthCoop — ۲ انتخابات باز»
- ضعیف: «سلام! خبرهای خوب»
- ضعیف: «Important Notification»

## 4. Preheader

Where supported, preheader should complement the subject rather than repeat it.

Examples:

- subject: «کد تأیید ایمیل EarthCoop»
- preheader: «این کد ۵ دقیقه اعتبار دارد.»

- subject: «هفته شما در EarthCoop»
- preheader: «۲ انتخابات و ۱ نظرسنجی باز در گروه‌های شما.»

## 5. Message structure

Default user-facing structure:

1. eyebrow/category label if useful;
2. heading;
3. short opening paragraph;
4. core information/action;
5. one primary CTA;
6. optional secondary links;
7. contextual note / security note;
8. footer.

For security email, strip this down to the minimum necessary.

## 6. Greeting

Preferred:

- `{{display_name}} عزیز،`
- or neutral `سلام،` when identity is not available.

Avoid artificial honorifics or over-formal language.

## 7. CTA rules

One primary CTA per email.

CTA labels should describe the action:

- «شروع فعالیت»
- «تکمیل پروفایل»
- «مشاهده و پاسخ به تیکت»
- «بررسی پروژه»
- «مشاهده مشارکت‌های باز»
- «شروع ثبت‌نام»

Avoid:

- «کلیک کنید»
- «بیشتر»
- «ادامه»
unless the destination is obvious from nearby context.

Secondary actions should be plain links, not multiple competing buttons.

## 8. Link rules

All user-facing links should be generated from named routes or a central URL/context builder.

Do not hard-code production hostnames into template content.

Each link must have:

- a stable semantic variable name;
- a known authenticated/public access expectation;
- a clear label;
- no surprise redirect to an unrelated surface.

## 9. Security email rules

Verification/reset email should:

- make the code visually prominent;
- state exact validity;
- say not to share the code;
- state what to do if the user did not initiate the request;
- avoid onboarding/promotional content;
- avoid multiple CTAs.

## 10. Onboarding email rules

Welcome email may be longer than transactional mail but should still fit a first-session mental model.

It should answer:

- what membership completion means;
- what the user should do first;
- where their groups/participation are;
- where authoritative learning material is;
- how to get help.

Do not explain the entire EarthCoop constitution/economy in the email. Link to the authoritative source.

## 11. Weekly report rules

Weekly reports should prioritize action.

Bad:
«گروه‌ها: 18 | انتخابات: 2 | نظرسنجی: 1»

Better:
«این هفته ۲ انتخابات و ۱ نظرسنجی در گروه‌های شما باز است.»

Metrics may still appear in a compact summary block, but the user should understand what deserves attention.

## 12. Support email rules

Support email must reflect actual Production capability.

At current closure state:

- users can create/view/reply to tickets inside EarthCoop;
- outbound ticket emails work;
- direct inbound reply-by-email is **not active in Production** because Mailgun/`MAILGUN_SECRET` is not configured.

Therefore support email must currently say:
«برای پاسخ، تیکت را در EarthCoop باز کنید.»

It must **not** say:
«به همین ایمیل Reply کنید.»

This rule may change only after inbound provider integration is configured and smoke-tested.

## 13. Contact email rules

A ContactMessage is not automatically a Ticket.

Contact reply copy must not imply:

- a formal support ticket exists;
- the sender is an authenticated EarthCoop member;
- a case number exists unless conversion actually happened.

## 14. Invitation email rules

Invitation messages must explain:

- why the recipient received the message;
- whether it is a requested code or a member invitation;
- the invitation code;
- expiry;
- the next action;
- that the recipient may ignore the message if uninterested.

Member-originated invitation should name the inviter only when that identity is intentionally part of the invitation contract.

## 15. Formatting rules

Recommended body conventions:

- RTL container;
- short paragraphs;
- clear headings;
- 16px body-equivalent readability;
- generous line spacing;
- one column;
- no dependency on JS;
- no externally required web font;
- buttons with adequate tap size;
- text links visible without hover;
- no critical information encoded by color alone.

## 16. Plain-text fallback

Long-term target: every delivered HTML email should have a readable plain-text alternative.

Plain text should preserve:

- reason for email;
- code/important value;
- primary link;
- support/security note.

## 17. Footer

Default footer should be concise:

- EarthCoop identity;
- why the recipient received the email;
- support/contact link where relevant;
- communication-preferences link only for communications that can be suppressed/configured.

Required security messages must not imply that users can unsubscribe from security mail.

## 18. Copy safety

Before publishing a new version, review:

- does every claim match current product behavior?
- do all links exist and resolve correctly?
- does the email accidentally expose private/internal data?
- does it imply a responsibility/deadline that the system does not enforce?
- is the tone appropriate for the message classification?
- does it remain understandable on mobile?
- is there exactly one primary action?

## 19. Versioning

Never overwrite historical published content.

Professional revisions should publish new immutable versions:

- v1 remains historical;
- v2 becomes current;
- delivery history retains the exact version used.

## 20. Review checklist

A template is ready when:

- mission is stated;
- audience is clear;
- subject is specific;
- primary CTA is clear or intentionally absent;
- copy matches Production behavior;
- required links are route-generated;
- variables are documented;
- no unsupported capability is promised;
- rendering is mobile-safe;
- historical version remains intact.
