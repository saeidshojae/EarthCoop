<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$files = DB::table('steward_knowledge_files')->select('id','title','search_priority','file_type')->get();
echo "📚 Knowledge Files in Database:\n";
echo "==============================\n";
foreach($files as $f) { 
    echo $f->id . ': ' . $f->title . ' (' . $f->file_type . ') - Priority: ' . $f->search_priority . "\n";
}
echo "\n✅ Total: " . count($files) . " files\n";
