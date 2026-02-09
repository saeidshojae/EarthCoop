<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Checking role 'support' and user_role entries...\n";
$role = DB::table('roles')->where('slug', 'support')->first();
if (! $role) {
    echo "Role 'support' not found.\n";
    exit(1);
}
echo "Role found: id={$role->id}, name={$role->name}, slug={$role->slug}\n";

$rel = DB::table('user_role')->where('role_id', $role->id)->first();
if (! $rel) {
    echo "No entries in user_role for role_id={$role->id}\n";
} else {
    echo "user_role entry: user_id={$rel->user_id}, role_id={$rel->role_id}\n";
    $user = DB::table('users')->where('id', $rel->user_id)->first();
    if ($user) {
        echo "User exists: id={$user->id}, email={$user->email}\n";
    } else {
        echo "User id {$rel->user_id} not found in users table\n";
    }
}
