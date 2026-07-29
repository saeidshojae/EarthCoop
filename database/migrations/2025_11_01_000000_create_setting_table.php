<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting', function (Blueprint $table) {

            $table->id();

            $table->boolean('invation_status')->default(false);

            $table->boolean('finger_status')->default(false);

            $table->integer('expire_invation_time')->nullable();

            $table->integer('count_invation')->default(0);

            $table->text('najm_summary')->nullable();

            $table->string('welcome_titre')->nullable();

            $table->longText('welcome_content')->nullable();

            $table->string('home_titre')->nullable();

            $table->longText('home_content')->nullable();

            $table->unsignedBigInteger('najm_bahar_user_threshold')->default(0);

            $table->unsignedBigInteger('najm_bahar_initial_amount')->default(0);

            $table->unsignedTinyInteger('najm_bahar_initial_active_percentage')->default(30);

            $table->string('najm_bahar_initial_active_type',20)
                    ->default('percentage')
                    ->comment('percentage | fixed_amount');

            $table->unsignedBigInteger('najm_bahar_initial_active_fixed_amount')->default(0);

            $table->boolean('najm_bahar_auto_activation_enabled')->default(false);

            $table->string('najm_bahar_auto_activation_period',20)
                    ->default('monthly')
                    ->comment('monthly | yearly');

            $table->unsignedBigInteger('najm_bahar_auto_activation_amount')->default(0);

            $table->integer('reputation_to_gol_ratio')->default(100);

            $table->boolean('reputation_conversion_enabled')->default(true);

            $table->string('najm_bahar_membership_fee_account',32)->default('');

            $table->string('najm_bahar_membership_fee_insurance_account',32)->default('');

            $table->string('najm_bahar_membership_fee_burn_account',32)->default('');

            $table->unsignedInteger('najm_bahar_membership_fee_amount')->default(0);

            $table->boolean('najm_bahar_amounts_in_gol')->default(false);

            $table->unsignedBigInteger('najm_bahar_membership_fee_membership_amount')->default(0);

            $table->unsignedBigInteger('najm_bahar_membership_fee_insurance_amount')->default(0);

            $table->unsignedBigInteger('najm_bahar_membership_fee_burn_amount')->default(0);

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting');
    }
};