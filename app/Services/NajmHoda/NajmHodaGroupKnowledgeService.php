<?php

namespace App\Services\NajmHoda;

use App\Models\Blog;
use App\Models\Group;
use App\Models\Message;
use App\Models\NajmHodaGroupActionItem;
use App\Models\Poll;
use Carbon\CarbonInterface;

/**
 * Read-only, server-grounded knowledge snapshot for a group and time window.
 *
 * This is intentionally model-independent: it collects the factual source set
 * that later summary/minutes/action-item synthesis may use. The LLM is never
 * allowed to invent group activity that is absent from this snapshot.
 */
class NajmHodaGroupKnowledgeService
{
    /**
     * @return array<string,mixed>
     */
    public function snapshot(Group $group, CarbonInterface $from, CarbonInterface $to, int $limitPerType = 100): array
    {
        $limit = max(1, min($limitPerType, 250));

        $messages = Message::query()
            ->where('group_id', $group->id)
            ->whereBetween('created_at', [$from, $to])
            ->with('user:id,first_name,last_name,email')
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Message $message): array => [
                'id' => (int) $message->id,
                'author' => $this->userName($message->user),
                'text' => $this->cleanText((string) $message->message, 1200),
                'created_at' => optional($message->created_at)->toIso8601String(),
            ])->values()->all();

        $posts = Blog::query()
            ->where('group_id', $group->id)
            ->whereBetween('created_at', [$from, $to])
            ->with('user:id,first_name,last_name,email')
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Blog $post): array => [
                'id' => (int) $post->id,
                'author' => $this->userName($post->user),
                'title' => $this->cleanText((string) $post->title, 300),
                'text' => $this->cleanText((string) $post->content, 1800),
                'created_at' => optional($post->created_at)->toIso8601String(),
            ])->values()->all();

        $polls = Poll::query()
            ->where('group_id', $group->id)
            ->whereBetween('created_at', [$from, $to])
            ->with(['options:id,poll_id,text'])
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Poll $poll): array => [
                'id' => (int) $poll->id,
                'question' => $this->cleanText((string) $poll->question, 500),
                'options' => $poll->options->pluck('text')->map(fn ($value) => $this->cleanText((string) $value, 250))->values()->all(),
                'is_active' => (bool) $poll->is_active,
                'expires_at' => optional($poll->expires_at)->toIso8601String(),
                'created_at' => optional($poll->created_at)->toIso8601String(),
            ])->values()->all();

        $actionItems = NajmHodaGroupActionItem::query()
            ->where('group_id', $group->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (NajmHodaGroupActionItem $item): array => [
                'id' => (int) $item->id,
                'title' => $this->cleanText((string) $item->title, 500),
                'details' => $this->cleanText((string) $item->details, 1000),
                'assignee_name' => $this->cleanText((string) $item->assignee_name, 250),
                'priority' => (string) $item->priority,
                'status' => (string) $item->status,
                'due_at' => optional($item->due_at)->toIso8601String(),
                'created_at' => optional($item->created_at)->toIso8601String(),
            ])->values()->all();

        return [
            'group' => [
                'id' => (int) $group->id,
                'name' => trim((string) $group->name),
            ],
            'window' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
            'counts' => [
                'messages' => count($messages),
                'posts' => count($posts),
                'polls' => count($polls),
                'action_items' => count($actionItems),
            ],
            'messages' => $messages,
            'posts' => $posts,
            'polls' => $polls,
            'action_items' => $actionItems,
        ];
    }

    private function cleanText(string $value, int $limit): string
    {
        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
        return mb_substr($text, 0, $limit);
    }

    private function userName($user): string
    {
        if (! $user) {
            return '';
        }
        $name = trim(((string) ($user->first_name ?? '')) . ' ' . ((string) ($user->last_name ?? '')));
        return $name !== '' ? $name : (string) ($user->email ?? '');
    }
}
