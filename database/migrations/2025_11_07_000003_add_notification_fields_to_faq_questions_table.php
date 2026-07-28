<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faq_questions', function (Blueprint $table) {
            $table->timestamp('answered_at')->nullable()->after('answer');
            $table->timestamp('notified_at')->nullable()->after('answered_at');
        });
    }

    public function down(): void
    {
        Schema::table('faq_questions', function (Blueprint $table) {
            $table->dropColumn(['answered_at', 'notified_at']);
        });
    }
};



