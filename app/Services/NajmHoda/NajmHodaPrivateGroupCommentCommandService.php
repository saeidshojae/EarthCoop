<?php

namespace App\Services\NajmHoda;

use App\Events\CommentCreated;
use App\Models\Blog;
use App\Models\Comment;
use App\Models\Group;
use App\Models\NajmHodaGroupConfig;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Structured private-widget command surface for system-authored group comments.
 *
 * This interceptor deliberately runs before the generic group command parser so
 * phrases such as «روی پست #8 یک نظر بذار» cannot be misclassified as creating
 * a new post merely because the target noun contains «پست».
 */
class NajmHodaPrivateGroupCommentCommandService extends NajmHodaGroupAssistantService
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
        if (!$group) {
            return null;
        }

        $pendingKey = $this->pendingKey((int) $requester->id, $conversationId, $groupId);
        $pending = Cache::get($pendingKey);

        if (is_array($pending) && $this->isCancellation($message)) {
            Cache::forget($pendingKey);
            return $this->widgetResponse('درخواست ثبت نظر لغو شد و هیچ تغییری در گروه انجام نشد.', 'cancelled', 'create_comment');
        }

        if (is_array($pending) && $this->isConfirmation($message)) {
            Cache::forget($pendingKey);
            return $this->widgetExecutionResponse($this->executeConfirmed($requester, $group, $pending));
        }

        if (!$this->looksLikeCommentCommand($message)) {
            return null;
        }

        $this->ensureGroupAssistantSetup($group);
        $config = NajmHodaGroupConfig::query()->where('group_id', $group->id)->first();
        if (!$config) {
            return $this->widgetResponse('تنظیمات نجم هدا برای این گروه یافت نشد.', 'failed', 'create_comment');
        }

        $config = $this->applyGlobalGroupAssistantOverrides($config);
        if (!$config->enabled) {
            return $this->widgetResponse('دستیار نجم هدا برای این گروه غیرفعال است.', 'blocked', 'create_comment');
        }

        $policy = $this->getActionExecutorPolicy($config);
        $check = $this->policyGate->ensureActionExecutionAllowed($group, (int) $requester->id, $policy);
        if (!(bool) ($check['allowed'] ?? false)) {
            return $this->widgetResponse(
                (string) ($check['group_reply'] ?? 'شما مجوز تفویض ثبت نظر به نجم هدا را ندارید.'),
                'blocked',
                'create_comment'
            );
        }

        if (!$this->policyGate->isCapabilityEnabled($policy, 'create_comment')) {
            return $this->widgetResponse('ثبت نظر توسط نجم هدا در این گروه غیرفعال است.', 'blocked', 'create_comment');
        }

        $payload = $this->resolvePayload($requester, $group, $message);
        if (!(bool) ($payload['valid'] ?? false)) {
            return $this->widgetResponse(
                (string) ($payload['message'] ?? 'هدف یا متن نظر کامل نیست.'),
                'needs_input',
                'create_comment'
            );
        }

        $post = $payload['post'];
        $commentText = (string) $payload['comment_text'];
        Cache::put($pendingKey, [
            'action' => 'create_comment',
            'group_id' => (int) $group->id,
            'requester_user_id' => (int) $requester->id,
            'post_id' => (int) $post->id,
            'comment_text' => $commentText,
        ], now()->addMinutes(15));

        $groupName = trim((string) $group->name) ?: ('#' . $group->id);
        $postTitle = trim((string) $post->title) ?: ('پست #' . $post->id);
        $preview = "نوع اقدام: ثبت نظر توسط نجم هدا\n"
            . "هدف: پست #{$post->id} — {$postTitle}\n"
            . "متن نظر: {$commentText}\n"
            . 'منتشرکننده سیستمی: نجم هدا';

        return $this->widgetResponse(
            "درخواست اجرایی برای گروه «{$groupName}» آماده شد.\n\n{$preview}\n\nاگر همین نظر باید ثبت شود «تأیید» بفرستید. برای انصراف «لغو» بفرستید. تا قبل از تأیید هیچ تغییری در گروه اعمال نمی‌شود.",
            'awaiting_confirmation',
            'create_comment'
        );
    }

    /** @return array<string,mixed> */
    protected function resolvePayload(User $requester, Group $group, string $text): array
    {
        $post = null;
        if (preg_match('/پست\s*#?(\d+)/u', $text, $match)) {
            $post = Blog::query()
                ->where('group_id', $group->id)
                ->whereKey((int) $match[1])
                ->first();
        } elseif (mb_stripos($text, 'آخرین پست') !== false || mb_stripos($text, 'پست آخر') !== false) {
            $post = Blog::query()->where('group_id', $group->id)->latest('id')->first();
        } elseif (mb_stripos($text, 'پست من') !== false || mb_stripos($text, 'پستم') !== false) {
            $post = Blog::query()
                ->where('group_id', $group->id)
                ->where('user_id', $requester->id)
                ->latest('id')
                ->first();
        }

        if (!$post) {
            return [
                'valid' => false,
                'message' => 'پست هدف را مشخص کنید؛ مثلاً «روی پست #8 یک نظر بذار: ...» یا «روی آخرین پست نظر بذار: ...».',
            ];
        }

        $commentText = '';
        $patterns = [
            '/(?:یک\s+)?(?:نظر|کامنت)\s+(?:بذار|بگذار|بزار|ثبت کن|بنویس)\s*[:：]\s*(.+)$/us',
            '/(?:نظر|کامنت)\s*[:：]\s*(.+)$/us',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $commentText = trim((string) ($match[1] ?? ''));
                break;
            }
        }

        if ($commentText === '') {
            return [
                'valid' => false,
                'message' => 'متن نظر را صریحاً مشخص کنید؛ مثلاً «روی پست #8 یک نظر بذار: متن نظر».',
            ];
        }

        return [
            'valid' => true,
            'post' => $post,
            'comment_text' => $commentText,
        ];
    }

    /** @param array<string,mixed> $pending */
    protected function executeConfirmed(User $requester, Group $group, array $pending): array
    {
        $config = NajmHodaGroupConfig::query()->where('group_id', $group->id)->first();
        if (!$config) {
            return ['decision' => 'failed', 'reason' => 'group_assistant_config_missing', 'group_reply' => 'تنظیمات نجم هدا برای گروه یافت نشد.'];
        }

        $config = $this->applyGlobalGroupAssistantOverrides($config);
        $policy = $this->getActionExecutorPolicy($config);
        $check = $this->policyGate->ensureActionExecutionAllowed($group, (int) $requester->id, $policy);
        if (!(bool) ($check['allowed'] ?? false)) {
            return [
                'decision' => 'skipped',
                'reason' => (string) ($check['reason'] ?? 'action_policy_denied'),
                'group_reply' => (string) ($check['group_reply'] ?? 'مجوز اجرای اقدام وجود ندارد.'),
            ];
        }
        if (!$this->policyGate->isCapabilityEnabled($policy, 'create_comment')) {
            return ['decision' => 'skipped', 'reason' => 'create_comment_disabled', 'group_reply' => 'ثبت نظر توسط نجم هدا در این گروه غیرفعال است.'];
        }

        $postId = (int) ($pending['post_id'] ?? 0);
        $commentText = trim((string) ($pending['comment_text'] ?? ''));
        $post = Blog::query()->where('group_id', $group->id)->whereKey($postId)->first();
        if (!$post || $commentText === '') {
            return ['decision' => 'failed', 'reason' => 'confirmed_comment_payload_invalid', 'group_reply' => 'پست هدف یا متن نظر دیگر معتبر نیست.'];
        }

        $bot = $this->ensureBotUser();
        $execution = $this->groupActionExecutor->execute(
            'private_widget_group_comment',
            [
                'group_id' => (int) $group->id,
                'requester_user_id' => (int) $requester->id,
                'post_id' => (int) $post->id,
                'source' => 'najm_hoda_private_widget',
                'action' => 'create_comment',
            ],
            (bool) ($policy['dry_run'] ?? false),
            function () use ($post, $group, $bot, $commentText): array {
                $comment = Comment::create([
                    'user_id' => (int) $bot->id,
                    'blog_id' => (int) $post->id,
                    'message' => $commentText,
                ]);
                event(new CommentCreated($comment, $post, $group, $bot));

                return [
                    'decision' => 'executed',
                    'reason' => 'comment_created_from_confirmed_payload',
                    'group_reply' => "نظر روی پست {$post->id} ثبت شد. شناسه نظر: {$comment->id}",
                    'context' => [
                        'action' => 'create_comment',
                        'post_id' => (int) $post->id,
                        'comment_id' => (int) $comment->id,
                    ],
                ];
            }
        );

        return $execution ?? ['decision' => 'failed', 'reason' => 'comment_execution_failed', 'group_reply' => 'ثبت نظر انجام نشد.'];
    }

    protected function looksLikeCommentCommand(string $message): bool
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

        $hasCommentNoun = mb_stripos($plain, 'نظر') !== false || mb_stripos($plain, 'کامنت') !== false || mb_stripos($plain, 'comment') !== false;
        $hasWriteVerb = false;
        foreach (['بذار', 'بگذار', 'بزار', 'ثبت کن', 'بنویس', 'add ', 'write '] as $verb) {
            if (mb_stripos($plain, $verb) !== false) {
                $hasWriteVerb = true;
                break;
            }
        }

        return $hasCommentNoun && $hasWriteVerb;
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
        return "najm_hoda:private_group_comment:user:{$userId}:conversation:{$conversation}:group:{$groupId}";
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
            $message = 'انجام شد. نتیجه‌ی اقدام در گروه منتشر شد و گفت‌وگوی مدیریتی فقط در همین چت خصوصی باقی ماند.';
            if ($detail !== '') {
                $message .= "\n\n{$detail}";
            }
            return $this->widgetResponse($message, 'executed', 'create_comment');
        }

        return $this->widgetResponse($detail !== '' ? $detail : 'ثبت نظر انجام نشد.', $decision ?: 'failed', 'create_comment');
    }
}
