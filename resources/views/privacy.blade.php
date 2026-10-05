@extends('layouts.unified')

@section('title', 'سیاست حریم خصوصی ارث‌کوپ')
@section('meta_description', 'نحوه جمع‌آوری، استفاده، نگهداری و حذف اطلاعات کاربران در ارث‌کوپ و داده‌های ورود با حساب گوگل.')

@section('content')
<div dir="rtl" class="container mx-auto max-w-4xl px-4 py-8 sm:py-12">
    <article class="overflow-hidden rounded-3xl border border-emerald-100 bg-white shadow-sm">
        <header class="bg-gradient-to-l from-emerald-950 to-emerald-800 px-6 py-10 text-white sm:px-10">
            <p class="mb-3 text-sm font-semibold text-emerald-200">شفافیت و اختیار کاربر</p>
            <h1 class="text-3xl font-black leading-tight sm:text-4xl">سیاست حریم خصوصی ارث‌کوپ</h1>
            <p class="mt-4 max-w-2xl leading-8 text-emerald-50">
                این سند توضیح می‌دهد ارث‌کوپ چه اطلاعاتی را دریافت می‌کند، چرا از آن استفاده می‌کند و شما چگونه می‌توانید درباره اطلاعات خود تصمیم بگیرید.
            </p>
            <p class="mt-4 text-sm text-emerald-200">آخرین بازبینی: ۱۳ مهر ۱۴۰۵</p>
        </header>

        <div class="space-y-10 px-6 py-8 text-right leading-8 text-slate-700 sm:px-10 sm:py-10">
            <section>
                <h2 class="mb-3 text-2xl font-bold text-slate-950">۱. دامنه این سیاست</h2>
                <p>این سیاست درباره اطلاعاتی است که هنگام استفاده از وب‌سایت، ثبت‌نام، ورود، تکمیل نمایه و مشارکت در خدمات ارث‌کوپ پردازش می‌شود. استفاده از سامانه همچنین تابع <a class="font-semibold text-emerald-700 underline" href="{{ route('terms') }}">شرایط استفاده</a> است.</p>
            </section>

            <section>
                <h2 class="mb-3 text-2xl font-bold text-slate-950">۲. اطلاعاتی که دریافت می‌کنیم</h2>
                <ul class="list-inside list-disc space-y-2">
                    <li>اطلاعات حساب و تماس، مانند نام، نشانی ایمیل و اطلاعاتی که خودتان در نمایه وارد می‌کنید.</li>
                    <li>اطلاعات فنی ضروری، مانند نشانی IP، نوع مرورگر، زمان درخواست و داده‌های امنیتی و نشست.</li>
                    <li>محتوا و سوابقی که آگاهانه در بخش‌های مشارکتی سامانه ثبت می‌کنید.</li>
                    <li>اطلاعات پایه‌ای که با اجازه شما از ارائه‌دهنده ورود دریافت می‌شود.</li>
                </ul>
            </section>

            <section>
                <h2 class="mb-3 text-2xl font-bold text-slate-950">۳. ورود با حساب گوگل</h2>
                <p>اگر «ورود با حساب گوگل» را انتخاب کنید، ارث‌کوپ فقط اطلاعات پایه‌ای مجازشده برای احراز هویت—مانند شناسه حساب گوگل، نام، نشانی ایمیل و در صورت ارائه، تصویر نمایه—را دریافت می‌کند. ارث‌کوپ رمز عبور حساب گوگل شما را دریافت، مشاهده یا ذخیره نمی‌کند. می‌توانید دسترسی اعطاشده را از تنظیمات حساب گوگل خود لغو کنید.</p>
            </section>

            <section>
                <h2 class="mb-3 text-2xl font-bold text-slate-950">۴. هدف استفاده از اطلاعات</h2>
                <ul class="list-inside list-disc space-y-2">
                    <li>ایجاد و مدیریت حساب، احراز هویت و جلوگیری از دسترسی غیرمجاز.</li>
                    <li>ارائه قابلیت‌های سامانه، پاسخ به درخواست‌های پشتیبانی و ارسال پیام‌های ضروری خدمت.</li>
                    <li>حفظ امنیت، تشخیص سوءاستفاده، رفع خطا و بهبود قابلیت اطمینان سامانه.</li>
                    <li>انجام تکالیف قانونی و رسیدگی به اختلاف‌ها یا درخواست‌های معتبر.</li>
                </ul>
            </section>

            <section>
                <h2 class="mb-3 text-2xl font-bold text-slate-950">۵. کوکی‌ها و نشست</h2>
                <p>ارث‌کوپ از کوکی‌ها و شناسه‌های نشست ضروری برای ورود امن، حفظ وضعیت کاربر، تنظیم زبان و محافظت در برابر درخواست‌های جعلی استفاده می‌کند. استفاده از کوکی‌های غیرضروری، در صورت افزوده‌شدن، باید با اطلاع و انتخاب مناسب کاربر همراه باشد.</p>
            </section>

            <section>
                <h2 class="mb-3 text-2xl font-bold text-slate-950">۶. اشتراک‌گذاری اطلاعات</h2>
                <p>اطلاعات شخصی فروخته نمی‌شود. دسترسی فقط در حد لازم به ارائه‌دهندگان فنی متعهد به محرمانگی، یا در پاسخ به الزام قانونی معتبر داده می‌شود. محتوایی که خودتان برای انتشار عمومی ثبت می‌کنید ممکن است مطابق تنظیمات همان بخش برای دیگران قابل مشاهده باشد.</p>
            </section>

            <section>
                <h2 class="mb-3 text-2xl font-bold text-slate-950">۷. نگهداری و امنیت</h2>
                <p>اطلاعات تنها تا زمانی نگهداری می‌شود که برای ارائه خدمت، امنیت، حل اختلاف یا تکالیف قانونی لازم باشد. ارث‌کوپ از کنترل‌های فنی و سازمانی متناسب برای کاهش خطر دسترسی، تغییر، افشا یا حذف غیرمجاز استفاده می‌کند؛ با این حال هیچ سامانه اینترنتی مصونیت مطلق ندارد.</p>
            </section>

            <section>
                <h2 class="mb-3 text-2xl font-bold text-slate-950">۸. حقوق و انتخاب‌های شما</h2>
                <p>می‌توانید برای مشاهده، اصلاح یا محدودکردن اطلاعات حساب خود و نیز دریافت توضیح درباره پردازش آن‌ها با ما تماس بگیرید. ممکن است برای حفاظت از حساب، پیش از اجرای درخواست نیاز به احراز هویت باشد.</p>
            </section>

            <section id="delete-data" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                <h2 class="mb-3 text-2xl font-bold text-slate-950">۹. درخواست حذف اطلاعات</h2>
                <p>برای درخواست حذف حساب یا اطلاعات مرتبط، از نشانی <a class="font-semibold text-emerald-800 underline" href="mailto:contact@earthcoop.ir">contact@earthcoop.ir</a> پیام بفرستید و نشانی ایمیل حساب خود را ذکر کنید. پس از احراز هویت، اطلاعات مشمول درخواست حذف می‌شود؛ مگر بخشی که نگهداری آن برای امنیت، جلوگیری از تقلب، حل اختلاف یا الزام قانونی ضروری باشد.</p>
            </section>

            <section>
                <h2 class="mb-3 text-2xl font-bold text-slate-950">۱۰. کودکان و تغییرات سیاست</h2>
                <p>خدمات ارث‌کوپ برای استفاده مستقل کودکان طراحی نشده است. تغییرات مهم این سیاست با تاریخ بازبینی تازه و، در صورت لزوم، اطلاع‌رسانی مناسب منتشر می‌شود.</p>
            </section>

            <section>
                <h2 class="mb-3 text-2xl font-bold text-slate-950">۱۱. تماس با ما</h2>
                <p>پرسش‌ها و درخواست‌های مرتبط با حریم خصوصی را به <a class="font-semibold text-emerald-700 underline" href="mailto:contact@earthcoop.ir">contact@earthcoop.ir</a> ارسال کنید.</p>
            </section>
        </div>
    </article>
</div>
@endsection
