<?php

namespace App\Support\Communication;

use BackedEnum;

final class CommunicationAdminLabels
{
    public static function trigger(mixed $value): string
    {
        return self::map($value, [
            'event' => 'رویدادمحور',
            'scheduled' => 'زمان‌بندی‌شده',
            'conditional' => 'شرطی',
        ]);
    }

    public static function audience(mixed $value): string
    {
        return self::map($value, [
            'event.user' => 'کاربر مرتبط با رویداد',
            'specific.user' => 'کاربر مشخص',
            'role.member' => 'اعضا',
            'role.manager' => 'مدیران',
            'role.inspector' => 'بازرسان',
        ]);
    }

    public static function condition(mixed $value): string
    {
        return self::map($value, [
            'user.registration_complete' => 'تکمیل ثبت‌نام',
            'user.email_verified' => 'تأیید ایمیل',
        ]);
    }

    public static function classification(mixed $value): string
    {
        return self::map($value, [
            'required' => 'الزامی',
            'operational' => 'عملیاتی',
            'optional' => 'اختیاری',
        ]);
    }

    public static function frequency(mixed $value): string
    {
        return self::map($value, [
            'hourly' => 'ساعتی',
            'daily' => 'روزانه',
            'weekly' => 'هفتگی',
        ]);
    }

    public static function deliveryStatus(mixed $value): string
    {
        return self::map($value, [
            'pending' => 'در انتظار',
            'queued' => 'در صف',
            'sending' => 'در حال ارسال',
            'retrying' => 'در حال تلاش مجدد',
            'sent' => 'ارسال‌شده',
            'failed' => 'ناموفق',
            'suppressed' => 'عدم ارسال',
            'invalid' => 'نامعتبر',
            'cancelled' => 'لغوشده',
        ]);
    }

    public static function campaignStatus(mixed $value): string
    {
        return self::map($value, [
            'draft' => 'پیش‌نویس',
            'preview' => 'پیش‌نمایش',
            'scheduled' => 'زمان‌بندی‌شده',
            'running' => 'در حال ارسال',
            'completed' => 'تکمیل‌شده',
            'paused' => 'متوقف موقت',
            'cancelled' => 'لغوشده',
            'failed' => 'ناموفق',
        ]);
    }

    public static function failureClass(mixed $value): string
    {
        return self::map($value, [
            'transient' => 'موقت و قابل تلاش مجدد',
            'permanent' => 'دائمی',
        ]);
    }

    public static function locale(mixed $value): string
    {
        return self::map($value, [
            'fa' => 'فارسی',
            'en' => 'انگلیسی',
            'ar' => 'عربی',
        ]);
    }

    /** @param array<string,string> $labels */
    private static function map(mixed $value, array $labels): string
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        $key = trim((string) ($value ?? ''));
        if ($key === '') {
            return '—';
        }

        return $labels[$key] ?? $key;
    }
}
