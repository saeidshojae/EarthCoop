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
            'content' => '<p>به راهنمای EarthCoop خوش آمدید. در سه بخش زیر، مسیر ثبت‌نام، تکمیل اطلاعات و استفاده از گروه‌ها و امکانات حکمرانی را به‌صورت مرحله‌به‌مرحله دنبال کنید.</p>',
            'content_translations' => json_encode([
                'fa' => '<p>به راهنمای EarthCoop خوش آمدید. در سه بخش زیر، مسیر ثبت‌نام، تکمیل اطلاعات و استفاده از گروه‌ها و امکانات حکمرانی را به‌صورت مرحله‌به‌مرحله دنبال کنید.</p>',
                'en' => '<p>Welcome to the EarthCoop user guide. Use the three sections below to follow registration, complete your information, and learn how to use groups and governance features step by step.</p>',
                'ar' => '<p>مرحباً بكم في دليل استخدام EarthCoop. استخدموا الأقسام الثلاثة أدناه للتعرّف خطوة بخطوة على التسجيل واستكمال المعلومات واستخدام المجموعات وميزات الحوكمة.</p>',
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