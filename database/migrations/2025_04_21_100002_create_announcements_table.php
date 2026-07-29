<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->text('content')->nullable();
            $table->string('group_level')->nullable()->comment('Level of group: global, country, province, county, section, city, rural, region, village, neighborhood, street, alley');
            $table->string('image')->nullable();
            $table->boolean('should_pin')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('group_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};