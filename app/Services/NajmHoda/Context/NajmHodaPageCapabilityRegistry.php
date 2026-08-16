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

        if ($pageKind === 'group_chat' && !(bool) ($resource['can_participate'] ?? false)) {
            $contracts = array_values(array_filter(
                $contracts,
                fn (array $contract): bool => !(bool) ($contract['requires_participation'] ?? false)
            ));
        }

        return array_map(function (array $contract): array {
            unset($contract['requires_participation']);
            return $contract;
        }, $contracts);
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
