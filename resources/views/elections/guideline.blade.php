@extends('layouts.unified')

@section('title', 'شیوه‌نامه و راهنمای انتخابات EarthCoop')

@push('styles')
<style>
    .eg-page{direction:rtl;color:#172033;padding:2rem 0 4rem}.eg-wrap{width:min(1120px,calc(100% - 2rem));margin:auto}.eg-hero{position:relative;overflow:hidden;border:1px solid rgba(16,185,129,.2);border-radius:28px;padding:3rem;background:linear-gradient(135deg,rgba(236,253,245,.96),rgba(239,246,255,.96));box-shadow:0 24px 70px rgba(15,23,42,.08)}.eg-hero:after{content:"";position:absolute;width:260px;height:260px;border-radius:50%;background:rgba(59,130,246,.12);left:-80px;top:-100px}.eg-kicker{display:inline-flex;gap:.55rem;align-items:center;padding:.45rem .85rem;border-radius:999px;background:#fff;color:#047857;font-weight:800;font-size:.86rem;border:1px solid rgba(16,185,129,.2)}.eg-hero h1{font-size:clamp(1.8rem,4vw,3rem);font-weight:900;margin:1rem 0 .8rem;color:#0f172a;line-height:1.5}.eg-lead{font-size:1.08rem;line-height:2.05;color:#475569;max-width:850px}.eg-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-top:1.7rem}.eg-summary div{background:rgba(255,255,255,.8);border:1px solid rgba(148,163,184,.2);border-radius:16px;padding:1rem}.eg-summary strong{display:block;color:#0f766e;margin-bottom:.25rem}.eg-layout{display:grid;grid-template-columns:260px minmax(0,1fr);gap:24px;margin-top:24px;align-items:start}.eg-nav{position:sticky;top:18px;background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:1rem;box-shadow:0 12px 35px rgba(15,23,42,.05)}.eg-nav a{display:block;text-decoration:none;color:#475569;padding:.62rem .75rem;border-radius:10px;font-size:.91rem}.eg-nav a:hover{background:#ecfdf5;color:#047857}.eg-content{display:grid;gap:18px}.eg-card{background:#fff;border:1px solid #e2e8f0;border-radius:22px;padding:1.6rem 1.7rem;box-shadow:0 12px 35px rgba(15,23,42,.045);scroll-margin-top:20px}.eg-card h2{font-size:1.35rem;font-weight:900;color:#0f172a;margin:0 0 .8rem}.eg-card h3{font-size:1.05rem;font-weight:800;color:#0f766e;margin:1.2rem 0 .5rem}.eg-card p,.eg-card li{line-height:2;color:#475569}.eg-card ul,.eg-card ol{padding-right:1.3rem;margin:.5rem 0}.eg-note{background:#f8fafc;border-right:4px solid #10b981;border-radius:14px;padding:1rem 1.1rem;margin:1rem 0;color:#334155;line-height:1.9}.eg-warning{background:#fff7ed;border-right-color:#f59e0b}.eg-steps{counter-reset:step;display:grid;gap:10px}.eg-step{position:relative;padding:1rem 3.4rem 1rem 1rem;background:#f8fafc;border-radius:15px;border:1px solid #e2e8f0}.eg-step:before{counter-increment:step;content:counter(step);position:absolute;right:1rem;top:1rem;width:30px;height:30px;display:grid;place-items:center;border-radius:50%;background:#10b981;color:#fff;font-weight:900}.eg-table{width:100%;border-collapse:collapse;margin-top:.8rem}.eg-table th,.eg-table td{padding:.8rem;border-bottom:1px solid #e2e8f0;text-align:right;vertical-align:top;line-height:1.8}.eg-table th{color:#0f766e;background:#f8fafc}.eg-faq details{border-top:1px solid #e2e8f0;padding:.9rem 0}.eg-faq details:first-child{border-top:0}.eg-faq summary{cursor:pointer;font-weight:800;color:#0f172a}.eg-faq p{margin:.55rem 0 0}.eg-source{font-size:.88rem;color:#64748b;background:#f8fafc;border-radius:14px;padding:1rem}.eg-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:1.4rem}.eg-btn{display:inline-flex;align-items:center;gap:.5rem;text-decoration:none;border-radius:12px;padding:.75rem 1rem;font-weight:800}.eg-btn-primary{background:#10b981;color:#fff}.eg-btn-soft{background:#fff;color:#0f766e;border:1px solid #a7f3d0}@media(max-width:900px){.eg-summary{grid-template-columns:repeat(2,1fr)}.eg-layout{grid-template-columns:1fr}.eg-nav{position:static;display:flex;overflow:auto;gap:4px}.eg-nav a{white-space:nowrap}.eg-hero{padding:2rem}}@media(max-width:560px){.eg-page{padding-top:1rem}.eg-wrap{width:min(100% - 1rem,1120px)}.eg-hero{padding:1.4rem;border-radius:20px}.eg-summary{grid-template-columns:1fr}.eg-card{padding:1.25rem;border-radius:18px}.eg-table{font-size:.9rem}.eg-table th,.eg-table td{padding:.65rem}.eg-nav{margin-inline:-.1rem}}
</style>
@endpush

@section('content')
<div class="eg-page">
    <div class="eg-wrap">
        <section class="eg-hero">
            <span class="eg-kicker"><i class="fas fa-vote-yea"></i> راهنمای رسمی و ساده انتخابات سیستمی</span>
            <h1>انتخابات در EarthCoop چگونه کار می‌کند؟</h1>
            <p class="eg-lead">انتخابات EarthCoop یک رأی‌گیری مقطعی با ثبت‌نام نامزدها نیست. سامانه، پس از فراهم‌شدن شرایط هر گروه، چرخه انتخابات را به‌صورت سیستمی آغاز می‌کند؛ اعضای واجد شرایط اشخاص مورد اعتماد خود را از میان اعضای واجد شرایط برای مسئولیت مدیریت و بازرسی انتخاب می‌کنند، و سامانه از رأی‌گیری تا شمارش، پیشنهاد مسئولیت، پذیرش و نصب نهایی را با قواعد ثبت‌شده و قابل بازبینی پیش می‌برد.</p>
            <div class="eg-summary">
                <div><strong>بدون نامزدی رسمی</strong><span>لازم نیست کسی پیشاپیش اعلام نامزدی کند.</span></div>
                <div><strong>پیوسته و سیستمی</strong><span>شروع و پیشروی چرخه تابع سیاست همان گروه است.</span></div>
                <div><strong>قابل تغییر</strong><span>تا پایان مهلت رأی‌گیری می‌توانید برگه خود را اصلاح یا پس بگیرید.</span></div>
                <div><strong>قابل حسابرسی</strong><span>مراحل حساس و تغییرات مهم، سابقه قابل پیگیری دارند.</span></div>
            </div>
        </section>

        <div class="eg-layout">
            <nav class="eg-nav" aria-label="فهرست راهنمای انتخابات">
                <a href="#idea">ایده اصلی</a><a href="#start">شروع انتخابات</a><a href="#vote">چطور رأی بدهم؟</a><a href="#privacy">حریم خصوصی</a><a href="#count">شمارش و نتیجه</a><a href="#acceptance">پذیرش مسئولیت</a><a href="#representation">نمایندگی سطوح بالاتر</a><a href="#reports">گزارش‌ها و بازخورد</a><a href="#review">اعتراض و بازبینی</a><a href="#faq">پرسش‌های رایج</a>
            </nav>

            <div class="eg-content">
                <section class="eg-card" id="idea">
                    <h2>۱. ایده اصلی: انتخاب افراد مورد اعتماد، نه رقابت انتخاباتی</h2>
                    <p>در EarthCoop «نامزد رسمی» شرط انتخابات نیست. وقتی یک چرخه باز است، شما فهرست اعضایی را می‌بینید که طبق شرایط همان چرخه قابل انتخاب‌اند و می‌توانید از میان آنان مدیران و بازرسان مورد اعتماد خود را معرفی کنید. هدف این طراحی آن است که انتخاب مسئولان به تشخیص مستقیم اعضا وابسته باشد، نه به تشکیل فهرست انتخاباتی، تبلیغات یا اعلام نامزدی قبلی.</p>
                    <div class="eg-note">مدیر و بازرس دو مسئولیت جدا هستند. یک فرد در یک برگه رأی نمی‌تواند هم‌زمان برای هر دو مسئولیت انتخاب شود. تعداد انتخاب‌های مجاز هر نقش نیز در همان چرخه مشخص است.</div>
                </section>

                <section class="eg-card" id="start">
                    <h2>۲. انتخابات چه زمانی شروع می‌شود؟</h2>
                    <p>برای هر نوع و سطح گروه، سیاست انتخاباتی مشخص می‌کند چه تعداد عضو فعال برای آغاز لازم است، چند مدیر و بازرس باید انتخاب شوند و پنجره رأی‌گیری چه مدت باز بماند. وقتی شرایط لازم فراهم شود، سامانه چرخه را ایجاد و مدیریت می‌کند؛ شروع یا پایان عادی انتخابات وابسته به اقدام دستی یک مدیر نیست.</p>
                    <p>در آغاز هر چرخه، قواعد مؤثر همان چرخه و فهرست افراد واجد شرایط ثبت می‌شود. بنابراین تغییر تنظیمات برای آینده نباید بی‌صدا قواعد یک چرخه در حال اجرا یا نتیجه تاریخی آن را عوض کند.</p>
                    <h3>چرخه به زبان ساده</h3>
                    <div class="eg-steps">
                        <div class="eg-step"><strong>فراهم‌شدن شرایط گروه:</strong> حدنصاب و سایر شرایط سیاست انتخاباتی برقرار می‌شود.</div>
                        <div class="eg-step"><strong>بازشدن رأی‌گیری:</strong> اعضای واجد شرایط برگه انتخابات را دریافت می‌کنند.</div>
                        <div class="eg-step"><strong>توقف رأی‌گیری و شمارش:</strong> در پایان مهلت، داده رأی برای شمارش تثبیت و نتیجه طبق روش قطعی محاسبه می‌شود.</div>
                        <div class="eg-step"><strong>پیشنهاد مسئولیت:</strong> به افراد برگزیده، به ترتیب نتیجه، پیشنهاد رسمی مسئولیت داده می‌شود.</div>
                        <div class="eg-step"><strong>پذیرش و نصب:</strong> فقط پس از پذیرش قرارداد و احراز شرایط، انتصاب رسمی انجام می‌شود.</div>
                    </div>
                </section>

                <section class="eg-card" id="vote">
                    <h2>۳. چطور رأی بدهم؟</h2>
                    <ol>
                        <li>در گروهی که انتخابات فعال دارد، «انتخابات فعال» را باز کنید.</li>
                        <li>در تب «مدیران»، افراد مورد اعتماد خود را تا سقف مجاز انتخاب کنید.</li>
                        <li>در تب «بازرسان»، افراد مورد اعتماد برای بازرسی را انتخاب کنید.</li>
                        <li>برای هر انتخاب، سطح افشای همان رأی را تعیین کنید.</li>
                        <li>در صورت تمایل، دلیل یا توضیحی برای رأی، تغییر رأی یا پس‌گرفتن آن بنویسید و مشخص کنید چه کسانی اجازه دیدن آن را داشته باشند.</li>
                        <li>برگه را ثبت کنید. تا پایان پنجره رأی‌گیری می‌توانید انتخاب‌ها را تغییر دهید یا همه انتخاب‌ها را پس بگیرید.</li>
                    </ol>
                    <div class="eg-note">ثبت تغییر یا پس‌گرفتن رأی، سابقه تاریخی را بی‌صدا پاک نمی‌کند. تغییرات لازم برای سلامت و حسابرسی انتخابات به‌صورت رویداد ثبت می‌شوند، در حالی که نمایش عادی همچنان تابع قواعد حریم خصوصی است.</div>
                </section>

                <section class="eg-card" id="privacy">
                    <h2>۴. چه کسی می‌تواند ببیند من به چه کسی رأی داده‌ام؟</h2>
                    <p>سطح افشای رأی برای هر انتخاب جداگانه قابل تعیین است. سه حالت اصلی وجود دارد:</p>
                    <table class="eg-table">
                        <thead><tr><th>حالت</th><th>معنا</th></tr></thead>
                        <tbody>
                            <tr><td><strong>محرمانه</strong></td><td>هویت رأی‌دهنده در نمایش عادی پنهان می‌ماند. مدیر یا بازرس بودن، به‌تنهایی اجازه دیدن هویت یک رأی محرمانه را ایجاد نمی‌کند.</td></tr>
                            <tr><td><strong>همه اعضا</strong></td><td>اعضای فعال و مجاز همان گروه می‌توانند هویت رأی‌دهنده را در مسیرهای مجاز ببینند.</td></tr>
                            <tr><td><strong>منتخبین</strong></td><td>نمایش هویت به مدیران و بازرسان منصوب و فعال همان گروه محدود می‌شود.</td></tr>
                        </tbody>
                    </table>
                    <h3>توضیح همراه رأی</h3>
                    <p>توضیح اختیاری شما تنظیم جداگانه‌ای دارد: می‌تواند برای همه اعضای مجاز، فقط منتخبین، یا فقط فرد مرتبط قابل مشاهده باشد. همچنین می‌توانید بخواهید توضیح در نمایش عادی ناشناس باشد. «ناشناس بودن توضیح» با «دامنه افرادی که اجازه دیدن توضیح را دارند» دو انتخاب مستقل‌اند.</p>
                    <div class="eg-note">سامانه برای حسابرسی حفاظت‌شده ممکن است اطلاعات لازم را نگهداری کند، اما این به معنی نمایش عمومی آن اطلاعات نیست. دسترسی حسابرسی، مسیر و مجوز جداگانه دارد.</div>
                </section>

                <section class="eg-card" id="count">
                    <h2>۵. رأی‌ها چگونه شمرده می‌شوند و اگر آرا مساوی باشد چه می‌شود؟</h2>
                    <p>پس از بسته‌شدن پنجره رأی‌گیری، شمارش بر پایه داده تثبیت‌شده همان چرخه انجام می‌شود. روش شمارش و قاعده حل تساوی باید قطعی و قابل بازتولید باشد؛ یعنی با همان داده و همان قواعد، دوباره به همان نتیجه برسیم. انتخاب تصادفی برای تعیین جانشین یا پرکردن مسئولیت، بخشی از سازوکار معتبر انتخابات نیست.</p>
                    <p>نتیجه شمارش، رتبه افراد را مشخص می‌کند؛ اما «قرارگرفتن در رتبه برنده» هنوز به معنی قبول مسئولیت یا نصب رسمی نیست.</p>
                </section>

                <section class="eg-card" id="acceptance">
                    <h2>۶. برنده‌شدن با مسئول‌شدن فرق دارد</h2>
                    <p>در EarthCoop سه مرحله از هم جداست: <strong>نتیجه رأی و رتبه‌بندی</strong>، <strong>پذیرش یا رد پیشنهاد مسئولیت</strong> و <strong>انتصاب رسمی</strong>. پس از شمارش، به افراد واجد رتبه لازم پیشنهاد مسئولیت داده می‌شود. متن و شرایط مسئولیت در قالب قرارداد نسخه‌بندی‌شده مشخص است.</p>
                    <p>اگر فرد پیشنهاد را رد کند، در مهلت مقرر پاسخ ندهد، یا در مرحله لازم واجد شرایط نباشد، نتیجه تاریخی رأی او پاک نمی‌شود؛ سامانه طبق رتبه‌بندی ثبت‌شده سراغ فرد بعدی می‌رود تا ظرفیت مسئولیت تکمیل شود یا فهرست معتبر به پایان برسد.</p>
                    <div class="eg-warning eg-note">هیچ‌کس صرفاً به‌خاطر کسب رأی، بدون پذیرش مسئولیت و طی مسیر انتصاب، مدیر یا بازرس رسمی نمی‌شود.</div>
                </section>

                <section class="eg-card" id="representation">
                    <h2>۷. پس از انتصاب چه اتفاقی می‌افتد؟</h2>
                    <p>وقتی انتصاب معتبر تکمیل شد، نقش رسمی مدیر یا بازرس و آثار نمایندگی مربوط به آن از مسیر خود سامانه انتخابات اعمال می‌شود. در ساختار پلکانی EarthCoop، نمایندگی در سطح بالاتر نیز تابع قواعد معتبر همان ساختار است و به‌صورت ثبت‌شده اعمال می‌شود؛ نه با تغییر دستی و پراکنده نقش‌ها.</p>
                    <p>اگر بعداً کرسی خالی شود، سازوکار جانشینی و تداوم مسئولیت از سابقه و رتبه‌بندی معتبر استفاده می‌کند. هدف این است که اداره گروه با خروج یا عدم پذیرش یک نفر متوقف نشود.</p>
                </section>

                <section class="eg-card" id="reports">
                    <h2>۸. گزارش محبوبیت، تغییر رأی و بازخوردها چه هستند؟</h2>
                    <p>سامانه می‌تواند گزارش‌های تجمیعی مانند تعداد رأی فعلی، ورود و خروج رأی، تغییر خالص، فاصله تا مرز انتخاب و روندهای معنادار را ارائه کند. این گزارش‌ها برای فهم وضعیت عمومی‌اند، نه برای بازسازی هویت رأی‌دهندگان.</p>
                    <p>برای جلوگیری از افشای غیرمستقیم هویت، گزارش‌های آماری در نمونه‌های خیلی کوچک یا بازه‌های زمانی ناکافی می‌توانند محدود یا پنهان شوند. بازخوردهای متنی نیز مسیر انتشار و تعدیل خود را دارند و موضوعات مشترک می‌توانند بدون افشای نویسنده ناشناس جمع‌بندی شوند تا مسئولان بتوانند به موضوع پاسخ عمومی بدهند.</p>
                </section>

                <section class="eg-card" id="review">
                    <h2>۹. اگر به روند یا نتیجه اعتراض داشته باشم چه؟</h2>
                    <p>انتخابات فقط شمارش رأی نیست؛ امکان بازبینی رویه نیز بخشی از طراحی است. درخواست بازبینی باید به یک رویداد واقعی همان انتخابات متصل باشد. سامانه ابتدا می‌تواند شواهد ثبت‌شده، داده شمارش و قواعد همان چرخه را بررسی کند و در شرایط مقرر، مسیر بازبینی انسانی نیز وجود دارد.</p>
                    <p>در بازبینی انسانی، حمایت لازم، مهلت تصمیم، امکان توقف موقت در موارد لازم و تصمیم مستدل در نظر گرفته شده است. اصلاح نیز نباید تاریخ را بی‌صدا بازنویسی کند؛ تصمیم و مرجع اصلاح باید قابل پیگیری بماند.</p>
                </section>

                <section class="eg-card" id="faq">
                    <h2>۱۰. پرسش‌های رایج</h2>
                    <div class="eg-faq">
                        <details><summary>آیا باید برای مدیر یا بازرس شدن نامزد شوم؟</summary><p>خیر. مدل انتخابات EarthCoop بدون نامزدی رسمی طراحی شده است. اعضا از میان افراد واجد شرایط انتخاب می‌کنند.</p></details>
                        <details><summary>آیا مجبورم تمام ظرفیت مدیران یا بازرسان را پر کنم؟</summary><p>ظرفیت، سقف انتخاب‌های مجاز را تعیین می‌کند. برگه شما باید قواعد اعتبارسنجی همان چرخه را رعایت کند؛ رابط رأی‌گیری تعداد انتخاب فعلی و سقف مجاز را نشان می‌دهد.</p></details>
                        <details><summary>آیا یک نفر را می‌توانم هم مدیر و هم بازرس انتخاب کنم؟</summary><p>خیر؛ در یک برگه، یک عضو نمی‌تواند هم‌زمان برای هر دو مسئولیت انتخاب شود.</p></details>
                        <details><summary>آیا بعد از ثبت رأی می‌توانم نظرم را عوض کنم؟</summary><p>بله، تا زمانی که پنجره رأی‌گیری باز است می‌توانید برگه را به‌روزرسانی یا انتخاب‌ها را پس بگیرید.</p></details>
                        <details><summary>اگر فرد منتخب مسئولیت را قبول نکند چه می‌شود؟</summary><p>سامانه نتیجه رأی را پاک نمی‌کند؛ طبق رتبه‌بندی و قواعد جانشینی به فرد بعدی پیشنهاد می‌دهد.</p></details>
                        <details><summary>آیا تغییر تنظیمات ادمین نتیجه انتخابات جاری را عوض می‌کند؟</summary><p>قاعده اصلی این است که سیاست چرخه تثبیت شود؛ تغییرات آینده نباید به‌طور ضمنی تاریخ یا قواعد چرخه جاری را بازنویسی کند. تغییر استثنایی چرخه فعال مسیر صریح، دلیل و سابقه حسابرسی جداگانه دارد.</p></details>
                    </div>
                </section>

                <section class="eg-card">
                    <h2>یک اصل برای به خاطر سپردن</h2>
                    <p><strong>رأی شما انتخابِ فرد مورد اعتماد است؛ نتیجه رأی رتبه می‌سازد؛ رتبه پیشنهاد مسئولیت می‌سازد؛ و فقط پذیرش معتبر و انتصاب رسمی، مسئولیت واقعی ایجاد می‌کند.</strong></p>
                    <p class="eg-source">این راهنما ترجمه کاربرپسندِ قواعد مرجع انتخابات E0 و معماری اجرایی منطبق با آن است. برای سادگی، اصطلاحات فنی پایگاه‌داده، سرویس‌ها و جزئیات داخلی حذف شده‌اند؛ اما تفکیک چرخه، حریم رأی، بازخورد، گزارش، بازبینی، قرارداد مسئولیت، جانشینی و انتصاب حفظ شده است.</p>
                    <div class="eg-actions">
                        @auth
                            <a class="eg-btn eg-btn-primary" href="{{ route('home') }}"><i class="fas fa-home"></i> بازگشت به EarthCoop</a>
                        @else
                            <a class="eg-btn eg-btn-primary" href="{{ route('welcome') }}"><i class="fas fa-globe"></i> ورود به EarthCoop</a>
                        @endauth
                        <a class="eg-btn eg-btn-soft" href="#idea"><i class="fas fa-arrow-up"></i> مرور از ابتدا</a>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>
@endsection
