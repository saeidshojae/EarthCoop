<?php

namespace Maatwebsite\Excel\Facades;

use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Temporary compatibility bridge for the single legacy Excel::load()->get()
 * call still used by Admin\UserController.
 *
 * This is intentionally read-only and narrow. It should be removed once the
 * controller is migrated to a first-class import service.
 */
final class Excel
{
    public static function load($file): LegacyLoadedSpreadsheet
    {
        $path = is_object($file) && method_exists($file, 'getRealPath')
            ? $file->getRealPath()
            : (string) $file;

        if (!$path || !is_file($path)) {
            throw new RuntimeException('Spreadsheet file could not be opened.');
        }

        return new LegacyLoadedSpreadsheet($path);
    }
}

final class LegacyLoadedSpreadsheet
{
    public function __construct(private readonly string $path)
    {
    }

    public function get(): Collection
    {
        $reader = IOFactory::createReaderForFile($this->path);

        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        $spreadsheet = $reader->load($this->path);
        $sheet = $spreadsheet->getActiveSheet();

        // Do not calculate formulas from an uploaded workbook. Import needs raw
        // cell values only, which also reduces the attack surface of the parser.
        $rows = $sheet->toArray(null, false, false, false);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if ($rows === []) {
            return collect();
        }

        $headers = array_map([$this, 'normalizeHeader'], array_shift($rows));

        return collect($rows)
            ->filter(static fn (array $row): bool => collect($row)->contains(
                static fn ($value): bool => $value !== null && trim((string) $value) !== ''
            ))
            ->map(function (array $row) use ($headers): Fluent {
                $data = [];

                foreach ($headers as $index => $header) {
                    if ($header === '') {
                        continue;
                    }

                    $data[$header] = $row[$index] ?? null;
                }

                return new Fluent($data);
            })
            ->values();
    }

    private function normalizeHeader($value): string
    {
        $header = trim((string) $value);
        $header = preg_replace('/\s+/u', '_', $header) ?? $header;

        return mb_strtolower($header);
    }
}
