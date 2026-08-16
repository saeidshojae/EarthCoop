<?php

namespace App\Services\NajmHoda\Context;

/**
 * Canonical, server-owned UI capability descriptions used by Najm Hoda.
 *
 * These contracts are deliberately close to the actual UI implementation:
 * each actionable capability references stable selectors and source views.
 * Contract tests verify those selectors still exist, so UI drift fails CI
 * instead of silently teaching Najm Hoda stale instructions.
 */
class NajmHodaPageCapabilityRegistry
{
    /**
     * @param array<string, mixed>|null $resource
     * @return array<int, array<string, mixed>>
     */
    public function forPage(string $pageKind, ?array $resource = null): array
    {
        $contracts = match ($pageKind) {
            'group_chat' => $this->groupChatContracts(),
            default => [],
        };

        if ($pageKind === 'group_chat') {
            $resource = is_array($resource) ? $resource : [];
            $canParticipate = (bool) ($resource['can_participate'] ?? false);
            $role = isset($resource['viewer_group_role']) ? (int) $resource['viewer_group_role'] : null;
            $blocked = array_values(array_filter(array_map('strval', (array) ($resource['blocked_positions'] ?? []))));

            $contracts = array_values(array_filter($contracts, function (array $contract) use ($canParticipate, $role, $blocked): bool {
                if ((bool) ($contract['requires_participation'] ?? false) && !$canParticipate) {
                    return false;
                }

                // Role 5 can send text/recorded voice but the current composer
                // intentionally hides the attachment menu that contains post/poll creation.
                if ($role === 5 && in_array((string) ($contract['id'] ?? ''), ['create_post', 'create_poll'], true)) {
                    return false;
                }

                $position = (string) ($contract['blocked_by_position'] ?? '');
                if ($position !== '' && in_array($position, $blocked, true)) {
                    return false;
                }

                return true;
            }));
        }

        return array_map(function (array $contract): array {
            unset($contract['requires_participation'], $contract['blocked_by_position']);
            return $contract;
        }, $contracts);
    }

    /**
     * Delegated actions are intentionally separate from UI capabilities. A user
     * may be able to perform an action themselves while not being authorized to
     * ask a system identity to perform it on the group's behalf.
     *
     * @param array<string,mixed>|null $resource
     * @return array<int,array<string,mixed>>
     */
    public function delegatedActionsForGroup(?array $resource = null): array
    {
        $resource = is_array($resource) ? $resource : [];
        $role = isset($resource['viewer_group_role']) ? (int) $resource['viewer_group_role'] : null;
        $isPlatformAdmin = (string) ($resource['viewer_relation'] ?? '') === 'admin';

        if (!$isPlatformAdmin && !in_array($role, [2, 3], true)) {
            return [];
        }

        return [
            [
                'id' => 'najm_hoda_create_post',
                'label' => 'تفویض انتشار پست به نجم هدا',
                'requires_confirmation' => true,
                'result_visibility' => 'group_feed',
                'conversation_visibility' => 'private_widget',
            ],
            [
                'id' => 'najm_hoda_create_poll',
                'label' => 'تفویض ایجاد نظرسنجی به نجم هدا',
                'requires_confirmation' => true,
                'result_visibility' => 'group_feed',
                'conversation_visibility' => 'private_widget',
            ],
            [
                'id' => 'najm_hoda_create_comment',
                'label' => 'تفویض ثبت نظر به نجم هدا',
                'requires_confirmation' => true,
                'result_visibility' => 'group_feed',
                'conversation_visibility' => 'private_widget',
            ],
            [
                'id' => 'najm_hoda_react',
                'label' => 'تفویض واکنش به نجم هدا',
                'requires_confirmation' => true,
                'result_visibility' => 'group_feed',
                'conversation_visibility' => 'private_widget',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function groupChatContracts(): array
    {
        return [
            [
                'id' => 'read_group_feed',
                'label' => 'مشاهده گفتگوی گروه',
                'summary' => 'پیام‌ها، پست‌ها، نظرسنجی‌ها و رویدادهای منتشرشده گروه را در فید اصلی ببینید.',
                'ui' => [
                    'surface' => '#chat-box',
                    'steps' => ['فید اصلی گفتگو را مشاهده کنید و برای دیدن موارد قدیمی‌تر در آن پیمایش کنید.'],
                ],
                'source' => 'resources/views/groups/chat.blade.php',
            ],
            [
                'id' => 'send_message',
                'label' => 'ارسال پیام متنی',
                'summary' => 'در صورت داشتن مجوز مشارکت، پیام متنی را از composer پایین صفحه ارسال کنید.',
                'requires_participation' => true,
                'blocked_by_position' => 'message',
                'ui' => [
                    'surface' => '#message_editor',
                    'submit' => '#telegram-send-btn',
                    'steps' => [
                        'متن را در کادر «پیام خود را بنویسید...» وارد کنید.',
                        'دکمه ارسال با آیکون هواپیمای کاغذی را بزنید.',
                    ],
                ],
                'source' => 'resources/views/groups/chat.blade.php',
            ],
            [
                'id' => 'send_voice',
                'label' => 'ارسال پیام صوتی',
                'summary' => 'در صورت داشتن مجوز مشارکت، صدا را ضبط کنید یا فایل صوتی انتخاب کنید.',
                'requires_participation' => true,
                'blocked_by_position' => 'message',
                'ui' => [
                    'record_trigger' => '#voice-record-btn',
                    'file_trigger' => '#audio-upload-trigger',
                    'steps' => [
                        'برای ضبط صدا از دکمه میکروفون استفاده کنید.',
                        'برای انتخاب فایل صوتی، منوی پیوست را باز کرده و «ارسال فایل صوتی» را انتخاب کنید.',
                    ],
                ],
                'source' => 'resources/views/groups/chat.blade.php',
            ],
            [
                'id' => 'create_post',
                'label' => 'ایجاد پست',
                'summary' => 'از منوی پیوست، فرم ایجاد پست را باز کرده و عنوان، متن و در صورت نیاز دسته‌بندی یا فایل را وارد کنید.',
                'requires_participation' => true,
                'blocked_by_position' => 'post',
                'ui' => [
                    'menu_trigger' => '#chatCreateToggle',
                    'trigger' => '#create-post-btn',
                    'form' => '#postForm',
                    'fields' => ['#post_title', '#post_editor', '#post_category', '#post_img'],
                    'steps' => [
                        'دکمه پیوست کنار composer را باز کنید.',
                        '«ایجاد پست» را انتخاب کنید.',
                        'عنوان و متن پست را وارد کنید؛ دسته‌بندی و فایل اختیاری‌اند.',
                        'دکمه «انتشار پست» را بزنید.',
                    ],
                ],
                'source' => 'resources/views/groups/modals/post_form.blade.php',
            ],
            [
                'id' => 'create_poll',
                'label' => 'ایجاد نظرسنجی',
                'summary' => 'از منوی پیوست، فرم نظرسنجی را باز کرده و سؤال، مدت و حداقل دو گزینه را تعریف کنید.',
                'requires_participation' => true,
                'blocked_by_position' => 'poll',
                'ui' => [
                    'menu_trigger' => '#chatCreateToggle',
                    'trigger' => '#create-poll-btn',
                    'form' => '#pollForm',
                    'fields' => ['#poll_question', '#poll_expires_at', '#poll_type', '#dynamic-inputs'],
                    'steps' => [
                        'دکمه پیوست کنار composer را باز کنید.',
                        '«ایجاد نظرسنجی» را انتخاب کنید.',
                        'سؤال و مدت فعال بودن را وارد کنید و حداقل دو گزینه بسازید.',
                        'در صورت تخصصی بودن، نوع و تخصص مرتبط را انتخاب کنید.',
                        'دکمه «انتشار نظرسنجی» را بزنید.',
                    ],
                ],
                'source' => 'resources/views/groups/modals/poll_form.blade.php',
            ],
            [
                'id' => 'vote',
                'label' => 'رأی دادن',
                'summary' => 'در صورت داشتن مجوز مشارکت، روی گزینه یا نامزد قابل انتخاب در کارت رأی‌گیری فعال عمل کنید.',
                'requires_participation' => true,
                'ui' => [
                    'surface' => '#chat-box',
                    'steps' => ['کارت نظرسنجی یا انتخابات فعال را پیدا کنید و گزینه/نامزد مجاز را انتخاب کنید.'],
                ],
                'source' => 'resources/views/groups/chat.blade.php',
            ],
        ];
    }
}
