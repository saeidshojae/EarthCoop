<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = App\Models\Message::query()
    ->whereNotNull('voice_message')
    ->latest('id')
    ->take(20)
    ->get(['id','user_id','file_type','file_name','voice_message','created_at']);

foreach ($rows as $m) {
    echo $m->id . ' | u' . $m->user_id . ' | ' . ($m->file_type ?? 'null') . ' | ' . ($m->file_name ?? 'null') . ' | ' . basename((string) $m->voice_message) . ' | ' . $m->created_at . PHP_EOL;
}
