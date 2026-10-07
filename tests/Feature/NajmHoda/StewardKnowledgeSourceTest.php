<?php

namespace Tests\Feature\NajmHoda;

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use App\Models\FaqQuestion;
use App\Models\KbArticle;
use App\Models\StewardKnowledgeFile;
use App\Models\User;
use App\Modules\Blog\Models\BlogCategory;
use App\Modules\Blog\Models\Post as BlogPost;
use App\Services\NajmHoda\Agents\StewardAgent;
use App\Services\NajmHoda\Knowledge\StewardKnowledgeUrlIngestor;
use RuntimeException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StewardKnowledgeSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_large_extracted_knowledge_content_is_persisted_without_text_column_truncation(): void
    {
        $content = str_repeat('دانش ارث‌کوپ ', 12000);

        $file = StewardKnowledgeFile::create([
            'title' => 'منبع بزرگ',
            'original_filename' => 'large.txt',
            'file_path' => 'steward/knowledge/large.txt',
            'file_type' => 'txt',
            'file_size' => strlen($content),
            'extracted_content' => $content,
            'summary' => mb_substr($content, 0, 200),
            'search_priority' => 10,
            'is_active' => true,
        ]);

        $this->assertSame($content, $file->fresh()->extracted_content);
        $this->assertGreaterThan(65535, strlen($file->fresh()->extracted_content));
    }

    public function test_admin_can_upload_a_large_text_knowledge_source(): void
    {
        Storage::fake('local');

        $this->withoutMiddleware([
            AdminMiddleware::class,
            PermissionMiddleware::class,
        ]);

        $this->actingAs(User::factory()->create());

        $content = str_repeat('قانون اساسی ارث‌کوپ و عدالت زمین. ', 4000);
        $upload = UploadedFile::fake()->createWithContent('constitution.txt', $content);

        $response = $this->post(route('admin.najm-hoda.steward.upload-knowledge'), [
            'title' => 'قانون اساسی ارث‌کوپ',
            'knowledge_file' => $upload,
            'search_priority' => 10,
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonPath('success', true);

        $stored = StewardKnowledgeFile::where('title', 'قانون اساسی ارث‌کوپ')->firstOrFail();
        $this->assertSame($content, $stored->extracted_content);
        Storage::disk('local')->assertExists($stored->file_path);
    }

    public function test_normal_steward_ask_path_has_retrievable_uploaded_knowledge_context(): void
    {
        StewardKnowledgeFile::create([
            'title' => 'اصل عدالت زمین',
            'original_filename' => 'justice.txt',
            'file_path' => 'steward/knowledge/justice.txt',
            'file_type' => 'txt',
            'file_size' => 128,
            'extracted_content' => 'عدالت‌زمین یعنی هر حق به ذی‌حق خود برسد و منابع مرتبط باید مبنای پاسخ قرار گیرند.',
            'summary' => 'تعریف عدالت‌زمین',
            'search_priority' => 10,
            'is_active' => true,
        ]);

        $agent = new class extends StewardAgent {
            public function exposeKnowledgeContext(string $question): string
            {
                return $this->knowledgeContextFor($question);
            }
        };

        $context = $agent->exposeKnowledgeContext('عدالت‌زمین چیست؟');

        $this->assertStringContainsString('اصل عدالت زمین', $context);
        $this->assertStringContainsString('هر حق به ذی‌حق خود برسد', $context);

        $source = file_get_contents(app_path('Services/NajmHoda/Agents/StewardAgent.php'));
        $this->assertStringContainsString('public function ask(string $prompt, array $context = []): string', $source);
        $this->assertStringContainsString('parent::ask($promptWithKnowledge, $context)', $source);
    }

    public function test_admin_can_add_a_public_url_as_a_knowledge_source(): void
    {
        $this->withoutMiddleware([
            AdminMiddleware::class,
            PermissionMiddleware::class,
        ]);

        $this->actingAs(User::factory()->create());

        $this->app->instance(StewardKnowledgeUrlIngestor::class, new class extends StewardKnowledgeUrlIngestor {
            public function ingest(string $url): array
            {
                return [
                    'url' => $url,
                    'title' => 'قانون نمونه',
                    'content' => 'این متن از صفحه عمومی نمونه استخراج شده و باید در بازیابی مهماندار دیده شود.',
                    'content_type' => 'text/html; charset=UTF-8',
                ];
            }
        });

        $response = $this->postJson(route('admin.najm-hoda.steward.add-knowledge-url'), [
            'source_url' => 'https://example.org/law',
            'search_priority' => 9,
        ]);

        $response->assertCreated()->assertJsonPath('success', true);

        $source = StewardKnowledgeFile::where('source_url', 'https://example.org/law')->firstOrFail();
        $this->assertSame('url', $source->source_type);
        $this->assertSame('قانون نمونه', $source->title);
        $this->assertStringContainsString('صفحه عمومی نمونه', $source->extracted_content);
    }

    public function test_url_ingestor_rejects_private_network_targets_before_fetching(): void
    {
        $this->expectException(RuntimeException::class);

        (new StewardKnowledgeUrlIngestor())->ingest('http://127.0.0.1/internal');
    }

    public function test_settings_ui_exposes_file_and_url_knowledge_sources(): void
    {
        $view = file_get_contents(resource_path('views/admin/najm-hoda/settings.blade.php'));

        $this->assertStringContainsString('id="steward-upload-form"', $view);
        $this->assertStringContainsString('id="steward-url-form"', $view);
        $this->assertStringContainsString("route('admin.najm-hoda.steward.add-knowledge-url')", $view);
    }


    public function test_normal_steward_retrieval_includes_only_published_public_content_sources(): void
    {
        $author = User::factory()->create();
        $category = BlogCategory::create([
            'name' => 'راهنما',
            'slug' => 'guide',
            'is_active' => true,
            'order' => 1,
        ]);

        KbArticle::create([
            'title' => 'راهنمای عدالت مشارکتی',
            'slug' => 'justice-guide',
            'excerpt' => 'عدالت مشارکتی در ارث‌کوپ',
            'content' => 'متن منتشرشده پایگاه دانش درباره عدالت مشارکتی.',
            'status' => 'published',
            'published_at' => now(),
        ]);

        KbArticle::create([
            'title' => 'پیش‌نویس عدالت محرمانه',
            'slug' => 'justice-draft',
            'excerpt' => 'این متن نباید بازیابی شود',
            'content' => 'محتوای پیش‌نویس خصوصی',
            'status' => 'draft',
        ]);

        BlogPost::create([
            'title' => 'عدالت در اقتصاد تعاونی',
            'slug' => 'cooperative-justice',
            'excerpt' => 'مقاله عمومی بلاگ درباره عدالت',
            'content' => 'محتوای مقاله عمومی بلاگ ارث‌کوپ درباره عدالت مشارکتی.',
            'category_id' => $category->id,
            'user_id' => $author->id,
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        BlogPost::create([
            'title' => 'پیش‌نویس بلاگ عدالت',
            'slug' => 'draft-cooperative-justice',
            'excerpt' => 'نباید در پاسخ دیده شود',
            'content' => 'محتوای پیش‌نویس بلاگ',
            'category_id' => $category->id,
            'user_id' => $author->id,
            'status' => 'draft',
        ]);

        FaqQuestion::create([
            'title' => 'پرسش عدالت',
            'question' => 'عدالت مشارکتی چیست؟',
            'answer' => 'عدالت مشارکتی یعنی رعایت حق در مشارکت.',
            'is_published' => true,
            'status' => 'answered',
        ]);

        FaqQuestion::create([
            'title' => 'پرسش منتشرنشده عدالت',
            'question' => 'این پرسش نباید دیده شود',
            'answer' => 'پاسخ خصوصی',
            'is_published' => false,
            'status' => 'answered',
        ]);

        $agent = new class extends StewardAgent {
            public function exposeKnowledgeContext(string $question): string
            {
                return $this->knowledgeContextFor($question);
            }
        };

        $context = $agent->exposeKnowledgeContext('عدالت مشارکتی چیست؟');

        $this->assertStringContainsString('راهنمای عدالت مشارکتی', $context);
        $this->assertStringContainsString('عدالت در اقتصاد تعاونی', $context);
        $this->assertStringContainsString('پرسش عدالت', $context);

        $this->assertStringNotContainsString('پیش‌نویس عدالت محرمانه', $context);
        $this->assertStringNotContainsString('پیش‌نویس بلاگ عدالت', $context);
        $this->assertStringNotContainsString('پرسش منتشرنشده عدالت', $context);
    }

    public function test_steward_uses_public_blog_module_not_private_group_posts_as_global_knowledge(): void
    {
        $source = file_get_contents(app_path('Services/NajmHoda/Agents/StewardAgent.php'));

        $this->assertStringContainsString('use App\\Modules\\Blog\\Models\\Post as BlogPost;', $source);
        $this->assertStringContainsString('BlogPost::published()', $source);
        $this->assertStringNotContainsString('use App\\Models\\Blog;', $source);
    }


    public function test_retrieval_returns_a_snippet_around_the_actual_match_in_a_long_source(): void
    {
        $prefix = str_repeat('مقدمه نامرتبط ', 500);
        StewardKnowledgeFile::create([
            'title' => 'سند بلند',
            'original_filename' => 'long.txt',
            'file_path' => 'steward/knowledge/long.txt',
            'file_type' => 'txt',
            'file_size' => strlen($prefix) + 200,
            'extracted_content' => $prefix . ' ماده مالکیت خصوصی باید محترم شمرده شود و حق مالک قانونی محفوظ است.',
            'summary' => 'سند بلند',
            'search_priority' => 10,
            'is_active' => true,
        ]);

        $agent = new class extends StewardAgent {
            public function exposeKnowledgeContext(string $question): string
            {
                return $this->knowledgeContextFor($question);
            }
        };

        $context = $agent->exposeKnowledgeContext('مالکیت خصوصی چیست؟');

        $this->assertStringContainsString('مالکیت خصوصی باید محترم شمرده شود', $context);
        $this->assertLessThan(2500, mb_strlen($context));
    }

    public function test_retrieval_uses_meaningful_persian_terms_beyond_the_first_five_words(): void
    {
        StewardKnowledgeFile::create([
            'title' => 'سند حقوق',
            'original_filename' => 'rights.txt',
            'file_path' => 'steward/knowledge/rights.txt',
            'file_type' => 'txt',
            'file_size' => 200,
            'extracted_content' => 'در ارث‌کوپ مالکیت خصوصی باید محترم شمرده شود.',
            'summary' => 'حقوق',
            'search_priority' => 10,
            'is_active' => true,
        ]);

        $agent = new class extends StewardAgent {
            public function exposeKnowledgeContext(string $question): string
            {
                return $this->knowledgeContextFor($question);
            }
        };

        $context = $agent->exposeKnowledgeContext('لطفاً به من با توضیح روشن بگو مالکیت خصوصی چه جایگاهی دارد؟');

        $this->assertStringContainsString('مالکیت خصوصی', $context);
    }

    public function test_url_ingestor_rejects_credentials_and_non_web_ports_before_network_access(): void
    {
        $ingestor = new StewardKnowledgeUrlIngestor();

        try {
            $ingestor->ingest('https://user:secret@example.org/page');
            $this->fail('Credential-bearing URLs must be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('نام کاربری', $e->getMessage());
        }

        try {
            $ingestor->ingest('https://example.org:8443/page');
            $this->fail('Non-web ports must be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('پورت', $e->getMessage());
        }
    }

    public function test_steward_interaction_log_input_excludes_retrieved_knowledge_payload(): void
    {
        $agent = new class extends StewardAgent {
            public function exposeInteractionLogInput(string $prompt): string
            {
                return $this->interactionLogInput($prompt, []);
            }
        };

        $prompt = "سوال کاربر\n\n" . StewardAgent::KNOWLEDGE_CONTEXT_START
            . "\nمحتوای محرمانه سند\n"
            . StewardAgent::KNOWLEDGE_CONTEXT_END;

        $logged = $agent->exposeInteractionLogInput($prompt);

        $this->assertSame('سوال کاربر', $logged);
        $this->assertStringNotContainsString('محتوای محرمانه سند', $logged);
    }

    public function test_knowledge_upload_is_private_and_uses_collision_resistant_names(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/NajmHodaController.php'));

        $this->assertStringContainsString("Str::uuid()", $controller);
        $this->assertStringContainsString("storeAs('steward/knowledge', \$fileName, 'local')", $controller);
        $this->assertStringNotContainsString("storeAs('steward/knowledge', \$fileName, 'public')", $controller);
    }

    public function test_admin_knowledge_views_escape_untrusted_source_titles(): void
    {
        $settings = file_get_contents(resource_path('views/admin/najm-hoda/settings.blade.php'));
        $management = file_get_contents(resource_path('views/admin/najm-hoda/knowledge-files.blade.php'));

        $this->assertStringContainsString('escapeKnowledgeHtml', $settings);
        $this->assertStringNotContainsString('<span id="file-title-${file.id}">${file.title}</span>', $settings);
        $this->assertStringNotContainsString("onclick=\"editFileModal({{ \$file->id }}, '{{ \$file->title }}'", $management);
        $this->assertStringContainsString('data-title="{{ $file->title }}"', $management);
    }

    public function test_longtext_and_source_metadata_rollbacks_fail_closed_instead_of_truncating_or_null_breaking(): void
    {
        $longTextMigration = file_get_contents(database_path('migrations/2026_10_07_000100_expand_steward_knowledge_extracted_content.php'));
        $sourceMigration = file_get_contents(database_path('migrations/2026_10_07_000200_add_source_metadata_to_steward_knowledge_files.php'));

        $this->assertStringContainsString('LENGTH(extracted_content) > 65535', $longTextMigration);
        $this->assertStringContainsString('RuntimeException', $longTextMigration);
        $this->assertStringContainsString("where('source_type', 'url')", $sourceMigration);
        $this->assertStringContainsString('RuntimeException', $sourceMigration);
    }

    public function test_url_ingestor_has_streaming_download_limit_not_only_post_download_size_check(): void
    {
        $source = file_get_contents(app_path('Services/NajmHoda/Knowledge/StewardKnowledgeUrlIngestor.php'));

        $this->assertStringContainsString("'progress' =>", $source);
        $this->assertStringContainsString('MAX_BODY_BYTES', $source);
    }


    public function test_legacy_public_knowledge_artifacts_are_migrated_to_private_storage(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_10_07_000300_move_steward_knowledge_to_private_storage.php'));

        $this->assertStringContainsString("disk('public')->allFiles('steward/knowledge')", $migration);
        $this->assertStringContainsString("moveBetweenDisks('public', 'local'", $migration);
        $this->assertStringContainsString('hasUnsafeSourceRows', $migration);
        $this->assertStringContainsString('hasOversizedContent', $migration);
    }

    public function test_url_ingestion_logs_never_persist_full_query_or_credentials(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/NajmHodaController.php'));

        $this->assertStringContainsString('$safeSource', $controller);
        $this->assertStringNotContainsString("'url' => \$validated['source_url']", $controller);
    }

    public function test_steward_content_cache_observers_ignore_view_count_noise_and_cover_restores(): void
    {
        $blogObserver = file_get_contents(app_path('Observers/BlogObserver.php'));
        $kbObserver = file_get_contents(app_path('Observers/KbArticleObserver.php'));
        $sourceObserver = file_get_contents(app_path('Observers/StewardKnowledgeFileObserver.php'));

        $this->assertStringContainsString('$blog->wasChanged([', $blogObserver);
        $this->assertStringNotContainsString("'views_count'", $blogObserver);
        $this->assertStringContainsString('$article->wasChanged([', $kbObserver);
        $this->assertStringNotContainsString("'view_count'", $kbObserver);
        $this->assertStringContainsString('public function restored(StewardKnowledgeFile', $sourceObserver);
    }

}
