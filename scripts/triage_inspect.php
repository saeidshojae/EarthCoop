<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "Inspecting triage environment...\n";
echo 'Has table roles: ' . (Schema::hasTable('roles') ? 'yes' : 'no') . "\n";
echo 'Has table model_has_roles: ' . (Schema::hasTable('model_has_roles') ? 'yes' : 'no') . "\n";
echo 'Has table role_user: ' . (Schema::hasTable('role_user') ? 'yes' : 'no') . "\n";
echo 'Has table user_role: ' . (Schema::hasTable('user_role') ? 'yes' : 'no') . "\n";

$role = DB::table('roles')->where('slug', 'support')->orWhere('name', 'support')->first();
if (! $role) {
    echo "Role 'support' not found by slug or name 'support'.\n";
} else {
    echo "Role by slug/name 'support' found: id={$role->id}, name={$role->name}, slug={$role->slug}\n";

    if (Schema::hasTable('model_has_roles')) {
        $m = DB::table('model_has_roles')->where('role_id', $role->id)->get();
        echo "model_has_roles rows for role_id={$role->id}: " . $m->count() . "\n";
    }

    if (Schema::hasTable('role_user')) {
        $r = DB::table('role_user')->where('role_id', $role->id)->get();
        echo "role_user rows for role_id={$role->id}: " . $r->count() . "\n";
    }

    if (Schema::hasTable('user_role')) {
        $u = DB::table('user_role')->where('role_id', $role->id)->get();
        echo "user_role rows for role_id={$role->id}: " . $u->count() . "\n";
        foreach ($u as $row) {
            echo " -> user_role: user_id={$row->user_id}, role_id={$row->role_id}\n";
        }
    }
}

echo "Done.\n";
