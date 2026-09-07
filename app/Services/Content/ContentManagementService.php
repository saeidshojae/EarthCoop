<?php

namespace App\Services\Content;

use App\Models\FaqQuestion;
use App\Models\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ContentManagementService
{
    /** @return array<string,mixed> */
    public function draftFaqAnswer(FaqQuestion $question, ?string $answer, ?string $category = null): array
    {
        if ((string) $question->status === 'answered' && filled($question->answer)) {
            return [
                'success' => false,
                'status' => 'skipped',
                'reason' => 'faq_already_answered',
                'faq_question_id' => (int) $question->id,
            ];
        }

        return [
            'success' => true,
            'status' => 'drafted',
            'entity_type' => 'faq_question',
            'entity_id' => (int) $question->id,
            'current' => [
                'title' => (string) $question->title,
                'question' => (string) $question->question,
                'category' => $question->category,
                'answer' => $question->answer,
                'status' => (string) $question->status,
                'is_published' => (bool) $question->is_published,
            ],
            'proposal' => [
                'answer' => $answer,
                'category' => $category ?? $question->category,
                'status' => 'answered',
                'requires_mutation' => true,
                'requires_approval_to_publish' => true,
            ],
        ];
    }

    /** @param array<string,mixed> $changes @return array<string,mixed> */
    public function draftPageUpdate(Page $page, array $changes): array
    {
        $allowed = [
            'title','content','template','meta_title','meta_description','show_in_header',
            'title_translations','content_translations','meta_title_translations','meta_description_translations',
        ];
        $proposal = array_intersect_key($changes, array_flip($allowed));

        if ($proposal === []) {
            return [
                'success' => false,
                'status' => 'invalid_context',
                'reason' => 'no_supported_page_changes',
                'page_id' => (int) $page->id,
            ];
        }

        return [
            'success' => true,
            'status' => 'drafted',
            'entity_type' => 'page',
            'entity_id' => (int) $page->id,
            'current' => [
                'title' => (string) $page->title,
                'slug' => (string) $page->slug,
                'content' => (string) $page->content,
                'template' => (string) $page->template,
                'is_published' => (bool) $page->is_published,
                'show_in_header' => (bool) $page->show_in_header,
            ],
            'proposal' => $proposal + [
                'requires_mutation' => true,
                'requires_approval_to_publish' => true,
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function publish(string $entityType, int $entityId): array
    {
        return DB::transaction(function () use ($entityType, $entityId): array {
            $entity = $this->findForUpdate($entityType, $entityId);

            if ($entityType === 'page') {
                if ((bool) $entity->is_published) {
                    return ['entity_type'=>$entityType,'entity_id'=>$entityId,'status'=>'already_published'];
                }
                $entity->forceFill(['is_published' => true])->save();
            } elseif ($entityType === 'faq_question') {
                if (! filled($entity->answer)) {
                    throw new RuntimeException('FAQ question cannot be published without an answer.');
                }
                if ((bool) $entity->is_published) {
                    return ['entity_type'=>$entityType,'entity_id'=>$entityId,'status'=>'already_published'];
                }
                $entity->forceFill([
                    'is_published' => true,
                    'status' => 'answered',
                    'answered_at' => $entity->answered_at ?: now(),
                ])->save();
            }

            return ['entity_type'=>$entityType,'entity_id'=>$entityId,'status'=>'published'];
        });
    }

    /** @return array<string,mixed> */
    public function delete(string $entityType, int $entityId): array
    {
        return DB::transaction(function () use ($entityType, $entityId): array {
            $entity = $this->findForUpdate($entityType, $entityId);
            $entity->delete();
            return ['entity_type'=>$entityType,'entity_id'=>$entityId,'status'=>'deleted'];
        });
    }

    public function find(string $entityType, int $entityId): Model
    {
        if ($entityId < 1) throw new InvalidArgumentException('content_entity_id_required');
        $class = $this->modelMap()[$entityType] ?? null;
        if (! $class) throw new InvalidArgumentException('unsupported_content_entity_type');
        return $class::query()->findOrFail($entityId);
    }

    protected function findForUpdate(string $entityType, int $entityId): Model
    {
        if ($entityId < 1) throw new InvalidArgumentException('content_entity_id_required');
        $class = $this->modelMap()[$entityType] ?? null;
        if (! $class) throw new InvalidArgumentException('unsupported_content_entity_type');
        return $class::query()->whereKey($entityId)->lockForUpdate()->firstOrFail();
    }

    /** @return array<string,class-string<Model>> */
    protected function modelMap(): array
    {
        return [
            'page' => Page::class,
            'faq_question' => FaqQuestion::class,
        ];
    }
}
