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
        Schema::create('group_user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('muted')->default(false)->comment('Mute notifications for this group');
            $table->boolean('archived')->default(false)->comment('Archive this group');
            $table->json('notification_settings')->nullable()->comment('Custom notification settings');
            $table->timestamp('muted_until')->nullable()->comment('Mute until specific date');
            $table->timestamps();
            
            // One setting per user per group
            $table->unique(['group_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('group_user_settings');
    }
};
