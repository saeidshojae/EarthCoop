<?php

namespace App\Services\Admin;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdminSettingManagementService
{
    /** @return array<string,mixed> */
    public function recommend(string $key, mixed $value): array
    {
        $this->assertAllowedKey($key);
        $normalized = $this->normalize($key, $value);
        $settings = Setting::singleton();

        return [
            'success' => true,
            'status' => 'proposed',
            'setting_key' => $key,
            'current_value' => $settings->getAttribute($key),
            'proposed_value' => $normalized,
            'requires_approval' => true,
        ];
    }

    /** @return array<string,mixed> */
    public function change(string $key, mixed $value): array
    {
        $this->assertAllowedKey($key);
        $normalized = $this->normalize($key, $value);

        return DB::transaction(function () use ($key, $normalized): array {
            Setting::singleton();
            $settings = Setting::query()->whereKey(1)->lockForUpdate()->firstOrFail();

            $before = $settings->getAttribute($key);
            if ($before === $normalized) {
                return [
                    'setting_key'=>$key,
                    'status'=>'unchanged',
                    'old_value'=>$before,
                    'new_value'=>$normalized,
                ];
            }

            $settings->setAttribute($key, $normalized);
            $settings->save();

            return [
                'setting_key'=>$key,
                'status'=>'changed',
                'old_value'=>$before,
                'new_value'=>$settings->fresh()->getAttribute($key),
            ];
        });
    }

    /** @return array<int,string> */
    public function allowedKeys(): array
    {
        return [
            'invation_status',
            'finger_status',
            'expire_invation_time',
            'count_invation',
            'najm_summary',
            'welcome_titre',
            'welcome_content',
            'home_titre',
            'home_content',
        ];
    }

    protected function assertAllowedKey(string $key): void
    {
        if (! in_array($key, $this->allowedKeys(), true)) {
            throw new InvalidArgumentException('admin_setting_key_not_delegable');
        }
    }

    protected function normalize(string $key, mixed $value): mixed
    {
        return match ($key) {
            'invation_status', 'finger_status' => $this->booleanValue($value),
            'expire_invation_time' => $this->boundedInteger($value, 1, 525600),
            'count_invation' => $this->boundedInteger($value, 0, 1000000),
            'najm_summary', 'welcome_titre', 'home_titre' => $this->boundedString($value, 2000),
            'welcome_content', 'home_content' => $this->boundedString($value, 50000),
            default => throw new InvalidArgumentException('admin_setting_key_not_delegable'),
        };
    }

    protected function booleanValue(mixed $value): bool
    {
        if (is_bool($value)) return $value;
        if (in_array($value, [0, 1, '0', '1'], true)) return (bool) $value;
        throw new InvalidArgumentException('admin_setting_boolean_value_required');
    }

    protected function boundedInteger(mixed $value, int $min, int $max): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('admin_setting_integer_value_required');
        }
        $int = (int) $value;
        if ($int < $min || $int > $max) {
            throw new InvalidArgumentException('admin_setting_integer_value_out_of_range');
        }
        return $int;
    }

    protected function boundedString(mixed $value, int $max): string
    {
        if (! is_string($value)) throw new InvalidArgumentException('admin_setting_string_value_required');
        $value = trim($value);
        if (mb_strlen($value) > $max) throw new InvalidArgumentException('admin_setting_string_value_too_long');
        return $value;
    }
}
