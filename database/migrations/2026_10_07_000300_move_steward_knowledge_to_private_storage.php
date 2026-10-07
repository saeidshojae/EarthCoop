<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('steward_knowledge_files')
            ->whereNotNull('file_path')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $path = (string) $row->file_path;
                    if ($path === '' || !Storage::disk('public')->exists($path)) {
                        continue;
                    }

                    if (!Storage::disk('local')->exists($path)) {
                        $stream = Storage::disk('public')->readStream($path);
                        if (!is_resource($stream)) {
                            throw new \RuntimeException("Unable to read legacy steward knowledge file: {$path}");
                        }

                        try {
                            $written = Storage::disk('local')->put($path, $stream);
                        } finally {
                            fclose($stream);
                        }

                        if ($written !== true || !Storage::disk('local')->exists($path)) {
                            throw new \RuntimeException("Unable to migrate steward knowledge file to private storage: {$path}");
                        }
                    }

                    Storage::disk('public')->delete($path);
                }
            });
    }

    public function down(): void
    {
        DB::table('steward_knowledge_files')
            ->whereNotNull('file_path')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $path = (string) $row->file_path;
                    if ($path === '' || !Storage::disk('local')->exists($path)) {
                        continue;
                    }

                    if (!Storage::disk('public')->exists($path)) {
                        $stream = Storage::disk('local')->readStream($path);
                        if (!is_resource($stream)) {
                            throw new \RuntimeException("Unable to read private steward knowledge file during rollback: {$path}");
                        }

                        try {
                            $written = Storage::disk('public')->put($path, $stream);
                        } finally {
                            fclose($stream);
                        }

                        if ($written !== true || !Storage::disk('public')->exists($path)) {
                            throw new \RuntimeException("Unable to restore steward knowledge file to public storage: {$path}");
                        }
                    }

                    Storage::disk('local')->delete($path);
                }
            });
    }
};
