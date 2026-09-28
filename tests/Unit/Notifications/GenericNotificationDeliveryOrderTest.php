<?php

namespace Tests\Unit\Notifications;

use App\Notifications\GenericNotification;
use PHPUnit\Framework\TestCase;

class GenericNotificationDeliveryOrderTest extends TestCase
{
    public function test_database_channel_uses_sync_connection_before_async_delivery_channels(): void
    {
        $connections = (new GenericNotification('Title', 'Body'))->viaConnections();

        $this->assertSame('sync', $connections['database'] ?? null);
    }
}
