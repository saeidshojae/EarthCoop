<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_requests') && Schema::hasColumn('chat_requests', 'private_conversation_id')) {
            $acceptedRequests = DB::table('chat_requests')
                ->where('status', 'accepted')
                ->whereNull('private_conversation_id')
                ->orderBy('id')
                ->get(['id', 'sender_id', 'receiver_id']);

            $conversationMap = [];
            $now = now();

            foreach ($acceptedRequests as $request) {
                $participantIds = [(int) $request->sender_id, (int) $request->receiver_id];
                sort($participantIds);
                $pairKey = implode(':', $participantIds);

                if (! isset($conversationMap[$pairKey])) {
                    $conversationId = DB::table('private_conversations')->insertGetId([
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    foreach ($participantIds as $userId) {
                        DB::table('private_conversation_user')->updateOrInsert(
                            [
                                'private_conversation_id' => $conversationId,
                                'user_id' => $userId,
                            ],
                            [
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]
                        );
                    }

                    $conversationMap[$pairKey] = $conversationId;
                }

                DB::table('chat_requests')
                    ->where('id', $request->id)
                    ->update([
                        'private_conversation_id' => $conversationMap[$pairKey],
                        'updated_at' => $now,
                    ]);
            }
        }

        Schema::table('chat_requests', function (Blueprint $table) {
            if (Schema::hasColumn('chat_requests', 'request_to_group')) {
                $table->dropColumn('request_to_group');
            }
        });

        if (Schema::hasColumn('chat_requests', 'group_id')) {
            // Some environments created group_id without the conventional FK name.
            try {
                Schema::table('chat_requests', function (Blueprint $table) {
                    $table->dropForeign(['group_id']);
                });
            } catch (\Throwable $e) {
                // No foreign key (or non-standard name) exists; continue safely.
            }

            Schema::table('chat_requests', function (Blueprint $table) {
                $table->dropColumn('group_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('chat_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_requests', 'group_id')) {
                $table->foreignId('group_id')->nullable()->constrained('groups')->onDelete('cascade')->after('receiver_id');
            }

            if (! Schema::hasColumn('chat_requests', 'request_to_group')) {
                $table->unsignedBigInteger('request_to_group')->nullable()->after('message');
            }
        });
    }
};
