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
        if (Schema::hasTable('villages')) {
            return;
        }
        
        Schema::create('villages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            
            // Foreign keys - using unsignedBigInteger first
            $table->unsignedBigInteger('rural_id')->nullable();
            $table->unsignedBigInteger('district_id')->nullable();
            
            $table->tinyInteger('status')->default(0);
            $table->timestamps();
            
            // Indexes
            $table->index('rural_id');
            $table->index('district_id');
            $table->index('status');
            $table->index('name');
        });
        
        // Add foreign keys separately if tables exist
        if (Schema::hasTable('rurals')) {
            try {
                Schema::table('villages', function (Blueprint $table) {
                    $table->foreign('rural_id')->references('id')->on('rurals')->onDelete('cascade')->onUpdate('cascade');
                });
            } catch (\Exception $e) {
                // Ignore if foreign key already exists or table structure doesn't match
            }
        }
        
        // Note: district_id foreign key is not added as it may cause constraint issues
        // The column exists for querying purposes but without foreign key constraint
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('villages');
    }
};
