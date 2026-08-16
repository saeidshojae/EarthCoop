<?php

namespace App\Services\NajmHoda;

use App\Models\Group;
use App\Models\Message;
use App\Models\NajmHodaGroupConfig;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Private, page-aware command surface for Najm Hoda group operations.
 *
 * Management/user conversation stays in the private Najm Hoda widget. Only
 * the resulting group artifact is published by the existing group assistant
 * execution engine. The system bot remains an is_system identity and is never
 * treated as a cooperative/economic member.
 */
class NajmHodaPrivateGroupCommandService extends NajmHodaGroupAssistantService
{
    /**
     * Intercept only explicit group actions or a confirmation/cancellation of a
     * previously proposed action. Ordinary questions continue to the LLM.
     *
     * @param array<string, mixed> $pageContext
     * @return array<string, mixed>|null
     */
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

        $pendingKey = $this->pendingKey($requester->id, $conversationId, $groupId);
        $pending = Cache::get($pendingKey);

        if (is_array($pending) && $this->isCancellation($message)) {
            Cache::forget($pendingKey);
            return $this->widgetResponse('درخواست اجرایی لغو شد و هیچ تغییری در گروه انجام نشد.', 'cancelled');
        }

        if (is_array($pending) && $this->isConfirmation($message)) {
            Cache::forget($pendingKey);
            $result = $this->executeConfirmedWidgetCommand($requester, $group, (string) ($pending['command'] ?? ''));
            return $this->widgetExecutionResponse($result);
        }

        // Questions such as «چطور نظرسنجی بسازم؟» are guidance requests, not
        // execution requests. They must continue to the grounded assistant.
        if (!$this->looksLikeExecutionRequest($message)) {
            return null;
        }

        $plan = $this->planWidgetCommand($requester, $group, $message);
        if ($plan === null) {
            return null;
        }

        if (!(bool) ($plan['allowed'] ?? false)) {
            return $this->widgetResponse(
                (string) ($plan['message'] ?? 'شما مجوز اجرای این اقدام را در این گروه ندارید.'),
                'blocked'
            );
        }

        Cache::put($pendingKey, [
            'group_id' => $group->id,
            'requester_user_id' => $requester->id,
            'action' => (string) ($plan['action'] ?? ''),
            'command' => $message,
        ], now()->addMinutes(15));

        $preview = (string) ($plan['preview'] ?? 'درخواست آماده اجرا است.');
        $groupName = trim((string) $group->name) ?: ('#' . $group->id);

        return $this->widgetResponse(
            "درخواست اجرایی برای گروه «{$groupName}» آماده شد.\n\n{$preview}\n\nاگر درست است «تأیید» بفرستید. برای انصراف «لغو» بفرستید. تا قبل از تأیید هیچ تغییری در گروه اعمال نمی‌شود.",
            'awaiting_confirmation',
            (string) ($plan['action'] ?? '')
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function planWidgetCommand(User $requester, Group $group, string $text): ?array
    {
        $this->ensureGroupAssistantSetup($group);
        $config = NajmHodaGroupConfig::query()->where('group_id', $group->id)->first();
        if (!$config) {
            return null;
        }

        $config = $this->applyGlobalGroupAssistantOverrides($config);
        if (!$config->enabled) {
            return [
                'allowed' => false,
                'message' => 'دستیار نجم هدا برای این گروه غیرفعال است.',
            ];
        }

        $intent = $this->inferGroupActionIntent($text);
        if ($intent === null) {
            return null;
        }

        $policy = $this->getActionExecutorPolicy($config);
        $check = $this->policyGate->ensureActionExecutionAllowed($group, (int) $requester->id, $policy);
        if (!(bool) ($check['allowed'] ?? false)) {
            return [
                'allowed' => false,
                'action' => $intent,
                'message' => (string) ($check['group_reply'] ?? 'شما مجوز اجرای این اقدام را در این گروه ندارید.'),
            ];
        }

        return [
            'allowed' => true,
            'action' => $intent,
            'preview' => $this->buildGroupActionProposalReply($text),
        ];
    }

    /**
     * Reuse the existing group-assistant parser/executor; no management
     * conversation is written to the group feed.
     *
     * @return array<string, mixed>
     */
    protected function executeConfirmedWidgetCommand(User $requester, Group $group, string $text): array
    {
        $this->ensureGroupAssistantSetup($group);
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

        $bot = $this->ensureBotUser();
        $trigger = new Message();
        $trigger->forceFill([
            'group_id' => $group->id,
            'user_id' => $requester->id,
            'message' => $text,
        ]);
        $trigger->setRelation('group', $group);

        $execution = $this->groupActionExecutor->execute(
            'private_widget_group_action',
            [
                'group_id' => $group->id,
                'requester_user_id' => $requester->id,
                'source' => 'najm_hoda_private_widget',
            ],
            (bool) ($policy['dry_run'] ?? false),
            fn (): ?array => $this->executeStructuredGroupAction($group, $trigger, $bot, $text, $policy)
        );

        return $execution ?? [
            'decision' => 'failed',
            'reason' => 'action_parse_failed',
            'group_reply' => 'نتوانستم جزئیات کافی برای اجرای این درخواست استخراج کنم. درخواست را با جزئیات بیشتری بنویسید.',
        ];
    }

    protected function looksLikeExecutionRequest(string $message): bool
    {
        $plain = mb_strtolower(trim(strip_tags($message)));
        if ($plain === '') {
            return false;
        }

        foreach (['چطور', 'چگونه', 'روش ', 'مراحل ', 'راهنما', 'آیا می‌توان', 'آیا میتون', 'آیا می‌تون'] as $guidanceCue) {
            if (mb_stripos($plain, $guidanceCue) !== false) {
                return false;
            }
        }

        $executionCues = [
            'بساز', 'ایجاد کن', 'منتشر کن', 'ثبت کن', 'بذار', 'بگذار', 'بزار',
            'برگزار کن', 'راه بنداز', 'راه بینداز', 'بنویس', 'کامنت بذار',
            'نظر بذار', 'واکنش بده', 'لایک کن', 'دیسلایک کن',
            'create ', 'publish ', 'add ', 'react ',
        ];

        return $this->containsAnyKeyword($plain, $executionCues)
            && $this->inferGroupActionIntent($message) !== null;
    }

    protected function isConfirmation(string $message): bool
    {
        $plain = mb_strtolower(trim(strip_tags($message)));
        return in_array($plain, [
            'تایید', 'تأیید', 'تایید کن', 'تأیید کن', 'بله', 'بله تایید', 'بله تأیید',
            'انجام بده', 'اجرا کن', 'اوکی', 'ok', 'yes', 'confirm',
        ], true);
    }

    protected function isCancellation(string $message): bool
    {
        $plain = mb_strtolower(trim(strip_tags($message)));
        return in_array($plain, ['لغو', 'لغو کن', 'بیخیال', 'بی‌خیال', 'انصراف', 'cancel', 'no'], true);
    }

    protected function pendingKey(int $userId, ?int $conversationId, int $groupId): string
    {
        $conversation = $conversationId && $conversationId > 0 ? $conversationId : 0;
        return "najm_hoda:private_group_action:user:{$userId}:conversation:{$conversation}:group:{$groupId}";
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    protected function widgetExecutionResponse(array $result): array
    {
        $decision = (string) ($result['decision'] ?? 'failed');
        $reason = (string) ($result['reason'] ?? 'unknown');
        $detail = trim((string) ($result['group_reply'] ?? ''));

        if ($decision === 'executed') {
            $message = 'انجام شد. نتیجه‌ی اقدام در گروه منتشر شد و گفت‌وگوی مدیریتی فقط در همین چت خصوصی باقی ماند.';
            if ($detail !== '') {
                $message .= "\n\n{$detail}";
            }
            return $this->widgetResponse($message, 'executed', (string) data_get($result, 'context.action', ''));
        }

        if ($decision === 'proposed') {
            return $this->widgetResponse($detail !== '' ? $detail : 'اقدام فقط به‌صورت پیشنهاد ثبت شد و هنوز اجرا نشده است.', 'proposed');
        }

        return $this->widgetResponse(
            $detail !== '' ? $detail : "اقدام اجرا نشد ({$reason}).",
            $decision !== '' ? $decision : 'failed'
        );
    }
}
