<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_point_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('request_key', 191);
            $table->string('conversion_key', 191)->unique();
            $table->unsignedInteger('requested_points');
            $table->unsignedInteger('consumed_points')->default(0);
            $table->unsignedBigInteger('amount_gol');
            $table->unsignedInteger('ratio');
            $table->unsignedBigInteger('policy_version_id')->nullable();
            $table->string('policy_version')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->unique(['user_id', 'request_key'], 'user_point_conversions_user_request_unique');
        });

        Schema::table('user_point_consumptions', function (Blueprint $table) {
            $table->foreignId('user_point_conversion_id')
                ->nullable()
                ->after('user_id')
                ->constrained('user_point_conversions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_point_consumptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_point_conversion_id');
        });

        Schema::dropIfExists('user_point_conversions');
    }
};
