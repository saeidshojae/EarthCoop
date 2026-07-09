<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = App\Models\Message::query()
    ->whereNotNull('voice_message')
    ->latest('id')
    ->take(6)
    ->get(['id','user_id','voice_message','file_type']);
foreach($rows as $m){
    echo $m->id.'|u'.$m->user_id.'|'.$m->file_type.'|'.$m->voice_message.PHP_EOL;
}
