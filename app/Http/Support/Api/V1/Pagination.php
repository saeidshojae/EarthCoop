<?php

namespace App\Http\Support\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class Pagination
{
    public static function page(Request $request, int $default = 25, int $max = 100): PageOptions
    {
        $page = $request->query('page', []);

        if (! is_array($page)) {
            throw ValidationException::withMessages([
                'page' => ['The page parameter must be an object.'],
            ]);
        }

        $number = self::positiveInteger($page['number'] ?? 1, 'page.number');
        $size = self::positiveInteger($page['size'] ?? $default, 'page.size');

        return new PageOptions(
            number: $number,
            size: min($size, max(1, $max)),
        );
    }

    public static function cursor(Request $request, int $default = 50, int $max = 100): CursorOptions
    {
        $page = $request->query('page', []);

        if (! is_array($page)) {
            throw ValidationException::withMessages([
                'page' => ['The page parameter must be an object.'],
            ]);
        }

        $cursor = $page['cursor'] ?? null;
        if ($cursor === '') {
            $cursor = null;
        }

        if ($cursor !== null && ! is_string($cursor)) {
            throw ValidationException::withMessages([
                'page.cursor' => ['The cursor must be an opaque string.'],
            ]);
        }

        if (is_string($cursor) && strlen($cursor) > 2048) {
            throw ValidationException::withMessages([
                'page.cursor' => ['The cursor is too long.'],
            ]);
        }

        $limit = self::positiveInteger($page['limit'] ?? $default, 'page.limit');

        return new CursorOptions(
            cursor: $cursor,
            limit: min($limit, max(1, $max)),
        );
    }

    private static function positiveInteger(mixed $value, string $field): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && $value !== '' && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        throw ValidationException::withMessages([
            $field => ['The value must be a positive integer.'],
        ]);
    }
}
