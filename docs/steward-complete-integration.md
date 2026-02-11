# 🤖 Steward Agent - Integration Complete: KB + Blog + FAQ

## **نمای کلی**

Steward Agent اکنون از **سه منبع محتوایی** برای پاسخ دادن به کاربران استفاده می‌کند:

```
┌─────────────────────────────────────────────────────────┐
│         📚 Knowledge Base (20 مقاله)                    │
│         📝 Blog Posts (14 پست)                         │
│         ❓ FAQ Questions (0... سوال)                    │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
          ┌─────────────────────┐
          │  Steward Agent      │
          │                     │
          │  • جستجوی هوشمند   │
          │  • Cache خودکار   │
          │  • Observers        │
          └─────────────────────┘
                   │
                   ▼
        جواب‌های دقیق و مستند‌شده
```

---

## **منابع محتوایی**

### 1️⃣ **Knowledge Base (پایگاه دانش)**
- **مدل:** `KbArticle`
- **وضعیت:** منتشر شده (`status = published`)
- **محتوا:** 20 مقاله در 18 دسته
- **نمونه:** راهنماهای تفصیلی، آموزش‌ها
- **Observer:** `KbArticleObserver`

```php
// جستجو
KbArticle::where('status', 'published')
    ->whereTitle or excerpt contains $keyword
    ->take(3) // حداکثر 3 نتیجه
```

### 2️⃣ **Blog Posts (وبلاگ)**
- **مدل:** `Blog`
- **وضعیت:** فعال (بدون فیلتر منطقی)
- **محتوا:** 14 پست در گروه‌های مختلف
- **نمونه:** اخبار، نکات و ترفندها
- **Observer:** `BlogObserver`

```php
// جستجو
Blog::where('title or content contains $keyword')
    ->with('group')
    ->take(3) // حداکثر 3 نتیجه
```

### 3️⃣ **FAQ Questions (سوالات متداول)**
- **مدل:** `FaqQuestion`
- **وضعیت:** منتشر و دارای پاسخ (`is_published = true` + `answer != null`)
- **محتوا:** سوالات و پاسخ‌های رایج
- **نمونه:** سوالاتی که مکرر از کاربران پرسیده می‌شود
- **Observer:** `FaqQuestionObserver`

```php
// جستجو
FaqQuestion::published() // scope
    ->where('title or question or answer contains $keyword')
    ->take(3) // حداکثر 3 نتیجه
```

---

## **معمارِی Observers**

### Cache Invalidation Flow

```
Content Changes
├─ KbArticle::created/updated/deleted
│  └─ KbArticleObserver → Cache::forget('steward_content_summary')
├─ Blog::created/updated/deleted
│  └─ BlogObserver → Cache::forget('steward_content_summary')
└─ FaqQuestion::created/updated/deleted
   └─ FaqQuestionObserver → Cache::forget('steward_content_summary')
        │
        ▼
   دفعه‌ی بعدی Steward سوال می‌پرسد
   ↓
   تمام منابع تازه‌شده است!
```

### Registration

```php
// app/Providers/AppServiceProvider.php
KbArticle::observe(KbArticleObserver::class);
Blog::observe(BlogObserver::class);
FaqQuestion::observe(FaqQuestionObserver::class);
```

---

## **جریان کار Steward Agent**

### User Question → Response Flow

```
کاربر پرسد:
"چطور کیف پول شارژ کنم؟"
        ↓
┌─────────────────────────────────┐
│  findRelatedContent()           │
│  - جستجوی KB Articles          │
│  - جستجوی Blog Posts           │
│  - جستجوی FAQ Questions        │
└──────────────┬──────────────────┘
               │
               ▼
    ┌──────────────────────────┐
    │ Results (até 9 items):   │
    │ • 3 KB Articles          │
    │ • 3 Blog Posts           │
    │ • 3 FAQ Questions        │
    └──────────────┬───────────┘
                   │
                   ▼
    ┌──────────────────────────┐
    │ formatContentForPrompt()  │
    │ Format: JSON اور markdown│
    └──────────────┬───────────┘
                   │
                   ▼
    ┌──────────────────────────┐
    │  Send to OpenRouter      │
    │  (با System Prompt)       │
    └──────────────┬───────────┘
                   │
                   ▼
        Response with Links:
        
"برای شارژ کیف پول:

1️⃣ به بخش 'کیف پول' بروید
2️⃣ روی 'شارژ' کلیک کنید
3️⃣ روش پرداخت را انتخاب کنید

📚 منابع:
• مدیریت کیف پول دیجیتال
  /support/knowledge-base/digital-wallet-management
  
• سکه‌های بهار
  /blogs/bahar-coins

❓ سوال متداول:
کیف پول چند روز برای شارژ طول می‌کشد؟
→ جواب: ...
```

---

## **فایل‌های تغییر‌یافته**

### Core Changes

```
✅ app/Services/NajmHoda/Agents/StewardAgent.php
   ├─ Added imports: Blog, FaqQuestion
   ├─ getSystemPrompt() - منابع متعدد
   ├─ getContentSummary() - جدید (تمام منابع)
   ├─ findRelatedContent() - جدید (3x جستجو)
   └─ formatContentForPrompt() - جدید (3x format)

✅ app/Observers/BlogObserver.php
   └─ جدید - Observer برای Blog

✅ app/Observers/FaqQuestionObserver.php
   └─ جدید - Observer برای FAQ

✅ app/Providers/AppServiceProvider.php
   ├─ Added imports: Blog, BlogObserver, FaqQuestion, FaqQuestionObserver
   └─ Registered 3 Observers

✅ app/Console/Commands/TestStewardComplete.php
   └─ جدید - Test command برای تمام منابع
```

---

## **استفاده (Usage)**

### 1. **Automatic Updates**

هر بار که محتوا تغییر کند:

```php
// Admin یک مقاله جدید اضافه می‌کند
$article = KbArticle::create([
    'title' => '...',
    'content' => '...',
    'status' => 'published'
]);
// ↓ KbArticleObserver::created() فیری می‌شود
// ↓ Cache::forget('steward_content_summary')
// ↓ Steward بار بعدی این چیز جدید را می‌داند!

// یا Blog پست
$blog = Blog::create([...]);
// ↓ BlogObserver::created()
// ↓ Cache cleared

// یا FAQ جدید
$faq = FaqQuestion::create(['is_published' => true, 'answer' => '...']);
// ↓ FaqQuestionObserver::created() (if published)
// ↓ Cache cleared
```

### 2. **Testing**

```bash
# نمایش تمام منابع
php artisan steward:test-complete

# نمایش KB
php artisan kb:show

# نمایش Dashboard (برای Admin)
# /admin/kb/steward-dashboard
```

### 3. **Manual Cache Clear**

```bash
# اگر مشکلی پیش آمد:
php artisan cache:forget steward_content_summary
```

---

## **Database Queries**

### Knowledge Base
```sql
SELECT * FROM kb_articles 
WHERE status = 'published' 
AND (title LIKE '%keyword%' OR excerpt LIKE '%keyword%');
```

### Blog Posts
```sql
SELECT * FROM blogs 
WHERE title LIKE '%keyword%' OR content LIKE '%keyword%';
```

### FAQ
```sql
SELECT * FROM faq_questions 
WHERE is_published = true 
AND answer IS NOT NULL
AND (title LIKE '%keyword%' OR question LIKE '%keyword%' OR answer LIKE '%keyword%');
```

---

## **Performance Optimization**

### Caching Strategy

```
┌─────────────────────────────────────┐
│  First Request                      │
│  - getContentSummary() called       │
│  - All queries executed             │
│  - Result cached (1 hour)           │
│  - Subsequent requests use cache    │
└────────────────┬────────────────────┘
                 │
                 ▼
        Cache Hit (Fast!)
        ↓ 0-5ms (vs 100-200ms)
                 │
                 ▼
        Content changed
        ↓ Observer clears
        ↓ Next request re-queries
```

### TTL (Time To Live)

```php
Cache::remember('steward_content_summary', 3600, function () {
    // 3600 seconds = 1 hour
});
```

---

## **مثال: Steward Response**

### Query
```
کاربر: "چطور نقش دارم؟"
```

### Process
```
1. findRelatedContent('چطور نقش دارم؟')
   - KB: جستجو → "سیستم امتیازدهی"
   - Blog: جستجو → "نقاط و امتیازها"
   - FAQ: جستجو → "چطور نقش کسب کنم؟"

2. formatContentForPrompt()
   📚 Knowledge Base:
   • سیستم امتیازدهی
   
   📝 Blog:
   • نقاط و امتیازها (نکات و ترفندها)
   
   ❓ FAQ:
   • چطور نقش کسب کنم؟

3. OpenRouter with System Prompt
   + منابع + User Question

4. Response:
   "نقش در EarthCoop به این صورت کار می‌کند:
   
   📚 منابع بیشتر:
   - سیستم امتیازدهی
   - نقاط و امتیازها
   - FAQ: چطور نقش کسب کنم؟"
```

---

## **خلاصه آمار**

| منبع | تعداد | Observer | Query |
|------|-------|----------|-------|
| 📚 Knowledge Base | 20 | ✅ KbArticleObserver | title/excerpt |
| 📝 Blog Posts | 14 | ✅ BlogObserver | title/content |
| ❓ FAQ Questions | 0 | ✅ FaqQuestionObserver | question/answer |
| **کل** | **34** | **3 Observers** | **Intelligent Search** |

---

## **Troubleshooting**

| مشکل | علت | حل |
|------|-----|-----|
| Steward منابع قدیمی نشان می‌دهد | Cache قدیمی | `php artisan cache:clear` |
| Blog پست‌ها نمایان نیستند | Cache outdated | `Cache::forget('steward_content_summary')` |
| FAQ‌ها جستجو نمی‌شود | صفحه‌بندی | بررسی `is_published = true` |

---

## **تکامل آتی**

- [ ] Semantic search (جستجوی معنایی)
- [ ] Automatic article suggestion
- [ ] User feedback rating
- [ ] Analytics dashboard
- [ ] Multi-language support
- [ ] Vector embeddings برای جستجوی بهتر

---

**🎉 Steward Agent اکنون از تمام منابع محتوا استفاده می‌کند!**
