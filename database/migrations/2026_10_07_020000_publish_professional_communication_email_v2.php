<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MARKER = 'earthcoop-email-content-v2';

    public function up(): void
    {
        // Keep application links host-agnostic in immutable content. The delivery
        // presenter resolves root-relative href/src values against the current
        // APP_URL so domain changes do not require republishing template content.
        $urls = [
            'home' => '/home',
            'profile' => '/profile',
            'groups' => '/groups',
            'participation' => '/history',
            'governance' => '/location-governance/me',
            'preferences' => '/profile/communication-preferences',
            'register' => '/register',
        ];

        $this->publish(
            'onboarding.welcome',
            '{{display_name}} عزیز، به EarthCoop خوش آمدید',
            '<!-- '.self::MARKER.' -->'
            .'<h1>{{display_name}} عزیز، خوش آمدید.</h1>'
            .'<p>عضویت شما در EarthCoop کامل شده است. برای شروع لازم نیست همه بخش‌ها را یک‌باره بشناسید؛ از چند قدم ساده آغاز کنید.</p>'
            .'<div class="ec-box"><strong>سه قدم برای شروع</strong>'
            .'<ol><li>پروفایل و اطلاعات مکانی خود را مرور و در صورت نیاز تکمیل کنید.</li>'
            .'<li>گروه‌های خود را ببینید؛ عضویت‌های مکانی، تخصصی و سایر گروه‌های مرتبط از اینجا در دسترس‌اند.</li>'
            .'<li>بخش مشارکت‌های من را بررسی کنید تا انتخابات، نظرسنجی‌ها و فعالیت‌های جاری را از دست ندهید.</li></ol></div>'
            .'<p style="text-align:center"><a class="ec-button" href="'.$urls['home'].'">شروع فعالیت در EarthCoop</a></p>'
            .'<p><strong>دسترسی‌های سریع:</strong><br>'
            .'<a href="{{profile_url}}">پروفایل من</a> · '
            .'<a href="'.$urls['groups'].'">گروه‌های من</a> · '
            .'<a href="'.$urls['participation'].'">مشارکت‌های من</a> · '
            .'<a href="'.$urls['governance'].'">مکان و حکمرانی من</a></p>'
            .'<p style="font-size:14px;color:#6b7280">برای مدیریت گزارش‌ها و پیام‌های قابل تنظیم می‌توانید از <a href="'.$urls['preferences'].'">تنظیمات ارتباطی</a> استفاده کنید.</p>',
        );

        $memberBody = '<!-- '.self::MARKER.' -->'
            .'<h1>هفته شما در EarthCoop</h1>'
            .'<p>{{display_name}} عزیز، این خلاصه فعالیت و موارد باز شما از {{period_start}} تا {{period_end}} است.</p>'
            .'<table class="ec-metrics"><tr><td>گروه‌های مرتبط</td><td><strong>{{groups_count}}</strong></td></tr>'
            .'<tr><td>انتخابات باز</td><td><strong>{{open_elections_count}}</strong></td></tr>'
            .'<tr><td>نظرسنجی‌های باز</td><td><strong>{{open_polls_count}}</strong></td></tr>'
            .'<tr><td>اعلان‌های خوانده‌نشده</td><td><strong>{{unread_notifications_count}}</strong></td></tr></table>'
            .'<p>اگر انتخابات یا نظرسنجی بازی دارید، بهتر است ابتدا همان‌ها را بررسی کنید.</p>'
            .'<p style="text-align:center"><a class="ec-button" href="'.$urls['participation'].'">مشاهده مشارکت‌های من</a></p>'
            .'<p><a href="'.$urls['groups'].'">گروه‌های من</a> · <a href="'.$urls['home'].'">داشبورد</a> · <a href="'.$urls['preferences'].'">تنظیمات ارتباطی</a></p>';

        $this->publish(
            'reports.member.weekly',
            'هفته شما در EarthCoop — {{period_start}} تا {{period_end}}',
            $memberBody,
        );

        $this->publish(
            'reports.manager.weekly',
            'گزارش هفتگی مدیریت EarthCoop — {{display_name}}',
            '<!-- '.self::MARKER.' -->'
            .'<h1>گزارش هفتگی مدیریت</h1>'
            .'<p>{{display_name}} عزیز، این خلاصه هفته شما از {{period_start}} تا {{period_end}} است.</p>'
            .'<h2>مشارکت شخصی</h2>'
            .'<table class="ec-metrics"><tr><td>گروه‌های شما</td><td><strong>{{groups_count}}</strong></td></tr>'
            .'<tr><td>انتخابات باز</td><td><strong>{{open_elections_count}}</strong></td></tr>'
            .'<tr><td>نظرسنجی‌های باز</td><td><strong>{{open_polls_count}}</strong></td></tr>'
            .'<tr><td>اعلان‌های خوانده‌نشده</td><td><strong>{{unread_notifications_count}}</strong></td></tr></table>'
            .'<h2>مسئولیت مدیریتی</h2>'
            .'<table class="ec-metrics"><tr><td>گروه‌های تحت مدیریت</td><td><strong>{{managed_groups_count}}</strong></td></tr>'
            .'<tr><td>انتخابات باز در گروه‌های تحت مدیریت</td><td><strong>{{open_elections_in_managed_groups_count}}</strong></td></tr>'
            .'<tr><td>نظرسنجی‌های باز در گروه‌های تحت مدیریت</td><td><strong>{{open_polls_in_managed_groups_count}}</strong></td></tr></table>'
            .'<p style="text-align:center"><a class="ec-button" href="'.$urls['participation'].'">بررسی مشارکت‌ها و مسئولیت‌ها</a></p>'
            .'<p><a href="'.$urls['groups'].'">گروه‌های من</a> · <a href="'.$urls['preferences'].'">تنظیمات ارتباطی</a></p>',
        );

        $this->publish(
            'reports.inspector.weekly',
            'گزارش هفتگی بازرسی EarthCoop — {{display_name}}',
            '<!-- '.self::MARKER.' -->'
            .'<h1>گزارش هفتگی بازرسی</h1>'
            .'<p>{{display_name}} عزیز، این خلاصه هفته شما از {{period_start}} تا {{period_end}} است.</p>'
            .'<h2>مشارکت شخصی</h2>'
            .'<table class="ec-metrics"><tr><td>گروه‌های شما</td><td><strong>{{groups_count}}</strong></td></tr>'
            .'<tr><td>انتخابات باز</td><td><strong>{{open_elections_count}}</strong></td></tr>'
            .'<tr><td>نظرسنجی‌های باز</td><td><strong>{{open_polls_count}}</strong></td></tr>'
            .'<tr><td>اعلان‌های خوانده‌نشده</td><td><strong>{{unread_notifications_count}}</strong></td></tr></table>'
            .'<h2>حوزه بازرسی</h2>'
            .'<table class="ec-metrics"><tr><td>گروه‌های تحت بازرسی</td><td><strong>{{inspected_groups_count}}</strong></td></tr>'
            .'<tr><td>انتخابات باز در این گروه‌ها</td><td><strong>{{open_elections_in_inspected_groups_count}}</strong></td></tr>'
            .'<tr><td>نظرسنجی‌های باز در این گروه‌ها</td><td><strong>{{open_polls_in_inspected_groups_count}}</strong></td></tr></table>'
            .'<p style="text-align:center"><a class="ec-button" href="'.$urls['participation'].'">بررسی حوزه‌های مرتبط</a></p>'
            .'<p><a href="'.$urls['groups'].'">گروه‌های من</a> · <a href="'.$urls['preferences'].'">تنظیمات ارتباطی</a></p>',
        );

        $this->publish(
            'auth.email_verification',
            'کد تأیید ایمیل EarthCoop',
            '<!-- '.self::MARKER.' --><h1>تأیید ایمیل</h1>'
            .'<p>برای تأیید این آدرس ایمیل در EarthCoop، کد زیر را وارد کنید:</p>'
            .'<div class="ec-box" style="text-align:center;font-size:28px;letter-spacing:4px"><strong>{{code}}</strong></div>'
            .'<p>این کد ۵ دقیقه اعتبار دارد و نباید در اختیار شخص دیگری قرار گیرد.</p>'
            .'<div class="ec-note">اگر شما این فرایند را آغاز نکرده‌اید، این پیام را نادیده بگیرید.</div>',
        );

        $this->publish(
            'auth.password_reset',
            'کد بازیابی رمز عبور EarthCoop',
            '<!-- '.self::MARKER.' --><h1>بازیابی رمز عبور</h1>'
            .'<p>برای ادامه فرایند بازیابی رمز عبور، کد زیر را وارد کنید:</p>'
            .'<div class="ec-box" style="text-align:center;font-size:28px;letter-spacing:4px"><strong>{{code}}</strong></div>'
            .'<p>این کد ۵ دقیقه اعتبار دارد و نباید در اختیار شخص دیگری قرار گیرد.</p>'
            .'<div class="ec-note">اگر شما درخواست بازیابی رمز عبور نداده‌اید، این پیام را نادیده بگیرید؛ بدون ادامه این فرایند رمز عبور شما تغییر نخواهد کرد.</div>',
        );

        $this->publish(
            'auth.invitation_issued',
            'درخواست شما تأیید شد — کد دعوت EarthCoop',
            '<!-- '.self::MARKER.' --><h1>کد دعوت شما آماده است</h1>'
            .'<p>درخواست شما برای دریافت کد دعوت EarthCoop تأیید شد.</p>'
            .'<div class="ec-box" style="text-align:center"><div style="font-size:26px"><strong>{{code}}</strong></div><div>معتبر تا {{expire_at}}</div></div>'
            .'<p>برای ادامه، ثبت‌نام را آغاز کنید و این کد را در مرحله مربوط وارد کنید.</p>'
            .'<p style="text-align:center"><a class="ec-button" href="'.$urls['register'].'">شروع ثبت‌نام</a></p>',
        );

        $this->publish(
            'membership.member_invitation',
            'دعوت برای پیوستن به EarthCoop',
            '<!-- '.self::MARKER.' --><h1>دعوت برای پیوستن به EarthCoop</h1>'
            .'<p>یکی از اعضای EarthCoop این کد دعوت را برای شما ایجاد کرده است.</p>'
            .'<div class="ec-box" style="text-align:center"><div style="font-size:26px"><strong>{{code}}</strong></div><div>معتبر تا {{expire_at}}</div></div>'
            .'<p>اگر مایل به عضویت هستید، ثبت‌نام را آغاز کرده و کد بالا را وارد کنید.</p>'
            .'<p style="text-align:center"><a class="ec-button" href="'.$urls['register'].'">شروع ثبت‌نام</a></p>'
            .'<p style="font-size:14px;color:#6b7280">اگر تمایلی به پیوستن ندارید، نیازی به انجام کاری نیست و می‌توانید این پیام را نادیده بگیرید.</p>',
        );

        $this->publish(
            'auth.invitation_rejected',
            'نتیجه بررسی درخواست کد دعوت EarthCoop',
            '<!-- '.self::MARKER.' --><h1>نتیجه بررسی درخواست</h1>'
            .'<p>درخواست شما برای دریافت کد دعوت بررسی شد و در این مرحله تأیید نشد.</p>'
            .'{{admin_note_html}}'
            .'<p style="font-size:14px;color:#6b7280">این پیام صرفاً نتیجه بررسی فعلی را اعلام می‌کند و به‌خودی‌خود بیانگر تخلف یا محدودیت دائمی نیست.</p>',
        );

        $this->publish(
            'faq.answer',
            'پاسخ به پرسش شما: {{title}}',
            '<!-- '.self::MARKER.' --><h1>پاسخ به پرسش شما</h1>'
            .'<p><strong>{{title}}</strong></p>'
            .'<div class="ec-box">{{answer}}</div>'
            .'<p>اگر این پاسخ مسئله شما را کامل حل نکرد، می‌توانید از مسیر پشتیبانی EarthCoop پیگیری کنید.</p>',
        );

        $this->publish(
            'contact.reply',
            'پاسخ EarthCoop به پیام شما: {{subject}}',
            '<!-- '.self::MARKER.' --><h1>پاسخ به پیام شما</h1>'
            .'<p>در پاسخ به پیام شما با موضوع <strong>{{subject}}</strong>:</p>'
            .'<div class="ec-box">{{rendered_html}}</div>'
            .'<p style="font-size:14px;color:#6b7280">این پاسخ مربوط به پیام تماس شماست و تا زمانی که صریحاً تیکت ایجاد نشده باشد، به معنی ایجاد تیکت پشتیبانی نیست.</p>',
        );

        $this->publish(
            'najm_bahar.project_assigned',
            'پروژه «{{project_title}}» برای بررسی به شما ارجاع شد',
            '<!-- '.self::MARKER.' --><h1>ارجاع پروژه نجم بهار</h1>'
            .'<p>سلام {{recipient_name}}، پروژه زیر برای بررسی به شما ارجاع شده است:</p>'
            .'<table class="ec-metrics"><tr><td>پروژه</td><td><strong>{{project_title}}</strong></td></tr>'
            .'<tr><td>دسته‌بندی</td><td>{{category}}</td></tr>'
            .'<tr><td>سرمایه مورد نیاز</td><td>{{required_capital}}</td></tr>'
            .'<tr><td>توضیح ارجاع</td><td>{{assignment_note}}</td></tr></table>'
            .'<p style="text-align:center"><a class="ec-button" href="{{project_url}}">بررسی پروژه</a></p>',
        );
    }

    public function down(): void
    {
        // Published versions are audit history. Remove only versions that have
        // never been referenced by a logical communication or recipient.
        $versionIds = DB::table('communication_template_versions')
            ->where('body', 'like', '%'.self::MARKER.'%')
            ->pluck('id');

        foreach ($versionIds as $versionId) {
            $usedByCommunication = DB::table('communications')
                ->where('communication_template_version_id', $versionId)
                ->exists();
            $usedByRecipient = DB::table('communication_recipients')
                ->where('communication_template_version_id', $versionId)
                ->exists();

            if (! $usedByCommunication && ! $usedByRecipient) {
                DB::table('communication_template_versions')
                    ->where('id', $versionId)
                    ->delete();
            }
        }
    }

    private function publish(string $key, string $subject, string $body): void
    {
        $templateId = DB::table('communication_templates')->where('key', $key)->value('id');
        if ($templateId === null) {
            throw new RuntimeException('communication_email_v2_template_missing:'.$key);
        }

        $alreadyPublished = DB::table('communication_template_versions')
            ->where('communication_template_id', $templateId)
            ->where('locale', 'fa')
            ->where('body', 'like', '%'.self::MARKER.'%')
            ->exists();

        if ($alreadyPublished) {
            return;
        }

        $latest = DB::table('communication_template_versions')
            ->where('communication_template_id', $templateId)
            ->where('locale', 'fa')
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->first();

        if ($latest === null || $latest->communication_sender_identity_id === null) {
            throw new RuntimeException('communication_email_v2_latest_version_missing:'.$key);
        }

        DB::table('communication_template_versions')->insert([
            'communication_template_id' => $templateId,
            'version' => ((int) $latest->version) + 1,
            'locale' => 'fa',
            'subject' => $subject,
            'body' => $body,
            'variables_schema' => $latest->variables_schema,
            'communication_sender_identity_id' => $latest->communication_sender_identity_id,
            'published_at' => now(),
            'created_by' => null,
            'approved_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
