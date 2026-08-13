<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\KbCategory;
use App\Models\KbTag;
use App\Models\KbArticle;
use App\Models\User;
use Illuminate\Support\Str;

class KnowledgeBaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // دریافت یا ایجاد کاربر نویسنده
        $author = User::where('email', 'support@earthcoop.ir')->firstOrFail();

        // ایجاد دسته‌بندی‌ها
        $categories = $this->createCategories();
        
        // ایجاد برچسب‌ها
        $tags = $this->createTags();
        
        // ایجاد مقالات
        $this->createArticles($categories, $tags, $author);
        
        $this->command->info('✅ پایگاه دانش با موفقیت ایجاد شد!');
        $this->command->info('📁 تعداد دسته‌بندی: ' . KbCategory::count());
        $this->command->info('🏷️  تعداد برچسب: ' . KbTag::count());
        $this->command->info('📚 تعداد مقالات: ' . KbArticle::count());
    }

    private function createCategories()
    {
        $categories = [
            [
                'name' => 'شروع کار',
                'slug' => 'getting-started',
                'icon' => 'fas fa-rocket',
                'description' => 'راهنماهای شروع کار و آشنایی با پلتفرم',
                'is_active' => true,
                'sort_order' => 1,
                'children' => [
                    ['name' => 'ثبت‌نام و پروفایل', 'slug' => 'registration-profile', 'icon' => 'fas fa-user-plus', 'description' => 'راهنمای ثبت‌نام و تکمیل پروفایل', 'sort_order' => 1],
                    ['name' => 'آشنایی با پلتفرم', 'slug' => 'platform-overview', 'icon' => 'fas fa-info-circle', 'description' => 'معرفی کلی پلتفرم و ویژگی‌ها', 'sort_order' => 2],
                    ['name' => 'راهنمای اولیه', 'slug' => 'basic-guide', 'icon' => 'fas fa-book-reader', 'description' => 'راهنمای گام‌به‌گام استفاده', 'sort_order' => 3],
                ]
            ],
            [
                'name' => 'گروه‌ها و همکاری',
                'slug' => 'groups-cooperation',
                'icon' => 'fas fa-users',
                'description' => 'راهنماهای مربوط به گروه‌بندی و همکاری',
                'is_active' => true,
                'sort_order' => 2,
                'children' => [
                    ['name' => 'گروه‌بندی هوشمند', 'slug' => 'smart-grouping', 'icon' => 'fas fa-sitemap', 'description' => 'نحوه کار سیستم گروه‌بندی', 'sort_order' => 1],
                    ['name' => 'مدیریت گروه‌ها', 'slug' => 'group-management', 'icon' => 'fas fa-cog', 'description' => 'ایجاد و مدیریت گروه‌ها', 'sort_order' => 2],
                    ['name' => 'گفتگوهای گروهی', 'slug' => 'group-chats', 'icon' => 'fas fa-comments', 'description' => 'استفاده از چت گروهی', 'sort_order' => 3],
                ]
            ],
            [
                'name' => 'حاکمیت و انتخابات',
                'slug' => 'governance-elections',
                'icon' => 'fas fa-vote-yea',
                'description' => 'راهنماهای سیستم انتخاباتی و دموکراتیک',
                'is_active' => true,
                'sort_order' => 3,
                'children' => [
                    ['name' => 'سیستم انتخاباتی', 'slug' => 'election-system', 'icon' => 'fas fa-poll', 'description' => 'نحوه شرکت در انتخابات', 'sort_order' => 1],
                    ['name' => 'نظرسنجی‌ها', 'slug' => 'polls', 'icon' => 'fas fa-chart-pie', 'description' => 'شرکت در نظرسنجی‌ها', 'sort_order' => 2],
                    ['name' => 'مشارکت در تصمیم‌گیری', 'slug' => 'decision-making', 'icon' => 'fas fa-hand-paper', 'description' => 'نحوه مشارکت در تصمیم‌گیری‌ها', 'sort_order' => 3],
                ]
            ],
            [
                'name' => 'اقتصاد بهار',
                'slug' => 'bahar-economy',
                'icon' => 'fas fa-coins',
                'description' => 'راهنماهای سکه‌های بهار و سیستم اقتصادی',
                'is_active' => true,
                'sort_order' => 4,
                'children' => [
                    ['name' => 'سکه‌های بهار', 'slug' => 'bahar-coins', 'icon' => 'fas fa-coins', 'description' => 'آشنایی با سکه‌های بهار', 'sort_order' => 1],
                    ['name' => 'کیف پول دیجیتال', 'slug' => 'digital-wallet', 'icon' => 'fas fa-wallet', 'description' => 'مدیریت کیف پول', 'sort_order' => 2],
                    ['name' => 'سرمایه‌گذاری', 'slug' => 'investment', 'icon' => 'fas fa-chart-line', 'description' => 'راهنمای سرمایه‌گذاری', 'sort_order' => 3],
                ]
            ],
            [
                'name' => 'پروژه‌ها',
                'slug' => 'projects',
                'icon' => 'fas fa-project-diagram',
                'description' => 'راهنماهای پروژه‌های جمعی',
                'is_active' => true,
                'sort_order' => 5,
                'children' => [
                    ['name' => 'پیشنهاد پروژه', 'slug' => 'project-proposal', 'icon' => 'fas fa-lightbulb', 'description' => 'نحوه پیشنهاد پروژه جدید', 'sort_order' => 1],
                    ['name' => 'سرمایه‌گذاری در پروژه‌ها', 'slug' => 'project-investment', 'icon' => 'fas fa-hand-holding-usd', 'description' => 'سرمایه‌گذاری در پروژه‌های موجود', 'sort_order' => 2],
                    ['name' => 'پیگیری پروژه‌ها', 'slug' => 'project-tracking', 'icon' => 'fas fa-tasks', 'description' => 'پیگیری وضعیت پروژه‌ها', 'sort_order' => 3],
                ]
            ],
            [
                'name' => 'امنیت و حریم خصوصی',
                'slug' => 'security-privacy',
                'icon' => 'fas fa-shield-alt',
                'description' => 'راهنماهای امنیت و حریم خصوصی',
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'سوالات متداول',
                'slug' => 'faq',
                'icon' => 'fas fa-question-circle',
                'description' => 'پاسخ به سوالات رایج کاربران',
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'پشتیبانی',
                'slug' => 'support',
                'icon' => 'fas fa-headset',
                'description' => 'راهنماهای استفاده از سیستم پشتیبانی',
                'is_active' => true,
                'sort_order' => 8,
            ],
        ];

        $createdCategories = [];
        foreach ($categories as $catData) {
            $children = $catData['children'] ?? [];
            unset($catData['children']);
            
            $category = KbCategory::create($catData);
            $createdCategories[$catData['slug']] = $category;
            
            foreach ($children as $childData) {
                $childData['parent_id'] = $category->id;
                $child = KbCategory::create($childData);
                $createdCategories[$childData['slug']] = $child;
            }
        }

        return $createdCategories;
    }

    private function createTags()
    {
        $tags = [
            ['name' => 'شروع کار', 'slug' => 'getting-started', 'color' => '#10b981'],
            ['name' => 'گروه‌بندی', 'slug' => 'grouping', 'color' => '#3b82f6'],
            ['name' => 'انتخابات', 'slug' => 'elections', 'color' => '#8b5cf6'],
            ['name' => 'سکه بهار', 'slug' => 'bahar-coin', 'color' => '#f59e0b'],
            ['name' => 'پروژه', 'slug' => 'project', 'color' => '#ef4444'],
            ['name' => 'امنیت', 'slug' => 'security', 'color' => '#6366f1'],
            ['name' => 'پشتیبانی', 'slug' => 'support', 'color' => '#06b6d4'],
            ['name' => 'کیف پول', 'slug' => 'wallet', 'color' => '#14b8a6'],
            ['name' => 'نظرسنجی', 'slug' => 'poll', 'color' => '#a855f7'],
            ['name' => 'دعوت', 'slug' => 'invitation', 'color' => '#ec4899'],
        ];

        $createdTags = [];
        foreach ($tags as $tagData) {
            $tag = KbTag::firstOrCreate(
                ['slug' => $tagData['slug']],
                array_merge($tagData, ['is_active' => true])
            );
            $createdTags[$tagData['slug']] = $tag;
        }

        return $createdTags;
    }

    private function createArticles($categories, $tags, $author)
    {
        $articles = $this->getArticlesData($categories, $tags);
        
        foreach ($articles as $articleData) {
            $articleTags = $articleData['tags'] ?? [];
            unset($articleData['tags']);
            
            $article = KbArticle::create(array_merge($articleData, [
                'author_id' => $author->id,
                'last_editor_id' => $author->id,
                'status' => 'published',
                'published_at' => now()->subDays(rand(1, 30)),
                'view_count' => rand(50, 500),
            ]));
            
            // اتصال برچسب‌ها
            $tagIds = [];
            foreach ($articleTags as $tagSlug) {
                if (isset($tags[$tagSlug])) {
                    $tagIds[] = $tags[$tagSlug]->id;
                }
            }
            $article->tags()->sync($tagIds);
        }
    }

    private function getArticlesData($categories, $tags)
    {
        return [
            // مقالات شروع کار
            [
                'title' => 'راهنمای کامل ثبت‌نام در EarthCoop',
                'slug' => 'complete-registration-guide',
                'excerpt' => 'راهنمای گام‌به‌گام ثبت‌نام در پلتفرم EarthCoop و تکمیل اطلاعات پروفایل',
                'content' => $this->getRegistrationGuideContent(),
                'category_id' => $categories['registration-profile']->id,
                'is_featured' => true,
                'tags' => ['getting-started'],
            ],
            [
                'title' => 'آشنایی با پلتفرم EarthCoop',
                'slug' => 'platform-introduction',
                'excerpt' => 'معرفی کامل پلتفرم EarthCoop، اهداف، ویژگی‌ها و نحوه کار آن',
                'content' => $this->getPlatformIntroductionContent(),
                'category_id' => $categories['platform-overview']->id,
                'is_featured' => true,
                'tags' => ['getting-started'],
            ],
            [
                'title' => 'راهنمای اولیه استفاده از پلتفرم',
                'slug' => 'basic-platform-guide',
                'excerpt' => 'راهنمای گام‌به‌گام استفاده از ویژگی‌های اصلی پلتفرم برای کاربران جدید',
                'content' => $this->getBasicGuideContent(),
                'category_id' => $categories['basic-guide']->id,
                'is_featured' => true,
                'tags' => ['getting-started'],
            ],
            
            // مقالات گروه‌بندی
            [
                'title' => 'گروه‌بندی هوشمند: چگونه کار می‌کند؟',
                'slug' => 'how-smart-grouping-works',
                'excerpt' => 'توضیح کامل سیستم گروه‌بندی هوشمند و نحوه گروه‌بندی خودکار کاربران',
                'content' => $this->getSmartGroupingContent(),
                'category_id' => $categories['smart-grouping']->id,
                'is_featured' => true,
                'tags' => ['grouping'],
            ],
            [
                'title' => 'پیوستن به گروه‌ها و مدیریت آن‌ها',
                'slug' => 'join-manage-groups',
                'excerpt' => 'راهنمای پیوستن به گروه‌ها، مشاهده گروه‌های خود و مدیریت آن‌ها',
                'content' => $this->getGroupManagementContent(),
                'category_id' => $categories['group-management']->id,
                'is_featured' => false,
                'tags' => ['grouping'],
            ],
            [
                'title' => 'استفاده از گفتگوهای گروهی',
                'slug' => 'using-group-chats',
                'excerpt' => 'راهنمای کامل استفاده از چت گروهی، ارسال پیام و اشتراک‌گذاری فایل',
                'content' => $this->getGroupChatContent(),
                'category_id' => $categories['group-chats']->id,
                'is_featured' => false,
                'tags' => ['grouping'],
            ],
            
            // مقالات انتخابات
            [
                'title' => 'راهنمای شرکت در انتخابات',
                'slug' => 'participate-in-elections',
                'excerpt' => 'راهنمای کامل نحوه شرکت در انتخابات، رای دادن و تغییر رای',
                'content' => $this->getElectionGuideContent(),
                'category_id' => $categories['election-system']->id,
                'is_featured' => true,
                'tags' => ['elections'],
            ],
            [
                'title' => 'شرکت در نظرسنجی‌ها',
                'slug' => 'participate-in-polls',
                'excerpt' => 'راهنمای شرکت در نظرسنجی‌ها و اعلام نظر در تصمیم‌گیری‌های جمعی',
                'content' => $this->getPollGuideContent(),
                'category_id' => $categories['polls']->id,
                'is_featured' => false,
                'tags' => ['poll'],
            ],
            [
                'title' => 'تفویض رای به کارشناسان',
                'slug' => 'delegate-vote',
                'excerpt' => 'نحوه تفویض رای خود به کارشناسان موضوعی برای تصمیم‌گیری‌های تخصصی',
                'content' => $this->getVoteDelegationContent(),
                'category_id' => $categories['decision-making']->id,
                'is_featured' => false,
                'tags' => ['elections'],
            ],
            
            // مقالات اقتصاد بهار
            [
                'title' => 'آشنایی با سکه‌های بهار',
                'slug' => 'bahar-coins-introduction',
                'excerpt' => 'معرفی کامل سکه‌های بهار، نحوه دریافت و استفاده از آن‌ها',
                'content' => $this->getBaharCoinsContent(),
                'category_id' => $categories['bahar-coins']->id,
                'is_featured' => true,
                'tags' => ['bahar-coin', 'wallet'],
            ],
            [
                'title' => 'مدیریت کیف پول دیجیتال',
                'slug' => 'digital-wallet-management',
                'excerpt' => 'راهنمای کامل استفاده از کیف پول دیجیتال، مشاهده موجودی و تراکنش‌ها',
                'content' => $this->getWalletManagementContent(),
                'category_id' => $categories['digital-wallet']->id,
                'is_featured' => true,
                'tags' => ['wallet', 'bahar-coin'],
            ],
            [
                'title' => 'راهنمای سرمایه‌گذاری در پروژه‌ها',
                'slug' => 'investment-guide',
                'excerpt' => 'راهنمای کامل سرمایه‌گذاری با سکه‌های بهار در پروژه‌های مختلف',
                'content' => $this->getInvestmentGuideContent(),
                'category_id' => $categories['investment']->id,
                'is_featured' => true,
                'tags' => ['investment', 'project', 'bahar-coin'],
            ],
            
            // مقالات پروژه‌ها
            [
                'title' => 'نحوه پیشنهاد پروژه جدید',
                'slug' => 'propose-new-project',
                'excerpt' => 'راهنمای پیشنهاد پروژه جدید و مراحل بررسی و تایید آن',
                'content' => $this->getProjectProposalContent(),
                'category_id' => $categories['project-proposal']->id,
                'is_featured' => false,
                'tags' => ['project'],
            ],
            [
                'title' => 'سرمایه‌گذاری در پروژه‌های موجود',
                'slug' => 'invest-in-projects',
                'excerpt' => 'راهنمای انتخاب و سرمایه‌گذاری در پروژه‌های موجود',
                'content' => $this->getProjectInvestmentContent(),
                'category_id' => $categories['project-investment']->id,
                'is_featured' => false,
                'tags' => ['project', 'investment'],
            ],
            [
                'title' => 'پیگیری وضعیت پروژه‌ها',
                'slug' => 'track-project-status',
                'excerpt' => 'نحوه پیگیری وضعیت پروژه‌هایی که در آن‌ها سرمایه‌گذاری کرده‌اید',
                'content' => $this->getProjectTrackingContent(),
                'category_id' => $categories['project-tracking']->id,
                'is_featured' => false,
                'tags' => ['project'],
            ],
            
            // مقالات امنیت
            [
                'title' => 'حفظ امنیت حساب کاربری',
                'slug' => 'account-security',
                'excerpt' => 'راهنمای حفظ امنیت حساب کاربری و محافظت از اطلاعات شخصی',
                'content' => $this->getSecurityContent(),
                'category_id' => $categories['security-privacy']->id,
                'is_featured' => true,
                'tags' => ['security'],
            ],
            
            // مقالات FAQ
            [
                'title' => 'سوالات متداول (FAQ)',
                'slug' => 'frequently-asked-questions',
                'excerpt' => 'پاسخ به سوالات رایج کاربران درباره پلتفرم و استفاده از آن',
                'content' => $this->getFAQContent(),
                'category_id' => $categories['faq']->id,
                'is_featured' => true,
                'tags' => [],
            ],
            
            // مقالات پشتیبانی
            [
                'title' => 'نحوه ایجاد تیکت پشتیبانی',
                'slug' => 'create-support-ticket',
                'excerpt' => 'راهنمای ایجاد تیکت پشتیبانی و دریافت کمک از تیم پشتیبانی',
                'content' => $this->getSupportTicketContent(),
                'category_id' => $categories['support']->id,
                'is_featured' => false,
                'tags' => ['support'],
            ],
            [
                'title' => 'استفاده از چت پشتیبانی',
                'slug' => 'use-support-chat',
                'excerpt' => 'راهنمای استفاده از چت زنده پشتیبانی برای ارتباط مستقیم',
                'content' => $this->getSupportChatContent(),
                'category_id' => $categories['support']->id,
                'is_featured' => false,
                'tags' => ['support'],
            ],
            [
                'title' => 'دعوت از دوستان و کسب سکه بهار',
                'slug' => 'invite-friends-earn-coins',
                'excerpt' => 'راهنمای دعوت دوستان به پلتفرم و کسب سکه‌های بهار از طریق ارجاع',
                'content' => $this->getInvitationContent(),
                'category_id' => $categories['registration-profile']->id,
                'is_featured' => false,
                'tags' => ['invitation', 'bahar-coin'],
            ],
        ];
    }

    // محتوای مقالات
    private function getRegistrationGuideContent()
    {
        return '<h2>راهنمای ثبت‌نام در EarthCoop</h2>
        <p>ثبت‌نام در EarthCoop یک فرآیند سه مرحله‌ای ساده است که به شما امکان می‌دهد به عضویت پلتفرم درآیید.</p>
        
        <h3>مرحله 1: اطلاعات پایه</h3>
        <p>در این مرحله باید اطلاعات زیر را وارد کنید:</p>
        <ul>
            <li><strong>نام و نام خانوادگی:</strong> نام کامل خود را وارد کنید</li>
            <li><strong>ایمیل:</strong> آدرس ایمیل معتبر خود را وارد کنید</li>
            <li><strong>رمز عبور:</strong> یک رمز عبور قوی انتخاب کنید</li>
            <li><strong>کد دعوت:</strong> اگر کد دعوت دارید، آن را وارد کنید</li>
        </ul>
        
        <h3>مرحله 2: مشخصات فردی</h3>
        <p>اطلاعات زیر برای گروه‌بندی هوشمند شما استفاده می‌شود:</p>
        <ul>
            <li><strong>سن:</strong> سن خود را وارد کنید</li>
            <li><strong>جنسیت:</strong> جنسیت خود را انتخاب کنید</li>
            <li><strong>شغل و صنف:</strong> شغل یا صنف خود را انتخاب کنید</li>
            <li><strong>تخصص و تجربه:</strong> تخصص‌ها و تجربیات خود را وارد کنید</li>
        </ul>
        
        <h3>مرحله 3: موقعیت جغرافیایی</h3>
        <p>موقعیت جغرافیایی خود را از سطح محله تا کشور مشخص کنید:</p>
        <ul>
            <li><strong>کشور:</strong> کشور محل سکونت</li>
            <li><strong>استان:</strong> استان محل سکونت</li>
            <li><strong>شهر:</strong> شهر محل سکونت</li>
            <li><strong>محله:</strong> محله محل سکونت (اختیاری)</li>
        </ul>
        
        <h3>نکات مهم</h3>
        <ul>
            <li>اطلاعات خود را با دقت وارد کنید تا گروه‌بندی بهتری برای شما انجام شود</li>
            <li>می‌توانید بعداً اطلاعات پروفایل خود را ویرایش کنید</li>
            <li>پس از ثبت‌نام، 10,000 سکه بهار به حساب شما واریز می‌شود</li>
        </ul>';
    }

    private function getPlatformIntroductionContent()
    {
        return '<h2>معرفی پلتفرم EarthCoop</h2>
        <p>EarthCoop یک پلتفرم تعاونی نوآورانه است که با هدف تسهیل همکاری در سطح محلی تا ملی و جهانی طراحی شده است.</p>
        
        <h3>اهداف پلتفرم</h3>
        <ul>
            <li>تسهیل همکاری و تعاون بین کاربران</li>
            <li>ایجاد بستری برای حل چالش‌های مشترک</li>
            <li>توسعه حاکمیت دموکراتیک</li>
            <li>ایجاد اقتصاد پایدار و عادلانه</li>
        </ul>
        
        <h3>ویژگی‌های کلیدی</h3>
        <ul>
            <li><strong>گروه‌بندی هوشمند:</strong> گروه‌بندی خودکار بر اساس مشخصات فردی و مکانی</li>
            <li><strong>حاکمیت دموکراتیک:</strong> سیستم انتخاباتی پویا و همیشگی</li>
            <li><strong>اقتصاد بهار:</strong> سیستم اقتصادی مبتنی بر سکه‌های بهار</li>
            <li><strong>پروژه‌های جمعی:</strong> امکان سرمایه‌گذاری در پروژه‌های مختلف</li>
            <li><strong>گفتگوهای گروهی:</strong> ارتباط و همکاری در گروه‌ها</li>
        </ul>
        
        <h3>چگونه کار می‌کند؟</h3>
        <p>پس از ثبت‌نام، شما به طور خودکار به گروه‌های مرتبط اضافه می‌شوید و می‌توانید در پروژه‌ها، انتخابات و نظرسنجی‌ها مشارکت کنید.</p>';
    }

    private function getBasicGuideContent()
    {
        return '<h2>راهنمای اولیه استفاده از پلتفرم</h2>
        <p>این راهنما به شما کمک می‌کند که به سرعت با ویژگی‌های اصلی پلتفرم آشنا شوید.</p>
        
        <h3>1. داشبورد کاربری</h3>
        <p>پس از ورود، به داشبورد کاربری خود دسترسی دارید که شامل:</p>
        <ul>
            <li>اعلان‌ها</li>
            <li>گروه‌های من</li>
            <li>مشارکت‌های من</li>
            <li>انتخابات جاری</li>
            <li>نظرسنجی‌های جاری</li>
        </ul>
        
        <h3>2. پیوستن به گروه‌ها</h3>
        <p>شما به طور خودکار به گروه‌های مرتبط اضافه می‌شوید. می‌توانید:</p>
        <ul>
            <li>گروه‌های خود را مشاهده کنید</li>
            <li>به گفتگوهای گروهی بپیوندید</li>
            <li>در پروژه‌های گروهی مشارکت کنید</li>
        </ul>
        
        <h3>3. شرکت در انتخابات</h3>
        <p>می‌توانید در انتخابات مختلف شرکت کنید و رای خود را به نامزدها بدهید.</p>
        
        <h3>4. سرمایه‌گذاری</h3>
        <p>با سکه‌های بهار خود می‌توانید در پروژه‌های مختلف سرمایه‌گذاری کنید.</p>';
    }

    private function getSmartGroupingContent()
    {
        return '<h2>گروه‌بندی هوشمند چگونه کار می‌کند؟</h2>
        <p>سیستم گروه‌بندی هوشمند EarthCoop از الگوریتم‌های پیشرفته برای شناسایی و گروه‌بندی کاربران استفاده می‌کند.</p>
        
        <h3>معیارهای گروه‌بندی</h3>
        <ul>
            <li><strong>مشخصات فردی:</strong> سن، جنسیت، شغل، تخصص و تجربه</li>
            <li><strong>موقعیت جغرافیایی:</strong> از سطح محله تا سطح جهانی</li>
            <li><strong>علایق و اهداف:</strong> پروژه‌ها و ابتکاراتی که به آن‌ها علاقه دارید</li>
        </ul>
        
        <h3>مزایای گروه‌بندی هوشمند</h3>
        <ul>
            <li>ارتباط سریع‌تر با افراد مناسب</li>
            <li>پیدا کردن پروژه‌های مرتبط</li>
            <li>بهره‌مندی از تجربیات دیگران</li>
            <li>مشارکت موثر در تصمیم‌گیری‌های جمعی</li>
        </ul>
        
        <h3>نحوه به‌روزرسانی گروه‌بندی</h3>
        <p>با به‌روزرسانی اطلاعات پروفایل خود، گروه‌بندی شما نیز به‌روزرسانی می‌شود.</p>';
    }

    private function getGroupManagementContent()
    {
        return '<h2>پیوستن به گروه‌ها و مدیریت آن‌ها</h2>
        <p>شما به طور خودکار به گروه‌های مرتبط اضافه می‌شوید، اما می‌توانید گروه‌های خود را مدیریت کنید.</p>
        
        <h3>مشاهده گروه‌های من</h3>
        <p>برای مشاهده گروه‌های خود:</p>
        <ol>
            <li>به بخش "گروه‌های من" در سایدبار مراجعه کنید</li>
            <li>لیست تمام گروه‌هایی که در آن‌ها عضو هستید را مشاهده کنید</li>
            <li>برای مشاهده جزئیات هر گروه، روی آن کلیک کنید</li>
        </ol>
        
        <h3>انواع گروه‌ها</h3>
        <ul>
            <li><strong>گروه‌های عمومی:</strong> برای همه کاربران قابل دسترسی</li>
            <li><strong>گروه‌های تخصصی:</strong> بر اساس تخصص و تجربه</li>
            <li><strong>گروه‌های انحصاری:</strong> برای اعضای خاص</li>
        </ul>
        
        <h3>مدیریت گروه</h3>
        <p>اگر مدیر گروه هستید، می‌توانید:</p>
        <ul>
            <li>اعضا را مدیریت کنید</li>
            <li>پروژه‌ها را ایجاد کنید</li>
            <li>نظرسنجی و انتخابات برگزار کنید</li>
        </ul>';
    }

    private function getGroupChatContent()
    {
        return '<h2>استفاده از گفتگوهای گروهی</h2>
        <p>گفتگوهای گروهی یکی از ابزارهای اصلی ارتباط در EarthCoop هستند.</p>
        
        <h3>ویژگی‌های گفتگوی گروهی</h3>
        <ul>
            <li>ارسال پیام متنی</li>
            <li>اشتراک‌گذاری فایل و تصویر</li>
            <li>جستجو در تاریخچه گفتگو</li>
            <li>اعلان‌های پیام جدید</li>
        </ul>
        
        <h3>نحوه استفاده</h3>
        <ol>
            <li>به گروه مورد نظر خود بروید</li>
            <li>به بخش گفتگو مراجعه کنید</li>
            <li>پیام خود را در کادر متنی وارد کنید</li>
            <li>برای ارسال، روی دکمه ارسال کلیک کنید</li>
        </ol>
        
        <h3>آداب گفتگو</h3>
        <ul>
            <li>با احترام با دیگران صحبت کنید</li>
            <li>از زبان مناسب استفاده کنید</li>
            <li>در بحث‌ها مشارکت فعال داشته باشید</li>
        </ul>';
    }

    private function getElectionGuideContent()
    {
        return '<h2>راهنمای شرکت در انتخابات</h2>
        <p>در EarthCoop، شما می‌توانید در انتخابات مختلف شرکت کنید و رای خود را به نامزدها بدهید.</p>
        
        <h3>انواع انتخابات</h3>
        <ul>
            <li><strong>انتخابات گروهی:</strong> برای انتخاب مدیر گروه</li>
            <li><strong>انتخابات پروژه:</strong> برای انتخاب مدیر پروژه</li>
            <li><strong>انتخابات عمومی:</strong> برای تصمیم‌گیری‌های کلان</li>
        </ul>
        
        <h3>نحوه شرکت در انتخابات</h3>
        <ol>
            <li>به بخش "انتخابات جاری" مراجعه کنید</li>
            <li>انتخابات مورد نظر را انتخاب کنید</li>
            <li>نامزدها و برنامه‌های آن‌ها را بررسی کنید</li>
            <li>رای خود را به نامزد مورد نظر بدهید</li>
        </ol>
        
        <h3>ویژگی‌های منحصر به فرد</h3>
        <ul>
            <li><strong>تغییر رای:</strong> می‌توانید در هر زمان رای خود را تغییر دهید</li>
            <li><strong>پس‌گیری رای:</strong> می‌توانید رای خود را پس بگیرید</li>
            <li><strong>تفویض رای:</strong> می‌توانید رای خود را به کارشناسان تفویض کنید</li>
        </ul>
        
        <h3>شفافیت</h3>
        <p>تمام فرآیندهای انتخاباتی شفاف و قابل ردیابی هستند.</p>';
    }

    private function getPollGuideContent()
    {
        return '<h2>شرکت در نظرسنجی‌ها</h2>
        <p>نظرسنجی‌ها ابزاری برای جمع‌آوری نظرات اعضا در مورد موضوعات مختلف هستند.</p>
        
        <h3>انواع نظرسنجی‌ها</h3>
        <ul>
            <li>نظرسنجی‌های گروهی</li>
            <li>نظرسنجی‌های پروژه</li>
            <li>نظرسنجی‌های عمومی</li>
        </ul>
        
        <h3>نحوه شرکت</h3>
        <ol>
            <li>به بخش "نظرسنجی‌های جاری" مراجعه کنید</li>
            <li>نظرسنجی مورد نظر را انتخاب کنید</li>
            <li>گزینه مورد نظر خود را انتخاب کنید</li>
            <li>در صورت نیاز، نظر خود را به صورت متنی اضافه کنید</li>
        </ol>
        
        <h3>تأثیر مشارکت شما</h3>
        <p>هر رای و نظری که می‌دهید، در شکل‌گیری آینده پلتفرم تأثیر دارد.</p>';
    }

    private function getVoteDelegationContent()
    {
        return '<h2>تفویض رای به کارشناسان</h2>
        <p>شما می‌توانید رای خود را به کارشناسان موضوعی تفویض کنید تا آن‌ها به نمایندگی از شما تصمیم بگیرند.</p>
        
        <h3>چرا تفویض رای؟</h3>
        <ul>
            <li>برای تصمیم‌گیری‌های تخصصی که نیاز به دانش خاص دارند</li>
            <li>وقتی وقت کافی برای بررسی موضوع ندارید</li>
            <li>برای اعتماد به کارشناسان در زمینه‌های خاص</li>
        </ul>
        
        <h3>نحوه تفویض</h3>
        <ol>
            <li>در صفحه انتخابات، گزینه "تفویض رای" را انتخاب کنید</li>
            <li>کارشناس مورد نظر خود را انتخاب کنید</li>
            <li>تایید کنید</li>
        </ol>
        
        <h3>مدیریت تفویض</h3>
        <p>می‌توانید در هر زمان تفویض خود را لغو کنید و خودتان رای دهید.</p>';
    }

    private function getBaharCoinsContent()
    {
        return '<h2>آشنایی با سکه‌های بهار</h2>
        <p>سکه‌های بهار، ارز دیجیتال داخلی منحصر به فرد EarthCoop است.</p>
        
        <h3>سرمایه اولیه</h3>
        <p>پس از ثبت‌نام، معادل 1 کیلوگرم طلا در قالب 10,000 سکه بهار به حساب شما واریز می‌شود.</p>
        
        <h3>استفاده از سکه‌های بهار</h3>
        <ul>
            <li>پرداخت حق عضویت سالانه</li>
            <li>سرمایه‌گذاری در پروژه‌ها</li>
            <li>خرید خدمات انحصاری</li>
            <li>خرید محصولات پایدار</li>
            <li>دسترسی به منابع آموزشی</li>
        </ul>
        
        <h3>کسب سکه بهار</h3>
        <ul>
            <li>دعوت دوستان به پلتفرم</li>
            <li>سرمایه‌گذاری در پروژه‌ها و دریافت سود</li>
            <li>مشارکت در پروژه‌ها</li>
        </ul>
        
        <h3>توسعه آینده</h3>
        <p>در مراحل بعدی، امکان ایجاد شرکت خصوصی و فروشگاه نیز وجود خواهد داشت.</p>';
    }

    private function getWalletManagementContent()
    {
        return '<h2>مدیریت کیف پول دیجیتال</h2>
        <p>کیف پول دیجیتال EarthCoop به شما امکان می‌دهد که سکه‌های بهار خود را مدیریت کنید.</p>
        
        <h3>دسترسی به کیف پول</h3>
        <p>برای دسترسی به کیف پول:</p>
        <ol>
            <li>به بخش "حساب مالی نجم بهار" در سایدبار مراجعه کنید</li>
            <li>موجودی خود را مشاهده کنید</li>
            <li>تاریخچه تراکنش‌ها را بررسی کنید</li>
        </ol>
        
        <h3>انواع تراکنش‌ها</h3>
        <ul>
            <li><strong>واریز اولیه:</strong> 10,000 سکه بهار پس از ثبت‌نام</li>
            <li><strong>کسب از دعوت:</strong> سکه‌های کسب شده از دعوت دوستان</li>
            <li><strong>سود سرمایه‌گذاری:</strong> سود حاصل از سرمایه‌گذاری</li>
            <li><strong>خرید و فروش:</strong> تراکنش‌های خرید و فروش</li>
        </ul>
        
        <h3>گزارش‌گیری</h3>
        <p>می‌توانید گزارش‌های مالی مختلفی از کیف پول خود دریافت کنید.</p>
        
        <h3>امنیت</h3>
        <p>کیف پول از سیستم‌های امنیتی پیشرفته استفاده می‌کند.</p>';
    }

    private function getInvestmentGuideContent()
    {
        return '<h2>راهنمای سرمایه‌گذاری در پروژه‌ها</h2>
        <p>با سکه‌های بهار خود می‌توانید در پروژه‌های مختلف سرمایه‌گذاری کنید.</p>
        
        <h3>انواع پروژه‌ها</h3>
        <ul>
            <li><strong>پروژه‌های محلی:</strong> بهبود محله و شهر</li>
            <li><strong>پروژه‌های انرژی تجدیدپذیر:</strong> سرمایه‌گذاری در انرژی پاک</li>
            <li><strong>ابتکارات اجتماعی:</strong> پروژه‌های اجتماعی</li>
        </ul>
        
        <h3>نحوه سرمایه‌گذاری</h3>
        <ol>
            <li>پروژه‌های موجود را بررسی کنید</li>
            <li>پروژه‌ای که با علایق شما همسو است را انتخاب کنید</li>
            <li>مقدار سرمایه‌گذاری خود را تعیین کنید</li>
            <li>تایید کنید</li>
        </ol>
        
        <h3>مزایای سرمایه‌گذاری</h3>
        <ul>
            <li>بازده مالی</li>
            <li>تأثیر مثبت بر جامعه</li>
            <li>یادگیری و تجربه</li>
        </ul>
        
        <h3>ریسک‌ها</h3>
        <p>همیشه قبل از سرمایه‌گذاری، اطلاعات پروژه را به دقت مطالعه کنید.</p>';
    }

    private function getProjectProposalContent()
    {
        return '<h2>نحوه پیشنهاد پروژه جدید</h2>
        <p>اگر ایده‌ای برای یک پروژه جدید دارید، می‌توانید آن را پیشنهاد دهید.</p>
        
        <h3>مراحل پیشنهاد پروژه</h3>
        <ol>
            <li>ایده خود را به صورت کامل بنویسید</li>
            <li>اهداف پروژه را مشخص کنید</li>
            <li>بودجه مورد نیاز را تخمین بزنید</li>
            <li>پروژه را برای بررسی ارسال کنید</li>
        </ol>
        
        <h3>معیارهای بررسی</h3>
        <ul>
            <li>قابلیت اجرا</li>
            <li>تأثیر مثبت</li>
            <li>همسویی با اهداف پلتفرم</li>
        </ul>
        
        <h3>پس از تایید</h3>
        <p>پس از تایید پروژه، می‌توانید سرمایه‌گذاری را شروع کنید.</p>';
    }

    private function getProjectInvestmentContent()
    {
        return '<h2>سرمایه‌گذاری در پروژه‌های موجود</h2>
        <p>می‌توانید در پروژه‌های موجود سرمایه‌گذاری کنید.</p>
        
        <h3>انتخاب پروژه</h3>
        <p>قبل از سرمایه‌گذاری:</p>
        <ul>
            <li>اطلاعات پروژه را مطالعه کنید</li>
            <li>اهداف و برنامه‌های پروژه را بررسی کنید</li>
            <li>ریسک‌ها را ارزیابی کنید</li>
        </ul>
        
        <h3>مقدار سرمایه‌گذاری</h3>
        <p>مقدار سرمایه‌گذاری خود را بر اساس:</p>
        <ul>
            <li>موجودی سکه بهار</li>
            <li>ریسک پروژه</li>
            <li>علاقه شما به پروژه</li>
        </ul>
        <p>تعیین کنید.</p>';
    }

    private function getProjectTrackingContent()
    {
        return '<h2>پیگیری وضعیت پروژه‌ها</h2>
        <p>می‌توانید وضعیت پروژه‌هایی که در آن‌ها سرمایه‌گذاری کرده‌اید را پیگیری کنید.</p>
        
        <h3>اطلاعات قابل مشاهده</h3>
        <ul>
            <li>وضعیت فعلی پروژه</li>
            <li>پیشرفت پروژه</li>
            <li>گزارش‌های مالی</li>
            <li>به‌روزرسانی‌ها</li>
        </ul>
        
        <h3>نحوه پیگیری</h3>
        <ol>
            <li>به بخش "مشارکت‌های من" مراجعه کنید</li>
            <li>پروژه مورد نظر را انتخاب کنید</li>
            <li>اطلاعات و به‌روزرسانی‌ها را مشاهده کنید</li>
        </ol>';
    }

    private function getSecurityContent()
    {
        return '<h2>حفظ امنیت حساب کاربری</h2>
        <p>رعایت نکات امنیتی برای محافظت از حساب کاربری شما بسیار مهم است.</p>
        
        <h3>نکات امنیتی</h3>
        <ul>
            <li>از رمز عبور قوی استفاده کنید</li>
            <li>رمز عبور خود را به کسی ندهید</li>
            <li>به طور منظم رمز عبور خود را تغییر دهید</li>
            <li>از ایمیل معتبر استفاده کنید</li>
        </ul>
        
        <h3>تنظیمات حریم خصوصی</h3>
        <p>می‌توانید تنظیمات حریم خصوصی خود را در بخش ویرایش پروفایل تغییر دهید.</p>
        
        <h3>در صورت مشکوک شدن</h3>
        <p>اگر فکر می‌کنید حساب شما در خطر است، فوراً با پشتیبانی تماس بگیرید.</p>';
    }

    private function getFAQContent()
    {
        return '<h2>سوالات متداول (FAQ)</h2>
        
        <h3>سوال 1: چگونه می‌توانم سکه بهار کسب کنم؟</h3>
        <p>شما می‌توانید از طریق دعوت دوستان، سرمایه‌گذاری در پروژه‌ها و مشارکت در فعالیت‌ها سکه بهار کسب کنید.</p>
        
        <h3>سوال 2: آیا می‌توانم رای خود را تغییر دهم؟</h3>
        <p>بله، شما می‌توانید در هر زمان رای خود را تغییر دهید یا پس بگیرید.</p>
        
        <h3>سوال 3: چگونه می‌توانم به گروه جدید بپیوندم؟</h3>
        <p>گروه‌بندی به صورت خودکار انجام می‌شود، اما می‌توانید با به‌روزرسانی اطلاعات پروفایل، گروه‌های خود را تغییر دهید.</p>
        
        <h3>سوال 4: آیا سرمایه‌گذاری در پروژه‌ها امن است؟</h3>
        <p>همه پروژه‌ها قبل از تایید بررسی می‌شوند، اما همیشه قبل از سرمایه‌گذاری، اطلاعات را به دقت مطالعه کنید.</p>
        
        <h3>سوال 5: چگونه می‌توانم با پشتیبانی تماس بگیرم؟</h3>
        <p>می‌توانید از طریق ایجاد تیکت یا چت پشتیبانی با ما تماس بگیرید.</p>';
    }

    private function getSupportTicketContent()
    {
        return '<h2>نحوه ایجاد تیکت پشتیبانی</h2>
        <p>برای دریافت کمک از تیم پشتیبانی، می‌توانید تیکت ایجاد کنید.</p>
        
        <h3>مراحل ایجاد تیکت</h3>
        <ol>
            <li>به بخش "تیکت‌ها" در منوی پشتیبانی مراجعه کنید</li>
            <li>روی "ایجاد تیکت جدید" کلیک کنید</li>
            <li>موضوع و توضیحات مشکل خود را وارد کنید</li>
            <li>در صورت نیاز، فایل ضمیمه اضافه کنید</li>
            <li>تیکت را ارسال کنید</li>
        </ol>
        
        <h3>پیشنهاد مقالات</h3>
        <p>قبل از ایجاد تیکت، مقالات مرتبط به شما پیشنهاد می‌شود که ممکن است پاسخ شما را داشته باشند.</p>
        
        <h3>پیگیری تیکت</h3>
        <p>می‌توانید وضعیت تیکت خود را در بخش "تیکت‌های من" مشاهده کنید.</p>';
    }

    private function getSupportChatContent()
    {
        return '<h2>استفاده از چت پشتیبانی</h2>
        <p>چت پشتیبانی امکان ارتباط مستقیم و فوری با تیم پشتیبانی را فراهم می‌کند.</p>
        
        <h3>نحوه استفاده</h3>
        <ol>
            <li>به بخش "چت پشتیبانی" مراجعه کنید</li>
            <li>پیام خود را ارسال کنید</li>
            <li>منتظر پاسخ بمانید</li>
        </ol>
        
        <h3>تبدیل به تیکت</h3>
        <p>در صورت نیاز، می‌توانید چت را به تیکت تبدیل کنید تا پیگیری بهتری داشته باشید.</p>
        
        <h3>ساعات پاسخگویی</h3>
        <p>تیم پشتیبانی در ساعات مشخصی پاسخگو است.</p>';
    }

    private function getInvitationContent()
    {
        return '<h2>دعوت از دوستان و کسب سکه بهار</h2>
        <p>با دعوت دوستان خود به EarthCoop، می‌توانید سکه بهار کسب کنید.</p>
        
        <h3>نحوه دعوت</h3>
        <ol>
            <li>به بخش "دعوت از دوستان" مراجعه کنید</li>
            <li>کد دعوت منحصر به فرد خود را کپی کنید</li>
            <li>کد را با دوستان خود به اشتراک بگذارید</li>
        </ol>
        
        <h3>کسب سکه</h3>
        <p>پس از ثبت‌نام دوست شما با کد شما، سکه بهار به حساب شما واریز می‌شود.</p>
        
        <h3>استفاده از سکه‌های کسب شده</h3>
        <p>می‌توانید از سکه‌های کسب شده برای سرمایه‌گذاری، خرید خدمات و موارد دیگر استفاده کنید.</p>';
    }
}



