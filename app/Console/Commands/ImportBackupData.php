<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportBackupData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:backup-data 
                            {--locations : Import location tables data}
                            {--occupational : Import occupational_fields data}
                            {--experience : Import experience_fields data}
                            {--pages : Import pages data}
                            {--all : Import all data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import data from backup SQL files (locations, occupational_fields, experience_fields, pages)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $importAll = $this->option('all');
        
        if ($importAll || $this->option('locations')) {
            $this->importLocations();
        }
        
        if ($importAll || $this->option('occupational')) {
            $this->importOccupationalFields();
        }
        
        if ($importAll || $this->option('experience')) {
            $this->importExperienceFields();
        }
        
        if ($importAll || $this->option('pages')) {
            $this->importPages();
        }
        
        if (!$importAll && !$this->option('locations') && !$this->option('occupational') && 
            !$this->option('experience') && !$this->option('pages')) {
            $this->error('Please specify what to import. Use --all or specific options like --locations, --occupational, --experience, --pages');
            return Command::FAILURE;
        }
        
        $this->info('Import completed successfully!');
        return Command::SUCCESS;
    }

    private function getSqlBackupPath(string $fileName): string
    {
        $primary = storage_path('app/sql-backups/' . $fileName);
        if (File::exists($primary)) {
            return $primary;
        }

        $legacy = base_path('_archive/sql-backups/' . $fileName);
        return $legacy;
    }
    
    private function importLocations()
    {
        $this->info('Importing location data...');

        $filePath = $this->getSqlBackupPath('import_locations_only.sql');
        
        if (!File::exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return;
        }
        
        $content = File::get($filePath);
        
        // Extract INSERT statements for location tables (multiline support)
        $tables = ['continents', 'countries', 'provinces', 'counties', 'districts', 
                   'cities', 'regions', 'neighborhoods', 'streets', 'alleies'];
        
        DB::statement("SET FOREIGN_KEY_CHECKS=0;");
        
        foreach ($tables as $table) {
            // Match INSERT statement that may span multiple lines
            $pattern = "/INSERT INTO `{$table}`.*?;/s";
            if (preg_match_all($pattern, $content, $matches)) {
                $this->info("Importing {$table}...");
                
                foreach ($matches[0] as $insert) {
                    try {
                        // Fix invalid datetime values (0000-00-00 00:00:00) to NULL
                        $insert = preg_replace("/'0000-00-00 00:00:00'/", "NULL", $insert);
                        
                        // Use INSERT IGNORE to skip duplicates
                        $insert = str_replace('INSERT INTO', 'INSERT IGNORE INTO', $insert);
                        
                        // Fix NULL status values to 0 for tables that don't allow NULL
                        if (in_array($table, ['regions', 'neighborhoods', 'streets', 'alleies'])) {
                            // Define status position for each table (0-indexed)
                            $statusPositions = [
                                'regions' => 6,        // id, name, parent_id, province_id, district_id, amar_code, status, created_at, updated_at
                                'neighborhoods' => 2,  // id, name, parent_id, status, created_at, updated_at
                                'streets' => 2,        // id, name, parent_id, status, created_at, updated_at
                                'alleies' => 2         // id, name, parent_id, status, created_at, updated_at
                            ];
                            
                            if (isset($statusPositions[$table])) {
                                $statusPos = $statusPositions[$table];
                                
                                // Parse each VALUES row and fix NULL status
                                $insert = preg_replace_callback(
                                    "/\(([^)]+)\)/",
                                    function($matches) use ($statusPos) {
                                        $row = $matches[1];
                                        $values = preg_split("/,\s*(?=(?:[^']*'[^']*')*[^']*$)/", $row);
                                        
                                        if (isset($values[$statusPos]) && trim($values[$statusPos]) === 'NULL') {
                                            $values[$statusPos] = '0';
                                        }
                                        
                                        return '(' . implode(', ', $values) . ')';
                                    },
                                    $insert
                                );
                            }
                        }
                        
                        DB::statement($insert);
                    } catch (\Exception $e) {
                        $this->warn("Error inserting into {$table}: " . $e->getMessage());
                    }
                }
                
                $this->info("✓ {$table} imported");
            } else {
                $this->warn("No data found for {$table}");
            }
        }
        
        DB::statement("SET FOREIGN_KEY_CHECKS=1;");
    }
    
    private function importOccupationalFields()
    {
        $this->info('Importing occupational_fields data...');

        $filePath = $this->getSqlBackupPath('ybwztpvr_earth (7).sql');
        
        if (!File::exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return;
        }
        
        $content = File::get($filePath);
        
        // Extract INSERT statement for occupational_fields
        $pattern = "/INSERT INTO `occupational_fields`.*?;/s";
        if (preg_match_all($pattern, $content, $matches)) {
            $this->info("Importing occupational_fields...");
            
            DB::statement("SET FOREIGN_KEY_CHECKS=0;");
            DB::statement("TRUNCATE TABLE `occupational_fields`;");
            
            foreach ($matches[0] as $insert) {
                try {
                    DB::statement($insert);
                } catch (\Exception $e) {
                    $this->warn("Error inserting into occupational_fields: " . $e->getMessage());
                }
            }
            
            DB::statement("SET FOREIGN_KEY_CHECKS=1;");
            $this->info("✓ occupational_fields imported");
        } else {
            $this->warn("No occupational_fields data found in backup file");
        }
    }
    
    private function importExperienceFields()
    {
        $this->info('Importing experience_fields data...');

        $filePath = $this->getSqlBackupPath('ybwztpvr_earth (7).sql');
        
        if (!File::exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return;
        }
        
        $content = File::get($filePath);
        
        // Extract INSERT statement for experience_fields
        $pattern = "/INSERT INTO `experience_fields`.*?;/s";
        if (preg_match_all($pattern, $content, $matches)) {
            $this->info("Importing experience_fields...");
            
            DB::statement("SET FOREIGN_KEY_CHECKS=0;");
            DB::statement("TRUNCATE TABLE `experience_fields`;");
            
            foreach ($matches[0] as $insert) {
                try {
                    DB::statement($insert);
                } catch (\Exception $e) {
                    $this->warn("Error inserting into experience_fields: " . $e->getMessage());
                }
            }
            
            DB::statement("SET FOREIGN_KEY_CHECKS=1;");
            $this->info("✓ experience_fields imported");
        } else {
            $this->warn("No experience_fields data found in backup file");
        }
    }
    
    private function importPages()
    {
        $this->info('Importing pages data...');

        $filePath = $this->getSqlBackupPath('ybwztpvr_earth (7).sql');
        
        if (!File::exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return;
        }
        
        $content = File::get($filePath);
        
        // Extract INSERT statement for pages
        $pattern = "/INSERT INTO `pages`.*?;/s";
        if (preg_match_all($pattern, $content, $matches)) {
            $this->info("Importing pages...");
            
            DB::statement("SET FOREIGN_KEY_CHECKS=0;");
            
            foreach ($matches[0] as $insert) {
                try {
                    // Use INSERT IGNORE to avoid duplicate key errors
                    $insert = str_replace('INSERT INTO', 'INSERT IGNORE INTO', $insert);
                    // Add default values for template and show_in_header columns that exist in DB but not in backup
                    $insert = preg_replace(
                        "/INSERT IGNORE INTO `pages`\s*\(([^)]+)\)\s*VALUES/i",
                        "INSERT IGNORE INTO `pages` ($1, `template`, `show_in_header`) VALUES",
                        $insert
                    );
                    // Add NULL, 0 for template and show_in_header in each VALUES row
                    $insert = preg_replace(
                        "/\)\s*\)\s*([,;])/",
                        ", NULL, 0)$1",
                        $insert
                    );
                    
                    DB::statement($insert);
                } catch (\Exception $e) {
                    $this->warn("Error inserting into pages: " . $e->getMessage());
                }
            }
            
            DB::statement("SET FOREIGN_KEY_CHECKS=1;");
            $this->info("✓ pages imported");
        } else {
            $this->warn("No pages data found in backup file");
        }
    }
}
