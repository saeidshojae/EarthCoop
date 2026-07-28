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
        Schema::table('announcements', function (Blueprint $table) {
            if (!Schema::hasColumn('announcements', 'group_level')) {
                $table->string('group_level')->nullable()->after('content')->comment('Level of group: global, country, province, county, section, city, rural, region, village, neighborhood, street, alley');
            }
        });
        
        // Add index for better query performance
        if (Schema::hasTable('announcements') && Schema::hasColumn('announcements', 'group_level')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->index('group_level');
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
        Schema::table('announcements', function (Blueprint $table) {
            if (Schema::hasColumn('announcements', 'group_level')) {
                $table->dropIndex(['group_level']);
                $table->dropColumn('group_level');
            }
        });
    }
};
