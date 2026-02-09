<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Page;

class CreateContactPage extends Command
{
    protected $signature = 'pages:create-contact';

    protected $description = 'Create or update the Contact Us page with the unified template';

    public function handle(): int
    {
        $page = Page::firstOrNew(['slug' => 'contact']);

        $page->fill([
            'title' => $page->title ?? 'تماس با ما',
            'template' => 'contact',
            'is_published' => true,
            'show_in_header' => false,
            'meta_title' => $page->meta_title ?? 'تماس با ما - EarthCoop',
            'meta_description' => $page->meta_description ?? 'با تیم EarthCoop در ارتباط باشید و سوالات یا پیشنهادهای خود را با ما در میان بگذارید.',
            'content' => $page->content ?? '<p>تیم ما برای پاسخ‌گویی به پرسش‌ها و همراهی با شما آماده است. از طریق فرم تماس موجود در صفحه پیام خود را ثبت کنید.</p>',
        ]);

        $page->title_translations = $page->title_translations ?: [
            'fa' => 'تماس با ما',
            'en' => 'Contact Us',
            'ar' => 'تواصل معنا',
        ];

        $page->content_translations = $page->content_translations ?: [
            'fa' => '<p>تیم ما برای پاسخ‌گویی به پرسش‌ها و همراهی با شما آماده است. از طریق فرم موجود در صفحه، با ما در ارتباط باشید.</p>',
            'en' => '<p>Our team is ready to answer your questions and collaborate with you. Reach out through the contact form on this page.</p>',
            'ar' => '<p>فريقنا جاهز للإجابة على أسئلتكم والتعاون معكم. تواصلوا معنا عبر نموذج الاتصال في هذه الصفحة.</p>',
        ];

        $page->meta_title_translations = $page->meta_title_translations ?: [
            'fa' => 'تماس با ما - EarthCoop',
            'en' => 'Contact Us - EarthCoop',
            'ar' => 'تواصل معنا - EarthCoop',
        ];

        $page->meta_description_translations = $page->meta_description_translations ?: [
            'fa' => 'راه‌های ارتباطی با EarthCoop برای ارسال پیام و پیشنهاد.',
            'en' => 'Ways to connect with EarthCoop and share your feedback.',
            'ar' => 'طرق التواصل مع EarthCoop ومشاركة ملاحظاتكم.',
        ];

        $page->save();

        $this->info('Contact page has been created/updated successfully.');

        return self::SUCCESS;
    }
}

