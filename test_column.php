<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use App\Models\Setting;

if (Schema::hasColumn('setting', 'najm_bahar_initial_active_percentage')) {
    echo "✓ Column 'najm_bahar_initial_active_percentage' exists\n";
    $setting = Setting::first();
    if ($setting) {
        echo "Current value: " . ($setting->najm_bahar_initial_active_percentage ?? 'null') . "\n";
    }
} else {
    echo "✗ Column does not exist\n";
}
