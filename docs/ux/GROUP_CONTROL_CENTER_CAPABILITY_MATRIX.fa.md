# ماتریس حفظ قابلیت‌ها — Group Control Center

> وضعیت: canonical inventory برای بازآرایی Chat-first روی branch `agent/pre-main-ui-polish`
>
> قاعده: هیچ کنترل، route، policy، permission یا search فعلی قبل از داشتن مقصد نهایی و regression contract حذف نمی‌شود.

## قرارداد سطوح تجربه

| سطح | نقش نهایی |
|---|---|
| `groups/chat/{group}` | مقصد روزمره عضو؛ گفتگو و محتوای زنده |
| Hero چت | هویت و وضعیت ضروری گروه + ورودی واضح «پنل گروه»؛ فقط actionهای بسیار پرتکرار |
| Group Control Center | مرکز عملیات داخل Chat با چهار تب `محتوا / اعضا / حکمرانی / ابزارها` |
| `groups/{group}` | داشبورد وضعیت، شاخص‌ها و گزارش‌های گروه؛ نه lobby ورود به Chat |

## ماتریس capability

| capability | current_surface | final_tab / surface | route_or_action | visibility_source فعلی که باید حفظ شود | search_contract |
|---|---|---|---|---|---|
| باز/بسته‌کردن Hero | Chat Hero | Hero | `data-group-chat-action="toggle-group-hero"` | همه کاربران دارای دسترسی Chat | ندارد |
| بازکردن پنل گروه | Hero | Hero → Control Center | `data-chat-page-action="open-group-info"` | همه کاربران Chat | ندارد |
| ایجاد پست | Hero + composer | `محتوا` + composer shortcut | `data-chat-page-action="open-blog"` | فعلاً `yourRole !== 5`؛ شرط عیناً حفظ شود | جست‌وجوی محتوا باید عنوان/متن پست را پوشش دهد |
| فهرست/مرور پست‌ها | Panel تب `post` | `محتوا` | rendering فعلی `$blogs` + route نظرات `groups.comment` | اعضای مجاز پنل | جست‌وجوی contextual محتوا + فیلترهای فعلی لایک/دیسلایک/نظر/category حفظ شوند |
| ساخت نظرسنجی | Hero + composer | `محتوا` + composer shortcut | `data-chat-page-action="open-poll"` | فعلاً `yourRole !== 5` | جست‌وجوی محتوا باید عنوان/متن نظرسنجی را پوشش دهد |
| مرور نظرسنجی | Panel تب `poll` | `محتوا` | partial فعلی `groups.partials.poll` | اعضای مجاز | جست‌وجوی contextual محتوا؛ پاک‌کردن query همه نتایج را برگرداند |
| شرکت در انتخابات جاری | Hero | `حکمرانی`؛ در صورت active بودن می‌تواند shortcut محدود در Hero بماند | `data-chat-page-action="open-election"` | `$electionAvailable` + `$canParticipateElection` | جست‌وجوی contextual حکمرانی در عنوان/موضوع انتخابات هنگام وجود list |
| مرور انتخابات | Panel تب `election` | `حکمرانی` | partial poll با `main_type = 0` | اعضای مجاز | contextual |
| افزودن انتخابات | Hero + Panel action | `حکمرانی` | `data-chat-page-action="open-election-admin"` | فقط `yourRole in [2,3]` | ندارد |
| مدیریت اعضا | Hero | `اعضا` | `data-chat-page-action="manage-members"` | فعلاً مدیر `yourRole == 3`؛ policy فعلی تغییر نکند | search اعضا |
| فهرست اعضا | Panel تب `members` | `اعضا` | `$userMemberList` | اعضای مجاز | **الزامی:** نام + نقش + ایمیل؛ `membersSearch`; data attributes `data-name/data-role/data-email` حفظ شوند |
| مدیران و بازرسان | Panel تب `admins` | `اعضا` | `$admins` با roleهای 2/3 | اعضای مجاز | در Control Center جدید زیر همان search اعضا قابل فیلتر باشد |
| پروفایل عضو | Panel members/admins | `اعضا` | `profile.member.show` | همان visibility member list | search اعضا |
| تغییر موقت نقش عضو | Panel members | `اعضا` | `data-chat-feature-action="toggle-member-role"` | مدیر `yourRole == 3` و role هدف فعلی `[0,1]` | search اعضا |
| مجوز مشارکت در نشست بسته | Panel members | `اعضا` یا subsection حکمرانی/نشست با دسترسی از عضو | `groups.session-permissions.toggle` | `yourRole in [2,3]` و role هدف `[1,4,5]` | search اعضا |
| افزودن مهمان | Panel action/modal | `اعضا` | `#addUserButton` + guest modal | `group.location_level != 10` و `yourRole in [2,3]` | **الزامی:** `searchUsers` کد/نام/ایمیل/تلفن |
| درخواست چت مدیران گروه‌های دیگر | Panel action/modal | `اعضا` یا `حکمرانی` (ارتباط مدیریتی)؛ مقصد نهایی: `اعضا` subsection «ارتباط مدیران» | `#addChatRequestButton` + manager chat modal | `group.location_level != 10` و `yourRole in [2,3]` | **الزامی:** `searchManagers` مدیر یا گروه؛ incoming/outgoing tabs حفظ شوند |
| فعال/غیرفعال‌کردن نشست | Panel action | `حکمرانی` | `[data-session-toggle]` | `group.location_level != 10` و `yourRole in [2,3]` | ندارد |
| مدیریت مشارکت نشست | Panel action | `حکمرانی` | `[data-session-admin-open]` + `sessionParticipationBadge` | `group.location_level != 10` و `yourRole in [2,3]` | در modal/list مربوط search فعلی اگر موجود است حفظ شود؛ بدون اثبات حذف نشود |
| گزارش‌های محتوایی/کاربری | Hero | `حکمرانی` | `data-chat-page-action="manage-reports"` | مدیر `yourRole == 3` | search/filter موجود modal گزارش حفظ شود |
| آمار و گزارش‌گیری گروه | Panel تب `stats` | `حکمرانی` + shortcut به داشبورد | `loadGroupStats`/stats runtime فعلی | مدیر `yourRole == 3` | اگر list/filter دارد scope همان بخش حفظ شود |
| تنظیمات گروه | Hero | `حکمرانی` | `data-chat-page-action="group-settings"` | شرط فعلی Hero عیناً حفظ شود؛ backend authorization مرجع نهایی است | ندارد |
| ویرایش گروه | Panel action | `حکمرانی` | `data-chat-page-action="open-group-edit"` | `group.location_level != 10` و `yourRole in [2,3]` | ندارد |
| خروج از گروه | Hero + Panel | Control Center footer/contextual danger action | route `groups.logout` | همه اعضایی که route اجازه می‌دهد | ندارد |
| navigation گروه‌های مرتبط | Panel تب `group` | **حذف نمی‌شود**؛ در `ابزارها` subsection «گروه‌های من» یا navigation سبک | لینک فعلی هر گروه فعال به `groups.chat` | membership + location/specialty approval + pivot status فعلی | **الزامی:** `groupSearch` + `searchType` نام/محتوا |
| داشبورد/نمای وضعیت گروه | `groups/{group}` و مسیر قبل از Chat | `ابزارها` + CTA «داشبورد و گزارش‌های گروه» | route `groups.show` | اعضای مجاز طبق controller/policy فعلی | search داخلی dashboard در صورت وجود حفظ شود |
| گفت‌وگوی گروه | CTA فعلی `groups/{group}` | CTA اصلی dashboard و مقصد primary از «گروه‌های من» | route `groups.chat` | active/approved membership constraints فعلی | ندارد |
| دبیرخانه گروه | ماژول مستقل Secretariat | `ابزارها` + shortcut ثانویه dashboard | route `secretariat.group` (`/secretariat/groups/{group}`) | **discoverable برای همه اعضا**؛ محتوای داخل فقط با Policy/ACL دبیرخانه | search خود دبیرخانه مستقل می‌ماند |
| پنل مدیریتی نجم هدا | `groups/{group}` / view مستقل | `حکمرانی` با card برجسته + shortcut dashboard | route canonical `groups.najm-hoda.panel` (و attention route مستقل `groups.najm-hoda.attention`) | **فقط مدیر/بازرس `[2,3]`**؛ backend authorization مرجع | search/filters خود پنل نجم هدا مستقل |
| نجم هدا — attention | ماژول مدیریتی | `حکمرانی` در مسیر نجم هدا، نه shortcut جدا مگر UX لازم کند | `groups.najm-hoda.attention` | Authenticate + authorization کنترلر؛ از Control Center فقط مدیر/بازرس | مستقل |
| حساب و امور مالی گروه — نجم بهار | `groups/{group}` / Najm Bahar group dashboard | `ابزارها` با عنوان صریح «حساب و امور مالی گروه — نجم بهار» | route canonical `groups.najm-bahar.dashboard` | authorization فعلی Najm Bahar حفظ شود | search/filter داخل ماژول مالی مستقل |
| کیف پول گروه | Najm Bahar | داخل dashboard نجم بهار؛ در Control Center duplicate نشود | route/action فعلی wallet گروه | authorization موجود | مستقل |
| انتقال وجه گروه | Najm Bahar | داخل dashboard نجم بهار | route/action transfer فعلی | authorization موجود | مستقل |
| حساب‌های فرعی گروه | Najm Bahar | داخل dashboard نجم بهار | `groups.najm-bahar.sub-accounts.index` و CRUD/transfer routes موجود | authorization موجود | search/filter ماژول مستقل |
| سوابق تراکنش/گزارش مالی | Najm Bahar | داخل dashboard نجم بهار | routes/report UI فعلی | authorization موجود | search/filter ماژول مستقل |

## Search Preservation Registry

این registry حداقل search/filterهای مشاهده‌شده در implementation فعلی را canonical می‌کند. هر Task باید قبل از حذف markup قدیمی parity مقصد را اثبات کند.

| search/filter فعلی | source | scope فعلی | قرارداد مقصد |
|---|---|---|---|
| `groupSearch` | `group_info_panel.blade.php` | گروه‌های مرتبط | `ابزارها` → گروه‌های من؛ نام/محتوا مطابق `searchType` |
| `searchType` | `group_info_panel.blade.php` | select نام گروه / محتوا | عین capability حفظ شود یا با UI معادل واضح‌تر جایگزین شود؛ هیچ mode حذف نشود |
| `membersSearch` | `group_info_panel.blade.php` | نام، نقش، ایمیل عضو | `اعضا`؛ search واحد اعضا/مدیران/بازرسان |
| `searchUsers` | guest modal | کد کاربری، نام، ایمیل، تلفن | modal افزودن مهمان؛ بدون تغییر scope |
| `searchManagers` | manager chat modal | مدیر یا گروه | modal ارتباط مدیران؛ بدون تغییر scope |
| post filter buttons | تب پست | همه / بیشترین لایک / دیسلایک / نظر / دسته | `محتوا`; در کنار search contextual حفظ شود |
| manager chat incoming/outgoing tabs | manager chat modal | direction filter | حفظ کامل |
| هر search/filter دیگری که در UAT یا source بعدی کشف شود | هر سطح | — | قبل از حذف source قدیمی به این registry افزوده شود |

## قرارداد چهار تب جدید

1. **محتوا**: ایجاد/مرور پست، نظرسنجی و محتوای گروه؛ search contextual محتوا.
2. **اعضا**: اعضا، مدیران، بازرسان، پروفایل، guest و ارتباط مدیران؛ search contextual نام/نقش/ایمیل.
3. **حکمرانی**: انتخابات، نشست، گزارش‌ها، تنظیمات/ویرایش و **نجم هدا فقط مدیر/بازرس**.
4. **ابزارها**: داشبورد گروه، **دبیرخانه برای همه اعضا (Policy داخل ماژول)**، **نجم بهار** و navigation کمکی گروه‌ها.

## قواعد حذف legacy

- هیچ tab یا action قدیمی تا زمانی که destination جدید + handler + visibility + search parity تست نشده حذف نمی‌شود.
- شرط‌های Blade موجود هنگام انتقال عیناً حفظ می‌شوند مگر اینکه backend policy صریحاً سخت‌گیرانه‌تر باشد.
- visibility در UI جای authorization backend را نمی‌گیرد.
- Control Center نباید منطق مالی، دبیرخانه یا نجم هدا را duplicate کند؛ فقط ورودی واضح به ماژول canonical می‌دهد.
- مسیر `گروه‌های من → Chat` فقط href/CTA را تغییر می‌دهد و approval/pending/membership logic را دست نمی‌زند.
