<?php

namespace App\Services\Legal;

use App\Models\NajmBaharAgreement;
use App\Models\Term;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LegalMarkdownImportService
{
    private const FILES = [
        'membership' => ['legal/earthcoop-membership-statute.fa.md', 'terms'],
        'terms' => ['legal/earthcoop-terms-of-use.fa.md', 'terms'],
        'najm-bahar' => ['legal/drafts/najm-bahar-agreement.fa.md', 'najm_bahar_agreements'],
    ];

    public function preview(string $slug): array
    {
        $item = self::FILES[$slug] ?? null;
        if (! $item) {
            throw ValidationException::withMessages(['slug' => 'سند قابل ورود نیست.']);
        }
        $path = resource_path($item[0]);
        $text = file_get_contents($path);
        $sections = preg_split('/(?=^##\s+)/mu', $text, -1, PREG_SPLIT_NO_EMPTY);
        $intro = array_shift($sections);
        preg_match('/^#\s+(.+)$/mu', $intro, $match);
        $title = trim($match[1] ?? $slug);
        $children = [];
        foreach ($sections as $section) {
            $lines = explode("\n", $section, 2);
            $children[] = [
                'title' => trim(preg_replace('/^##\s+/u', '', $lines[0])),
                'content' => trim($lines[1] ?? ''),
            ];
        }
        return ['slug' => $slug, 'source_type' => $item[1], 'title' => $title, 'content' => trim($intro), 'children' => $children];
    }

    public function importToNewRoot(string $slug): int
    {
        $data = $this->preview($slug);
        // A separate root is always created: never overwrite the legacy terms.
        // Idempotence is based on an exactly named imported root.
        return DB::transaction(function () use ($data) {
            $model = $data['source_type'] === 'terms' ? Term::class : NajmBaharAgreement::class;
            $existing = $model::query()->whereNull('parent_id')->where('title', $data['title'])->lockForUpdate()->first();
            if ($existing) {
                throw ValidationException::withMessages(['document' => 'سندی با این عنوان وجود دارد؛ برای جلوگیری از بازنویسی ناخواسته ابتدا آن را بررسی کنید.']);
            }

            $rootFields = ['title' => $data['title'], 'parent_id' => null];
            $rootFields[$data['source_type'] === 'terms' ? 'message' : 'content'] = $data['content'];
            if ($data['source_type'] !== 'terms') $rootFields['order'] = 0;
            $root = $model::create($rootFields);

            foreach ($data['children'] as $index => $child) {
                $fields = ['parent_id' => $root->id, 'title' => $child['title']];
                $fields[$data['source_type'] === 'terms' ? 'message' : 'content'] = $child['content'];
                if ($data['source_type'] !== 'terms') $fields['order'] = $index + 1;
                $model::create($fields);
            }
            return $root->id;
        });
    }
}
