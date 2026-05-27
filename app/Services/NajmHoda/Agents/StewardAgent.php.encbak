<?php

namespace App\Services\NajmHoda\Agents;

use App\Services\NajmHoda\BaseAgent;
use App\Models\User;
use App\Models\KbArticle;
use App\Models\Blog;
use App\Models\FaqQuestion;
use App\Models\StewardKnowledgeFile;
use Illuminate\Support\Facades\Cache;

/**
 * عامل مهماندار نجم‌هدا
 * 
 * مسئولیت‌ها:
 * - پاسخگویی به سوالات کاربران
 * - آموزش کاربران جدید
 * - جمع‌آوری و تحلیل بازخوردها
 * - ایجاد محتوای آموزشی
 * - مدیریت انجمن کاربران
 */
class StewardAgent extends BaseAgent
{
    protected string $role = 'steward';
    
    protected array $expertise = [
        'user_support',
        'onboarding',
        'training',
        'feedback_collection',
        'community_management',
        'content_creation',
        'communication',
        'user_engagement',
    ];
    
    /**
     * پایگاه دانش نهادشده
     */
    protected array $knowledgeBase = [];
    
    public function getSystemPrompt(): string
    {
        $contentSummary = $this->getContentSummary();
        
        return "شما مهماندار (پشتیبان) پروژه NewEarthCoop هستید و بخشی از سیستم نجم‌هدا.

**نام شما:** مهماندار نجم‌هدا 👨‍✈️

**ماموریت:**
ارائه بهترین تجربه به کاربران ارثکوپ و کمک به آنها در استفاده از سیستم

**مسئولیت‌های شما:**
1. پاسخگویی به سوالات کاربران به زبان ساده و قابل فهم
2. آموزش کاربران جدید (Onboarding)
3. جمع‌آوری و تحلیل بازخوردها
4. ایجاد محتوای آموزشی (راهنماها، ویدئوها، FAQ)
5. مدیریت و تقویت انجمن کاربران
6. ارتباط مؤثر و همدلانه با کاربران
7. شناسایی نیازها و مشکلات کاربران
8. گزارش مشکلات به تیم فنی

**منابع محتوایی موجود:**
{$contentSummary}

**دستورالعمل‌های استفاده:**
- در هر پاسخ، اگر منابع مرتبطی وجود دارد، حتما به آن‌ها اشاره کن
- لینک‌ها را بصورت: [نام](URL) درج کن
- ترجیح بده از FAQ برای سوالات متداول و KnowledgeBase برای راهنماهای تفصیلی
- در صورت سوالات پیچیده که جواب آن در Blog یا FAQ نباشد، صادقانه بگو

**درباره پروژه ارثکوپ:**
ارثکوپ یک پلتفرم تعاونی اقتصادی است که:
- امکان سرمایه‌گذاری عادلانه و دموکراتیک
- شرکت در حراج‌ها و مزایده‌ها
- مدیریت کیف پول و دارایی‌ها
- تعامل اجتماعی با سایر اعضا
- شفافیت کامل در تراکنش‌ها

**ویژگی‌های اصلی سیستم:**
- سیستم احراز هویت امن
- حراج‌های آنلاین
- کیف پول دیجیتال
- سیستم امتیازدهی
- انجمن و گفتگوها
- گزارش‌گیری مالی

**نحوه پاسخگویی شما:**
- همیشه مؤدب، صبور و مهربان باشید
- به زبان ساده و قابل فهم توضیح دهید
- مثال‌های عملی و واضح ارائه کنید
- در صورت نیاز، تصویر یا ویدئو پیشنهاد دهید
- با کاربر همدلی کنید و احساسش را درک کنید
- اگر جوابی ندارید، صادقانه بگویید و به تیم فنی ارجاع دهید
- همیشه مثبت و امیدوارکننده باشید
- حتما به منابع موجود ارجاع بده";
    }
    
    /**
     * دریافت خلاصه کل محتوا (Knowledge Base + Blog + FAQ)
     */
    protected function getContentSummary(): string
    {
        return Cache::remember('steward_content_summary', 3600, function () {
            $summary = "🎯 منابع محتوایی موجود:\n\n";
            
            // 1. Knowledge Base Articles
            $summary .= "📚 پایگاه دانش (Knowledge Base):\n";
            $articles = KbArticle::where('status', 'published')
                ->with('category')
                ->get();
            
            if ($articles->isNotEmpty()) {
                $grouped = $articles->groupBy(function($article) {
                    return $article->category?->name ?? 'سایر';
                });
                foreach ($grouped as $category => $items) {
                    $summary .= "  • {$category}: {$items->count()} مقاله\n";
                }
                $summary .= "\n";
            } else {
                $summary .= "  (هیچ مقاله‌ای))\n\n";
            }
            
            // 2. Blog Posts
            $summary .= "📝 وبلاگ (Blog Posts):\n";
            $blogs = Blog::select('id', 'title', 'group_id')
                ->with('group:id,name')
                ->get();
            
            if ($blogs->isNotEmpty()) {
                $blogsByGroup = $blogs->groupBy(function($blog) {
                    return $blog->group?->name ?? 'عمومی';
                });
                foreach ($blogsByGroup as $groupName => $posts) {
                    $summary .= "  • {$groupName}: {$posts->count()} پست\n";
                }
                $summary .= "\n";
            } else {
                $summary .= "  (هیچ پستی)\n\n";
            }
            
            // 3. FAQ Questions
            $summary .= "❓ سوالات متداول (FAQ):\n";
            $faqs = FaqQuestion::published()->get();
            
            if ($faqs->isNotEmpty()) {
                $faqsByCategory = $faqs->groupBy('category');
                foreach ($faqsByCategory as $category => $questions) {
                    $summary .= "  • {$category}: {$questions->count()} سوال\n";
                }
                $summary .= "\n";
            } else {
                $summary .= "  (هیچ سوالی)\n\n";
            }
            
            // 4. Uploaded Knowledge Files
            $summary .= "📎 فایل‌های دانش آپلودشده:\n";
            $knowledgeFiles = StewardKnowledgeFile::active()->get();
            
            if ($knowledgeFiles->isNotEmpty()) {
                $filesByType = $knowledgeFiles->groupBy('file_type');
                foreach ($filesByType as $type => $files) {
                    $summary .= "  • {$type}: {$files->count()} فایل\n";
                }
                $summary .= "\n";
            } else {
                $summary .= "  (هیچ فایلی)\n\n";
            }
            
            $summary .= "✅ تمام این منابع در پاسخ‌های من استفاده می‌شوند";
            return $summary;
        });
    }
    
    /**
     * دریافت خلاصه پایگاه دانش (برای سازگاری عقبی)
     */
    protected function getKnowledgeBaseSummary(): string
    {
        return $this->getContentSummary();
    }
    
    /**
     * جستجوی مقالات مرتبط
     */
    protected function findRelatedArticles(string $question): array
    {
        $keywords = preg_split('/\s+/', trim($question), -1, PREG_SPLIT_NO_EMPTY);
        $keywords = array_slice($keywords, 0, 5); // حداکثر 5 کلیدواژه
        
        $query = KbArticle::where('status', 'published')
            ->with('category');
        
        foreach ($keywords as $keyword) {
            if (strlen($keyword) > 2) {
                $query->orWhere('title', 'like', "%{$keyword}%")
                      ->orWhere('excerpt', 'like', "%{$keyword}%");
            }
        }
        
        return $query->take(5)->get()->toArray();
    }
    
    /**
     * جستجوی محتوا مرتبط (Knowledge Base + Blog + FAQ + Files)
     */
    protected function findRelatedContent(string $question): array
    {
        $keywords = preg_split('/\s+/', trim($question), -1, PREG_SPLIT_NO_EMPTY);
        $keywords = array_slice($keywords, 0, 5);
        
        $results = [
            'kb_articles' => [],
            'blog_posts' => [],
            'faq_questions' => [],
            'knowledge_files' => []
        ];
        
        // جستجو در Knowledge Base
        $kbQuery = KbArticle::where('status', 'published')->with('category');
        foreach ($keywords as $keyword) {
            if (strlen($keyword) > 2) {
                $kbQuery->orWhere('title', 'like', "%{$keyword}%")
                        ->orWhere('excerpt', 'like', "%{$keyword}%");
            }
        }
        $results['kb_articles'] = $kbQuery->take(3)->get()->map(function($article) {
            return [
                'type' => 'KB',
                'title' => $article->title,
                'slug' => $article->slug,
                'category' => $article->category?->name ?? 'عمومی',
                'excerpt' => $article->excerpt,
                'url' => "/support/knowledge-base/{$article->slug}"
            ];
        })->toArray();
        
        // جستجو در Blog
        $blogQuery = Blog::with('group');
        foreach ($keywords as $keyword) {
            if (strlen($keyword) > 2) {
                $blogQuery->orWhere('title', 'like', "%{$keyword}%")
                          ->orWhere('content', 'like', "%{$keyword}%");
            }
        }
        $results['blog_posts'] = $blogQuery->take(3)->get()->map(function($blog) {
            return [
                'type' => 'Blog',
                'title' => $blog->title,
                'group' => $blog->group?->name ?? 'عمومی',
                'excerpt' => substr($blog->content, 0, 100) . '...',
                'url' => "/groups/" . ($blog->group_id ?? '#')
            ];
        })->toArray();
        
        // جستجو در FAQ
        $faqQuery = FaqQuestion::published();
        foreach ($keywords as $keyword) {
            if (strlen($keyword) > 2) {
                $faqQuery->orWhere('title', 'like', "%{$keyword}%")
                         ->orWhere('question', 'like', "%{$keyword}%")
                         ->orWhere('answer', 'like', "%{$keyword}%");
            }
        }
        $results['faq_questions'] = $faqQuery->take(3)->get()->map(function($faq) {
            return [
                'type' => 'FAQ',
                'title' => $faq->title,
                'category' => $faq->category ?? 'سایر',
                'question' => $faq->question,
                'answer' => substr($faq->answer, 0, 100) . '...'
            ];
        })->toArray();
        
        // جستجو در فایل‌های دانش
        $results['knowledge_files'] = $this->searchKnowledgeFiles($question);
        
        // مرتب‌سازی منابع بر اساس اولویت
        $results = $this->sortBySourcePriority($results);
        
        return $results;
    }
    
    /**
     * مرتب‌سازی منابع بر اساس اولویت تنظیم‌شده
     */
    protected function sortBySourcePriority(array $results): array
    {
        $priorities = config('najm-hoda.agents.steward.source_priorities', [
            'kb_articles' => 10,
            'knowledge_files' => 8,
            'faq_questions' => 7,
            'blog_posts' => 5,
        ]);
        
        // مرتب‌سازی کلیدها بر اساس اولویت
        uksort($results, function($a, $b) use ($priorities) {
            $priorityA = $priorities[$a] ?? 0;
            $priorityB = $priorities[$b] ?? 0;
            return $priorityB <=> $priorityA; // نزولی
        });
        
        return $results;
    }
    
    /**
     * فرمت‌کردن مقالات برای Prompt
     */
    protected function formatArticlesForPrompt(array $articles): string
    {
        if (empty($articles)) {
            return 'مقاله‌ی مرتبطی یافت نشد.';
        }
        
        $formatted = "موارد زیر مرتبط هستند:\n\n";
        
        foreach ($articles as $article) {
            $category = $article['category']['name'] ?? 'عمومی';
            $formatted .= "- **{$article['title']}** ({$category})\n";
            if (!empty($article['excerpt'])) {
                $formatted .= "  {$article['excerpt']}\n";
            }
            $formatted .= "  URL: https://localhost:8000/support/knowledge-base/{$article['slug']}\n\n";
        }
        
        return $formatted;
    }
    
    /**
     * پاسخ به سوال کاربر
     */
    public function answerQuestion(string $question, array $userContext = []): array
    {
        $context = $this->buildUserContext($userContext);
        
        // جستجوی محتوا از تمام منابع
        $relatedContent = $this->findRelatedContent($question);
        $contentContext = $this->formatContentForPrompt($relatedContent);
        
        $prompt = "کاربر سوال زیر را پرسیده:

**سوال:** {$question}

**اطلاعات کاربر:**
{$context}

**منابع محتوایی مرتبط (مرتب‌شده بر اساس اولویت):**
{$contentContext}

لطفاً:
1. پاسخ کامل و واضح بده (به زبان ساده)
2. گام به گام توضیح بده (اگر نیاز باشد)
3. مثال عملی بزن
4. اگر منابعی در پایگاه دانش، فایل‌های آپلودی، وبلاگ یا FAQ مرتبط است، آنها را در پاسخ پیشنهاد بده
5. لینک‌های مفید ارائه بده
6. سوالات مرتبط را پیش‌بینی کن
7. منابع با اولویت بالاتر (مانند مقالات پایگاه دانش و فایل‌های دانش) معتبرتر هستند

**مهم:**
- اگر سوال فنی و پیچیده است، به تیم فنی ارجاع بده
- اگر مربوط به پرداخت است، دقت زیادی کن
- همیشه امنیت کاربر را در نظر بگیر
- ترجیح بده منابع مرتبط را نام برد

خروجی به فرمت JSON:
```json
{
  \"answer\": \"پاسخ کامل\",
  \"steps\": [\"گام 1\", \"گام 2\"],
  \"example\": \"مثال عملی\",
  \"resources\": [
    {\"type\": \"KB\", \"title\": \"نام\", \"url\": \"لینک\"},
    {\"type\": \"Blog\", \"title\": \"نام\", \"url\": \"لینک\"},
    {\"type\": \"FAQ\", \"title\": \"نام\"}
  ],
  \"related_questions\": [],
  \"needs_escalation\": false
}
```";

        $response = $this->ask($prompt, $userContext);
        
        return $this->parseJsonResponse($response);
    }
    
    /**
     * فرمت‌کردن محتوای مرتبط برای Prompt
     */
    protected function formatContentForPrompt(array $content): string
    {
        $formatted = "";
        
        // Knowledge Base Articles
        if (!empty($content['kb_articles'])) {
            $formatted .= "📚 مقالات پایگاه دانش:\n";
            foreach ($content['kb_articles'] as $article) {
                $formatted .= "  • {$article['title']} ({$article['category']})\n";
                if (!empty($article['excerpt'])) {
                    $formatted .= "    {$article['excerpt']}\n";
                }
                $formatted .= "    URL: {$article['url']}\n";
            }
            $formatted .= "\n";
        }
        
        // Uploaded Knowledge Files
        if (!empty($content['knowledge_files'])) {
            $formatted .= "📎 فایل‌های دانش آپلودشده:\n";
            foreach ($content['knowledge_files'] as $file) {
                $formatted .= "  • {$file['title']} (نوع: {$file['file_type']}, اولویت: {$file['priority']})\n";
                if (!empty($file['excerpt'])) {
                    $formatted .= "    {$file['excerpt']}\n";
                }
                if (!empty($file['content'])) {
                    $formatted .= "    محتوا: {$file['content']}\n";
                }
            }
            $formatted .= "\n";
        }
        
        // FAQ Questions
        if (!empty($content['faq_questions'])) {
            $formatted .= "❓ سوالات متداول:\n";
            foreach ($content['faq_questions'] as $faq) {
                $formatted .= "  • {$faq['title']} ({$faq['category']})\n";
                $formatted .= "    سوال: {$faq['question']}\n";
                $formatted .= "    جواب: {$faq['answer']}\n";
            }
            $formatted .= "\n";
        }
        
        // Blog Posts
        if (!empty($content['blog_posts'])) {
            $formatted .= "📝 پست‌های وبلاگ:\n";
            foreach ($content['blog_posts'] as $post) {
                $formatted .= "  • {$post['title']} (گروه: {$post['group']})\n";
                $formatted .= "    {$post['excerpt']}\n";
            }
            $formatted .= "\n";
        }
        
        if (empty($formatted)) {
            $formatted = "هیچ منبع مرتبطی یافت نشد.\n";
        }
        
        return $formatted;
    }
    
    /**
     * جستجوی فایل‌های دانش آپلودشده
     */
    protected function searchKnowledgeFiles(string $question): array
    {
        $keywords = preg_split('/\s+/', trim($question), -1, PREG_SPLIT_NO_EMPTY);
        $keywords = array_slice($keywords, 0, 5);
        
        $query = StewardKnowledgeFile::active();
        
        // جستجوی کلمات کلیدی در عنوان و محتوا
        $query->where(function($q) use ($keywords) {
            foreach ($keywords as $keyword) {
                if (strlen($keyword) > 2) {
                    $q->orWhere('title', 'like', "%{$keyword}%")
                      ->orWhere('extracted_content', 'like', "%{$keyword}%");
                }
            }
        });
        
        return $query->orderBy('search_priority', 'desc')
                     ->take(3)
                     ->get()
                     ->map(function($file) {
                         return [
                             'type' => 'File',
                             'title' => $file->title,
                             'file_type' => strtoupper($file->file_type),
                             'excerpt' => $file->summary ?? substr($file->extracted_content, 0, 200),
                             'content' => substr($file->extracted_content, 0, 1000),
                             'priority' => $file->search_priority,
                         ];
                     })->toArray();
    }
    
    /**
     * آموزش کاربر جدید
     */
    public function onboardUser(User $user): array
    {
        $prompt = "یک کاربر جدید به سیستم اضافه شده:

**اطلاعات کاربر:**
- نام: {$user->name}
- ایمیل: {$user->email}
- تاریخ عضویت: {$user->created_at->format('Y-m-d')}

یک برنامه آموزشی شخصی‌سازی شده بساز که شامل:

1. **پیام خوش‌آمدگویی:**
   - گرم و صمیمی
   - هیجان‌انگیز
   - امیدوارکننده

2. **آشنایی با ویژگی‌های اصلی:**
   - 5 ویژگی مهم
   - توضیح ساده هر کدام

3. **راهنمای قدم به قدم:**
   - اولین کارها (First Steps)
   - تکمیل پروفایل
   - احراز هویت
   - اولین حراج

4. **منابع آموزشی:**
   - ویدئوها
   - راهنماهای نوشتاری
   - FAQ

5. **نکات مهم:**
   - امنیت
   - بهترین روش‌ها
   - اشتباهات رایج

6. **راه‌های دریافت کمک:**
   - چت با پشتیبانی
   - ایمیل
   - انجمن

خروجی: JSON با ساختار کامل";

        $response = $this->ask($prompt);
        
        return $this->parseJsonResponse($response);
    }
    
    /**
     * تحلیل بازخوردها
     */
    public function analyzeFeedback($feedbacks): array
    {
        $feedbackText = $this->formatFeedbacks($feedbacks);
        
        $prompt = "بازخوردهای زیر را تحلیل کن:

{$feedbackText}

تحلیل شامل:

1. **موضوعات اصلی (دسته‌بندی):**
   - مشکلات فنی
   - درخواست‌های ویژگی
   - نارضایتی‌ها
   - تحسین‌ها

2. **احساسات کلی:**
   - مثبت: X%
   - منفی: Y%
   - خنثی: Z%

3. **مشکلات پرتکرار:**
   - شناسایی الگوها
   - اولویت‌بندی

4. **پیشنهادات کاربران:**
   - ویژگی‌های جدید
   - بهبودها

5. **اولویت‌بندی اقدامات:**
   - فوری (Critical)
   - مهم (High)
   - متوسط (Medium)
   - کم (Low)

6. **پاسخ‌های پیشنهادی:**
   - برای بازخوردهای منفی
   - برای پیشنهادات

7. **گزارش برای مدیریت:**
   - خلاصه وضعیت
   - توصیه‌های عملی

فرمت: JSON";

        $response = $this->ask($prompt);
        
        return $this->parseJsonResponse($response);
    }
    
    /**
     * ایجاد محتوای آموزشی
     */
    public function createTutorial(string $topic, string $format = 'markdown'): string
    {
        $prompt = "یک آموزش کامل برای موضوع زیر بساز:

**موضوع:** {$topic}

محتوا باید شامل:

1. **مقدمه:**
   - چرا این مهم است؟
   - چه کسانی نیاز دارند؟

2. **پیش‌نیازها:**
   - دانش مورد نیاز
   - ابزارها

3. **آموزش گام به گام:**
   - توضیحات واضح
   - تصاویر (توصیف محل)
   - مثال‌های عملی

4. **نکات و ترفندها:**
   - Tips مفید
   - میانبرها

5. **مشکلات رایج و راه حل:**
   - خطاهای معمول
   - نحوه رفع

6. **منابع بیشتر:**
   - لینک‌های مفید
   - ویدئوهای مرتبط

**فرمت:** {$format}
**زبان:** فارسی ساده و روان
**لحن:** دوستانه و آموزشی

فقط محتوا را برگردان، بدون توضیحات اضافی.";

        return $this->ask($prompt);
    }
    
    /**
     * ایجاد FAQ
     */
    public function generateFAQ(string $category = 'general'): array
    {
        $prompt = "یک لیست FAQ (سوالات متداول) برای دسته \"{$category}\" بساز:

برای هر سوال:
- سوال واضح و مستقیم
- پاسخ کامل اما مختصر
- مثال (در صورت نیاز)
- لینک مرتبط (در صورت وجود)

حداقل 10 سوال متداول.

دسته‌بندی‌ها:
- عمومی (general)
- ثبت‌نام و احراز هویت
- کیف پول و پرداخت
- حراج و مزایده
- امنیت
- مشکلات فنی

فرمت: JSON
```json
{
  \"category\": \"\",
  \"faqs\": [
    {
      \"question\": \"\",
      \"answer\": \"\",
      \"example\": \"\",
      \"related_link\": \"\"
    }
  ]
}
```";

        $response = $this->ask($prompt);
        
        return $this->parseJsonResponse($response);
    }
    
    /**
     * ساخت context کاربر
     */
    protected function buildUserContext(array $userContext): string
    {
        $context = [];
        
        if (isset($userContext['user_id'])) {
            try {
                $user = User::find($userContext['user_id']);
                if ($user) {
                    $context[] = "نام: {$user->name}";
                    $context[] = "عضویت از: {$user->created_at->diffForHumans()}";
                    $context[] = "آخرین ورود: " . ($user->last_login ? $user->last_login->diffForHumans() : 'هرگز');
                }
            } catch (\Exception $e) {
                // ignore
            }
        }
        
        if (isset($userContext['previous_questions'])) {
            $context[] = "سوالات قبلی: " . implode(', ', $userContext['previous_questions']);
        }
        
        return empty($context) ? 'اطلاعات کاربر در دسترس نیست' : implode("\n", $context);
    }
    
    /**
     * فرمت کردن بازخوردها
     */
    protected function formatFeedbacks($feedbacks): string
    {
        if (is_string($feedbacks)) {
            return $feedbacks;
        }
        
        if (is_array($feedbacks) || is_object($feedbacks)) {
            $formatted = [];
            foreach ($feedbacks as $index => $feedback) {
                $content = is_object($feedback) ? $feedback->content : ($feedback['content'] ?? '');
                $rating = is_object($feedback) ? ($feedback->rating ?? 'N/A') : ($feedback['rating'] ?? 'N/A');
                
                $formatted[] = "[بازخورد #{$index}] امتیاز: {$rating}/5\n{$content}";
            }
            return implode("\n---\n", $formatted);
        }
        
        return 'بازخوردی یافت نشد';
    }
    
    /**
     * پارس کردن پاسخ JSON
     */
    protected function parseJsonResponse(string $response): array
    {
        try {
            $response = preg_replace('/```json\s*(.*?)\s*```/s', '$1', $response);
            $response = preg_replace('/```\s*(.*?)\s*```/s', '$1', $response);
            
            $decoded = json_decode(trim($response), true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            
            return ['raw_response' => $response];
        } catch (\Exception $e) {
            return ['raw_response' => $response, 'error' => $e->getMessage()];
        }
    }
}
