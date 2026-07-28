<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Blog\Models\BlogCategory;
use App\Modules\Blog\Models\BlogTag;
use App\Modules\Blog\Models\Post;
use App\Models\User;
use Illuminate\Support\Str;

class BlogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // ایجاد دسته‌بندی‌های نمونه
        $categories = [
            [
                'name' => 'تکنولوژی',
                'slug' => 'technology',
                'description' => 'مقالات مرتبط با تکنولوژی و فناوری اطلاعات',
                'is_active' => true,
                'order' => 1
            ],
            [
                'name' => 'کسب و کار',
                'slug' => 'business',
                'description' => 'مقالات مرتبط با کسب و کار و کارآفرینی',
                'is_active' => true,
                'order' => 2
            ],
            [
                'name' => 'طراحی',
                'slug' => 'design',
                'description' => 'مقالات مرتبط با طراحی و UX/UI',
                'is_active' => true,
                'order' => 3
            ],
            [
                'name' => 'برنامه‌نویسی',
                'slug' => 'programming',
                'description' => 'آموزش برنامه‌نویسی و توسعه نرم‌افزار',
                'is_active' => true,
                'order' => 4
            ],
        ];

        foreach ($categories as $category) {
            BlogCategory::create($category);
        }

        // ایجاد برچسب‌های نمونه
        $tags = [
            'Laravel', 'PHP', 'JavaScript', 'Vue.js', 'React', 
            'Python', 'AI', 'Machine Learning', 'DevOps', 'Docker',
            'Git', 'MySQL', 'MongoDB', 'Redis', 'AWS'
        ];

        foreach ($tags as $tag) {
            BlogTag::create([
                'name' => $tag,
                'slug' => Str::slug($tag)
            ]);
        }

        // دریافت اولین کاربر (یا ایجاد یک کاربر نمونه)
        $user = User::first();
        if (!$user) {
            $user = User::create([
                'name' => 'مدیر سیستم',
                'email' => 'admin@newearthcoop.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now()
            ]);
        }

        // ایجاد مقالات نمونه
        $posts = [
            [
                'title' => 'مقدمه‌ای بر Laravel 10',
                'slug' => 'intro-to-laravel-10',
                'excerpt' => 'در این مقاله با ویژگی‌های جدید Laravel 10 آشنا می‌شویم و نحوه استفاده از آن‌ها را بررسی می‌کنیم.',
                'content' => 'Laravel یکی از محبوب‌ترین فریمورک‌های PHP است که توسط Taylor Otwell توسعه داده شده است. نسخه 10 این فریمورک با ویژگی‌های جدید و بهبودهای قابل توجهی منتشر شده است که شامل بهبود عملکرد، امنیت بیشتر و قابلیت‌های جدید است.',
                'category_id' => 4,
                'user_id' => $user->id,
                'status' => 'published',
                'published_at' => now(),
                'is_featured' => true,
                'allow_comments' => true,
                'views_count' => rand(100, 1000),
                'meta_title' => 'آموزش Laravel 10 | راهنمای کامل',
                'meta_description' => 'آموزش کامل Laravel 10 با مثال‌های کاربردی'
            ],
            [
                'title' => 'بهترین روش‌های طراحی UI/UX در سال 2024',
                'slug' => 'best-uiux-practices-2024',
                'excerpt' => 'روندها و تکنیک‌های جدید در طراحی رابط کاربری و تجربه کاربری که باید بدانید.',
                'content' => 'طراحی رابط کاربری و تجربه کاربری در سال 2024 با تحولات قابل توجهی همراه بوده است. استفاده از رنگ‌های تیره، انیمیشن‌های ظریف، و طراحی مینیمال از جمله روندهای رایج امسال است.',
                'category_id' => 3,
                'user_id' => $user->id,
                'status' => 'published',
                'published_at' => now()->subDays(1),
                'is_featured' => true,
                'allow_comments' => true,
                'views_count' => rand(100, 1000),
            ],
            [
                'title' => 'راهنمای شروع کسب و کار آنلاین',
                'slug' => 'online-business-startup-guide',
                'excerpt' => 'همه چیز درباره راه‌اندازی یک کسب و کار موفق آنلاین از صفر تا صد.',
                'content' => 'راه‌اندازی یک کسب و کار آنلاین در دنیای امروز آسان‌تر از همیشه است. با داشتن یک ایده خوب، برنامه‌ریزی دقیق و استراتژی بازاریابی مناسب می‌توانید کسب و کار موفقی راه‌اندازی کنید.',
                'category_id' => 2,
                'user_id' => $user->id,
                'status' => 'published',
                'published_at' => now()->subDays(2),
                'is_featured' => false,
                'allow_comments' => true,
                'views_count' => rand(100, 1000),
            ],
            [
                'title' => 'هوش مصنوعی و آینده برنامه‌نویسی',
                'slug' => 'ai-and-programming-future',
                'excerpt' => 'تاثیر هوش مصنوعی بر صنعت برنامه‌نویسی و شغل‌های آینده.',
                'content' => 'هوش مصنوعی در حال تغییر چهره برنامه‌نویسی است. ابزارهایی مانند GitHub Copilot و ChatGPT توانایی‌های جدیدی به برنامه‌نویسان داده‌اند اما همچنان نیاز به مهارت‌های انسانی وجود دارد.',
                'category_id' => 1,
                'user_id' => $user->id,
                'status' => 'published',
                'published_at' => now()->subDays(3),
                'is_featured' => true,
                'allow_comments' => true,
                'views_count' => rand(100, 1000),
            ],
            [
                'title' => 'پیش‌نویس: مقاله در حال تکمیل',
                'slug' => 'draft-article-in-progress',
                'excerpt' => 'این مقاله هنوز کامل نشده و در حال تکمیل است.',
                'content' => 'محتوای این مقاله در حال نوشته شدن است...',
                'category_id' => 1,
                'user_id' => $user->id,
                'status' => 'draft',
                'published_at' => null,
                'is_featured' => false,
                'allow_comments' => true,
                'views_count' => 0,
            ],
        ];

        foreach ($posts as $postData) {
            $post = Post::create($postData);

            // اضافه کردن برچسب‌های تصادفی
            $randomTags = BlogTag::inRandomOrder()->limit(rand(2, 4))->pluck('id');
            $post->tags()->attach($randomTags);
        }

        $this->command->info('✅ دیتای نمونه وبلاگ با موفقیت ایجاد شد!');
        $this->command->info('📝 تعداد دسته‌بندی: ' . BlogCategory::count());
        $this->command->info('🏷️  تعداد برچسب: ' . BlogTag::count());
        $this->command->info('📰 تعداد مقالات: ' . Post::count());
    }
}
