<?php

namespace App\Services\UserImport;

use InvalidArgumentException;

class UserImportRowNormalizer
{
    /**
     * Normalize a legacy Laravel Excel row into the array shape expected by
     * the user-import business logic.
     *
     * @param mixed $row
     * @return array<string, mixed>
     */
    public function normalize($row): array
    {
        if (is_array($row)) {
            return $row;
        }

        if (is_object($row) && method_exists($row, 'toArray')) {
            $data = $row->toArray();

            if (is_array($data)) {
                return $data;
            }
        }

        throw new InvalidArgumentException('Unsupported user import row type.');
    }
}
