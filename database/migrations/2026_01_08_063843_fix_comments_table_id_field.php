<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Check if comments table exists
        if (Schema::hasTable('comments')) {
            // Get current structure
            $columns = DB::select("SHOW COLUMNS FROM comments WHERE Field = 'id'");
            
            if (!empty($columns)) {
                $column = $columns[0];
                $isAutoIncrement = strpos($column->Extra ?? '', 'auto_increment') !== false;
                
                if (!$isAutoIncrement) {
                    // Check if id is already a primary key
                    $primaryKeys = DB::select("SHOW KEYS FROM comments WHERE Key_name = 'PRIMARY' AND Column_name = 'id'");
                    
                    if (empty($primaryKeys)) {
                        // Make id a primary key first
                        DB::statement('ALTER TABLE comments MODIFY id BIGINT UNSIGNED NOT NULL');
                        DB::statement('ALTER TABLE comments ADD PRIMARY KEY (id)');
                    }
                    
                    // Now add auto increment
                    DB::statement('ALTER TABLE comments MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
                }
            }
        } else {
            // Create the comments table if it doesn't exist
            Schema::create('comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('blog_id')->constrained('blogs')->onDelete('cascade');
                $table->text('message');
                $table->foreignId('parent_id')->nullable()->constrained('comments')->onDelete('cascade');
                $table->timestamps();
                
                $table->index('user_id');
                $table->index('blog_id');
                $table->index('parent_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // We don't want to drop the table on rollback
        // Just revert the auto-increment if needed
        if (Schema::hasTable('comments')) {
            // Note: This will fail if there are records, but that's expected for rollback
            DB::statement('ALTER TABLE comments MODIFY id BIGINT UNSIGNED NOT NULL');
        }
    }
};
