<?php

namespace Tests\Feature\NajmHoda;

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use App\Models\StewardKnowledgeFile;
use App\Models\User;
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
        Storage::fake('public');

        $this->withoutMiddleware([
            AdminMiddleware::class,
            PermissionMiddleware::class,
        ]);

        $this->actingAs(User::factory()->create());

        $content = str_repeat('قانون اساسی ارث‌کوپ و عدالت زمین. ', 4000);
        $upload = UploadedFile::fake()->createWithContent('constitution.txt', $content);

        $response = $this->postJson(route('admin.najm-hoda.steward.upload-knowledge'), [
            'title' => 'قانون اساسی ارث‌کوپ',
            'knowledge_file' => $upload,
            'search_priority' => 10,
        ]);

        $response->assertCreated()->assertJsonPath('success', true);

        $stored = StewardKnowledgeFile::where('title', 'قانون اساسی ارث‌کوپ')->firstOrFail();
        $this->assertSame($content, $stored->extracted_content);
        Storage::disk('public')->assertExists($stored->file_path);
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

}
