<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InspectGroupUserPivot extends Command
{
    protected $signature = 'db:inspect-group-user';
    protected $description = 'Inspect group_user table to check AUTO_INCREMENT and indexes.';

    public function handle(): int
    {
        if (!Schema::hasTable('group_user')) {
            $this->error('group_user table does not exist.');
            return self::FAILURE;
        }

        $driver = config('database.default');
        $connection = config('database.connections.' . $driver);
        $database = $connection['database'] ?? null;

        $this->info('DB driver: ' . $driver);
        $this->info('Database: ' . ($database ?? 'n/a'));

        if ($driver === 'mysql' && $database) {
            $col = DB::table('information_schema.COLUMNS')
                ->select('COLUMN_TYPE', 'IS_NULLABLE', 'COLUMN_DEFAULT', 'EXTRA')
                ->where('TABLE_SCHEMA', $database)
                ->where('TABLE_NAME', 'group_user')
                ->where('COLUMN_NAME', 'id')
                ->first();

            $this->line('id column: ' . json_encode($col));

            $pk = DB::table('information_schema.TABLE_CONSTRAINTS')
                ->select('CONSTRAINT_NAME', 'CONSTRAINT_TYPE')
                ->where('TABLE_SCHEMA', $database)
                ->where('TABLE_NAME', 'group_user')
                ->where('CONSTRAINT_TYPE', 'PRIMARY KEY')
                ->first();
            $this->line('Primary key: ' . json_encode($pk));

            $uniqueIdx = DB::select("SHOW INDEX FROM `group_user` WHERE Non_unique = 0");
            $this->line('Unique indexes: ' . json_encode($uniqueIdx));
        } else {
            $this->warn('Driver not MySQL/MariaDB; skipping information_schema checks.');
        }

        // Quick insert simulation (no write): just show what Eloquent would insert
        $this->line('Eloquent expects AUTO_INCREMENT on id so it can omit id during pivot insert.');

        return self::SUCCESS;
    }
}
