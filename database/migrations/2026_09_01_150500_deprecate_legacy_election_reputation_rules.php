<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['election_candidate', 'election_participated'] as $key) {
            $rule = DB::table('reputation_rules')->where('key', $key)->first();

            if (! $rule) {
                continue;
            }

            $description = trim((string) ($rule->description ?? ''));
            $note = 'منسوخ: این قاعده متعلق به مدل قدیمی انتخابات است و در انتخابات سیستمی جاری entitlement امتیازی ایجاد نمی‌کند.';

            if (! str_contains($description, $note)) {
                $description = trim($description . ($description !== '' ? "\n" : '') . $note);
            }

            DB::table('reputation_rules')
                ->where('key', $key)
                ->update([
                    'active' => false,
                    'convertible' => false,
                    'description' => $description,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Deliberately non-reversible: re-enabling legacy election rewards would
        // reintroduce semantics that conflict with the current systemic election model.
    }
};
