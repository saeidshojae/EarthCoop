<?php

namespace App\Http\Support\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class QueryOptions
{
    public static function filters(Request $request, array $allowed): array
    {
        $filters = $request->query('filter', []);

        if (! is_array($filters)) {
            throw ValidationException::withMessages([
                'filter' => ['The filter parameter must be an object.'],
            ]);
        }

        $mapped = [];
        foreach ($filters as $publicName => $value) {
            if (! array_key_exists($publicName, $allowed)) {
                throw ValidationException::withMessages([
                    'filter.' . $publicName => ['Unsupported filter.'],
                ]);
            }

            $mapped[$allowed[$publicName]] = $value;
        }

        return $mapped;
    }

    public static function sort(Request $request, array $allowed, array $default = []): array
    {
        $raw = $request->query('sort');
        $items = $raw === null || $raw === ''
            ? $default
            : self::parseSortInput($raw);

        $mapped = [];
        foreach ($items as $item) {
            if (! is_string($item) || trim($item) === '') {
                throw ValidationException::withMessages([
                    'sort' => ['Sort fields must be non-empty strings.'],
                ]);
            }

            $item = trim($item);
            $direction = str_starts_with($item, '-') ? 'desc' : 'asc';
            $publicName = ltrim($item, '-');

            if ($publicName === '' || ! array_key_exists($publicName, $allowed)) {
                throw ValidationException::withMessages([
                    'sort' => ['Unsupported sort field: ' . $publicName],
                ]);
            }

            $mapped[] = [
                'field' => $allowed[$publicName],
                'direction' => $direction,
            ];
        }

        return $mapped;
    }

    private static function parseSortInput(mixed $raw): array
    {
        if (! is_string($raw)) {
            throw ValidationException::withMessages([
                'sort' => ['The sort parameter must be a comma-separated string.'],
            ]);
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            static fn (string $item): bool => $item !== '',
        ));
    }
}
