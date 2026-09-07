<?php

namespace App\Console\Commands;

use App\Services\Notifications\AnnouncementManagementService;
use Illuminate\Console\Command;

class RepairLegacyAnnouncementPins extends Command
{
    protected $signature = 'announcements:repair-legacy-pins';

    protected $description = 'Repair legacy synthetic announcement pins and re-attribute announcements to the EarthCoop management identity.';

    public function handle(AnnouncementManagementService $announcements): int
    {
        $stats = $announcements->repairLegacyArtifacts();

        $this->info('Announcement legacy repair completed.');
        $this->line('Re-attributed announcements: ' . $stats['announcements_reattributed']);
        $this->line('Legacy pins repaired: ' . $stats['legacy_pins_repaired']);
        $this->line('Legacy synthetic messages deleted: ' . $stats['legacy_messages_deleted']);
        $this->line('Missing direct pins created: ' . $stats['pins_created']);

        return self::SUCCESS;
    }
}
