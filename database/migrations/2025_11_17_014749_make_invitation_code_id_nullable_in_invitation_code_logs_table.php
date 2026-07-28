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
        // Drop the foreign key constraint first
        Schema::table('invitation_code_logs', function (Blueprint $table) {
            $table->dropForeign(['invitation_code_id']);
        });
        
        // Make the column nullable using raw SQL
        \DB::statement('ALTER TABLE invitation_code_logs MODIFY invitation_code_id BIGINT UNSIGNED NULL');
        
        // Re-add the foreign key constraint (nullable foreign keys are allowed)
        Schema::table('invitation_code_logs', function (Blueprint $table) {
            $table->foreign('invitation_code_id')
                  ->references('id')
                  ->on('invitation_codes')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop the foreign key constraint
        Schema::table('invitation_code_logs', function (Blueprint $table) {
            $table->dropForeign(['invitation_code_id']);
        });
        
        // Make the column NOT nullable again using raw SQL
        \DB::statement('ALTER TABLE invitation_code_logs MODIFY invitation_code_id BIGINT UNSIGNED NOT NULL');
        
        // Re-add the foreign key constraint
        Schema::table('invitation_code_logs', function (Blueprint $table) {
            $table->foreign('invitation_code_id')
                  ->references('id')
                  ->on('invitation_codes')
                  ->onDelete('cascade');
        });
    }
};
