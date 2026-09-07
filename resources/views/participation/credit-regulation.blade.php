@extends('layouts.unified')

@section('title', 'نظام‌نامه اعتبارات مشارکت EarthCoop')

@push('styles')
<style>
    .pcr-page{direction:rtl;color:#172033;padding:2rem 0 4rem;overflow-x:clip}.pcr-wrap{width:min(1120px,calc(100% - 2rem));max-width:100%;margin:auto;min-width:0}.pcr-hero{position:relative;overflow:hidden;min-width:0;border:1px solid rgba(16,185,129,.2);border-radius:28px;padding:3rem;background:linear-gradient(135deg,rgba(236,253,245,.97),rgba(239,246,255,.96));box-shadow:0 24px 70px rgba(15,23,42,.08)}.pcr-hero:after{content:"";position:absolute;width:260px;height:260px;border-radius:50%;background:rgba(59,130,246,.1);left:-80px;top:-100px}.pcr-kicker{display:inline-flex;max-width:100%;gap:.55rem;align-items:center;padding:.45rem .85rem;border-radius:999px;background:#fff;color:#047857;font-weight:800;font-size:.86rem;border:1px solid rgba(16,185,129,.2)}.pcr-hero h1{font-size:clamp(1.8rem,4vw,3rem);font-weight:900;margin:1rem 0 .8rem;color:#0f172a;line-height:1.5}.pcr-lead{font-size:1.08rem;line-height:2.05;color:#475569;max-width:880px;overflow-wrap:anywhere}.pcr-live{display:flex;min-width:0;gap:.7rem;align-items:flex-start;margin-top:1.35rem;padding:1rem 1.1rem;border-radius:16px;background:rgba(255,255,255,.84);border:1px solid rgba(16,185,129,.22);color:#334155;line-height:1.85}.pcr-live>div{min-width:0;overflow-wrap:anywhere}.pcr-live i{color:#10b981;margin-top:.25rem;flex:0 0 auto}.pcr-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:1.35rem;min-width:0}.pcr-summary div{min-width:0;background:rgba(255,255,255,.82);border:1px solid rgba(148,163,184,.2);border-radius:16px;padding:1rem}.pcr-summary strong{display:block;color:#0f766e;margin-bottom:.25rem}.pcr-summary span{display:block;min-width:0;color:#64748b;font-size:.9rem;line-height:1.7;overflow-wrap:anywhere}.pcr-layout{display:grid;grid-template-columns:260px minmax(0,1fr);gap:24px;margin-top:24px;align-items:start;min-width:0}.pcr-nav{position:sticky;top:18px;min-width:0;max-width:100%;background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:1rem;box-shadow:0 12px 35px rgba(15,23,42,.05)}.pcr-nav a{display:block;text-decoration:none;color:#475569;padding:.62rem .75rem;border-radius:10px;font-size:.91rem}.pcr-nav a:hover{background:#ecfdf5;color:#047857}.pcr-content{min-width:0;display:grid;gap:18px}.pcr-card{min-width:0;background:#fff;border:1px solid #e2e8f0;border-radius:22px;padding:1.6rem 1.7rem;box-shadow:0 12px 35px rgba(15,23,42,.045);scroll-margin-top:20px}.pcr-card h2{font-size:1.35rem;font-weight:900;color:#0f172a;margin:0 0 .8rem;overflow-wrap:anywhere}.pcr-card h3{font-size:1.05rem;font-weight:800;color:#0f766e;margin:1.2rem 0 .5rem;overflow-wrap:anywhere}.pcr-card p,.pcr-card li{line-height:2;color:#475569;overflow-wrap:anywhere}.pcr-card ul,.pcr-card ol{padding-right:1.3rem;margin:.5rem 0;min-width:0}.pcr-note{min-width:0;background:#f8fafc;border-right:4px solid #10b981;border-radius:14px;padding:1rem 1.1rem;margin:1rem 0;color:#334155;line-height:1.9;overflow-wrap:anywhere}.pcr-warning{background:#fff7ed;border-right-color:#f59e0b}.pcr-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:1rem;min-width:0}.pcr-mini{min-width:0;padding:1rem;border:1px solid #e2e8f0;background:#f8fafc;border-radius:16px}.pcr-mini strong{display:block;color:#0f766e;margin-bottom:.35rem;overflow-wrap:anywhere}.pcr-mini p{margin:0;font-size:.92rem}.pcr-tiers{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:1rem;min-width:0}.pcr-tier{min-width:0;padding:1rem;border-radius:16px;border:1px solid #e2e8f0;background:#fff;text-align:center}.pcr-tier strong{display:block;color:#0f172a;font-size:1rem;overflow-wrap:anywhere}.pcr-tier span{display:block;color:#64748b;font-size:.84rem;margin-top:.35rem;overflow-wrap:anywhere}.pcr-table-wrap{width:100%;max-width:100%;overflow-x:auto;border:1px solid #e2e8f0;border-radius:16px;margin-top:1rem;min-width:0;overscroll-behavior-inline:contain;-webkit-overflow-scrolling:touch}.pcr-table{width:100%;min-width:880px;border-collapse:collapse}.pcr-table th,.pcr-table td{padding:.75rem .8rem;border-bottom:1px solid #e2e8f0;text-align:right;vertical-align:top;line-height:1.75;font-size:.86rem}.pcr-table th{color:#0f766e;background:#f8fafc;font-weight:800;white-space:nowrap}.pcr-table tr:last-child td{border-bottom:0}.pcr-rule-name{font-weight:800;color:#0f172a}.pcr-rule-meta{display:block;color:#94a3b8;font-size:.74rem;margin-top:.2rem}.pcr-badge{display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .55rem;border-radius:999px;font-size:.76rem;font-weight:800;white-space:nowrap}.pcr-badge--on{background:#ecfdf5;color:#047857}.pcr-badge--off{background:#f1f5f9;color:#64748b}.pcr-badge--warn{background:#fff7ed;color:#b45309}.pcr-badge--bad{background:#fef2f2;color:#b91c1c}.pcr-conversion{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:1rem 0;min-width:0}.pcr-stat{min-width:0;background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:1rem}.pcr-stat strong{display:block;color:#047857;font-size:1.15rem;margin-bottom:.25rem;overflow-wrap:anywhere}.pcr-stat span{display:block;color:#64748b;font-size:.84rem;line-height:1.7;overflow-wrap:anywhere}.pcr-faq details{min-width:0;border-top:1px solid #e2e8f0;padding:.9rem 0}.pcr-faq details:first-child{border-top:0}.pcr-faq summary{cursor:pointer;font-weight:800;color:#0f172a;overflow-wrap:anywhere}.pcr-faq p{margin:.55rem 0 0}.pcr-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:1.3rem;min-width:0}.pcr-btn{display:inline-flex;align-items:center;gap:.5rem;text-decoration:none;border-radius:12px;padding:.75rem 1rem;font-weight:800;min-width:0}.pcr-btn-primary{background:#10b981;color:#fff}.pcr-btn-soft{background:#fff;color:#0f766e;border:1px solid #a7f3d0}@media(max-width:900px){.pcr-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.pcr-layout{grid-template-columns:minmax(0,1fr)}.pcr-nav{position:static;display:flex;overflow-x:auto;overflow-y:hidden;gap:4px;-webkit-overflow-scrolling:touch}.pcr-nav a{white-space:nowrap;flex:0 0 auto}.pcr-hero{padding:2rem}.pcr-tiers{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:640px){.pcr-page{padding-top:.75rem}.pcr-wrap{width:calc(100% - .75rem)}.pcr-hero{padding:1.15rem 1rem;border-radius:18px}.pcr-kicker{font-size:.78rem;padding:.4rem .65rem}.pcr-hero h1{font-size:1.65rem;line-height:1.55;margin:.8rem 0 .55rem}.pcr-lead{font-size:.95rem;line-height:1.95}.pcr-live{font-size:.86rem;padding:.85rem;border-radius:14px;line-height:1.8}.pcr-summary,.pcr-grid,.pcr-conversion,.pcr-tiers{grid-template-columns:minmax(0,1fr)}.pcr-summary{gap:9px}.pcr-summary div{padding:.85rem}.pcr-layout{gap:14px;margin-top:14px}.pcr-nav{padding:.65rem;border-radius:16px}.pcr-nav a{padding:.55rem .65rem;font-size:.82rem}.pcr-content{gap:12px}.pcr-card{padding:1rem .9rem;border-radius:16px}.pcr-card h2{font-size:1.12rem;line-height:1.75;margin-bottom:.65rem}.pcr-card h3{font-size:.98rem;line-height:1.7}.pcr-card p,.pcr-card li{font-size:.9rem;line-height:1.9}.pcr-card ul,.pcr-card ol{padding-right:1.1rem}.pcr-mini,.pcr-tier,.pcr-stat{padding:.85rem}.pcr-note{padding:.85rem .8rem;font-size:.88rem;line-height:1.85}.pcr-table-wrap{border-radius:12px}.pcr-table{min-width:760px}.pcr-table th,.pcr-table td{padding:.6rem;font-size:.8rem}.pcr-actions{display:grid}.pcr-btn{justify-content:center;width:100%;text-align:center}.pcr-faq summary{font-size:.92rem;line-height:1.8}}
</style>
@endpush

@section('content')
@php
    $activeRules = collect($actionRules)->where('active', true)->count();
    $groupedRules = collect($actionRules)->groupBy('group_label');
@endphp

<div class="pcr-page">
    <div class="pcr-wrap">
        <section class="pcr-hero">
            <span class="pcr-kicker"><i class="fas fa-award"></i> مرجع زنده نظام اعتبار و مشارکت</span>
            <h1>نظام‌نامه اعتبارات مشارکت</h1>
            <p class="pcr-lead">این صفحه توضیح رسمی و قابل‌فهم نظام امتیازدهی EarthCoop است: چه فعالیت‌هایی امتیاز می‌سازند یا کم می‌کنند، هر امتیاز در کدام بُعد اعتبار ثبت می‌شود، سطح‌های اعتباری چگونه تعیین می‌شوند و چه بخشی از امتیاز مشارکت می‌تواند طبق سیاست جاری نجم بهار به پول فعال تبدیل شود.</p>

            <div class="pcr-live">
                <i class="fas fa-sync-alt" aria-hidden="true"></i>
                <div><strong>این نظام‌نامه از تنظیمات مؤثر سامانه ساخته می‌شود.</strong> مقادیر امتیاز، سقف‌ها، وضعیت فعال یا غیرفعال قواعد، سطح‌ها و نرخ تبدیل از منبع اجرایی جاری خوانده می‌شوند؛ بنابراین تغییر تنظیمات معتبر سامانه بدون بازنویسی این متن در اعداد نمایش‌داده‌شده منعکس می‌شود.</div>
            </div>

            <div class="pcr-summary">
                <div><strong>منبع سیاست پولی</strong><span>{{ $policySnapshot['monetary_source_label'] }}</span></div>
                <div><strong>نسخه مؤثر</strong><span>{{ $policySnapshot['monetary_version'] !== null ? 'نسخه '.number_format($policySnapshot['monetary_version']) : 'فاقد شماره نسخه' }}</span></div>
                <div><strong>قواعد فعال امتیاز</strong><span>{{ number_format($activeRules) }} قاعده از منبع جاری</span></div>
                <div><strong>تبدیل به بهار</strong><span>{{ $conversion['enabled'] ? 'فعال' : 'در حال حاضر غیرفعال' }}</span></div>
            </div>
        </section>

        <div class="pcr-layout">
            <nav class="pcr-nav" aria-label="فهرست نظام‌نامه اعتبارات مشارکت">
                <a href="#foundation">مفاهیم پایه</a>
                <a href="#dimensions">ابعاد اعتبار</a>
                <a href="#tiers">سطح‌های اعتباری</a>
                <a href="#rules">قواعد امتیازدهی</a>
                <a href="#conversion">تبدیل به بهار</a>
                <a href="#safeguards">سقف‌ها و جلوگیری از سوءاستفاده</a>
                <a href="#faq">پرسش‌های رایج</a>
            </nav>

            <main class="pcr-content">
                <section class="pcr-card" id="foundation">
                    <h2>۱. اعتبار مشارکت چیست؟</h2>
                    <p>سامانه برای بخشی از رفتارها و مشارکت‌های قابل ثبت، رویداد امتیازی ایجاد می‌کند. نتیجه این رویدادها «سابقه اعتبار» شما را می‌سازد. بعضی رویدادها امتیاز مثبت دارند، بعضی رویدادهای تأییدشده می‌توانند امتیاز منفی داشته باشند و بعضی قواعد نیز ممکن است موقتاً غیرفعال باشند.</p>
                    <div class="pcr-grid">
                        <div class="pcr-mini"><strong>امتیاز کل</strong><p>جمع سابقه امتیازی شماست و مبنای سطح اعتباری است. تبدیل بخشی از ظرفیت اقتصادیِ امتیاز مشارکت، این سابقه را پاک نمی‌کند.</p></div>
                        <div class="pcr-mini"><strong>امتیاز قابل تبدیل</strong><p>فقط از رویدادهای مثبتِ بُعد «مشارکت» که قاعده جاری آن‌ها قابل تبدیل است ایجاد می‌شود.</p></div>
                        <div class="pcr-mini"><strong>ظرفیت مصرف‌شده</strong><p>وقتی امتیاز قابل تبدیل به پول فعال تبدیل می‌شود، همان ظرفیت برای تبدیل دوباره مصرف‌شده محسوب می‌شود.</p></div>
                        <div class="pcr-mini"><strong>امتیاز منفی</strong><p>رویدادهای منفیِ معتبر می‌توانند اعتبار کل یا ظرفیت مشارکت را مطابق بُعد و منطق همان رویداد تعدیل کنند.</p></div>
                    </div>
                    <div class="pcr-note">اعتبار اجتماعی و پول دو دفتر متفاوت‌اند: تبدیل امتیاز، سابقه مشارکت ثبت‌شده را حذف نمی‌کند؛ تنها ظرفیت اقتصادیِ قابل استفاده برای تبدیل مجدد را مصرف می‌کند.</div>
                </section>

                <section class="pcr-card" id="dimensions">
                    <h2>۲. انواع و ابعاد امتیاز</h2>
                    <p>هر رویداد امتیازی در یک بُعد ثبت می‌شود. این تفکیک کمک می‌کند یک عدد واحد، همه جنبه‌های رفتار و مشارکت عضو را با هم مخلوط نکند.</p>
                    <div class="pcr-grid">
                        @foreach($dimensions as $dimension)
                            <div class="pcr-mini">
                                <strong>{{ $dimension['label'] }}</strong>
                                @switch($dimension['key'])
                                    @case('participation')
                                        <p>فعالیت و حضور سازنده در زیست‌بوم. فقط بخشی از رویدادهای مثبت این بُعد، در صورت داشتن علامت «قابل تبدیل»، ظرفیت تبدیل به بهار ایجاد می‌کنند.</p>
                                        @break
                                    @case('reliability')
                                        <p>نشانه‌های مرتبط با پایبندی، رفتار قابل اتکا و همچنین آثار منفیِ تخلفات یا نقض تعهدات تأییدشده.</p>
                                        @break
                                    @case('expertise')
                                        <p>رویدادهایی که در آینده یا اکنون برای نشان‌دادن مشارکت تخصصی و شایستگی حرفه‌ای در این بُعد ثبت می‌شوند.</p>
                                        @break
                                    @case('civic_trust')
                                        <p>رویدادهای مرتبط با اعتماد مدنی و سابقه قابل اتکای فرد در فرایندهای جمعی و مسئولیت اجتماعی.</p>
                                        @break
                                @endswitch
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="pcr-card" id="tiers">
                    <h2>۳. سطح‌های اعتباری: برنزی و بالاتر</h2>
                    <p>سطح اعتباری از امتیاز کل محاسبه می‌شود. مرز هر سطح در زیر مستقیماً از تنظیمات جاری همان موتور محاسبه سطح خوانده شده است.</p>
                    <div class="pcr-tiers">
                        @foreach($tiers as $tier)
                            <div class="pcr-tier">
                                <strong>{{ $tier['label'] }}</strong>
                                <span>از {{ number_format($tier['minimum_points']) }} امتیاز</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="pcr-note">تبدیل امتیاز مشارکت به بهار، امتیاز کل تاریخی را کم نمی‌کند؛ بنابراین صرفِ تبدیل باعث پایین‌آمدن سطح اعتباری نمی‌شود.</div>
                </section>

                <section class="pcr-card" id="rules">
                    <h2>۴. جدول کامل قواعد جاری امتیازدهی</h2>
                    <p>این جدول snapshot قواعد مؤثر در زمان بازشدن همین صفحه است. اگر مدیر سامانه وزن، سقف، بُعد، قابلیت تبدیل یا وضعیت یک قاعده را تغییر دهد، بازکردن دوباره صفحه مقدار جدید را نشان می‌دهد.</p>

                    @foreach($groupedRules as $groupLabel => $rules)
                        <h3>{{ $groupLabel }}</h3>
                        <div class="pcr-table-wrap">
                            <table class="pcr-table">
                                <thead>
                                    <tr>
                                        <th>رویداد</th>
                                        <th>وضعیت</th>
                                        <th>اثر امتیازی</th>
                                        <th>بُعد</th>
                                        <th>قابل تبدیل</th>
                                        <th>سقف دوره‌ای</th>
                                        <th>تکرار</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rules as $rule)
                                        <tr>
                                            <td>
                                                <span class="pcr-rule-name">{{ $rule['label'] }}</span>
                                                <span class="pcr-rule-meta">{{ $rule['source_label'] }}</span>
                                                @if($rule['description'])<span class="pcr-rule-meta">{{ $rule['description'] }}</span>@endif
                                            </td>
                                            <td><span class="pcr-badge {{ $rule['active'] ? 'pcr-badge--on' : 'pcr-badge--off' }}">{{ $rule['active'] ? 'فعال' : 'غیرفعال' }}</span></td>
                                            <td>
                                                @if($rule['weight'] > 0)
                                                    <span class="pcr-badge pcr-badge--on">+{{ number_format($rule['weight']) }} امتیاز</span>
                                                @elseif($rule['weight'] < 0)
                                                    <span class="pcr-badge pcr-badge--bad">−{{ number_format(abs($rule['weight'])) }} امتیاز</span>
                                                @else
                                                    <span class="pcr-badge pcr-badge--off">بدون اثر عددی</span>
                                                @endif
                                            </td>
                                            <td>{{ $rule['dimension_label'] }}</td>
                                            <td>
                                                <span class="pcr-badge {{ $rule['convertible'] ? 'pcr-badge--on' : 'pcr-badge--off' }}">{{ $rule['convertible'] ? 'بله' : 'خیر' }}</span>
                                            </td>
                                            <td>{{ $rule['daily_cap'] !== null ? number_format($rule['daily_cap']).' امتیاز در پنجره جاری' : 'بدون سقف عددی ثبت‌شده' }}</td>
                                            <td>{{ $rule['repeat_policy_label'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach

                    <div class="pcr-note pcr-warning">«فعال بودن یک قاعده» با «قابل تبدیل بودن آن» یکی نیست. یک فعالیت می‌تواند برای سابقه و سطح اعتبار امتیاز ایجاد کند، ولی اجازه تبدیل آن امتیاز به بهار را نداشته باشد.</div>
                </section>

                <section class="pcr-card" id="conversion">
                    <h2>۵. تبدیل امتیاز مشارکت به بهار چگونه انجام می‌شود؟</h2>
                    <p>تبدیل، پول تازه‌ای به عرضه اضافه نمی‌کند. سامانه به اندازه مجاز از پول کمرنگ موجود در حساب عضو را فعال می‌کند و در مقابل، ظرفیت تبدیل متناظر از امتیاز مشارکت را مصرف‌شده ثبت می‌کند.</p>

                    <div class="pcr-conversion">
                        <div class="pcr-stat"><strong>{{ $conversion['enabled'] ? 'فعال' : 'غیرفعال' }}</strong><span>وضعیت فعلی امکان تبدیل</span></div>
                        <div class="pcr-stat"><strong>{{ number_format($conversion['points_per_gol']) }} امتیاز</strong><span>برای فعال‌سازی یک گل طبق سیاست جاری</span></div>
                        <div class="pcr-stat"><strong>{{ number_format($conversion['points_per_bahar']) }} امتیاز</strong><span>معادل یک بهار کامل با نرخ جاری</span></div>
                    </div>

                    <ol>
                        <li>فقط امتیاز مثبتِ بُعد مشارکت که قاعده آن در زمان ثبت رویداد قابل تبدیل بوده است، وارد ظرفیت تبدیل می‌شود.</li>
                        <li>امتیازهایی که قبلاً برای تبدیل مصرف شده‌اند دوباره قابل استفاده نیستند.</li>
                        <li>درخواست تبدیل بر مبنای مضرب کامل نرخ «امتیاز به گل» محاسبه می‌شود؛ بخش کمتر از یک واحد کامل برای تبدیل بعدی باقی می‌ماند.</li>
                        <li>حساب عضو باید به اندازه مبلغ حاصل، موجودی کمرنگ قابل فعال‌سازی داشته باشد.</li>
                        <li>پس از موفقیت، ظرفیت امتیاز مصرف و همان مقدار پول کمرنگ به پول فعال تبدیل می‌شود؛ سابقه کل اعتبار باقی می‌ماند.</li>
                    </ol>

                    <div class="pcr-note">
                        واحد خرد حساب پولی «گل» است و هر بهار شامل {{ number_format($conversion['gol_per_bahar']) }} گل است. حداقل بلوک امتیازی قابل تبدیل با سیاست فعلی {{ number_format($conversion['minimum_convertible_points']) }} امتیاز است.
                    </div>

                    @if(!$conversion['enabled'])
                        <div class="pcr-note pcr-warning">تبدیل در سیاست مؤثر فعلی غیرفعال است. این صفحه نرخ و قواعد ثبت‌شده را برای شفافیت نشان می‌دهد، اما تا فعال‌شدن policy امکان اجرای تبدیل وجود ندارد.</div>
                    @endif
                </section>

                <section class="pcr-card" id="safeguards">
                    <h2>۶. سقف‌ها، تکرار و جلوگیری از امتیازسازی مصنوعی</h2>
                    <ul>
                        <li><strong>وضعیت فعال:</strong> قاعده غیرفعال نباید برای رویداد جدید امتیاز ایجاد کند.</li>
                        <li><strong>سقف دوره‌ای:</strong> برای قواعدی که سقف دارند، مجموع امتیاز مثبت در پنجره محاسباتی جاری نمی‌تواند از سقف نمایش‌داده‌شده بالاتر برود.</li>
                        <li><strong>سیاست تکرار:</strong> بعضی رویدادها فقط یک‌بار، بعضی یک‌بار برای هر مصداق و بعضی تکرارپذیرند؛ جدول بالا وضعیت ثبت‌شده هر قاعده را نشان می‌دهد.</li>
                        <li><strong>رویدادهای یکتا:</strong> مسیرهایی که برای یک رخداد واقعی کلید یکتای امتیازی دارند، در برابر ثبت مجدد همان رخداد محافظت می‌شوند.</li>
                        <li><strong>تخلف و گزارش:</strong> کسر اعتبار برای رویدادهای نظارتی باید از مسیر تأییدشده همان قاعده رخ دهد؛ صرف گزارش خام به‌تنهایی نباید جای تصمیم معتبر را بگیرد.</li>
                    </ul>
                    <div class="pcr-note">هدف امتیاز، تشویق مشارکت واقعی و ثبت سابقه قابل اتکاست؛ نه تبدیل کلیک‌های تکراری یا رفتار ساختگی به اعتبار یا پول.</div>
                </section>

                <section class="pcr-card pcr-faq" id="faq">
                    <h2>۷. پرسش‌های رایج</h2>
                    <details open><summary>آیا با تبدیل امتیاز به بهار، امتیاز کل من کم می‌شود؟</summary><p>خیر. سابقه کل اعتبار باقی می‌ماند. آنچه مصرف می‌شود ظرفیت تبدیل همان امتیازهای واجد شرایط است تا یک امتیاز دوبار به پول تبدیل نشود.</p></details>
                    <details><summary>آیا همه امتیازهای مثبت قابل تبدیل‌اند؟</summary><p>خیر. قاعده باید مثبت، در بُعد مشارکت و دارای وضعیت «قابل تبدیل» باشد. ستون «قابل تبدیل» جدول بالا وضعیت جاری هر رویداد را نشان می‌دهد.</p></details>
                    <details><summary>اگر مدیر سامانه عدد یک قاعده را تغییر دهد چه می‌شود؟</summary><p>جدول این صفحه در بازدید بعدی مقدار جاری را از منبع اجرایی می‌خواند. رویدادهای تاریخی همچنان سابقه ثبت‌شده خود را دارند و این صفحه قرار نیست دفتر تاریخ را بازنویسی کند.</p></details>
                    <details><summary>برنزی، نقره‌ای، طلایی و پلاتینی چه هستند؟</summary><p>این‌ها سطح‌های اعتباری بر اساس امتیاز کل‌اند. حداقل امتیاز هر سطح در بخش سطح‌ها از تنظیمات فعلی سامانه نمایش داده می‌شود.</p></details>
                    <details><summary>چرا ممکن است امتیاز قابل تبدیل من از امتیاز کل کمتر باشد؟</summary><p>چون امتیاز کل همه ابعاد و رویدادهای معتبر را در بر می‌گیرد، ولی ظرفیت تبدیل فقط از مجموعه محدودتری از امتیازهای مشارکتِ مثبت و قابل تبدیل ساخته می‌شود و بخش مصرف‌شده نیز دوباره قابل تبدیل نیست.</p></details>

                    <div class="pcr-actions">
                        <a class="pcr-btn pcr-btn-primary" href="{{ route('history.index') }}"><i class="fas fa-chart-line"></i> بازگشت به مشارکت‌های من</a>
                        <a class="pcr-btn pcr-btn-soft" href="#rules"><i class="fas fa-table"></i> مشاهده جدول قواعد جاری</a>
                    </div>
                </section>
            </main>
        </div>
    </div>
</div>
@endsection
