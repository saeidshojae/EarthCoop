<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Date;

class PagesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = now();

        // صفحه اول: درباره EarthCoop
        $page1 = [
            'title' => 'درباره EarthCoop',
            'title_translations' => json_encode([
                'fa' => 'درباره EarthCoop',
                'en' => 'About EarthCoop',
                'ar' => 'حول EarthCoop',
            ]),
            'slug' => 'drbarh-earthcoop',
            'template' => 'about',
            'content' => '<p>به سامانه شراکت جهانی&nbsp;<strong>ارث‌کوپ</strong>&nbsp;خوش آمدید 🌍</p>
<p>ما انسان‌ها امروز با چالش‌هایی روبرو هستیم که همه‌ ما را در هر نقطه‌ای از سیاره زمین به یک اندازه تهدید می‌کنند. بحران‌هایی مانند گرمایش جهانی، تخریب محیط زیست، جنگ، خشونت، بیماری، گرسنگی، فقر و نابرابری، نتیجه‌ی سیاست‌ها و رفتارهای ناعادلانه و ناپایدار گذشته‌ی ما هستند.</p>
<p>اما هنوز امیدی است.<br />
اگر امروز با روحیه‌ای نو، آگاهانه و همدلانه، مسئولیت تاریخی خود را بپذیریم و دست در دست یکدیگر بگذاریم، می‌توانیم آینده‌ای بهتر بسازیم.</p>
<p><strong>ارث‌کوپ (EarthCoop)</strong>&nbsp;بر اساس این باور شکل گرفته که:<br />
<strong>زمین، خانه مشترک همه‌ی ماست.</strong><br />
خانه‌ای که ما در آن&nbsp;<em>وارثانی برابر</em>&nbsp;هستیم و در مالکیت، حقوق و مسئولیت‌های آن شریک یکدیگریم.</p>
<p>این شراکت جهانی، نقطه آغاز ساخت یک جامعه همکارانه و هم‌سرنوشت است که همه‌ی مردم زمین را دربرمی‌گیرد.</p>
<p>&nbsp;</p>
<h2>ارث‌کوپ چیست؟</h2>
<p>ارث‌کوپ یک شبکه مشارکت جهانی، با ساختار قانونی و شفاف است که برای همکاری، هم‌فکری و سرمایه‌گذاری جمعی از محله تا سیاره طراحی شده است.</p>
<p>با عضویت در ارث‌کوپ، شما یکی از&nbsp;<strong>سهامداران واقعی</strong>&nbsp;این شبکه خواهید بود.</p>
<p>در آغاز ثبت‌نام، از شما اطلاعات زیر دریافت می‌شود:</p>
<ul>
<li><p><strong>هویتی</strong>&nbsp;(نام، سن، جنسیت و ...)</p></li>
<li><p><strong>زمینه‌های شغلی، صنفی، تخصصی و تجربی</strong></p></li>
<li><p><strong>موقعیت مکانی دقیق</strong>&nbsp;(از قاره تا کوچه)</p></li>
</ul>
<p>بر این اساس، شما در سه نوع گروه سیستمی عضو می‌شوید:</p>
<ol>
<li><p><strong>گروه‌های مجامع عمومی</strong>&nbsp;(بر اساس مکان سکونت)</p></li>
<li><p><strong>گروه‌های تخصصی</strong>&nbsp;(بر اساس صنف و تخصص)</p></li>
<li><p><strong>گروه‌های اختصاصی</strong>&nbsp;(بر اساس سن و جنسیت)</p></li>
</ol>
<p>هر گروه، نقشی مشخص در توسعه پروژه‌های محلی و جهانی خواهد داشت و در چارچوب شفاف، مسئولانه و مشارکتی فعالیت می‌کند</p>
<p>&nbsp;</p>
<h3><strong>دعوت به عضویت</strong></h3>
<p>اگر شما هم به ساختن دنیایی بهتر برای خود، فرزندانتان و همه‌ی ساکنان این سیاره باور دارید، همین امروز عضویت بالقوه‌ی خود را در&nbsp;<strong>ارث‌کوپ</strong>&nbsp;فعال کنید.<br />
با هم، آینده‌ای متفاوت بسازیم.</p>',
            'content_translations' => json_encode([
                'fa' => '<p>به سامانه شراکت جهانی&nbsp;<strong>ارث‌کوپ</strong>&nbsp;خوش آمدید 🌍</p>
<p>ما انسان‌ها امروز با چالش‌هایی روبرو هستیم که همه‌ ما را در هر نقطه‌ای از سیاره زمین به یک اندازه تهدید می‌کنند. بحران‌هایی مانند گرمایش جهانی، تخریب محیط زیست، جنگ، خشونت، بیماری، گرسنگی، فقر و نابرابری، نتیجه‌ی سیاست‌ها و رفتارهای ناعادلانه و ناپایدار گذشته‌ی ما هستند.</p>
<p>اما هنوز امیدی است.<br />
اگر امروز با روحیه‌ای نو، آگاهانه و همدلانه، مسئولیت تاریخی خود را بپذیریم و دست در دست یکدیگر بگذاریم، می‌توانیم آینده‌ای بهتر بسازیم.</p>
<p><strong>ارث‌کوپ (EarthCoop)</strong>&nbsp;بر اساس این باور شکل گرفته که:<br />
<strong>زمین، خانه مشترک همه‌ی ماست.</strong><br />
خانه‌ای که ما در آن&nbsp;<em>وارثانی برابر</em>&nbsp;هستیم و در مالکیت، حقوق و مسئولیت‌های آن شریک یکدیگریم.</p>
<p>این شراکت جهانی، نقطه آغاز ساخت یک جامعه همکارانه و هم‌سرنوشت است که همه‌ی مردم زمین را دربرمی‌گیرد.</p>
<p>&nbsp;</p>
<h2>ارث‌کوپ چیست؟</h2>
<p>ارث‌کوپ یک شبکه مشارکت جهانی، با ساختار قانونی و شفاف است که برای همکاری، هم‌فکری و سرمایه‌گذاری جمعی از محله تا سیاره طراحی شده است.</p>
<p>با عضویت در ارث‌کوپ، شما یکی از&nbsp;<strong>سهامداران واقعی</strong>&nbsp;این شبکه خواهید بود.</p>
<p>در آغاز ثبت‌نام، از شما اطلاعات زیر دریافت می‌شود:</p>
<ul>
<li><p><strong>هویتی</strong>&nbsp;(نام، سن، جنسیت و ...)</p></li>
<li><p><strong>زمینه‌های شغلی، صنفی، تخصصی و تجربی</strong></p></li>
<li><p><strong>موقعیت مکانی دقیق</strong>&nbsp;(از قاره تا کوچه)</p></li>
</ul>
<p>بر این اساس، شما در سه نوع گروه سیستمی عضو می‌شوید:</p>
<ol>
<li><p><strong>گروه‌های مجامع عمومی</strong>&nbsp;(بر اساس مکان سکونت)</p></li>
<li><p><strong>گروه‌های تخصصی</strong>&nbsp;(بر اساس صنف و تخصص)</p></li>
<li><p><strong>گروه‌های اختصاصی</strong>&nbsp;(بر اساس سن و جنسیت)</p></li>
</ol>
<p>هر گروه، نقشی مشخص در توسعه پروژه‌های محلی و جهانی خواهد داشت و در چارچوب شفاف، مسئولانه و مشارکتی فعالیت می‌کند</p>
<p>&nbsp;</p>
<h3><strong>دعوت به عضویت</strong></h3>
<p>اگر شما هم به ساختن دنیایی بهتر برای خود، فرزندانتان و همه‌ی ساکنان این سیاره باور دارید، همین امروز عضویت بالقوه‌ی خود را در&nbsp;<strong>ارث‌کوپ</strong>&nbsp;فعال کنید.<br />
با هم، آینده‌ای متفاوت بسازیم.</p>',
                'en' => '<p>Welcome to the global partnership platform <strong>EarthCoop</strong> 🌍</p>
<p>We humans today face challenges that threaten all of us equally, everywhere on planet Earth. Crises like global warming, environmental degradation, war, violence, disease, hunger, poverty and inequality are the result of our past unfair and unsustainable policies and behaviors.</p>
<p>But there is still hope.<br />
If today, with a new, conscious and empathetic spirit, we accept our historical responsibility and join hands together, we can build a better future.</p>
<p><strong>EarthCoop</strong> is founded on the belief that:<br />
<strong>The Earth is the common home of all of us.</strong><br />
A home where we are <em>equal heirs</em> and partners in its ownership, rights and responsibilities.</p>
<p>This global partnership is the starting point for building a cooperative and shared‑destiny community that embraces all people of the Earth.</p>
<p>&nbsp;</p>
<h2>What is EarthCoop?</h2>
<p>EarthCoop is a global partnership network, with a transparent and legal structure, designed for cooperation, consultation and collective investment from the neighbourhood to the planet.</p>
<p>By joining EarthCoop, you become one of the <strong>real shareholders</strong> of this network.</p>
<p>At the beginning of registration, the following information is collected from you:</p>
<ul>
<li><p><strong>Identity</strong> (name, age, gender, etc.)</p></li>
<li><p><strong>Professional, trade, specialized and experiential fields</strong></p></li>
<li><p><strong>Exact geographical location</strong> (from continent to alley)</p></li>
</ul>
<p>Based on this, you will become a member of three types of systemic groups:</p>
<ol>
<li><p><strong>General Assembly groups</strong> (based on place of residence)</p></li>
<li><p><strong>Specialized groups</strong> (based on profession and expertise)</p></li>
<li><p><strong>Specific groups</strong> (based on age and gender)</p></li>
</ol>
<p>Each group will have a specific role in the development of local and global projects, operating within a transparent, responsible and participatory framework.</p>
<p>&nbsp;</p>
<h3><strong>Invitation to Membership</strong></h3>
<p>If you also believe in building a better world for yourself, your children and all inhabitants of this planet, activate your potential membership in <strong>EarthCoop</strong> today.<br />
Together, let us build a different future.</p>',
                'ar' => '<p>مرحباً بكم في منصة الشراكة العالمية <strong>EarthCoop</strong> 🌍</p>
<p>نحن البشر اليوم نواجه تحديات تهددنا جميعاً بالتساوي في كل مكان على كوكب الأرض. أزمات مثل الاحترار العالمي، تدمير البيئة، الحروب، العنف، الأمراض، الجوع، الفقر وعدم المساواة هي نتاج سياساتنا وسلوكياتنا غير العادلة وغير المستدامة في الماضي.</p>
<p>لكن لا يزال هناك أمل.<br />
إذا قبلنا اليوم، بروح جديدة وواعية ومتعاطفة، مسؤوليتنا التاريخية ووضعنا أيدينا في أيدي بعضنا البعض، يمكننا بناء مستقبل أفضل.</p>
<p><strong>EarthCoop</strong> تأسست على اعتقاد أن:<br />
<strong>الأرض هي البيت المشترك لنا جميعاً.</strong><br />
بيت نحن فيه <em>ورثة متساوون</em> وشركاء في ملكيته وحقوقه ومسؤولياته.</p>
<p>هذه الشراكة العالمية هي نقطة الانطلاق لبناء مجتمع تعاوني ومشترك المصير يضم جميع شعوب الأرض.</p>
<p>&nbsp;</p>
<h2>ما هو EarthCoop؟</h2>
<p>EarthCoop هي شبكة شراكة عالمية، ذات هيكل قانوني وشفاف، مصممة للتعاون والتشاور والاستثمار الجماعي من الحي إلى الكوكب.</p>
<p>بالانضمام إلى EarthCoop، تصبح واحداً من <strong>المساهمين الحقيقيين</strong> في هذه الشبكة.</p>
<p>في بداية التسجيل، يتم جمع المعلومات التالية منك:</p>
<ul>
<li><p><strong>الهوية</strong> (الاسم، العمر، الجنس، إلخ.)</p></li>
<li><p><strong>المجالات المهنية، النقابية، التخصصية والخبرية</strong></p></li>
<li><p><strong>الموقع الجغرافي الدقيق</strong> (من القارة إلى الزقاق)</p></li>
</ul>
<p>بناءً على ذلك، ستصبح عضواً في ثلاثة أنواع من المجموعات النظامية:</p>
<ol>
<li><p><strong>مجموعات الجمعيات العامة</strong> (على أساس مكان السكن)</p></li>
<li><p><strong>مجموعات تخصصية</strong> (على أساس المهنة والتخصص)</p></li>
<li><p><strong>مجموعات خاصة</strong> (على أساس العمر والجنس)</p></li>
</ol>
<p>لكل مجموعة دور محدد في تطوير المشاريع المحلية والعالمية، وتعمل ضمن إطار شفاف ومسؤول وتشاركي.</p>
<p>&nbsp;</p>
<h3><strong>دعوة للعضوية</strong></h3>
<p>إذا كنت تؤمن أيضاً ببناء عالم أفضل لنفسك ولأطفالك ولجميع سكان هذا الكوكب، ففعّل عضويتك المحتملة في <strong>EarthCoop</strong> اليوم.<br />
معاً، دعونا نبني مستقبلاً مختلفاً.</p>'
            ]),
            'meta_title' => 'About EarthCoop',
            'meta_title_translations' => json_encode([
                'fa' => 'درباره EarthCoop',
                'en' => 'About EarthCoop',
                'ar' => 'حول EarthCoop'
            ]),
            'meta_description' => 'درباره EarthCoop',
            'meta_description_translations' => json_encode([
                'fa' => 'درباره EarthCoop، شبکه مشارکت جهانی برای ساختن آینده‌ای بهتر',
                'en' => 'About EarthCoop, a global partnership network for building a better future',
                'ar' => 'حول EarthCoop، شبكة شراكة عالمية لبناء مستقبل أفضل'
            ]),
            'is_published' => 1,
            'show_in_header' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // صفحه دوم: راهنمای استفاده
        $page2 = [
            'title' => 'راهنمای استفاده',
            'title_translations' => json_encode([
                'fa' => 'راهنمای استفاده',
                'en' => 'User Guide',
                'ar' => 'دليل الاستخدام'
            ]),
            'slug' => 'rahnmay-astfadh',
            'template' => 'help',
            'content' => '<p>این راهنما به شما کمک می‌کند تا با نحوه‌ی ثبت‌نام، ورود، ساختار گروه‌ها و نظام دموکراتیک EarthCoop آشنا شوید. چند دقیقه وقت بگذارید تا با این زیست‌بوم و نقشی که می‌توانید در توسعه آن داشته باشید بیشتر آشنا شوید.</p>
<p><strong>EarthCoop</strong>&nbsp;یک پلتفرم جهانی برای تسهیل همکاری های&nbsp; اجتماعی و اقتصادی است که با هدف تحقق&nbsp;<em>عدالت، شفافیت، مشارکت</em>&nbsp;و&nbsp;<em>توسعه پایدار</em>&nbsp;طراحی شده است. شما با عضویت در ارثکوپ از تمام حقوق مالکانه خود بر سیاره بهرمند خواهید شد.</p>
<p>&nbsp;</p>
<h3>🔒<strong>&nbsp;پذیرش شرایط و آغاز ثبت‌نام</strong></h3>
<p>اولین گام برای پیوستن به EarthCoop، مطالعه و پذیرش&nbsp;<strong>اساسنامه و شرایط استفاده</strong>&nbsp;است.</p>
<p>با وارد کردن کد دعوت و تأیید شرایط، وارد مرحله‌ی ثبت‌نام اولیه می‌شوید. در این مرحله کافی‌ست:</p>
<ul>
<li><p>آدرس ایمیل معتبر وارد کنید</p></li>
<li><p>یک رمز عبور تعیین کنید</p></li>
<li><p>ایمیل خود را تأیید نمایید</p></li>
</ul>
<p>همچنین می‌توانید از طریق حساب گوگل نیز ثبت‌نام کنید.</p>
<p>&nbsp;</p>
<p>&nbsp;</p>
<h2>مراحل ثبت‌نام در EarthCoop</h2>
<p>&nbsp;</p>
<h3><strong>۱. اطلاعات هویتی</strong></h3>
<p>در این مرحله اطلاعات زیر از شما دریافت می‌شود:</p>
<ul>
<li><p>نام و نام خانوادگی</p></li>
<li><p>تاریخ تولد</p></li>
<li><p>جنسیت</p></li>
<li><p>ملیت</p></li>
<li><p>کد ملی</p></li>
<li><p>شماره تلفن همراه</p></li>
</ul>
<p>ما برای حفظ شفافیت و هویت واقعی سهامداران، اطمینان حاصل می‌کنیم که هیچ‌کس نتواند بیش از یک حساب داشته باشد. همچنین، از اطلاعات سن و جنسیت شما برای تشکیل&nbsp;<strong>گروه‌های اختصاصی سنی و جنسیتی</strong>&nbsp;استفاده می‌شود.</p>
<h3>&nbsp;</h3>
<h3><strong>۲. اطلاعات شغلی و تخصصی</strong></h3>
<p>در این بخش، باید:</p>
<ul>
<li><p><strong>زمینه‌های فعالیت شغلی و صنفی</strong></p></li>
<li><p><strong>زمینه‌های علمی و تجربی</strong></p></li>
</ul>
<p>خود را از فهرست موجود انتخاب کنید. انتخاب‌ها در سه سطح انجام می‌شود (مثلاً: "آموزش &gt; معلمان &gt; دبیر ریاضی"). امکان انتخاب چندگانه در هر بخش وجود دارد. این اطلاعات مبنای عضویت شما در&nbsp;<strong>گروه‌های تخصصی (صنفی و علمی)</strong>&nbsp;خواهد بود.</p>
<h3>&nbsp;</h3>
<h3>۳<strong>. اطلاعات مکانی</strong></h3>
<p>در این مرحله، باید موقعیت مکانی خود را به‌صورت دقیق انتخاب کنید:</p>
<p>قاره &rarr; کشور &rarr; استان &rarr; شهرستان &rarr; بخش &rarr; شهر یا دهستان &rarr; منطقه شهری یا روستا &rarr; محله</p>
<ul>
<li><p>مشخص‌کردن محل سکونت تا سطح&nbsp;<strong>محله</strong>&nbsp;الزامی است.</p></li>
<li><p>به‌صورت اختیاری، می‌توانید خیابان و کوچه را هم تعیین کنید تا به گروه‌های دقیق‌تر بپیوندید.</p></li>
<li><p>اگر مکان شما در فهرست موجود نیست، می‌توانید مکان جدیدی را پیشنهاد دهید.</p></li>
</ul>
<p>با تکمیل این مرحله، با زدن دکمه&nbsp;<strong>&laquo;تکمیل ثبت‌نام&raquo;</strong>، ثبت‌نام شما به پایان می‌رسد.</p>
<p>&nbsp;</p>
<p>&nbsp;</p>
<h2>خانه شما در EarthCoop</h2>
<p>&nbsp;</p>
<p>پس از ثبت‌نام، به&nbsp;<strong>داشبورد کاربری</strong>&nbsp;خود هدایت می‌شوید. در اینجا به تمام امکانات EarthCoop دسترسی دارید:</p>
<p>&nbsp;</p>
<h3><strong>👤 حساب کاربری</strong></h3>
<p>در این بخش می‌توانید:</p>
<ul>
<li><p>اطلاعات خود را مشاهده و مدیریت کنید</p></li>
<li><p>نمایش یا مخفی بودن آن‌ها را برای دیگران تنظیم کنید</p></li>
<li><p>بیوگرافی، مدارک، تصویر و لینک‌های شخصی خود را اضافه کنید</p></li>
<li><p>زمینه‌های فعالیت خود را تغییر دهید (تغییر زمینه‌ها باعث تغییر عضویت گروهی شما خواهد شد)&nbsp;</p></li>
</ul>
<p>&nbsp;</p>
<h3><strong>🤝 دعوت از دوستان</strong></h3>
<p>هر کاربر امکان ارسال&nbsp;<strong>۱۰ کد دعوت</strong>&nbsp;دارد. هر کد تا&nbsp;<strong>۴۸ ساعت</strong>&nbsp;معتبر است و پس از ثبت‌نام موفق طرف مقابل، شما به‌عنوان معرف ثبت می‌شوید.</p>
<p>🎁&nbsp;<strong>پاداش دعوت:</strong>&nbsp;هر دعوت موفق معادل&nbsp;<strong>۱۰ بهار</strong>&nbsp;(واحد پول داخلی EarthCoop) برابر با ارزش&nbsp;<strong>۱ گرم طلای خالص</strong>&nbsp;به شما پاداش می‌دهد.</p>
<p>&nbsp;</p>
<h3><strong>👥 گروه‌های من</strong></h3>
<p>در این بخش به تمامی گروه‌هایی که به‌صورت خودکار عضو آن‌ها شده‌اید دسترسی دارید:</p>
<ul>
<li><p><strong>گروه‌های مجامع عمومی:</strong>&nbsp;از سطح محله تا سطح جهانی</p></li>
<li><p><strong>گروه‌های تخصصی:</strong>&nbsp;صنفی و علمی (با امکان فیلتر بر اساس سطح مکانی)</p></li>
<li><p><strong>گروه‌های اختصاصی:</strong>&nbsp;سنی و جنسیتی در سطوح مختلف</p></li>
</ul>
<p>🔎 نقش شما در هر گروه (فعال یا ناظر) نیز مشخص است:</p>
<ul>
<li><p><strong>ناظر:</strong>&nbsp;امکان مشاهده، بدون مشارکت</p></li>
<li><p><strong>فعال:</strong>&nbsp;امکان ارسال پست، شرکت در انتخابات، نظرسنجی، رأی‌گیری و...</p></li>
</ul>
<p>&nbsp;</p>
<h3><strong>💬 گفتگوهای خصوصی</strong></h3>
<p>فهرست چت‌های شخصی شما با سایر کاربران در این بخش قابل مدیریت است.</p>
<p>&nbsp;</p>
<h3><strong>🏘️ محیط گروه‌ها</strong></h3>
<p>با کلیک بر روی نام هر گروه، وارد محیط آن گروه می‌شوید. در گروه‌هایی که فعال هستید، می‌توانید پست و نظرسنجی با موضوعاتی مانند:</p>
<ul>
<li><p>معرفی خود</p></li>
<li><p>پیشنهاد برای محله</p></li>
<li><p>انتقاد</p></li>
<li><p>گزارش مشکل</p></li>
<li><p>درخواست نشست مجمع عمومی</p></li>
</ul>
<p>منتشر کنید.</p>
<p>📌&nbsp;<strong>نکته مهم:</strong>&nbsp;گروه‌های محله‌ای بنیادی‌ترین بخش EarthCoop هستند.<br />
اگر تنها عضو محله‌ی خود هستید، یعنی شما بنیان‌گذار آن گروه هستید. بنابراین دعوت از هم‌محله‌ای‌ها اولویت دارد!</p>
<p>&nbsp;</p>
<h3><strong>🗳️ انتخابات</strong></h3>
<p>EarthCoop از یک&nbsp;<strong>سیستم انتخاباتی خودکار، لحظه‌ای و شفاف</strong>&nbsp;استفاده می‌کند. به محض رسیدن اعضای فعال یک گروه عمومی (مثلاً محله) به&nbsp;<strong>حدنصاب مشخص (پیش‌فرض: ۲۰ نفر)</strong>، انتخابات برای تعیین مدیران و بازرسان آغاز می‌شود.</p>
<p>انتخابات پویاست، یعنی:</p>
<ul>
<li><p><strong>هر کاربر هر زمان می‌تواند رأی خود را ثبت یا تغییر دهد</strong></p></li>
<li><p><strong>نتایج هر سه ماه یک‌بار به‌روز می‌شوند</strong></p></li>
<li><p>هیچ مدیری بدون رأی حداقلی بیش از ۳ ماه نمی‌ماند</p></li>
</ul>
<p>&nbsp;</p>',
            'content_translations' => json_encode([
                'fa' => '<p>این راهنما به شما کمک می‌کند تا با نحوه‌ی ثبت‌نام، ورود، ساختار گروه‌ها و نظام دموکراتیک EarthCoop آشنا شوید. چند دقیقه وقت بگذارید تا با این زیست‌بوم و نقشی که می‌توانید در توسعه آن داشته باشید بیشتر آشنا شوید.</p>
<p><strong>EarthCoop</strong>&nbsp;یک پلتفرم جهانی برای تسهیل همکاری های&nbsp; اجتماعی و اقتصادی است که با هدف تحقق&nbsp;<em>عدالت، شفافیت، مشارکت</em>&nbsp;و&nbsp;<em>توسعه پایدار</em>&nbsp;طراحی شده است. شما با عضویت در ارثکوپ از تمام حقوق مالکانه خود بر سیاره بهرمند خواهید شد.</p>
<p>&nbsp;</p>
<h3>🔒<strong>&nbsp;پذیرش شرایط و آغاز ثبت‌نام</strong></h3>
<p>اولین گام برای پیوستن به EarthCoop، مطالعه و پذیرش&nbsp;<strong>اساسنامه و شرایط استفاده</strong>&nbsp;است.</p>
<p>با وارد کردن کد دعوت و تأیید شرایط، وارد مرحله‌ی ثبت‌نام اولیه می‌شوید. در این مرحله کافی‌ست:</p>
<ul>
<li><p>آدرس ایمیل معتبر وارد کنید</p></li>
<li><p>یک رمز عبور تعیین کنید</p></li>
<li><p>ایمیل خود را تأیید نمایید</p></li>
</ul>
<p>همچنین می‌توانید از طریق حساب گوگل نیز ثبت‌نام کنید.</p>
<p>&nbsp;</p>
<p>&nbsp;</p>
<h2>مراحل ثبت‌نام در EarthCoop</h2>
<p>&nbsp;</p>
<h3><strong>۱. اطلاعات هویتی</strong></h3>
<p>در این مرحله اطلاعات زیر از شما دریافت می‌شود:</p>
<ul>
<li><p>نام و نام خانوادگی</p></li>
<li><p>تاریخ تولد</p></li>
<li><p>جنسیت</p></li>
<li><p>ملیت</p></li>
<li><p>کد ملی</p></li>
<li><p>شماره تلفن همراه</p></li>
</ul>
<p>ما برای حفظ شفافیت و هویت واقعی سهامداران، اطمینان حاصل می‌کنیم که هیچ‌کس نتواند بیش از یک حساب داشته باشد. همچنین، از اطلاعات سن و جنسیت شما برای تشکیل&nbsp;<strong>گروه‌های اختصاصی سنی و جنسیتی</strong>&nbsp;استفاده می‌شود.</p>
<h3>&nbsp;</h3>
<h3><strong>۲. اطلاعات شغلی و تخصصی</strong></h3>
<p>در این بخش، باید:</p>
<ul>
<li><p><strong>زمینه‌های فعالیت شغلی و صنفی</strong></p></li>
<li><p><strong>زمینه‌های علمی و تجربی</strong></p></li>
</ul>
<p>خود را از فهرست موجود انتخاب کنید. انتخاب‌ها در سه سطح انجام می‌شود (مثلاً: "آموزش &gt; معلمان &gt; دبیر ریاضی"). امکان انتخاب چندگانه در هر بخش وجود دارد. این اطلاعات مبنای عضویت شما در&nbsp;<strong>گروه‌های تخصصی (صنفی و علمی)</strong>&nbsp;خواهد بود.</p>
<h3>&nbsp;</h3>
<h3>۳<strong>. اطلاعات مکانی</strong></h3>
<p>در این مرحله، باید موقعیت مکانی خود را به‌صورت دقیق انتخاب کنید:</p>
<p>قاره &rarr; کشور &rarr; استان &rarr; شهرستان &rarr; بخش &rarr; شهر یا دهستان &rarr; منطقه شهری یا روستا &rarr; محله</p>
<ul>
<li><p>مشخص‌کردن محل سکونت تا سطح&nbsp;<strong>محله</strong>&nbsp;الزامی است.</p></li>
<li><p>به‌صورت اختیاری، می‌توانید خیابان و کوچه را هم تعیین کنید تا به گروه‌های دقیق‌تر بپیوندید.</p></li>
<li><p>اگر مکان شما در فهرست موجود نیست، می‌توانید مکان جدیدی را پیشنهاد دهید.</p></li>
</ul>
<p>با تکمیل این مرحله، با زدن دکمه&nbsp;<strong>&laquo;تکمیل ثبت‌نام&raquo;</strong>، ثبت‌نام شما به پایان می‌رسد.</p>
<p>&nbsp;</p>
<p>&nbsp;</p>
<h2>خانه شما در EarthCoop</h2>
<p>&nbsp;</p>
<p>پس از ثبت‌نام، به&nbsp;<strong>داشبورد کاربری</strong>&nbsp;خود هدایت می‌شوید. در اینجا به تمام امکانات EarthCoop دسترسی دارید:</p>
<p>&nbsp;</p>
<h3><strong>👤 حساب کاربری</strong></h3>
<p>در این بخش می‌توانید:</p>
<ul>
<li><p>اطلاعات خود را مشاهده و مدیریت کنید</p></li>
<li><p>نمایش یا مخفی بودن آن‌ها را برای دیگران تنظیم کنید</p></li>
<li><p>بیوگرافی، مدارک، تصویر و لینک‌های شخصی خود را اضافه کنید</p></li>
<li><p>زمینه‌های فعالیت خود را تغییر دهید (تغییر زمینه‌ها باعث تغییر عضویت گروهی شما خواهد شد)&nbsp;</p></li>
</ul>
<p>&nbsp;</p>
<h3><strong>🤝 دعوت از دوستان</strong></h3>
<p>هر کاربر امکان ارسال&nbsp;<strong>۱۰ کد دعوت</strong>&nbsp;دارد. هر کد تا&nbsp;<strong>۴۸ ساعت</strong>&nbsp;معتبر است و پس از ثبت‌نام موفق طرف مقابل، شما به‌عنوان معرف ثبت می‌شوید.</p>
<p>🎁&nbsp;<strong>پاداش دعوت:</strong>&nbsp;هر دعوت موفق معادل&nbsp;<strong>۱۰ بهار</strong>&nbsp;(واحد پول داخلی EarthCoop) برابر با ارزش&nbsp;<strong>۱ گرم طلای خالص</strong>&nbsp;به شما پاداش می‌دهد.</p>
<p>&nbsp;</p>
<h3><strong>👥 گروه‌های من</strong></h3>
<p>در این بخش به تمامی گروه‌هایی که به‌صورت خودکار عضو آن‌ها شده‌اید دسترسی دارید:</p>
<ul>
<li><p><strong>گروه‌های مجامع عمومی:</strong>&nbsp;از سطح محله تا سطح جهانی</p></li>
<li><p><strong>گروه‌های تخصصی:</strong>&nbsp;صنفی و علمی (با امکان فیلتر بر اساس سطح مکانی)</p></li>
<li><p><strong>گروه‌های اختصاصی:</strong>&nbsp;سنی و جنسیتی در سطوح مختلف</p></li>
</ul>
<p>🔎 نقش شما در هر گروه (فعال یا ناظر) نیز مشخص است:</p>
<ul>
<li><p><strong>ناظر:</strong>&nbsp;امکان مشاهده، بدون مشارکت</p></li>
<li><p><strong>فعال:</strong>&nbsp;امکان ارسال پست، شرکت در انتخابات، نظرسنجی، رأی‌گیری و...</p></li>
</ul>
<p>&nbsp;</p>
<h3><strong>💬 گفتگوهای خصوصی</strong></h3>
<p>فهرست چت‌های شخصی شما با سایر کاربران در این بخش قابل مدیریت است.</p>
<p>&nbsp;</p>
<h3><strong>🏘️ محیط گروه‌ها</strong></h3>
<p>با کلیک بر روی نام هر گروه، وارد محیط آن گروه می‌شوید. در گروه‌هایی که فعال هستید، می‌توانید پست و نظرسنجی با موضوعاتی مانند:</p>
<ul>
<li><p>معرفی خود</p></li>
<li><p>پیشنهاد برای محله</p></li>
<li><p>انتقاد</p></li>
<li><p>گزارش مشکل</p></li>
<li><p>درخواست نشست مجمع عمومی</p></li>
</ul>
<p>منتشر کنید.</p>
<p>📌&nbsp;<strong>نکته مهم:</strong>&nbsp;گروه‌های محله‌ای بنیادی‌ترین بخش EarthCoop هستند.<br />
اگر تنها عضو محله‌ی خود هستید، یعنی شما بنیان‌گذار آن گروه هستید. بنابراین دعوت از هم‌محله‌ای‌ها اولویت دارد!</p>
<p>&nbsp;</p>
<h3><strong>🗳️ انتخابات</strong></h3>
<p>EarthCoop از یک&nbsp;<strong>سیستم انتخاباتی خودکار، لحظه‌ای و شفاف</strong>&nbsp;استفاده می‌کند. به محض رسیدن اعضای فعال یک گروه عمومی (مثلاً محله) به&nbsp;<strong>حدنصاب مشخص (پیش‌فرض: ۲۰ نفر)</strong>، انتخابات برای تعیین مدیران و بازرسان آغاز می‌شود.</p>
<p>انتخابات پویاست، یعنی:</p>
<ul>
<li><p><strong>هر کاربر هر زمان می‌تواند رأی خود را ثبت یا تغییر دهد</strong></p></li>
<li><p><strong>نتایج هر سه ماه یک‌بار به‌روز می‌شوند</strong></p></li>
<li><p>هیچ مدیری بدون رأی حداقلی بیش از ۳ ماه نمی‌ماند</p></li>
</ul>
<p>&nbsp;</p>',
                'en' => '<p>This guide helps you become familiar with how to register, log in, the group structure, and the democratic system of EarthCoop. Take a few minutes to learn more about this ecosystem and the role you can play in its development.</p>
<p><strong>EarthCoop</strong> is a global platform for facilitating social and economic cooperation, designed to achieve <em>justice, transparency, participation</em> and <em>sustainable development</em>. By joining EarthCoop, you will benefit from all your proprietary rights over the planet.</p>
<p>&nbsp;</p>
<h3>🔒 <strong>Accepting Terms and Starting Registration</strong></h3>
<p>The first step to join EarthCoop is to read and accept the <strong>Statute and Terms of Use</strong>.</p>
<p>By entering the invitation code and confirming the terms, you will enter the initial registration stage. At this stage, you just need to:</p>
<ul>
<li><p>Enter a valid email address</p></li>
<li><p>Set a password</p></li>
<li><p>Verify your email</p></li>
</ul>
<p>You can also register via your Google account.</p>
<p>&nbsp;</p>
<p>&nbsp;</p>
<h2>Registration Steps in EarthCoop</h2>
<p>&nbsp;</p>
<h3><strong>1. Identity Information</strong></h3>
<p>At this stage, the following information is collected from you:</p>
<ul>
<li><p>First and last name</p></li>
<li><p>Date of birth</p></li>
<li><p>Gender</p></li>
<li><p>Nationality</p></li>
<li><p>National ID</p></li>
<li><p>Mobile phone number</p></li>
</ul>
<p>To maintain transparency and real identity of shareholders, we ensure that no one can have more than one account. Also, your age and gender information is used to form <strong>specific age and gender groups</strong>.</p>
<h3>&nbsp;</h3>
<h3><strong>2. Professional and Specialized Information</strong></h3>
<p>In this section, you need to select from the existing list:</p>
<ul>
<li><p><strong>Professional and trade fields</strong></p></li>
<li><p><strong>Scientific and experiential fields</strong></p></li>
</ul>
<p>Selections are made at three levels (e.g., "Education &gt; Teachers &gt; Math Teacher"). Multiple selections are allowed in each section. This information will be the basis for your membership in <strong>specialized (trade and scientific) groups</strong>.</p>
<h3>&nbsp;</h3>
<h3><strong>3. Location Information</strong></h3>
<p>At this stage, you must select your exact location:</p>
<p>Continent &rarr; Country &rarr; Province &rarr; County &rarr; District &rarr; City or Village &rarr; Urban or Rural Area &rarr; Neighbourhood</p>
<ul>
<li><p>Specifying the place of residence down to the <strong>neighbourhood</strong> level is mandatory.</p></li>
<li><p>Optionally, you can also specify the street and alley to join more precise groups.</p></li>
<li><p>If your location is not in the list, you can suggest a new location.</p></li>
</ul>
<p>After completing this step, click the <strong>«Complete Registration»</strong> button to finish your registration.</p>
<p>&nbsp;</p>
<p>&nbsp;</p>
<h2>Your Home in EarthCoop</h2>
<p>&nbsp;</p>
<p>After registration, you will be directed to your <strong>dashboard</strong>. Here you have access to all EarthCoop features:</p>
<p>&nbsp;</p>
<h3><strong>👤 User Account</strong></h3>
<p>In this section you can:</p>
<ul>
<li><p>View and manage your information</p></li>
<li><p>Set visibility of your information for others</p></li>
<li><p>Add biography, documents, profile picture, and personal links</p></li>
<li><p>Change your activity fields (changes will affect your group memberships)</p></li>
</ul>
<p>&nbsp;</p>
<h3><strong>🤝 Invite Friends</strong></h3>
<p>Each user can send <strong>10 invitation codes</strong>. Each code is valid for <strong>48 hours</strong> and after successful registration of the other party, you will be recorded as the referrer.</p>
<p>🎁 <strong>Invitation Reward:</strong> Each successful invitation gives you a reward equal to <strong>10 Bahar</strong> (EarthCoop internal currency) equivalent to <strong>1 gram of pure gold</strong>.</p>
<p>&nbsp;</p>
<h3><strong>👥 My Groups</strong></h3>
<p>In this section you have access to all groups you have automatically joined:</p>
<ul>
<li><p><strong>General Assembly groups:</strong> from neighbourhood level to global level</p></li>
<li><p><strong>Specialized groups:</strong> trade and scientific (with filter by location level)</p></li>
<li><p><strong>Specific groups:</strong> age and gender at different levels</p></li>
</ul>
<p>🔎 Your role in each group (active or observer) is also shown:</p>
<ul>
<li><p><strong>Observer:</strong> can view, no participation</p></li>
<li><p><strong>Active:</strong> can post, participate in elections, polls, voting, etc.</p></li>
</ul>
<p>&nbsp;</p>
<h3><strong>💬 Private Conversations</strong></h3>
<p>Your personal chat list with other users can be managed here.</p>
<p>&nbsp;</p>
<h3><strong>🏘️ Group Environment</strong></h3>
<p>By clicking on the name of each group, you enter the group environment. In groups where you are active, you can publish posts and polls on topics such as:</p>
<ul>
<li><p>Introduction of yourself</p></li>
<li><p>Suggestions for the neighbourhood</p></li>
<li><p>Criticism</p></li>
<li><p>Problem reporting</p></li>
<li><p>Request for general assembly meeting</p></li>
</ul>
<p>📌 <strong>Important note:</strong> Neighbourhood groups are the most fundamental part of EarthCoop.<br />
If you are the only member of your neighbourhood, it means you are the founder of that group. So inviting neighbours is a priority!</p>
<p>&nbsp;</p>
<h3><strong>🗳️ Elections</strong></h3>
<p>EarthCoop uses an <strong>automated, real‑time and transparent electoral system</strong>. As soon as the active members of a public group (e.g., a neighbourhood) reach a <strong>specified quorum (default: 20 people)</strong>, elections are held to determine managers and inspectors.</p>
<p>Elections are dynamic, meaning:</p>
<ul>
<li><p><strong>Any user can register or change their vote at any time</strong></p></li>
<li><p><strong>Results are updated every three months</strong></p></li>
<li><p>No manager stays in office without a minimum vote for more than 3 months</p></li>
</ul>
<p>&nbsp;</p>',
                'ar' => '<p>يساعدك هذا الدليل على التعرف على كيفية التسجيل، تسجيل الدخول، هيكل المجموعات، والنظام الديمقراطي لـ EarthCoop. خصص بضع دقائق لتعرف أكثر على هذا النظام البيئي والدور الذي يمكنك أن تلعبه في تطويره.</p>
<p><strong>EarthCoop</strong> هي منصة عالمية لتسهيل التعاون الاجتماعي والاقتصادي، صُممت لتحقيق <em>العدالة، الشفافية، المشاركة</em> و <em>التنمية المستدامة</em>. بالانضمام إلى EarthCoop، سوف تستفيد من جميع حقوقك الملكية على الكوكب.</p>
<p>&nbsp;</p>
<h3>🔒 <strong>قبول الشروط وبدء التسجيل</strong></h3>
<p>الخطوة الأولى للانضمام إلى EarthCoop هي قراءة وقبول <strong>النظام الأساسي وشروط الاستخدام</strong>.</p>
<p>بإدخال رمز الدعوة وتأكيد الشروط، ستدخل مرحلة التسجيل الأولية. في هذه المرحلة، يكفي:</p>
<ul>
<li><p>إدخال عنوان بريد إلكتروني صالح</p></li>
<li><p>تعيين كلمة مرور</p></li>
<li><p>تأكيد بريدك الإلكتروني</p></li>
</ul>
<p>يمكنك أيضاً التسجيل عبر حساب Google الخاص بك.</p>
<p>&nbsp;</p>
<p>&nbsp;</p>
<h2>خطوات التسجيل في EarthCoop</h2>
<p>&nbsp;</p>
<h3><strong>١. معلومات الهوية</strong></h3>
<p>في هذه المرحلة، يتم جمع المعلومات التالية منك:</p>
<ul>
<li><p>الاسم الأول واسم العائلة</p></li>
<li><p>تاريخ الميلاد</p></li>
<li><p>الجنس</p></li>
<li><p>الجنسية</p></li>
<li><p>الرقم الوطني</p></li>
<li><p>رقم الهاتف المحمول</p></li>
</ul>
<p>للحفاظ على الشفافية والهوية الحقيقية للمساهمين، نضمن ألا يتمكن أي شخص من امتلاك أكثر من حساب واحد. كما تُستخدم معلومات العمر والجنس لتكوين <strong>مجموعات خاصة عمرية وجندرية</strong>.</p>
<h3>&nbsp;</h3>
<h3><strong>٢. المعلومات المهنية والتخصصية</strong></h3>
<p>في هذا القسم، يجب عليك اختيار:</p>
<ul>
<li><p><strong>مجالات النشاط المهني والنقابي</strong></p></li>
<li><p><strong>المجالات العلمية والخبرية</strong></p></li>
</ul>
<p>يتم الاختيار على ثلاثة مستويات (مثلاً: "التعليم &gt; المعلمون &gt; مدرس رياضيات"). يمكن الاختيار المتعدد في كل قسم. ستكون هذه المعلومات أساس عضويتك في <strong>المجموعات التخصصية (النقابية والعلمية)</strong>.</p>
<h3>&nbsp;</h3>
<h3><strong>٣. المعلومات المكانية</strong></h3>
<p>في هذه المرحلة، يجب عليك تحديد موقعك بدقة:</p>
<p>القارة &rarr; الدولة &rarr; المحافظة &rarr; المدينة &rarr; المنطقة &rarr; الحي</p>
<ul>
<li><p>تحديد مكان السكن حتى مستوى <strong>الحي</strong> إلزامي.</p></li>
<li><p>اختيارياً، يمكنك تحديد الشارع والزقاق للانضمام إلى مجموعات أكثر دقة.</p></li>
<li><p>إذا لم يكن مكانك مدرجاً، يمكنك اقتراح موقع جديد.</p></li>
</ul>
<p>بعد إكمال هذه الخطوة، اضغط على زر <strong>«إكمال التسجيل»</strong> لإنهاء تسجيلك.</p>
<p>&nbsp;</p>
<p>&nbsp;</p>
<h2>منزلك في EarthCoop</h2>
<p>&nbsp;</p>
<p>بعد التسجيل، سيتم توجيهك إلى <strong>لوحة التحكم</strong> الخاصة بك. هنا يمكنك الوصول إلى جميع ميزات EarthCoop:</p>
<p>&nbsp;</p>
<h3><strong>👤 الحساب الشخصي</strong></h3>
<p>في هذا القسم يمكنك:</p>
<ul>
<li><p>عرض وإدارة معلوماتك</p></li>
<li><p>تحديد إظهار أو إخفاء معلوماتك للآخرين</p></li>
<li><p>إضافة السيرة الذاتية، الوثائق، الصورة الشخصية والروابط الشخصية</p></li>
<li><p>تغيير مجالات نشاطك (التغيير سيؤثر على عضويتك في المجموعات)</p></li>
</ul>
<p>&nbsp;</p>
<h3><strong>🤝 دعوة الأصدقاء</strong></h3>
<p>يمكن لكل مستخدم إرسال <strong>١٠ رموز دعوة</strong>. كل رمز صالح لمدة <strong>٤٨ ساعة</strong> وبعد تسجيل الطرف الآخر بنجاح، سيتم تسجيلك كداعٍ.</p>
<p>🎁 <strong>مكافأة الدعوة:</strong> كل دعوة ناجحة تمنحك مكافأة تعادل <strong>١٠ بهار</strong> (العملة الداخلية لـ EarthCoop) بما يعادل <strong>١ غرام من الذهب الخالص</strong>.</p>
<p>&nbsp;</p>
<h3><strong>👥 مجموعاتي</strong></h3>
<p>في هذا القسم يمكنك الوصول إلى جميع المجموعات التي انضممت إليها تلقائياً:</p>
<ul>
<li><p><strong>مجموعات الجمعيات العامة:</strong> من مستوى الحي إلى المستوى العالمي</p></li>
<li><p><strong>المجموعات التخصصية:</strong> نقابية وعلمية (مع إمكانية التصفية حسب المستوى المكاني)</p></li>
<li><p><strong>المجموعات الخاصة:</strong> عمرية وجندرية على مستويات مختلفة</p></li>
</ul>
<p>🔎 دورك في كل مجموعة (نشط أو مراقب) موضح أيضاً:</p>
<ul>
<li><p><strong>مراقب:</strong> يمكنه المشاهدة، بدون مشاركة</p></li>
<li><p><strong>نشط:</strong> يمكنه النشر، المشاركة في الانتخابات، الاستطلاعات، التصويت، إلخ.</p></li>
</ul>
<p>&nbsp;</p>
<h3><strong>💬 المحادثات الخاصة</strong></h3>
<p>يمكن إدارة قائمة المحادثات الشخصية مع المستخدمين الآخرين هنا.</p>
<p>&nbsp;</p>
<h3><strong>🏘️ بيئة المجموعات</strong></h3>
<p>بالنقر على اسم كل مجموعة، تدخل إلى بيئتها. في المجموعات التي تكون نشطاً فيها، يمكنك نشر منشورات واستطلاعات حول مواضيع مثل:</p>
<ul>
<li><p>تقديم نفسك</p></li>
<li><p>اقتراحات للحي</p></li>
<li><p>النقد</p></li>
<li><p>الإبلاغ عن مشكلة</p></li>
<li><p>طلب عقد جمعية عامة</p></li>
</ul>
<p>📌 <strong>ملاحظة مهمة:</strong> مجموعات الأحياء هي الجزء الأكثر أساسية في EarthCoop.<br />
إذا كنت العضو الوحيد في حيك، فهذا يعني أنك مؤسس تلك المجموعة. لذا فإن دعوة الجيران هي الأولوية!</p>
<p>&nbsp;</p>
<h3><strong>🗳️ الانتخابات</strong></h3>
<p>يستخدم EarthCoop <strong>نظاماً انتخابياً آلياً، لحظياً وشفافاً</strong>. بمجرد وصول الأعضاء النشطين في مجموعة عامة (مثلاً حي) إلى <strong>نصاب محدد (افتراضياً: ٢٠ شخصاً)</strong>، تُجرى الانتخابات لتحديد المديرين والمفتشين.</p>
<p>الانتخابات ديناميكية، مما يعني:</p>
<ul>
<li><p><strong>يمكن لأي مستخدم تسجيل أو تغيير صوته في أي وقت</strong></p></li>
<li><p><strong>يتم تحديث النتائج كل ثلاثة أشهر</strong></p></li>
<li><p>لا يبقى أي مدير في منصبه دون حد أدنى من الأصوات لأكثر من ٣ أشهر</p></li>
</ul>
<p>&nbsp;</p>'
            ]),
            'meta_title' => 'راهنمای استفاده از ارثکوپ',
            'meta_title_translations' => json_encode([
                'fa' => 'راهنمای استفاده از ارثکوپ',
                'en' => 'EarthCoop User Guide',
                'ar' => 'دليل استخدام EarthCoop'
            ]),
            'meta_description' => 'راهنمای کامل ثبت‌نام، گروه‌ها و نظام دموکراتیک EarthCoop',
            'meta_description_translations' => json_encode([
                'fa' => 'راهنمای کامل ثبت‌نام، گروه‌ها و نظام دموکراتیک EarthCoop',
                'en' => 'Complete guide to registration, groups and democratic system of EarthCoop',
                'ar' => 'دليل شامل للتسجيل والمجموعات والنظام الديمقراطي في EarthCoop'
            ]),
            'is_published' => 1,
            'show_in_header' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // صفحه سوم: همکاری
        $page3 = [
            'title' => 'همکاری',
            'title_translations' => json_encode([
                'fa' => 'همکاری',
                'en' => 'Cooperation',
                'ar' => 'التعاون'
            ]),
            'slug' => 'hmkary',
            'template' => 'cooperation',
            'content' => '<p>ما در تیم توسعه ارث‌کوپ برای توسعه بهتر این زیست بوم به همکاری شما نیازمندیم.</p>
<p>اگر در زمینه های زیر تخصص دارید و مایلید از توسعه دهندگان ارث‌کوپ باشید با ما تماس بگیرید: Team@EarthCoop.ir</p>
<p>&nbsp;</p>',
            'content_translations' => json_encode([
                'fa' => '<p>ما در تیم توسعه ارث‌کوپ برای توسعه بهتر این زیست بوم به همکاری شما نیازمندیم.</p>
<p>اگر در زمینه های زیر تخصص دارید و مایلید از توسعه دهندگان ارث‌کوپ باشید با ما تماس بگیرید: Team@EarthCoop.ir</p>
<p>&nbsp;</p>',
                'en' => '<p>We in the EarthCoop development team need your cooperation to better develop this ecosystem.</p>
<p>If you have expertise in the following fields and would like to be among EarthCoop developers, contact us at: Team@EarthCoop.ir</p>
<p>&nbsp;</p>',
                'ar' => '<p>نحن في فريق تطوير EarthCoop نحتاج إلى تعاونكم لتطوير هذا النظام البيئي بشكل أفضل.</p>
<p>إذا كان لديكم خبرة في المجالات التالية وترغبون في أن تكونوا من مطوري EarthCoop، تواصلوا معنا على: Team@EarthCoop.ir</p>
<p>&nbsp;</p>'
            ]),
            'meta_title' => 'فرصت همکاری',
            'meta_title_translations' => json_encode([
                'fa' => 'فرصت همکاری',
                'en' => 'Cooperation Opportunity',
                'ar' => 'فرصة التعاون'
            ]),
            'meta_description' => 'فرصت همکاری با تیم توسعه EarthCoop',
            'meta_description_translations' => json_encode([
                'fa' => 'فرصت همکاری با تیم توسعه EarthCoop',
                'en' => 'Opportunity to cooperate with EarthCoop development team',
                'ar' => 'فرصة للتعاون مع فريق تطوير EarthCoop'
            ]),
            'is_published' => 1,
            'show_in_header' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // درج رکوردها
        DB::table('pages')->insert([$page1, $page2, $page3]);
    }
}