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
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // 1. Create provinces table if not exists
        if (!Schema::hasTable('provinces')) {
            Schema::create('provinces', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('amar_code')->nullable();
                $table->foreignId('country_id')->constrained('countries')->onDelete('cascade')->onUpdate('cascade');
                $table->tinyInteger('status')->default(0);
                $table->timestamps();
            });
        }
        
        // 2. Modify regions table: change city_id to parent_id and add province_id, district_id, amar_code
        if (Schema::hasColumn('regions', 'city_id')) {
            // Drop foreign key using raw SQL
            $foreignKeys = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'regions' AND COLUMN_NAME = 'city_id' AND CONSTRAINT_NAME != 'PRIMARY'");
            foreach ($foreignKeys as $fk) {
                DB::statement("ALTER TABLE `regions` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            }
            Schema::table('regions', function (Blueprint $table) {
                $table->dropColumn('city_id');
            });
        }
        
        Schema::table('regions', function (Blueprint $table) {
            if (!Schema::hasColumn('regions', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('name');
            }
            if (!Schema::hasColumn('regions', 'province_id')) {
                $table->unsignedBigInteger('province_id')->nullable()->after('parent_id');
            }
            if (!Schema::hasColumn('regions', 'district_id')) {
                $table->unsignedBigInteger('district_id')->nullable()->after('province_id');
            }
            if (!Schema::hasColumn('regions', 'amar_code')) {
                $table->string('amar_code')->nullable()->after('district_id');
            }
        });
        
        // Add foreign key for province_id after column is created
        if (Schema::hasColumn('regions', 'province_id')) {
            DB::statement("ALTER TABLE `regions` ADD CONSTRAINT `regions_province_id_foreign` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        }
        
        // 3. Modify neighborhoods table: change region_id to parent_id
        if (Schema::hasColumn('neighborhoods', 'region_id')) {
            $foreignKeys = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'neighborhoods' AND COLUMN_NAME = 'region_id' AND CONSTRAINT_NAME != 'PRIMARY'");
            foreach ($foreignKeys as $fk) {
                DB::statement("ALTER TABLE `neighborhoods` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            }
            Schema::table('neighborhoods', function (Blueprint $table) {
                $table->dropColumn('region_id');
            });
        }
        
        Schema::table('neighborhoods', function (Blueprint $table) {
            if (!Schema::hasColumn('neighborhoods', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('name');
            }
        });
        
        // 4. Modify streets table: change neighborhood_id to parent_id
        if (Schema::hasColumn('streets', 'neighborhood_id')) {
            $foreignKeys = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'streets' AND COLUMN_NAME = 'neighborhood_id' AND CONSTRAINT_NAME != 'PRIMARY'");
            foreach ($foreignKeys as $fk) {
                DB::statement("ALTER TABLE `streets` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            }
            Schema::table('streets', function (Blueprint $table) {
                $table->dropColumn('neighborhood_id');
            });
        }
        
        Schema::table('streets', function (Blueprint $table) {
            if (!Schema::hasColumn('streets', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('name');
            }
        });
        
        // 5. Modify alleies table: change street_id to parent_id
        if (Schema::hasColumn('alleies', 'street_id')) {
            $foreignKeys = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alleies' AND COLUMN_NAME = 'street_id' AND CONSTRAINT_NAME != 'PRIMARY'");
            foreach ($foreignKeys as $fk) {
                DB::statement("ALTER TABLE `alleies` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            }
            Schema::table('alleies', function (Blueprint $table) {
                $table->dropColumn('street_id');
            });
        }
        
        Schema::table('alleies', function (Blueprint $table) {
            if (!Schema::hasColumn('alleies', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('name');
            }
        });
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Reverse changes (optional - for rollback)
        Schema::table('alleies', function (Blueprint $table) {
            if (Schema::hasColumn('alleies', 'parent_id')) {
                $table->dropColumn('parent_id');
            }
            if (!Schema::hasColumn('alleies', 'street_id')) {
                $table->unsignedBigInteger('street_id')->nullable()->after('name');
            }
        });
        
        Schema::table('streets', function (Blueprint $table) {
            if (Schema::hasColumn('streets', 'parent_id')) {
                $table->dropColumn('parent_id');
            }
            if (!Schema::hasColumn('streets', 'neighborhood_id')) {
                $table->unsignedBigInteger('neighborhood_id')->nullable()->after('name');
            }
        });
        
        Schema::table('neighborhoods', function (Blueprint $table) {
            if (Schema::hasColumn('neighborhoods', 'parent_id')) {
                $table->dropColumn('parent_id');
            }
            if (!Schema::hasColumn('neighborhoods', 'region_id')) {
                $table->unsignedBigInteger('region_id')->nullable()->after('name');
            }
        });
        
        Schema::table('regions', function (Blueprint $table) {
            if (Schema::hasColumn('regions', 'parent_id')) {
                $table->dropColumn('parent_id');
            }
            if (Schema::hasColumn('regions', 'province_id')) {
                $table->dropForeign(['province_id']);
                $table->dropColumn('province_id');
            }
            if (Schema::hasColumn('regions', 'district_id')) {
                $table->dropColumn('district_id');
            }
            if (Schema::hasColumn('regions', 'amar_code')) {
                $table->dropColumn('amar_code');
            }
            if (!Schema::hasColumn('regions', 'city_id')) {
                $table->unsignedBigInteger('city_id')->nullable()->after('name');
            }
        });
    }
};
