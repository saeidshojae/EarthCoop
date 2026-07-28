<?php

namespace Database\Seeders;

use App\Modules\Blog\Models\BlogCategory;
use App\Modules\Blog\Models\BlogTag;
use App\Modules\Blog\Models\Post;
use App\Modules\Blog\Models\BlogComment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EarthCoopBlogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // نویسنده پیش‌فرض
        $author = User::first();
        if (!$author) {
            $author = User::create([
                'first_name' => 'تیم تولید محتوای',
                'last_name' => 'EarthCoop',
                'email' => 'content@earthcoop.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);
        }

        // پاک‌سازی مطالب قبلی وبلاگ
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('blog_post_tag')->truncate();
        BlogComment::truncate();
        Post::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // دسته‌بندی‌ها
        $categories = [
            ['name' => 'محیط زیست و اقلیم', 'slug' => 'environment-climate', 'description' => 'تحلیل روندهای اقلیمی و محیط زیستی', 'order' => 1],
            ['name' => 'انرژی‌های تجدیدپذیر', 'slug' => 'renewable-energy', 'description' => 'خورشیدی، بادی، زمین‌گرمایی و فراتر از آن', 'order' => 2],
            ['name' => 'همکاری جهانی', 'slug' => 'global-cooperation', 'description' => 'راهکارهای تعاونی فراملی برای چالش‌های مشترک', 'order' => 3],
            ['name' => 'اقتصاد چرخشی و صفرزباله', 'slug' => 'circular-economy', 'description' => 'کاهش ضایعات و طراحی پایدار', 'order' => 4],
            ['name' => 'نمونه‌های موفق', 'slug' => 'success-stories', 'description' => 'مطالعات موردی از همکاری‌های موفق', 'order' => 5],
        ];

        $categoryMap = [];
        foreach ($categories as $cat) {
            $categoryMap[$cat['slug']] = BlogCategory::firstOrCreate(
                ['slug' => $cat['slug']],
                [
                    'name' => $cat['name'],
                    'description' => $cat['description'],
                    'is_active' => true,
                    'order' => $cat['order'],
                ]
            );
        }

        // برچسب‌ها
        $tags = [
            ['name' => 'تغییرات اقلیمی', 'slug' => 'climate-action'],
            ['name' => 'انرژی پاک', 'slug' => 'clean-energy'],
            ['name' => 'همکاری جهانی', 'slug' => 'global-collaboration'],
            ['name' => 'اقتصاد چرخشی', 'slug' => 'circular-economy'],
            ['name' => 'کشاورزی احیاگر', 'slug' => 'regenerative-agriculture'],
            ['name' => 'مدیریت آب', 'slug' => 'water-management'],
            ['name' => 'نمونه‌های موفق', 'slug' => 'success-cases'],
            ['name' => 'فناوری باز', 'slug' => 'open-tech'],
            ['name' => 'شهرهای تاب‌آور', 'slug' => 'resilient-cities'],
            ['name' => 'اقدام جمعی', 'slug' => 'collective-action'],
        ];

        $tagMap = [];
        foreach ($tags as $tag) {
            $tagMap[$tag['slug']] = BlogTag::firstOrCreate(
                ['slug' => $tag['slug']],
                ['name' => $tag['name']]
            );
        }

        // مقالات جدید
        $posts = [
            [
                'title' => 'چرا همکاری جهانی تنها راه مهار بحران اقلیمی است؟',
                'slug' => 'global-cooperation-for-climate',
                'excerpt' => 'از پیمان پاریس تا ابتکارات محلی؛ چرا بدون اقدام جمعی، هیچ کشوری از پیامدهای گرمایش جهانی در امان نیست.',
                'content' => $this->articleContent([
                    'مقدمه' => 'گرمایش جهانی مرز نمی‌شناسد و آلودگی کربن هر کشوری، گریبان همه را می‌گیرد.',
                    'نکات کلیدی' => [
                        'عدالت اقلیمی: کشورهای کم‌انتشار بیشترین آسیب را می‌بینند.',
                        'استانداردهای مشترک: گزارش‌دهی شفاف، قیمت‌گذاری کربن و ردیابی زنجیره تأمین.',
                        'دیپلماسی شهری: شبکه شهرهای C40 نشان داده همکاری شهری می‌تواند از دولت‌ها جلو بزند.',
                    ],
                    'گام‌های عملی' => [
                        'پیوند پروژه‌های محلی به اهداف SDG.',
                        'ایجاد صندوق‌های مشترک کاهش کربن در سطح منطقه‌ای.',
                        'اشتراک داده و فناوری متن‌باز برای پایش آلاینده‌ها.',
                    ],
                ]),
                'category' => 'environment-climate',
                'is_featured' => true,
                'tags' => ['climate-action', 'global-collaboration', 'collective-action'],
            ],
            [
                'title' => 'انرژی‌های تجدیدپذیر 2030: خورشیدی، بادی و ذخیره‌سازی هوشمند',
                'slug' => 'renewables-2030',
                'excerpt' => 'ترکیب فتوولتائیک، توربین‌های بادی ساحلی و باتری‌های شبکه چگونه هزینه انرژی پاک را می‌شکند.',
                'content' => $this->articleContent([
                    'وضعیت بازار' => 'هزینه هر کیلووات‌ساعت خورشیدی در ۱۰ سال ۸۰٪ کاهش یافته است.',
                    'فناوری‌های کلیدی' => [
                        'PV پربازده با سلول‌های پرواسکایت.',
                        'باد ساحلی و توربین‌های شناور برای اعماق بیشتر.',
                        'ذخیره‌سازی: باتری LFP، هیدروژن سبز و ذخیره‌سازی گرمایی.',
                    ],
                    'مدل‌های تعاونی' => [
                        'نیروگاه خورشیدی اشتراکی برای محلات.',
                        'تأمین مالی جمعی توربین بادی محلی.',
                        'قرارداد خرید انرژی (PPA) جمعی برای کسب‌وکارهای کوچک.',
                    ],
                ]),
                'category' => 'renewable-energy',
                'is_featured' => true,
                'tags' => ['clean-energy', 'collective-action', 'open-tech'],
            ],
            [
                'title' => 'اقتصاد چرخشی و صفرزباله: از طراحی تا اجرا',
                'slug' => 'circular-zero-waste',
                'excerpt' => 'چگونه با طراحی چرخه‌گرا، جریان مواد را می‌بندیم و هزینه دفن را به سرمایه اجتماعی تبدیل می‌کنیم.',
                'content' => $this->articleContent([
                    'چارچوب' => 'طراحی برای جداسازی، استفاده مجدد و بازیافت از ابتدای زنجیره.',
                    'نمونه‌ها' => [
                        'مدل ودیعه‌گذاری بسته‌بندی در خرده‌فروشی محلی.',
                        'کارگاه‌های تعمیر جمعی (Repair Café) برای لوازم خانگی.',
                        'تفکیک در مبدأ با انگیزه اقتصادی به شکل کوین محلی.',
                    ],
                    'شاخص‌ها' => [
                        'کاهش پسماند سرانه',
                        'نرخ بازیافت مواد بحرانی (لیتیوم، مس، آلومینیوم)',
                        'کاهش انتشار ناشی از دفن',
                    ],
                ]),
                'category' => 'circular-economy',
                'is_featured' => false,
                'tags' => ['circular-economy', 'collective-action'],
            ],
            [
                'title' => 'شهرهای تاب‌آور: نقش همکاری شهروندی در تاب‌آوری آب‌وهوایی',
                'slug' => 'resilient-cities-cooperation',
                'excerpt' => 'از باغ‌های بارانی تا هشدار سیل محلی؛ مشارکت مردم چگونه زیرساخت خاکستری را تکمیل می‌کند.',
                'content' => $this->articleContent([
                    'چالش' => 'افزایش سیلاب‌های ناگهانی و موج‌های گرما در شهرها.',
                    'راهکارهای مردم‌بنیان' => [
                        'باغچه‌های بارانی و سطوح نفوذپذیر با مالکیت جمعی.',
                        'شبکه داوطلبان هشدار سیل با سنسورهای ارزان و متن‌باز.',
                        'سایه‌بان‌های شهری و خنک‌سازی طبیعت‌محور با مشارکت محلی.',
                    ],
                    'حاکمیت' => 'بودجه‌ریزی مشارکتی برای پروژه‌های تاب‌آوری محله.',
                ]),
                'category' => 'global-cooperation',
                'is_featured' => false,
                'tags' => ['resilient-cities', 'collective-action', 'global-collaboration'],
            ],
            [
                'title' => 'کشاورزی احیاگر تعاونی: خاک سالم، غذای سالم',
                'slug' => 'regenerative-coops',
                'excerpt' => 'تعاونی‌های کشاورزی چگونه با کشت پوششی، تناوب و مدیریت آب، هم پایداری و هم سود را بالا می‌برند.',
                'content' => $this->articleContent([
                    'اصول' => [
                        'کاهش شخم و حفظ پوشش گیاهی',
                        'تناوب و کشت میان‌بَری برای بازگرداندن مواد آلی',
                        'مدیریت آب و کانتوربندی برای جلوگیری از فرسایش',
                    ],
                    'مدل تعاونی' => 'اشتراک ادوات، خرید نهاده پایدار و فروش مشترک با برند تعاونی.',
                    'شاخص اثر' => 'افزایش کربن آلی خاک و کاهش ورودی شیمیایی.',
                ]),
                'category' => 'success-stories',
                'is_featured' => false,
                'tags' => ['regenerative-agriculture', 'water-management', 'success-cases'],
            ],
            [
                'title' => 'نمونه‌های موفق همکاری در بحران: از کووید تا سیل',
                'slug' => 'cooperation-in-crisis',
                'excerpt' => 'چهار مطالعه موردی از همیاری جوامع در بحران که نشان می‌دهد شبکه‌های محلی می‌توانند سریع‌تر از سیستم‌های متمرکز عمل کنند.',
                'content' => $this->articleContent([
                    'مطالعه موردی ۱' => 'توزیع غذای تعاونی در دوران قرنطینه با اپلیکیشن محلی.',
                    'مطالعه موردی ۲' => 'نقشه‌برداری مردمی از نیازهای سیل‌زدگان و تخصیص منابع.',
                    'مطالعه موردی ۳' => 'تولید ماسک و تجهیزات محافظتی توسط تعاونی‌های زنان.',
                    'درس‌آموخته' => 'زیرساخت دیجیتال محلی + اعتماد اجتماعی = واکنش سریع.',
                ]),
                'category' => 'success-stories',
                'is_featured' => true,
                'tags' => ['success-cases', 'collective-action', 'global-collaboration'],
            ],
            [
                'title' => 'فناوری باز برای پایداری: سنسورها، داده و شفافیت',
                'slug' => 'open-tech-for-sustainability',
                'excerpt' => 'چگونه سخت‌افزار و نرم‌افزار متن‌باز هزینه پایش محیط زیست را کاهش می‌دهد و اعتماد عمومی می‌سازد.',
                'content' => $this->articleContent([
                    'ابزارها' => [
                        'سنسورهای کیفیت هوا کم‌هزینه با کالیبراسیون جمعی.',
                        'داشبورد متن‌باز برای پایش کربن و مصرف انرژی.',
                        'بلاکچین سبک برای ردیابی زنجیره تأمین سبز.',
                    ],
                    'حکمرانی داده' => 'داده باز، حریم خصوصی و مجوزهای مشارکتی.',
                    'نمونه' => 'پلتفرم‌های شهروند-دانشمند در اروپا و آسیا.',
                ]),
                'category' => 'global-cooperation',
                'is_featured' => false,
                'tags' => ['open-tech', 'clean-energy', 'collective-action'],
            ],
            [
                'title' => 'مدیریت مشارکتی آب: از حوضه تا مزرعه',
                'slug' => 'collaborative-water-management',
                'excerpt' => 'مدل‌های همیاری برای احیای سفره‌های آب زیرزمینی، تقسیم منصفانه و سازگاری با خشکسالی.',
                'content' => $this->articleContent([
                    'چالش' => 'افت سفره‌های آب و رقابت بخشی.',
                    'راهکار مشارکتی' => [
                        'بودجه‌ریزی مشترک برای قنات و آبخوان‌داری.',
                        'سهمیه‌بندی شفاف با کنتور هوشمند و داده باز.',
                        'توافقات محلی برای الگوی کشت کم‌آب‌بر.',
                    ],
                    'نمونه' => 'تجربه حوضه گاوخونی و تعاونی‌های آب‌رسانی روستایی.',
                ]),
                'category' => 'environment-climate',
                'is_featured' => false,
                'tags' => ['water-management', 'collective-action', 'success-cases'],
            ],
            [
                'title' => 'اقتصاد تعاونی برای خنثی‌سازی کربن محلی',
                'slug' => 'cooperative-carbon-neutrality',
                'excerpt' => 'چگونه یک تعاونی محلی می‌تواند پورتفوی کربن خود را با درختکاری، بهینه‌سازی انرژی و خرید اعتبار مدیریت کند.',
                'content' => $this->articleContent([
                    'گام‌ها' => [
                        'حسابرسی کربن داوطلبانه در سطح محله.',
                        'بسته اقدام: عایق‌کاری، به‌روزرسانی روشنایی، نصب خورشیدی اشتراکی.',
                        'پروژه‌های جذب کربن محلی (درختکاری بومی، کشاورزی احیاگر).',
                    ],
                    'تأمین مالی' => 'صندوق محلی + کوین داخلی بهار برای تشویق مشارکت.',
                    'سنجش' => 'داشبورد شفاف برای ردیابی کاهش و جبران.',
                ]),
                'category' => 'renewable-energy',
                'is_featured' => false,
                'tags' => ['climate-action', 'clean-energy', 'collective-action'],
            ],
            [
                'title' => 'برنامه ۱۰۰ روزه اقدام اقلیمی برای اعضای EarthCoop',
                'slug' => '100-day-climate-action',
                'excerpt' => 'یک نقشه راه عملی برای اعضا: از ممیزی خانگی تا پیوستن به پروژه‌های جمعی و دعوت دوستان.',
                'content' => $this->articleContent([
                    'هفته ۱-۲' => 'ممیزی انرژی و آب در منزل؛ ثبت داده در پروفایل.',
                    'هفته ۳-۴' => 'پیوستن به یک گروه محلی و یک پروژه انرژی یا پسماند.',
                    'هفته ۵-۸' => 'مشارکت در انتخابات/نظرسنجی مربوط به بودجه پایداری محلی.',
                    'هفته ۹-12' => 'دعوت حداقل ۳ نفر، اشتراک تجربه و انتشار داستان در وبلاگ.',
                    'شاخص موفقیت' => 'کاهش مصرف، مشارکت در رأی‌گیری و افزایش اعضای فعال.',
                ]),
                'category' => 'environment-climate',
                'is_featured' => true,
                'tags' => ['collective-action', 'climate-action', 'invitation'],
            ],
        ];

        foreach ($posts as $index => $data) {
            $post = Post::create([
                'title' => $data['title'],
                'slug' => Str::slug($data['slug'] ?? $data['title']),
                'excerpt' => $data['excerpt'],
                'content' => $data['content'],
                'category_id' => $categoryMap[$data['category']]->id,
                'user_id' => $author->id,
                'status' => 'published',
                'published_at' => now()->subDays($index + 1),
                'is_featured' => $data['is_featured'],
                'allow_comments' => true,
                'meta_title' => $data['title'],
                'meta_description' => $data['excerpt'],
                'meta_keywords' => implode(',', $data['tags']),
                'views_count' => rand(120, 900),
            ]);

            $tagIds = collect($data['tags'] ?? [])
                ->filter(fn ($slug) => isset($tagMap[$slug]))
                ->map(fn ($slug) => $tagMap[$slug]->id)
                ->all();

            $post->tags()->sync($tagIds);
        }

        $this->command->info('🧹 مطالب قبلی حذف و 10 مقاله جدید محیط‌زیستی منتشر شد.');
    }

    /**
     * Helper to build simple HTML content blocks.
     */
    private function articleContent(array $sections): string
    {
        $html = '';
        foreach ($sections as $title => $value) {
            $html .= "<h2>{$title}</h2>";
            if (is_array($value)) {
                $html .= '<ul>';
                foreach ($value as $item) {
                    $html .= "<li>{$item}</li>";
                }
                $html .= '</ul>';
            } else {
                $html .= "<p>{$value}</p>";
            }
        }
        return $html;
    }
}


