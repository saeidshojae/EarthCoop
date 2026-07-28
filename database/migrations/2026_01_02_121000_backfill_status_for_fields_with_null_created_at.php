<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Some legacy imports may have NULL created_at.
     * Those rows were unintentionally left as status=0 (pending),
     * which makes ALL specialty/experience groups appear as pending.
     *
     * We treat NULL created_at as legacy and approve them.
     */
    public function up(): void
    {
        if (Schema::hasTable('occupational_fields') && Schema::hasColumn('occupational_fields', 'status')) {
            DB::table('occupational_fields')
                ->where('status', 0)
                ->whereNull('created_at')
                ->update(['status' => 1]);
        }

        if (Schema::hasTable('experience_fields') && Schema::hasColumn('experience_fields', 'status')) {
            DB::table('experience_fields')
                ->where('status', 0)
                ->whereNull('created_at')
                ->update(['status' => 1]);
        }
    }

    public function down(): void
    {
        // no-op
    }
};


