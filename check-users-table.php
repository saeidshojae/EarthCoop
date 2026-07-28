<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$columns = DB::getSchemaBuilder()->getColumnListing('users');
echo "Columns in users table:\n";
foreach($columns as $col) {
    echo "  - " . $col . "\n";
}

// Also get a sample user
$user = DB::table('users')->find(5);
echo "\nSample User (ID 5):\n";
if($user) {
    foreach((array)$user as $key => $val) {
        echo "  $key: $val\n";
    }
} else {
    echo "  User not found\n";
}
