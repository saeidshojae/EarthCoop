<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('announcements')) {
            return;
        }

        Schema::table('announcements', function (Blueprint $table) {
            if (!Schema::hasColumn('announcements', 'image')) {
                $table->string('image')->nullable()->after('content');
            }
            if (!Schema::hasColumn('announcements', 'should_pin')) {
                $table->boolean('should_pin')->default(true)->after('image');
            }
            if (!Schema::hasColumn('announcements', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->after('should_pin');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasTable('announcements')) {
            return;
        }

        Schema::table('announcements', function (Blueprint $table) {
            if (Schema::hasColumn('announcements', 'created_by')) {
                $table->dropForeign(['created_by']);
            }

            $toDrop = [];
            if (Schema::hasColumn('announcements', 'image')) {
                $toDrop[] = 'image';
            }
            if (Schema::hasColumn('announcements', 'should_pin')) {
                $toDrop[] = 'should_pin';
            }
            if (Schema::hasColumn('announcements', 'created_by')) {
                $toDrop[] = 'created_by';
            }

            if (!empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });
    }
};
