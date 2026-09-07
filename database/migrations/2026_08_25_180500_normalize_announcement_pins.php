<?php

use App\Models\Announcement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $legacyPins = DB::table('pinned_messages')
                ->whereNotNull('announcement_id')
                ->get(['id', 'announcement_id', 'message_id']);

            if ($legacyPins->isEmpty()) {
                return;
            }

            $messageIds = $legacyPins->pluck('message_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            foreach ($legacyPins as $pin) {
                DB::table('pinned_messages')->where('id', $pin->id)->update([
                    'message_id' => null,
                    'content_type' => Announcement::class,
                    'content_id' => (int) $pin->announcement_id,
                    'updated_at' => now(),
                ]);
            }

            if ($messageIds !== []) {
                DB::table('messages')->whereIn('id', $messageIds)->delete();
            }
        });
    }

    public function down(): void
    {
        // Legacy synthetic chat messages intentionally cannot be reconstructed.
        // Keeping direct announcement pins is safer than fabricating historical authorship/content.
    }
};
