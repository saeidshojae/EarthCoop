# 🤖 Steward Agent - Integration with Knowledge Base

## Overview

**Steward Agent** (مهماندار نجم‌هدا) is an AI-powered support agent that automatically integrates with the knowledge base to provide informed responses to user questions.

## Architecture

### Knowledge Base Sources

The Steward Agent now receives information from **three main sources**:

```
┌─────────────────────────────────────────────────────────┐
│         System Prompt (دستورات سیستمی)                 │
│                                                           │
│  • نقش و مسئولیت‌ها (Role & Responsibilities)          │
│  • خلاصه پایگاه دانش (KB Summary - Dynamic)             │
│  • دستورالعمل‌های استفاده (Usage Guidelines)          │
│  • سبک و تن کار (Tone & Style)                        │
└─────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────┐
│      Knowledge Base (پایگاه دانش - Database)            │
│                                                           │
│  • 20 مقاله منتشر شده                                   │
│  • 18 دسته‌ی سازمان‌یافته                              │
│  • 12+ برچسب (Tags)                                    │
│  • 4.7K+ بازدید کل                                     │
└─────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────┐
│      User Context & History                              │
│  (اطلاعات کاربر و تاریخ مکالمه)                        │
│                                                           │
│  • User ID, Name, Join Date                             │
│  • Previous Questions                                    │
│  • Conversation History                                  │
└─────────────────────────────────────────────────────────┘
```

## How It Works

### 1. System Prompt Integration

The `getSystemPrompt()` method now:

```php
public function getSystemPrompt(): string
{
    $kbSummary = $this->getKnowledgeBaseSummary();
    return "... System Prompt with KB Summary ...";
}
```

**Features:**
- ✓ Dynamically loads KB summary (cached for 1 hour)
- ✓ Groups articles by category
- ✓ Lists all published articles in prompt
- ✓ Embeds instructions to reference KB articles

### 2. Knowledge Base Query

The `findRelatedArticles()` method searches for relevant articles:

```php
protected function findRelatedArticles(string $question): array
{
    $keywords = preg_split('/\s+/', trim($question));
    $keywords = array_slice($keywords, 0, 5); // Max 5 keywords
    
    $query = KbArticle::where('status', 'published')
        ->with('category');
    
    // Search by title and excerpt
    foreach ($keywords as $keyword) {
        if (strlen($keyword) > 2) {
            $query->orWhere('title', 'like', "%{$keyword}%")
                  ->orWhere('excerpt', 'like', "%{$keyword}%");
        }
    }
    
    return $query->take(5)->get()->toArray();
}
```

**How it works:**
1. Extracts keywords from user question
2. Searches KB articles for matching titles/excerpts
3. Returns up to 5 most relevant articles
4. Passes to AI with full metadata (title, category, excerpt, URL)

### 3. Response Formatting

The `formatArticlesForPrompt()` method formats articles for the prompt:

```php
protected function formatArticlesForPrompt(array $articles): string
{
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
```

### 4. Answer with References

The `answerQuestion()` method combines everything:

```php
public function answerQuestion(string $question, array $userContext = []): array
{
    $context = $this->buildUserContext($userContext);
    $relatedArticles = $this->findRelatedArticles($question);
    $articlesContext = $this->formatArticlesForPrompt($relatedArticles);
    
    $prompt = "سوال: {$question}\n\nمقالات مرتبط:\n{$articlesContext}\n\n...";
    
    $response = $this->ask($prompt, $userContext);
    return $this->parseJsonResponse($response);
}
```

The AI response includes:
- Complete answer
- Step-by-step instructions
- Related KB articles with URLs
- Related questions suggestions
- Escalation info (if needed)

## File Structure

```
app/Services/NajmHoda/
├── Agents/
│   └── StewardAgent.php          ← Core integration
├── BaseAgent.php                  ← Base class for all agents
└── NajmHodaOrchestrator.php      ← Routes requests to agents

models/
└── KbArticle.php                  ← KB model

resources/views/
└── steward-dashboard.blade.php   ← Testing dashboard

config/
└── najm-hoda.php                  ← Configuration

routes/
└── web.php                        ← Routes including KB endpoints
```

## Testing

### Test Commands

```bash
# Test Steward Agent integration
php artisan steward:test-kb

# Show all KB articles
php artisan kb:show

# Show articles in specific category
php artisan kb:show --category="ثبت‌نام"
```

### Dashboard

Visit: `http://localhost:8000/steward-dashboard`

Shows:
- KB Statistics (20 articles, 18 categories, 4.7K+ views)
- Steward Agent capabilities
- Integration flow visualization
- System prompt information

## Database Model: KbArticle

```php
KbArticle {
    id: integer
    title: string
    slug: string (unique)
    excerpt: text
    content: longtext
    category_id: integer (FK)
    author_id: integer (FK)
    status: enum (published, draft, archived)
    is_featured: boolean
    view_count: integer
    published_at: timestamp
    created_at: timestamp
    updated_at: timestamp
    
    Relationships:
    - category: BelongsTo(KbCategory)
    - author: BelongsTo(User)
    - tags: BelongsToMany(KbTag)
}
```

## Query Performance

### Caching

Knowledge Base Summary is cached for **1 hour**:

```php
protected function getKnowledgeBaseSummary(): string
{
    return Cache::remember('steward_kb_summary', 3600, function () {
        // Query and format KB articles
    });
}
```

Clear cache when articles are added/updated:
```php
Cache::forget('steward_kb_summary');
```

### Database Queries

Article search uses indexed fields:
- `title` (indexed in migration)
- `excerpt` (text search)
- `status` (simple where clause)

## Integration Points

### 1. NajmHodaController
Passes user context to Steward Agent:
```php
$response = $this->najmHoda->route($message, [
    'user_id' => $user->id,
    'user_is_admin' => $isAdmin,
    'force_agent' => 'steward' // For non-admin users
]);
```

### 2. Widget Component
Sends chat requests to `/api/najm-hoda/chat`:
```javascript
fetch('/api/najm-hoda/chat', {
    method: 'POST',
    body: JSON.stringify({ 
        message: userInput,
        agent: 'steward'
    })
})
```

### 3. Configuration
`config/najm-hoda.php` settings:
```php
'knowledge_base_path' => storage_path('najm-hoda/knowledge'),
'agents' => [
    'steward' => [
        'enabled' => true,
        'temperature' => 0.7,
        'max_tokens' => 3000,
    ]
]
```

## Response Format

Example Steward response:

```json
{
    "answer": "پاسخ کامل و دقیق...",
    "steps": ["گام 1", "گام 2", "گام 3"],
    "example": "مثال عملی...",
    "kb_articles": [
        {
            "title": "راهنمای کامل ثبت‌نام در EarthCoop",
            "url": "https://localhost:8000/support/knowledge-base/complete-registration-guide"
        },
        {
            "title": "آشنایی با پلتفرم EarthCoop",
            "url": "https://localhost:8000/support/knowledge-base/platform-introduction"
        }
    ],
    "related_questions": ["سوال مرتبط 1", "سوال مرتبط 2"],
    "needs_escalation": false,
    "escalation_reason": ""
}
```

## Features

✅ **Dynamic KB Integration**
- KB articles automatically loaded in system prompt
- Cached for performance (1 hour TTL)

✅ **Intelligent Search**
- Keyword-based article matching
- Category information included
- Excerpt summaries for context

✅ **Rich References**
- Direct links to KB articles
- Category labeling
- Related article suggestions

✅ **Multi-Language Support**
- Farsi (فارسی) prompts and responses
- Culturally appropriate tone

✅ **Access Control**
- Non-admin users restricted to Steward agent
- Admin users can access all agents

✅ **Performance Optimized**
- KB summary cached (1 hour)
- Efficient database queries
- Minimal API calls to AI

## Future Enhancements

- [ ] Semantic search using embeddings
- [ ] Article recommendation engine
- [ ] User feedback loop (rate answer quality)
- [ ] Automatic article suggestion (if KB has relevant content)
- [ ] Multi-language KB support
- [ ] Analytics dashboard (top questions, KB gaps)
- [ ] Integration with ticket system (escalation to KB)
- [ ] KB article auto-generation from tickets

## Troubleshooting

### KB articles not showing in System Prompt

**Solution:** Clear the cache
```bash
php artisan cache:clear
# or specifically
php artisan cache:forget steward_kb_summary
```

### Articles not found in search

**Check:**
1. Article status is 'published'
2. Article has keyword matches in title/excerpt
3. Database query with: `php artisan kb:show`

### Slow responses

**Check:**
1. KB cache is working: `php artisan cache:forget steward_kb_summary`
2. Database indexes on `kb_articles(status, title, excerpt)`
3. API rate limits with OpenRouter

## Related Documentation

- [NajmHoda System Architecture](./najm-hoda.md)
- [Knowledge Base Management](./kb-management.md)
- [Agent Configuration](./agent-config.md)
- [API Endpoints](./api.md)
