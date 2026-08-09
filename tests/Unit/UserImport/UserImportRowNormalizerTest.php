<?php

namespace Tests\Unit\UserImport;

use App\Services\UserImport\UserImportRowNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UserImportRowNormalizerTest extends TestCase
{
    public function test_it_preserves_array_rows(): void
    {
        $normalizer = new UserImportRowNormalizer();
        $row = [
            'email' => 'user@example.com',
            'first_name' => 'Ali',
            'last_name' => 'Ahmadi',
        ];

        $this->assertSame($row, $normalizer->normalize($row));
    }

    public function test_it_normalizes_legacy_rows_with_to_array(): void
    {
        $normalizer = new UserImportRowNormalizer();
        $row = new class {
            public function toArray(): array
            {
                return [
                    'email' => 'legacy@example.com',
                    'first_name' => 'Sara',
                    'last_name' => 'Karimi',
                ];
            }
        };

        $this->assertSame([
            'email' => 'legacy@example.com',
            'first_name' => 'Sara',
            'last_name' => 'Karimi',
        ], $normalizer->normalize($row));
    }

    public function test_it_rejects_unsupported_row_types(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new UserImportRowNormalizer())->normalize('not-a-row');
    }
}
