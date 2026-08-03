<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Page;

class FaqContactPagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // FAQ page - ensure translations exist for fa/en/ar
        $faq = Page::firstOrNew(['slug' => 'faq']);
        $faq->fill([
            'title' => $faq->title ?? 'سوالات متداول',
            'template' => 'faq',
            'is_published' => true,
            'show_in_header' => false,
            'meta_title' => $faq->meta_title ?? 'سوالات متداول - EarthCoop',
            'meta_description' => $faq->meta_description ?? 'پاسخ سریع به رایج‌ترین سوالات درباره EarthCoop، عضویت و پروژه‌ها.',
            'content' => $faq->content ?? json_encode([], JSON_UNESCAPED_UNICODE),
        ]);

        // load existing translations (may be array or JSON)
        $existingTitleTrans = $faq->title_translations ?? [];
        if (is_string($existingTitleTrans)) {
            $existingTitleTrans = json_decode($existingTitleTrans, true) ?: [];
        }

        $existingContentTrans = $faq->content_translations ?? [];
        if (is_string($existingContentTrans)) {
            $existingContentTrans = json_decode($existingContentTrans, true) ?: [];
        }

        $defaultFa = [
            [
                'category' => 'عمومی',
                'category_label' => 'سوالات کلی',
                'icon' => 'fa-globe',
                'question' => 'EarthCoop چیست و چه هدفی را دنبال می‌کند؟',
                'answer' => '<p>EarthCoop یک تعاونی جهانی است که با هدف توسعه اقتصاد مشارکتی و ایجاد تاثیر پایدار فعالیت می‌کند.</p>'
            ],
            [
                'category' => 'عضویت',
                'category_label' => 'راهنمای عضویت',
                'icon' => 'fa-user-plus',
                'question' => 'چگونه می‌توانم عضو EarthCoop شوم؟',
                'answer' => '<p>برای عضویت، به صفحه ثبت‌نام مراجعه کنید و مراحل را دنبال کنید.</p>'
            ]
        ];

        $defaultEn = [
            [
                'category' => 'General',
                'category_label' => 'General Questions',
                'icon' => 'fa-globe',
                'question' => 'What is EarthCoop and what is its mission?',
                'answer' => '<p>EarthCoop is a global cooperative focused on collaborative ownership, sustainable finance, and community impact.</p>'
            ],
            [
                'category' => 'Membership',
                'category_label' => 'Membership Guide',
                'icon' => 'fa-user-plus',
                'question' => 'How can I join EarthCoop?',
                'answer' => '<p>Visit the registration page and follow the steps to create an account.</p>'
            ]
        ];

        $defaultAr = [
            [
                'category' => 'عام',
                'category_label' => 'أسئلة عامة',
                'icon' => 'fa-globe',
                'question' => 'ما هو EarthCoop وما هدفه؟',
                'answer' => '<p>EarthCoop تعاونية عالمية تركز على الملكية المشتركة والتأثير المستدام.</p>'
            ],
            [
                'category' => 'العضوية',
                'category_label' => 'دليل العضوية',
                'icon' => 'fa-user-plus',
                'question' => 'كيف يمكنني الانضمام إلى EarthCoop؟',
                'answer' => '<p>قم بزيارة صفحة التسجيل واتبع الخطوات لإنشاء حساب.</p>'
            ]
        ];

        $existingTitleTrans = array_merge([
            'fa' => 'سوالات متداول',
            'en' => 'Frequently Asked Questions',
            'ar' => 'الأسئلة الشائعة',
        ], $existingTitleTrans);

        $existingContentTrans['fa'] = $existingContentTrans['fa'] ?? json_encode($defaultFa, JSON_UNESCAPED_UNICODE);
        $existingContentTrans['en'] = $existingContentTrans['en'] ?? json_encode($defaultEn, JSON_UNESCAPED_UNICODE);
        $existingContentTrans['ar'] = $existingContentTrans['ar'] ?? json_encode($defaultAr, JSON_UNESCAPED_UNICODE);

        $faq->title_translations = $existingTitleTrans;
        $faq->content_translations = $existingContentTrans;

        $faq->meta_title_translations = array_merge([
            'fa' => 'سوالات متداول - EarthCoop',
            'en' => 'Frequently Asked Questions - EarthCoop',
            'ar' => 'الأسئلة الشائعة - EarthCoop',
        ], is_array($faq->meta_title_translations) ? $faq->meta_title_translations : (is_string($faq->meta_title_translations) ? json_decode($faq->meta_title_translations, true) : []));

        $faq->meta_description_translations = array_merge([
            'fa' => 'پاسخ به رایج‌ترین سوالات درباره عضویت، پروژه‌ها و امور مالی EarthCoop.',
            'en' => 'Quick answers to common questions about EarthCoop membership, projects, and finance.',
            'ar' => 'إجابات سريعة على الأسئلة الشائعة حول عضوية EarthCoop والمشاريع والمالية.',
        ], is_array($faq->meta_description_translations) ? $faq->meta_description_translations : (is_string($faq->meta_description_translations) ? json_decode($faq->meta_description_translations, true) : []));

        $faq->save();

        // Contact page - ensure translations exist
        $contact = Page::firstOrNew(['slug' => 'contact']);
        $contact->fill([
            'title' => $contact->title ?? 'تماس با ما',
            'template' => 'contact',
            'is_published' => true,
            'show_in_header' => false,
            'meta_title' => $contact->meta_title ?? 'تماس با ما - EarthCoop',
            'meta_description' => $contact->meta_description ?? 'با تیم EarthCoop در ارتباط باشید.',
            'content' => $contact->content ?? '<p>تیم ما آماده پاسخ‌گویی است.</p>',
        ]);

        $existingTitleTrans = $contact->title_translations ?? [];
        if (is_string($existingTitleTrans)) {
            $existingTitleTrans = json_decode($existingTitleTrans, true) ?: [];
        }

        $existingContentTrans = $contact->content_translations ?? [];
        if (is_string($existingContentTrans)) {
            $existingContentTrans = json_decode($existingContentTrans, true) ?: [];
        }

        $existingTitleTrans = array_merge([
            'fa' => 'تماس با ما',
            'en' => 'Contact Us',
            'ar' => 'تواصل معنا',
        ], $existingTitleTrans);

        $existingContentTrans['fa'] = $existingContentTrans['fa'] ?? '<p>تیم ما برای پاسخ‌گویی آماده است. از فرم تماس استفاده کنید.</p>';
        $existingContentTrans['en'] = $existingContentTrans['en'] ?? '<p>Our team is ready to help. Use the contact form to reach us.</p>';
        $existingContentTrans['ar'] = $existingContentTrans['ar'] ?? '<p>فريقنا جاهز للمساعدة. استخدم نموذج الاتصال للتواصل معنا.</p>';

        $contact->title_translations = $existingTitleTrans;
        $contact->content_translations = $existingContentTrans;

        $contact->meta_title_translations = array_merge([
            'fa' => 'تماس با ما - EarthCoop',
            'en' => 'Contact Us - EarthCoop',
            'ar' => 'تواصل معنا - EarthCoop',
        ], is_array($contact->meta_title_translations) ? $contact->meta_title_translations : (is_string($contact->meta_title_translations) ? json_decode($contact->meta_title_translations, true) : []));

        $contact->meta_description_translations = array_merge([
            'fa' => 'راه‌های ارتباط با EarthCoop برای ارسال پیام و پیشنهاد.',
            'en' => 'Ways to connect with EarthCoop and share your feedback.',
            'ar' => 'طرق التواصل مع EarthCoop ومشاركة ملاحظاتكم.',
        ], is_array($contact->meta_description_translations) ? $contact->meta_description_translations : (is_string($contact->meta_description_translations) ? json_decode($contact->meta_description_translations, true) : []));

        $contact->save();

        $this->command->info('FAQ and Contact pages seeded/updated.');
    }
}
