<?php
require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->handle(new Symfony\Component\Console\Input\ArgvInput(), new Symfony\Component\Console\Output\NullOutput());

try {
    $mailer = $app->make(Illuminate\Contracts\Mail\Mailer::class);
    $to = env('SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS', 'info@earthcoop.info'));
    echo "Sending test email to: $to\n";
    $mailer->raw('Test message from local test script', function($message) use ($to) {
        $message->to($to)->subject('Test email from NewEarthCoop');
    });
    echo "Mail queued/sent successfully.\n";
} catch (Throwable $e) {
    echo "Mail send failed: ". $e->getMessage() ."\n";
    echo $e->getTraceAsString();
}

