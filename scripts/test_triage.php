<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\TicketTriageService;
use App\Models\Ticket;

echo "Running Ticket Triage Test...\n";

/** @var TicketTriageService $triageService */
$triageService = app(TicketTriageService::class);
$subject = 'پرداخت ناموفق فوری';
$message = 'پرداخت انجام نشد و تراکنش از بین رفت';

$result = $triageService->triage($subject, $message);

echo "Triage result:\n";
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

// Create a ticket using triage result
$tracking = 'TST' . time() . strtoupper(bin2hex(random_bytes(3)));
$ticket = Ticket::create([
    'user_id' => null,
    'name' => 'Automated Test',
    'email' => 'test@example.com',
    'phone' => null,
    'subject' => $subject,
    'message' => $message,
    'status' => 'open',
    'priority' => $result['priority'] ?? null,
    'assignee_id' => $result['assignee_id'] ?? null,
    'tracking_code' => $tracking,
]);

echo "Created ticket ID: {$ticket->id}\n";
echo "Priority: {$ticket->priority}\n";
echo "Assignee ID: {$ticket->assignee_id}\n";

echo "Done.\n";
