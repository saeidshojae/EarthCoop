<?php

namespace App\Services\NajmHoda;

use App\Events\GroupFeedUpdated;
use App\Models\Blog;
use App\Models\Comment;
use App\Models\Group;
use App\Models\NajmHodaGroupConfig;
use App\Models\Reaction;
use App\Models\User;
use App\Services\GroupChat\GroupEventPublisher;
use Illuminate\Support\Facades\Cache;

/**
 * Structured private-widget reaction commands for Najm Hoda.
 *
 * The target and reaction type are resolved before confirmation and the exact
 * approved payload is executed afterwards. This avoids the historical
 * free-form parser changing the target/type between preview and execution.
 */
class NajmHodaPrivateGroupReactionCommandService extends NajmHodaGroupAssistantService
{
    /** @param array<string,mixed> $pageContext */
    public function intercept(User $requester, array $pageContext, string $message, ?int $conversationId = null): ?array
    {
        if ((string) ($pageContext['page_kind'] ?? '') !== 'group_chat') {
            return null;
        }

        $resource = is_array($pageContext['resource'] ?? null) ? $pageContext['resource'] : [];
        $groupId = (int) ($pageContext['resource_id'] ?? $resource['id'] ?? 0);
        if ($groupId <= 0) {
            return null;
        }

        $group = Group::query()->find($groupId);
        if (! $group) {
            return null;
        }

        $pendingKey = $this->pendingKey((int) $requester->id, $conversationId, $groupId);
        $pending = Cache::get($pendingKey);

        if (is_array($pending) && $this->isCancellation($message)) {
            Cache::forget($pendingKey);
            return $this->widgetResponse('درخواست ثبت واکنش لغو شد و هیچ تغییری در گروه انجام نشد.', 'cancelled', 'react');
        }

        if (is_array($pending) && $this->isConfirmation($message)) {
            Cache::forget($pendingKey);
            return $this->widgetExecutionResponse($this->executeConfirmed($requester, $group, $pending));
        }

        if (! $this->looksLikeReactionCommand($message)) {
            return null;
        }

        $this->ensureGroupAssistantSetup($group);
        $config = NajmHodaGroupConfig::query()->where('group_id', $group->id)->first();
        if (! $config) {
            return $this->widgetResponse('تنظیمات نجم هدا برای این گروه یافت نشد.', 'failed', 'react');
        }

        $config = $this->applyGlobalGroupAssistantOverrides($config);
        if (! $config->enabled) {
            return $this->widgetResponse('دستیار نجم هدا برای این گروه غیرفعال است.', 'blocked', 'react');
        }

        $policy = $this->getActionExecutorPolicy($config);
        $check = $this->policyGate->ensureActionExecutionAllowed($group, (int) $requester->id, $policy);
        if (! (bool) ($check['allowed'] ?? false)) {
            return $this->widgetResponse(
                (string) ($check['group_reply'] ?? 'شما مجوز تفویض واکنش به نجم هدا را ندارید.'),
                'blocked',
                'react'
            );
        }

        $payload = $this->resolvePayload($group, $message, $policy);
        if (! (bool) ($payload['valid'] ?? false)) {
            return $this->widgetResponse((string) ($payload['message'] ?? 'هدف یا نوع واکنش کامل نیست.'), 'needs_input', 'react');
        }

        Cache::put($pendingKey, [
            'action' => 'react',
            'group_id' => (int) $group->id,
            'requester_user_id' => (int) $requester->id,
            'target_type' => (string) $payload['target_type'],
            'target_id' => (int) $payload['target_id'],
            'reaction_type' => (int) $payload['reaction_type'],
        ], now()->addMinutes(15));

        $label = ((int) $payload['reaction_type']) === 1 ? 'لایک' : 'دیس‌لایک';
        $targetLabel = ((string) $payload['target_type']) === 'post' ? 'پست' : 'نظر';
        $groupName = trim((string) $group->name) ?: ('#' . $group->id);
        $preview = "نوع اقدام: ثبت واکنش توسط نجم هدا\n"
            . "هدف: {$targetLabel} #{$payload['target_id']}\n"
            . "واکنش: {$label}\n"
            . 'عامل سیستمی: نجم هدا';

        return $this->widgetResponse(
            "درخواست اجرایی برای گروه «{$groupName}» آماده شد.\n\n{$preview}\n\nاگر همین واکنش باید ثبت شود «تأیید» بفرستید. برای انصراف «لغو» بفرستید. تا قبل از تأیید هیچ تغییری در گروه اعمال نمی‌شود.",
            'awaiting_confirmation',
            'react'
        );
    }

    /** @return array<string,mixed> */
    protected function resolvePayload(Group $group, string $text, array $policy): array
    {
        $reactionType = $this->extractReactionType($text);
        if ($reactionType === null) {
            return ['valid' => false, 'message' => 'نوع واکنش را مشخص کنید؛ فعلاً «لایک» یا «دیس‌لایک» پشتیبانی می‌شود.'];
        }

        if (preg_match('/پست\s*#?(\d+)/u', $text, $match)) {
            if (! $this->policyGate->isCapabilityEnabled($policy, 'react_post')) {
                return ['valid' => false, 'message' => 'واکنش به پست توسط نجم هدا در این گروه غیرفعال است.'];
            }
            $post = Blog::query()->where('group_id', $group->id)->whereKey((int) $match[1])->first();
            if (! $post) {
                return ['valid' => false, 'message' => 'پست هدف در این گروه پیدا نشد.'];
            }
            return ['valid' => true, 'target_type' => 'post', 'target_id' => (int) $post->id, 'reaction_type' => $reactionType];
        }

        if (preg_match('/(?:کامنت|نظر)\s*#?(\d+)/u', $text, $match)) {
            if (! $this->policyGate->isCapabilityEnabled($policy, 'react_comment')) {
                return ['valid' => false, 'message' => 'واکنش به نظر توسط نجم هدا در این گروه غیرفعال است.'];
            }
            $comment = Comment::query()
                ->whereKey((int) $match[1])
                ->whereHas('blog', fn ($q) => $q->where('group_id', $group->id))
                ->first();
            if (! $comment) {
                return ['valid' => false, 'message' => 'نظر هدف در این گروه پیدا نشد.'];
            }
            return ['valid' => true, 'target_type' => 'comment', 'target_id' => (int) $comment->id, 'reaction_type' => $reactionType];
        }

        return [
            'valid' => false,
            'message' => 'هدف واکنش را با شناسه مشخص کنید؛ مثلاً «پست #8 را لایک کن» یا «نظر #12 را دیس‌لایک کن».',
        ];
    }

    /** @param array<string,mixed> $pending */
    protected function executeConfirmed(User $requester, Group $group, array $pending): array
    {
        $config = NajmHodaGroupConfig::query()->where('group_id', $group->id)->first();
        if (! $config) {
            return ['decision' => 'failed', 'reason' => 'group_assistant_config_missing', 'group_reply' => 'تنظیمات نجم هدا برای گروه یافت نشد.'];
        }

        $config = $this->applyGlobalGroupAssistantOverrides($config);
        $policy = $this->getActionExecutorPolicy($config);
        $check = $this->policyGate->ensureActionExecutionAllowed($group, (int) $requester->id, $policy);
        if (! (bool) ($check['allowed'] ?? false)) {
            return ['decision' => 'skipped', 'reason' => (string) ($check['reason'] ?? 'action_policy_denied'), 'group_reply' => (string) ($check['group_reply'] ?? 'مجوز اجرای اقدام وجود ندارد.')];
        }

        $targetType = (string) ($pending['target_type'] ?? '');
        $targetId = (int) ($pending['target_id'] ?? 0);
        $reactionType = (int) ($pending['reaction_type'] ?? -1);
        if (! in_array($reactionType, [0, 1], true) || ! in_array($targetType, ['post', 'comment'], true) || $targetId <= 0) {
            return ['decision' => 'failed', 'reason' => 'confirmed_reaction_payload_invalid', 'group_reply' => 'payload تأییدشده واکنش معتبر نیست.'];
        }

        $bot = $this->ensureBotUser();
        $execution = $this->groupActionExecutor->execute(
            'private_widget_group_reaction',
            [
                'group_id' => (int) $group->id,
                'requester_user_id' => (int) $requester->id,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'source' => 'najm_hoda_private_widget',
                'action' => 'react',
            ],
            (bool) ($policy['dry_run'] ?? false),
            function () use ($group, $bot, $targetType, $targetId, $reactionType, $policy): array {
                if ($targetType === 'post') {
                    if (! $this->policyGate->isCapabilityEnabled($policy, 'react_post')) {
                        return ['decision' => 'skipped', 'reason' => 'react_post_disabled', 'group_reply' => 'واکنش به پست توسط نجم هدا غیرفعال است.'];
                    }
                    $post = Blog::query()->where('group_id', $group->id)->whereKey($targetId)->first();
                    if (! $post) {
                        return ['decision' => 'failed', 'reason' => 'post_not_found', 'group_reply' => 'پست هدف دیگر وجود ندارد.'];
                    }

                    Reaction::query()->where('blog_id', $post->id)->where('user_id', $bot->id)->delete();
                    Reaction::create(['blog_id' => $post->id, 'user_id' => $bot->id, 'type' => $reactionType]);
                    $post->touch();
                    $likes = (int) $post->reactions()->where('type', 1)->count();
                    $dislikes = (int) $post->reactions()->where('type', 0)->count();
                    app(GroupEventPublisher::class)->publish(new GroupFeedUpdated((int) $group->id, 'post_reaction', [
                        'post_id' => (int) $post->id,
                        'likes' => $likes,
                        'dislikes' => $dislikes,
                        'system_authored' => true,
                        'system_actor_id' => (int) $bot->id,
                    ], 0));

                    return [
                        'decision' => 'executed',
                        'reason' => 'post_reacted_from_confirmed_payload',
                        'group_reply' => 'واکنش ' . ($reactionType === 1 ? 'لایک' : 'دیس‌لایک') . " روی پست {$post->id} ثبت شد.",
                        'context' => ['action' => 'react', 'target_type' => 'post', 'post_id' => (int) $post->id],
                    ];
                }

                if (! $this->policyGate->isCapabilityEnabled($policy, 'react_comment')) {
                    return ['decision' => 'skipped', 'reason' => 'react_comment_disabled', 'group_reply' => 'واکنش به نظر توسط نجم هدا غیرفعال است.'];
                }
                $comment = Comment::query()
                    ->whereKey($targetId)
                    ->whereHas('blog', fn ($q) => $q->where('group_id', $group->id))
                    ->first();
                if (! $comment) {
                    return ['decision' => 'failed', 'reason' => 'comment_not_found', 'group_reply' => 'نظر هدف دیگر وجود ندارد.'];
                }

                Reaction::query()->where('comment_id', $comment->id)->where('user_id', $bot->id)->delete();
                Reaction::create(['comment_id' => $comment->id, 'user_id' => $bot->id, 'type' => $reactionType, 'react_type' => 1]);
                $comment->touch();
                $comment->load(['reactions', 'blog']);
                app(GroupEventPublisher::class)->publish(new GroupFeedUpdated((int) $group->id, 'comment_reaction', [
                    'comment_id' => (int) $comment->id,
                    'blog_id' => (int) $comment->blog_id,
                    'comments_count' => (int) $comment->blog->comments()->count(),
                    'likes' => (int) $comment->reactions->where('type', 1)->count(),
                    'dislikes' => (int) $comment->reactions->where('type', 0)->count(),
                    'system_authored' => true,
                    'system_actor_id' => (int) $bot->id,
                ], 0));

                return [
                    'decision' => 'executed',
                    'reason' => 'comment_reacted_from_confirmed_payload',
                    'group_reply' => 'واکنش ' . ($reactionType === 1 ? 'لایک' : 'دیس‌لایک') . " روی نظر {$comment->id} ثبت شد.",
                    'context' => ['action' => 'react', 'target_type' => 'comment', 'comment_id' => (int) $comment->id],
                ];
            }
        );

        return $execution ?? ['decision' => 'failed', 'reason' => 'reaction_execution_failed', 'group_reply' => 'ثبت واکنش انجام نشد.'];
    }

    protected function looksLikeReactionCommand(string $message): bool
    {
        $plain = mb_strtolower(trim(strip_tags($message)));
        if ($plain === '') {
            return false;
        }
        foreach (['چطور', 'چگونه', 'راهنما', 'روش '] as $guidanceCue) {
            if (mb_stripos($plain, $guidanceCue) !== false) {
                return false;
            }
        }

        $hasReaction = false;
        foreach (['لایک', 'دیسلایک', 'دیس‌لایک', 'dislike', 'like', 'واکنش'] as $cue) {
            if (mb_stripos($plain, $cue) !== false) {
                $hasReaction = true;
                break;
            }
        }
        $hasCommand = false;
        foreach (['کن', 'بذار', 'بگذار', 'ثبت کن', 'واکنش بده', 'react'] as $cue) {
            if (mb_stripos($plain, $cue) !== false) {
                $hasCommand = true;
                break;
            }
        }

        return $hasReaction && $hasCommand;
    }

    protected function extractReactionType(string $text): ?int
    {
        $plain = mb_strtolower($text);
        if (mb_stripos($plain, 'دیسلایک') !== false || mb_stripos($plain, 'دیس‌لایک') !== false || mb_stripos($plain, 'dislike') !== false) {
            return 0;
        }
        if (mb_stripos($plain, 'لایک') !== false || mb_stripos($plain, 'like') !== false) {
            return 1;
        }
        return null;
    }

    protected function isConfirmation(string $message): bool
    {
        $plain = mb_strtolower(trim(strip_tags($message)));
        return in_array($plain, ['تایید', 'تأیید', 'تایید کن', 'تأیید کن', 'بله', 'انجام بده', 'اجرا کن', 'اوکی', 'ok', 'yes', 'confirm'], true);
    }

    protected function isCancellation(string $message): bool
    {
        $plain = mb_strtolower(trim(strip_tags($message)));
        return in_array($plain, ['لغو', 'لغو کن', 'بیخیال', 'بی‌خیال', 'انصراف', 'cancel', 'no'], true);
    }

    protected function pendingKey(int $userId, ?int $conversationId, int $groupId): string
    {
        $conversation = $conversationId && $conversationId > 0 ? $conversationId : 0;
        return "najm_hoda:private_group_reaction:user:{$userId}:conversation:{$conversation}:group:{$groupId}";
    }

    /** @return array<string,mixed> */
    protected function widgetResponse(string $message, string $status, string $action = ''): array
    {
        return [
            'success' => true,
            'message' => $message,
            'agent' => 'runtime',
            'agent_name' => 'نجم‌هدا',
            'agent_icon' => '🛡️',
            'suggestions' => [],
            'private_group_action' => true,
            'action' => $action,
            'action_status' => $status,
        ];
    }

    /** @param array<string,mixed> $result */
    protected function widgetExecutionResponse(array $result): array
    {
        $decision = (string) ($result['decision'] ?? 'failed');
        $detail = trim((string) ($result['group_reply'] ?? ''));
        if ($decision === 'executed') {
            $message = 'انجام شد. واکنش تأییدشده ثبت شد و گفت‌وگوی مدیریتی فقط در همین چت خصوصی باقی ماند.';
            if ($detail !== '') {
                $message .= "\n\n{$detail}";
            }
            return $this->widgetResponse($message, 'executed', 'react');
        }
        return $this->widgetResponse($detail !== '' ? $detail : 'ثبت واکنش انجام نشد.', $decision ?: 'failed', 'react');
    }
}
