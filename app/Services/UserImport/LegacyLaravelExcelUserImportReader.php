<?php

namespace App\Services\UserImport;

use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

class LegacyLaravelExcelUserImportReader
{
    public function __construct(
        protected UserImportRowNormalizer $normalizer
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function read(UploadedFile $file): array
    {
        $rows = Excel::load($file)->get();
        $normalized = [];

        foreach ($rows as $row) {
            $normalized[] = $this->normalizer->normalize($row);
        }

        return $normalized;
    }
}
