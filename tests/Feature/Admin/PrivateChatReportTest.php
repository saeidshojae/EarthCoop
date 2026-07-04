<?php

namespace Tests\Feature\Admin;

use App\Models\PrivateChatReport;
use App\Models\PrivateConversation;
use App\Models\PrivateMessage;
use App\Models\User;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\UpdateLastSeen;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PrivateChatReportTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(UpdateLastSeen::class);
        $this->withoutMiddleware(AdminMiddleware::class);

        if (! Schema::hasTable('private_conversations')) {
            Schema::create('private_conversations', function (Blueprint $table) {
                $table->id();
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('private_conversation_user')) {
            Schema::create('private_conversation_user', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('private_conversation_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('private_messages')) {
            Schema::create('private_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('private_conversation_id');
                $table->unsignedBigInteger('sender_id');
                $table->text('message');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('private_chat_reports')) {
            Schema::create('private_chat_reports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('private_conversation_id');
                $table->unsignedBigInteger('reported_message_id');
                $table->unsignedBigInteger('reporter_id');
                $table->unsignedBigInteger('reported_user_id');
                $table->string('reason');
                $table->text('description')->nullable();
                $table->string('status')->default('pending');
                $table->text('admin_notes')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_admin_can_view_private_chat_reports_and_review_them(): void
    {
        $admin = User::forceCreate([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin+' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $admin->setAttribute('is_admin', true);

        $reporter = User::forceCreate([
            'first_name' => 'Reporter',
            'last_name' => 'User',
            'email' => 'reporter+' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);

        $reportedUser = User::forceCreate([
            'first_name' => 'Reported',
            'last_name' => 'User',
            'email' => 'reported+' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);

        $conversation = PrivateConversation::create(['status' => 'active']);
        $conversation->users()->attach([$reporter->id, $reportedUser->id]);

        $message = PrivateMessage::create([
            'private_conversation_id' => $conversation->id,
            'sender_id' => $reportedUser->id,
            'message' => 'Message under review',
        ]);

        $report = PrivateChatReport::create([
            'private_conversation_id' => $conversation->id,
            'reported_message_id' => $message->id,
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reportedUser->id,
            'reason' => 'spam',
            'description' => 'Spam report',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.private-chat-reports'))
            ->assertOk()
            ->assertSee('گزارش‌های چت خصوصی');

        $this->actingAs($admin)
            ->get(route('admin.private-chat-reports.show', $report->id))
            ->assertOk()
            ->assertSee('جزئیات گزارش چت خصوصی');

        $this->actingAs($admin)
            ->post(route('admin.private-chat-reports.review', $report->id), [
                'status' => 'resolved',
                'admin_notes' => 'Handled by moderation',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('private_chat_reports', [
            'id' => $report->id,
            'status' => 'resolved',
            'reviewed_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.private-chat-reports.destroy', $report->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('private_chat_reports', ['id' => $report->id]);
    }

}