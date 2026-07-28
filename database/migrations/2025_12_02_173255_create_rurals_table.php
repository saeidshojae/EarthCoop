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
        if (Schema::hasTable('rurals')) {
            return;
        }
        
        Schema::create('rurals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            
            // Foreign keys - using unsignedBigInteger first, then adding foreign keys separately
            $table->unsignedBigInteger('province_id')->nullable();
            $table->unsignedBigInteger('county_id')->nullable();
            $table->unsignedBigInteger('district_id')->nullable();
            
            $table->tinyInteger('status')->default(0);
            $table->string('amar_code')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('province_id');
            $table->index('county_id');
            $table->index('district_id');
            $table->index('status');
            $table->index('name');
        });
        
        // Add foreign keys separately if tables exist
        if (Schema::hasTable('provinces')) {
            Schema::table('rurals', function (Blueprint $table) {
                $table->foreign('province_id')->references('id')->on('provinces')->onDelete('cascade')->onUpdate('cascade');
            });
        }
        
        if (Schema::hasTable('counties')) {
            Schema::table('rurals', function (Blueprint $table) {
                $table->foreign('county_id')->references('id')->on('counties')->onDelete('cascade')->onUpdate('cascade');
            });
        }
        
        if (Schema::hasTable('districts')) {
            Schema::table('rurals', function (Blueprint $table) {
                $table->foreign('district_id')->references('id')->on('districts')->onDelete('cascade')->onUpdate('cascade');
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
        Schema::dropIfExists('rurals');
    }
};
