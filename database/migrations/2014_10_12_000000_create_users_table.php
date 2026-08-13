<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            
            // فیلدهای احراز هویت
            $table->string('email')->unique()->nullable();
            $table->boolean('show_email')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('edited')->default(false);
            
            $table->string('phone')->unique()->nullable();
            $table->boolean('show_phone')->default(true);
            
            $table->string('password');
            $table->string('fingerprint_id')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            
            // اطلاعات هویتی
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->boolean('show_name')->default(true);
            
            $table->date('birth_date')->nullable();
            $table->boolean('show_birthdate')->default(true);
            
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->boolean('show_gender')->default(true);
            
            $table->string('nationality')->nullable();
            $table->string('national_id')->nullable();
            $table->boolean('show_national_id')->default(true);
            
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            
            // پروفایل
            $table->string('avatar')->nullable();
            $table->text('biografie')->nullable();
            $table->boolean('show_biografie')->default(true);
            
            $table->text('social_networks')->nullable();
            $table->boolean('show_social_networks')->default(true);
            
            $table->text('documents')->nullable();
            $table->boolean('show_documents')->default(true);
            
            $table->boolean('show_groups')->default(true);
            $table->boolean('show_created_at')->default(true);
            
            // مدیریت
            $table->boolean('is_admin')->default(false);
            // Technical identities (bots/content authors) are not cooperative members.
            $table->boolean('is_system')->default(false)->index();
            
            // آخرین فعالیت
            $table->timestamp('last_seen')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->timestamp('last_login_at')->nullable();
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
};
