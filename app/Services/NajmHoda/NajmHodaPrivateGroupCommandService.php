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
            $result = $this->executeConfirmedWidgetRequest($requester, $group, $pending);
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

        if ((bool) ($plan['needs_input'] ?? false)) {
            return $this->widgetResponse(
                (string) ($plan['message'] ?? 'برای اجرای این درخواست به اطلاعات بیشتری نیاز دارم.'),
                'needs_input',
                (string) ($plan['action'] ?? '')
            );
        }

        if (!(bool) ($plan['allowed'] ?? false)) {
            return $this->widgetResponse(
                (string) ($plan['message'] ?? 'شما مجوز اجرای این اقدام را در این گروه ندارید.'),
                'blocked',
                (string) ($plan['action'] ?? '')
            );
        }

        Cache::put($pendingKey, [
            'group_id' => $group->id,
            'requester_user_id' => $requester->id,
            'action' => (string) ($plan['action'] ?? ''),
            'command' => $message,
            // This structured payload is produced by the same parser that
            // produced the preview. Confirmation therefore approves the exact
            // data that will be persisted, not a second parse of free-form text.
            'payload' => is_array($plan['payload'] ?? null) ? $plan['payload'] : [],
        ], now()->addMinutes(15));

        $preview = (string) ($plan['preview'] ?? 'درخواست آماده اجرا است.');
        $groupName = trim((string) $group->name) ?: ('#' . $group->id);

        return $this->widgetResponse(
            "درخواست اجرایی برای گروه «{$groupName}» آماده شد.\n\n{$preview}\n\nاگر همین مورد باید منتشر شود «تأیید» بفرستید. برای انصراف «لغو» بفرستید. تا قبل از تأیید هیچ تغییری در گروه اعمال نمی‌شود.",
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

        $preview = $this->buildPrivateActionPreview($intent, $text);
        if (!(bool) ($preview['valid'] ?? false)) {
            return [
                'allowed' => true,
                'needs_input' => true,
                'action' => $intent,
                'message' => (string) ($preview['message'] ?? 'اطلاعات این درخواست کامل نیست.'),
            ];
        }

        return [
            'allowed' => true,
            'action' => $intent,
            'preview' => (string) ($preview['preview'] ?? ''),
            'payload' => is_array($preview['payload'] ?? null) ? $preview['payload'] : [
                'action' => $intent,
                'command' => $text,
            ],
        ];
    }

    /**
     * Private widget commands are deliberately stricter than the historical
     * public-chat parser: Najm Hoda must not invent user-visible content before
     * asking for confirmation.
     *
     * @return array{valid:bool,preview?:string,message?:string,payload?:array<string,mixed>}
     */
    protected function buildPrivateActionPreview(string $intent, string $text): array
    {
        if ($intent === 'create_poll') {
            $question = $this->extractLabeledValue($text, ['سوال', 'سؤال', 'پرسش'], ['گزینه', 'گزینه‌ها', 'گزینه ها', 'مهلت']);
            $optionsRaw = $this->extractLabeledValue($text, ['گزینه', 'گزینه‌ها', 'گزینه ها'], ['مهلت']);
            $options = $this->splitOptions($optionsRaw);

            if ($question === '' || count($options) < 2) {
                return [
                    'valid' => false,
                    'message' => "برای ساخت نظرسنجی، سؤال و حداقل دو گزینه را مشخص کنید.\nمثال: «یک نظرسنجی بساز | سوال: بهترین زمان جلسه؟ | گزینه‌ها: شنبه، یکشنبه | مهلت: 3»",
                ];
            }

            $days = 3;
            if (preg_match('/مهلت\s*[:：]\s*(\d+)/u', $text, $match)) {
                $days = max(1, min((int) $match[1], 90));
            }

            return [
                'valid' => true,
                'preview' => implode("\n", [
                    'نوع اقدام: ایجاد نظرسنجی',
                    'سؤال: ' . $question,
                    'گزینه‌ها: ' . implode('، ', $options),
                    'مدت فعال بودن: ' . $days . ' روز',
                    'منتشرکننده سیستمی: نجم هدا',
                ]),
                'payload' => [
                    'action' => 'create_poll',
                    'question' => $question,
                    'options' => $options,
                    'days' => $days,
                ],
            ];
        }

        if ($intent === 'create_post') {
            $title = $this->extractLabeledValue($text, ['عنوان'], ['متن']);
            $content = $this->extractLabeledValue($text, ['متن'], []);

            if ($content === '') {
                return [
                    'valid' => false,
                    'message' => "برای انتشار پست، متن پست را صریحاً مشخص کنید تا نجم هدا چیزی را از خودش حدس نزند.\nمثال: «یک پست بساز | عنوان: گزارش جلسه | متن: ...»",
                ];
            }

            if ($title === '') {
                $title = mb_substr(trim(strip_tags($content)), 0, 70);
            }

            return [
                'valid' => true,
                'preview' => implode("\n", [
                    'نوع اقدام: انتشار پست',
                    'عنوان: ' . $title,
                    'متن: ' . mb_substr($content, 0, 500),
                    'منتشرکننده سیستمی: نجم هدا',
                ]),
                'payload' => [
                    'action' => 'create_post',
                    'title' => $title,
                    'content' => $content,
                ],
            ];
        }

        if ($intent === 'create_comment') {
            $target = 'محتوای مشخص‌شده در فرمان';
            if (preg_match('/پست\s*#?(\d+)/u', $text, $match)) {
                $target = 'پست #' . $match[1];
            } elseif (mb_stripos($text, 'آخرین پست') !== false || mb_stripos($text, 'پست آخر') !== false) {
                $target = 'آخرین پست گروه';
            } elseif (mb_stripos($text, 'پست من') !== false || mb_stripos($text, 'پستم') !== false) {
                $target = 'آخرین پست شما در گروه';
            }

            return [
                'valid' => true,
                'preview' => "نوع اقدام: ثبت نظر توسط نجم هدا\nهدف: {$target}\nجزئیات فرمان: " . mb_substr(trim(strip_tags($text)), 0, 500),
                'payload' => [
                    'action' => 'create_comment',
                    'command' => $text,
                ],
            ];
        }

        if ($intent === 'react') {
            return [
                'valid' => true,
                'preview' => "نوع اقدام: ثبت واکنش توسط نجم هدا\nجزئیات فرمان: " . mb_substr(trim(strip_tags($text)), 0, 500),
                'payload' => [
                    'action' => 'react',
                    'command' => $text,
                ],
            ];
        }

        return [
            'valid' => false,
            'message' => 'این نوع اقدام هنوز برای اجرای خصوصی در گروه پشتیبانی نمی‌شود.',
        ];
    }

    protected function extractLabeledValue(string $text, array $labels, array $stopLabels): string
    {
        $labelPattern = implode('|', array_map(fn (string $value): string => preg_quote($value, '/'), $labels));
        $stopPattern = implode('|', array_map(fn (string $value): string => preg_quote($value, '/'), $stopLabels));

        $pattern = '/(?:' . $labelPattern . ')\s*[:：]\s*(.+?)';
        if ($stopPattern !== '') {
            $pattern .= '(?=\s*(?:\||\n)\s*(?:' . $stopPattern . ')\s*[:：]|$)';
        } else {
            $pattern .= '$';
        }
        $pattern .= '/us';

        if (!preg_match($pattern, $text, $match)) {
            return '';
        }

        return trim((string) ($match[1] ?? ''));
    }

    /** @return array<int,string> */
    protected function splitOptions(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $parts = preg_split('/[,،؛;\n]+/u', $value) ?: [];
        $options = [];
        foreach ($parts as $part) {
            $option = trim($part);
            if ($option !== '') {
                $options[] = $option;
            }
        }

        return array_values(array_unique($options));
    }

    /**
     * Execute the exact structured payload that was shown in the preview.
     * Poll/post no longer pass through the historical free-form parser after
     * confirmation. Comment/reaction still use the legacy parser because their
     * preview is the original explicit command itself.
     *
     * @param array<string,mixed> $pending
     * @return array<string,mixed>
     */
    protected function executeConfirmedWidgetRequest(User $requester, Group $group, array $pending): array
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

        $payload = is_array($pending['payload'] ?? null) ? $pending['payload'] : [];
        $action = (string) ($payload['action'] ?? $pending['action'] ?? '');
        $bot = $this->ensureBotUser();

        $execution = $this->groupActionExecutor->execute(
            'private_widget_group_action',
            [
                'group_id' => $group->id,
                'requester_user_id' => $requester->id,
                'source' => 'najm_hoda_private_widget',
                'action' => $action,
            ],
            (bool) ($policy['dry_run'] ?? false),
            function () use ($action, $payload, $group, $requester, $bot, $policy): ?array {
                if ($action === 'create_poll') {
                    return $this->executeConfirmedPollPayload($group, $bot, $payload, $policy);
                }

                if ($action === 'create_post') {
                    return $this->executeConfirmedPostPayload($group, $bot, $payload, $policy);
                }

                $command = trim((string) ($payload['command'] ?? ''));
                if ($command === '') {
                    return null;
                }

                $trigger = new Message();
                $trigger->forceFill([
                    'group_id' => $group->id,
                    'user_id' => $requester->id,
                    'message' => $command,
                ]);
                $trigger->setRelation('group', $group);

                return $this->executeStructuredGroupAction($group, $trigger, $bot, $command, $policy);
            }
        );

        return $execution ?? [
            'decision' => 'failed',
            'reason' => 'action_payload_invalid',
            'group_reply' => 'payload تأییدشده برای اجرا معتبر نبود.',
        ];
    }

    /** @param array<string,mixed> $payload */
    protected function executeConfirmedPollPayload(Group $group, User $bot, array $payload, array $policy): array
    {
        if (!$this->policyGate->isCapabilityEnabled($policy, 'create_poll')) {
            return [
                'decision' => 'skipped',
                'reason' => 'create_poll_disabled',
                'group_reply' => 'ایجاد نظرسنجی توسط نجم هدا در این گروه غیرفعال است.',
                'context' => ['action' => 'create_poll'],
            ];
        }

        $question = trim((string) ($payload['question'] ?? ''));
        $options = array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            (array) ($payload['options'] ?? [])
        ), static fn (string $value): bool => $value !== ''));
        $days = max(1, min((int) ($payload['days'] ?? 3), 90));

        if ($question === '' || count($options) < 2) {
            return [
                'decision' => 'failed',
                'reason' => 'confirmed_poll_payload_invalid',
                'group_reply' => 'payload تأییدشده نظرسنجی ناقص است.',
                'context' => ['action' => 'create_poll'],
            ];
        }

        $poll = \App\Models\Poll::create([
            'group_id' => $group->id,
            'question' => $question,
            'is_multiple' => 0,
            'is_anonymous' => 0,
            'is_active' => 1,
            'show_results' => 1,
            'expires_at' => now()->addDays($days),
            'created_by' => $bot->id,
            'type' => 0,
            'main_type' => 1,
        ]);

        foreach ($options as $option) {
            \App\Models\PollOption::create([
                'poll_id' => $poll->id,
                'text' => $option,
            ]);
        }

        event(new \App\Events\PollCreated($poll, $group, $bot));

        return [
            'decision' => 'executed',
            'reason' => 'poll_created_from_confirmed_payload',
            'group_reply' => "نظرسنجی جدید ثبت شد. شناسه نظرسنجی: {$poll->id}",
            'context' => [
                'action' => 'create_poll',
                'poll_id' => $poll->id,
                'options_count' => count($options),
            ],
        ];
    }

    /** @param array<string,mixed> $payload */
    protected function executeConfirmedPostPayload(Group $group, User $bot, array $payload, array $policy): array
    {
        if (!$this->policyGate->isCapabilityEnabled($policy, 'create_post')) {
            return [
                'decision' => 'skipped',
                'reason' => 'create_post_disabled',
                'group_reply' => 'ایجاد پست توسط نجم هدا در این گروه غیرفعال است.',
                'context' => ['action' => 'create_post'],
            ];
        }

        $title = trim((string) ($payload['title'] ?? ''));
        $content = trim((string) ($payload['content'] ?? ''));
        if ($content === '') {
            return [
                'decision' => 'failed',
                'reason' => 'confirmed_post_payload_invalid',
                'group_reply' => 'payload تأییدشده پست ناقص است.',
                'context' => ['action' => 'create_post'],
            ];
        }
        if ($title === '') {
            $title = mb_substr(trim(strip_tags($content)), 0, 70);
        }

        $categoryId = \App\Models\Category::query()->orderBy('id')->value('id');
        if (!$categoryId) {
            return [
                'decision' => 'failed',
                'reason' => 'missing_post_category',
                'group_reply' => 'برای ایجاد پست، دسته‌بندی فعال یافت نشد.',
                'context' => ['action' => 'create_post'],
            ];
        }

        $post = \App\Models\Blog::create([
            'title' => $title,
            'content' => $content,
            'group_id' => $group->id,
            'user_id' => $bot->id,
            'category_id' => $categoryId,
        ]);
        event(new \App\Events\BlogCreated($post, $group, $bot));

        return [
            'decision' => 'executed',
            'reason' => 'post_created_from_confirmed_payload',
            'group_reply' => "پست جدید ثبت شد. شناسه پست: {$post->id}",
            'context' => ['action' => 'create_post', 'post_id' => $post->id],
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
