<?php

namespace App\Console\Commands;

use App\Modules\Secretariat\Services\SecretariatLegacyAssessmentService;
use Illuminate\Console\Command;

class SecretariatLegacyAssess extends Command
{
    protected $signature = 'secretariat:legacy-assess {--limit=1000 : Maximum ticket attachments to assess} {--json : Emit machine-readable JSON}';

    protected $description = 'Assess legacy document sources for safe Secretariat migration without mutating data.';

    public function handle(SecretariatLegacyAssessmentService $assessment): int
    {
        $result = $assessment->assess((int) $this->option('limit'));

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        }

        $legacy = $result['legacy_files'];
        $tickets = $result['ticket_attachments'];

        $this->table(['Source', 'Rows', 'Candidates', 'Decision'], [
            ['files', $legacy['row_count'], $legacy['importable_count'], $legacy['decision']],
            ['ticket_attachments', $tickets['row_count'], $tickets['candidate_count'], $tickets['decision']],
        ]);

        $this->line('Ticket attachment issues: missing_storage=' . $tickets['missing_storage_count']
            . ', missing_ticket=' . $tickets['missing_ticket_count']
            . ', missing_uploader=' . $tickets['missing_uploader_count']);

        return self::SUCCESS;
    }
}
