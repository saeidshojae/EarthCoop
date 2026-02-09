<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Page;

class CreateFaqPage extends Command
{
    protected $signature = 'pages:create-faq';

    protected $description = 'Create or update the FAQ page with default structured content';

    public function handle(): int
    {
        $page = Page::firstOrNew(['slug' => 'faq']);

        $page->fill([
            'title' => $page->title ?? 'سوالات متداول',
            'template' => 'faq',
            'is_published' => true,
            'show_in_header' => false,
            'meta_title' => $page->meta_title ?? 'سوالات متداول - EarthCoop',
            'meta_description' => $page->meta_description ?? 'پاسخ سریع به رایج‌ترین سوالات درباره EarthCoop، عضویت و پروژه‌ها.',
            'content' => $page->content ?? json_encode([], JSON_UNESCAPED_UNICODE),
        ]);

        $page->title_translations = $page->title_translations ?: [
            'fa' => 'سوالات متداول',
            'en' => 'Frequently Asked Questions',
            'ar' => 'الأسئلة الشائعة',
        ];

        $defaultFaq = json_encode([
            [
                'category' => 'عمومی',
                'category_label' => 'سوالات کلی',
                'icon' => 'fa-globe',
                'question' => 'EarthCoop چیست و چه هدفی را دنبال می‌کند؟',
                'answer' => '<p>EarthCoop یک تعاونی جهانی است که با هدف توسعه اقتصاد مشارکتی، مالکیت جمعی دارایی‌ها و ایجاد تاثیر پایدار اجتماعی فعالیت می‌کند.</p>'
            ],
            [
                'category' => 'عضویت',
                'category_label' => 'راهنمای عضویت',
                'icon' => 'fa-user-plus',
                'question' => 'چگونه می‌توانم عضو EarthCoop شوم؟',
                'answer' => '<p>برای عضویت، کافی است به صفحه ثبت‌نام مراجعه کنید، مراحل تکمیل مشخصات و تایید ایمیل را انجام دهید و سپس فرم‌های اطلاعات تکمیلی را پر کنید.</p>'
            ],
            [
                'category' => 'امور مالی',
                'category_label' => 'سوالات مالی و حساب‌ها',
                'icon' => 'fa-coins',
                'question' => 'نجم بهار چیست و چگونه می‌توانم حساب خود را فعال کنم؟',
                'answer' => '<p>نجم بهار واحد مالی داخلی EarthCoop است. پس از تکمیل پروفایل و تایید توافق‌نامه مالی، حساب شما به‌طور خودکار فعال می‌شود.</p>'
            ],
            [
                'category' => 'پشتیبانی',
                'category_label' => 'پشتیبانی و پیگیری',
                'icon' => 'fa-headset',
                'question' => 'اگر پاسخ سوال خود را پیدا نکردم چه باید بکنم؟',
                'answer' => '<p>از طریق فرم «سوال جدید بپرسید» می‌توانید سوال خود را برای تیم پشتیبانی ارسال کنید. پاسخ در اسرع وقت به شما اطلاع داده می‌شود.</p>'
            ]
        ], JSON_UNESCAPED_UNICODE);

        $page->content_translations = $page->content_translations ?: [
            'fa' => $defaultFaq,
            'en' => json_encode([
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
                    'answer' => '<p>Simply visit the registration page, complete the required steps, verify your email, and provide the additional information requested.</p>'
                ],
                [
                    'category' => 'Finance',
                    'category_label' => 'Financial Questions',
                    'icon' => 'fa-coins',
                    'question' => 'What is the Spring account and how do I activate it?',
                    'answer' => '<p>The Spring account is our internal financial unit. After completing your profile and accepting the financial agreement, your account will be activated automatically.</p>'
                ],
                [
                    'category' => 'Support',
                    'category_label' => 'Support & Follow Up',
                    'icon' => 'fa-headset',
                    'question' => 'What if I cannot find the answer to my question?',
                    'answer' => '<p>Use the "Ask a Question" form to send your query to our support team. We will respond as soon as possible.</p>'
                ]
            ], JSON_UNESCAPED_UNICODE),
            'ar' => json_encode([
                [
                    'category' => 'عام',
                    'category_label' => 'أسئلة عامة',
                    'icon' => 'fa-globe',
                    'question' => 'ما هو EarthCoop وما هدفه؟',
                    'answer' => '<p>EarthCoop تعاونية عالمية تركز على الملكية المشتركة والتمويل المستدام والتأثير المجتمعي.</p>'
                ],
                [
                    'category' => 'العضوية',
                    'category_label' => 'دليل العضوية',
                    'icon' => 'fa-user-plus',
                    'question' => 'كيف يمكنني الانضمام إلى EarthCoop؟',
                    'answer' => '<p>قم بزيارة صفحة التسجيل، أكمل الخطوات المطلوبة، تحقق من بريدك الإلكتروني، ثم قدم المعلومات الإضافية المطلوبة.</p>'
                ],
                [
                    'category' => 'المالية',
                    'category_label' => 'الأسئلة المالية',
                    'icon' => 'fa-coins',
                    'question' => 'ما هو حساب نجم بهار وكيف يتم تفعيله؟',
                    'answer' => '<p>هو الوحدة المالية الداخلية لـ EarthCoop. بعد إكمال ملفك الشخصي وتوقيع الاتفاق المالي، يتم تفعيله تلقائياً.</p>'
                ],
                [
                    'category' => 'الدعم',
                    'category_label' => 'الدعم والمتابعة',
                    'icon' => 'fa-headset',
                    'question' => 'ماذا أفعل إذا لم أجد إجابة سؤالي؟',
                    'answer' => '<p>استخدم نموذج "اطرح سؤالاً" لإرسال استفسارك إلى فريق الدعم. سنرد عليك في أقرب وقت ممكن.</p>'
                ]
            ], JSON_UNESCAPED_UNICODE)
        ];

        $page->meta_title_translations = $page->meta_title_translations ?: [
            'fa' => 'سوالات متداول - EarthCoop',
            'en' => 'Frequently Asked Questions - EarthCoop',
            'ar' => 'الأسئلة الشائعة - EarthCoop',
        ];

        $page->meta_description_translations = $page->meta_description_translations ?: [
            'fa' => 'پاسخ به رایج‌ترین سوالات درباره عضویت، پروژه‌ها و امور مالی EarthCoop.',
            'en' => 'Quick answers to common questions about EarthCoop membership, projects, and finance.',
            'ar' => 'إجابات سريعة على الأسئلة الشائعة حول عضوية EarthCoop والمشاريع والمالية.',
        ];

        $page->save();

        $this->info('FAQ page has been created/updated successfully.');

        return self::SUCCESS;
    }
}



