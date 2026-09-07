# سیاست فشرده‌سازی توپولوژی انتخاباتی EarthCoop

## وضعیت

این سند مکمل فنی `E0_ELECTIONS_GOVERNANCE_SPEC_FA.md` و Master Roadmap بازسازی انتخابات است و از Phase E8 به بعد باید در resolution سلسله‌مراتب، appointment و continuity رعایت شود.

## اصل بنیادین

سطوح جغرافیایی/اداری EarthCoop لزوماً همگی «سطوح انتخاباتی مستقل» نیستند.

یک سطح بالادستی فقط زمانی انتخابات مستقل برگزار می‌کند که از **بیش از یک حوزهٔ نمایندگی ساختاری مستقل** تشکیل شده باشد. اگر یک parent از نظر توپولوژی ثبت‌شده دقیقاً یک حوزهٔ پایین‌دستی داشته باشد که تمام آن parent را تشکیل می‌دهد، برگزاری انتخابات دوباره بین همان افراد اطلاعات نمایندگی تازه‌ای تولید نمی‌کند و آن لایه انتخاباتی فشرده می‌شود.

## قاعده عمومی

برای هر رابطه child → parent:

- اگر parent از نظر ساختار مکان ثبت‌شده بیش از یک child constituency داشته باشد:
  - سمت child به parent منتقل نمی‌شود؛
  - مسئولان child در parent عضو فعال/نماینده می‌شوند؛
  - انتخابات parent پس از تحقق threshold/policy خودش برگزار می‌شود.

- اگر parent دقیقاً یک child constituency ساختاری داشته باشد:
  - انتخابات مستقل parent حذف/فشرده می‌شود؛
  - manager/inspector منتخب child به‌صورت appointment مشتق‌شده (`inherited`) همان مسئولیت را در parent نیز می‌گیرد؛
  - برای هر scope یک appointment مستقل و auditپذیر ثبت می‌شود؛
  - inheritance می‌تواند تا چند سطح تک‌حوزه‌ای پشت‌سرهم ادامه یابد؛
  - در نخستین parent چندحوزه‌ای inheritance متوقف و representation فعال می‌شود.

## ساختاری، نه جمعیتی

تعداد حوزه‌ها از **توپولوژی جغرافیایی ثبت‌شده** تعیین می‌شود، نه تعداد اعضای فعلی EarthCoop در آن حوزه‌ها.

در نتیجه کم‌بودن adoption نمی‌تواند باعث شود مسئول یک محله فقط به دلیل نبود کاربران کافی در شهرهای دیگر، به‌طور ناخواسته مسئول استان/کشور/جهان شود.

Member count و active membership فقط در threshold و eligibility انتخابات نقش دارند؛ نه در تشخیص تک‌حوزه‌ای بودن ساختاری.

## سطوح اختیاری

اگر یک سطح در مسیر واقعی کاربر وجود نداشته باشد، hierarchy resolver باید آن را skip کند.

نمونه:

`Neighborhood → City`

در شهری که `Region` ندارد، نباید Region مصنوعی ساخته شود.

همین اصل برای شاخه روستایی نیز برقرار است:

`Neighborhood → Village → Rural → Section`

یا هر مسیر معتبر دیگری که address واقعی کاربر نشان دهد.

## شاخه‌های موازی

در بعضی parentها چند نوع child هم‌سطح وجود دارد. نمونه مهم فعلی:

`Section → City | Rural`

بنابراین برای تشخیص تک‌حوزه‌ای بودن Section، مجموع City و Ruralهای ساختاری آن باید شمرده شود.

وجود یک City و یک Rural یعنی دو constituency و در نتیجه **عدم فشرده‌سازی**، حتی اگر از هر type فقط یک رکورد وجود داشته باشد.

## بالاترین کرسی معتبر

اگر فردی بعداً از طریق انتخابات واقعی در scope بالاتر همان track و همان position منصوب شود، appointment مستقل پایین‌تر او باید با audit به `superseded` تبدیل شود و assignment نمایندگی مشتق‌شده قبلی پایان یابد.

این قاعده نباید appointmentهای track متفاوت (مثلاً تخصص متفاوت) را بی‌دلیل حذف کند.

## عدم دوبرابر شدن قدرت نمایندگی

Inherited appointment باعث ایجاد رأی یا membership مضاعف برای یک انسان در یک group نمی‌شود. در اولین parent چندحوزه‌ای، فرد فقط یک active membership دارد.

## جبران خدمت

Inheritance به‌خودی‌خود به معنی ایجاد دستمزد دوم نیست. هر compensation مستقل برای scope inherited نیازمند policy صریح و versioned است.

## تغییر توپولوژی

اگر parent در زمان آینده از تک‌حوزه‌ای به چندحوزه‌ای تبدیل شود، inherited appointment نباید دائمی فرض شود.

Phase E9 باید topology reconciliation را انجام دهد:

1. تغییر topology را تشخیص و audit کند؛
2. inheritance دیگر معتبر را در زمان policy/cycle مناسب پایان دهد؛
3. مسئولان حوزه‌های جدید را به representation فعال parent تبدیل کند؛
4. انتخابات واقعی parent را پس از تحقق threshold آغاز کند؛
5. continuity مسئولیت را تا نصب جانشین معتبر حفظ کند.

## Invariantهای فنی

1. `direct` و `inherited` appointment از هم متمایزند.
2. هر `responsibility_offer + group + position` حداکثر یک appointment دارد.
3. هر inheritance به `source_appointment_id` قابل ردیابی است.
4. همه appointmentها و representationها actor/reason/timestamp/metadata دارند.
5. installation و representation transaction-safe و idempotent هستند.
6. Controller مجاز به mutation مستقیم role/representation انتخاباتی نیست.
7. topology compression هیچ‌گاه از current member population استنتاج نمی‌شود.
8. اولین parent چندحوزه‌ای پایان inheritance و آغاز representation است.
