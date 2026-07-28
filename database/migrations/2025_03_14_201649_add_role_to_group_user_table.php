<?php
// filepath: c:\Users\saeed\EarthCoop\earthcoop\database\migrations\xxxx_xx_xx_xxxxxx_add_role_to_group_user_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRoleToGroupUserTable extends Migration
{
    public function up()
    {
        Schema::table('group_user', function (Blueprint $table) {
            $table->enum('role', ['active', 'observer'])->default('observer')->after('user_id');
        });
    }

    public function down()
    {
        Schema::table('group_user', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
}