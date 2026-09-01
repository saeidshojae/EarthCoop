<?php

namespace Tests\Feature;

use App\Models\PrivateMessage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PrivateMessagingReadStateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_private_messages_have_persisted_read_state_contract(): void
    {
        $this->assertTrue(
            Schema::hasColumn('private_messages', 'read_at'),
            'Private messages must persist a nullable read_at timestamp.'
        );

        $message = new PrivateMessage();
        $casts = $message->getCasts();

        $this->assertArrayHasKey('read_at', $casts);
        $this->assertSame('datetime', $casts['read_at']);
    }
}
