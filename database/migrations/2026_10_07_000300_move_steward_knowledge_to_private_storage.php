<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        foreach (Storage::disk('public')->allFiles('steward/knowledge') as $path) {
            $this->moveBetweenDisks('public', 'local', $path);
        }
    }

    public function down(): void
    {
        $hasUnsafeSourceRows = DB::table('steward_knowledge_files')
            ->where('source_type', 'url')
            ->orWhereNull('original_filename')
            ->orWhereNull('file_path')
            ->orWhereNull('file_type')
            ->orWhereNull('file_size')
            ->exists();

        $hasOversizedContent = DB::table('steward_knowledge_files')
            ->whereRaw('LENGTH(extracted_content) > 65535')
            ->exists();

        if ($hasUnsafeSourceRows || $hasOversizedContent) {
            throw new \RuntimeException(
                'Cannot roll back private steward storage while newer source rows or oversized extracted content exist.'
            );
        }

        foreach (Storage::disk('local')->allFiles('steward/knowledge') as $path) {
            $this->moveBetweenDisks('local', 'public', $path);
        }
    }

    private function moveBetweenDisks(string $from, string $to, string $path): void
    {
        if (!Storage::disk($from)->exists($path)) {
            return;
        }

        if (!Storage::disk($to)->exists($path)) {
            $stream = Storage::disk($from)->readStream($path);
            if (!is_resource($stream)) {
                throw new \RuntimeException("Unable to read steward knowledge file: {$path}");
            }

            try {
                $written = Storage::disk($to)->put($path, $stream);
            } finally {
                fclose($stream);
            }

            if ($written !== true || !Storage::disk($to)->exists($path)) {
                throw new \RuntimeException("Unable to migrate steward knowledge file: {$path}");
            }
        }

        if (!Storage::disk($from)->delete($path)) {
            throw new \RuntimeException("Unable to remove steward knowledge file from source disk: {$path}");
        }
    }
};
