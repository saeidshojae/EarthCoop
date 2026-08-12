<?php

use App\Models\Message;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pinned_messages', function (Blueprint $table) {
            $table->string('content_type', 40)->nullable()->after('group_id');
            $table->unsignedBigInteger('content_id')->nullable()->after('content_type');
            $table->index(['group_id', 'content_type', 'content_id'], 'group_pins_content_index');
        });

        DB::table('pinned_messages')->whereNotNull('message_id')->update([
            'content_type' => Message::class,
            'content_id' => DB::raw('message_id'),
        ]);

        Schema::table('pinned_messages', function (Blueprint $table) {
            $table->dropForeign(['message_id']);
        });
        Schema::table('pinned_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('message_id')->nullable()->change();
            $table->foreign('message_id')->references('id')->on('messages')->nullOnDelete();
            $table->unique(['group_id', 'content_type', 'content_id'], 'group_pins_content_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pinned_messages', function (Blueprint $table) {
            $table->dropUnique('group_pins_content_unique');
            $table->dropIndex('group_pins_content_index');
            $table->dropColumn(['content_type', 'content_id']);
        });
    }
};
