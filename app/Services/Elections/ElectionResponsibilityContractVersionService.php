<?php

namespace App\Services\Elections;

use App\Models\ElectionResponsibilityContractVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ElectionResponsibilityContractVersionService
{
    public function publish(string $position, array $clauses, User $actor, string $reason): ElectionResponsibilityContractVersion
    {
        if (! in_array($position, ['manager', 'inspector'], true)) throw new InvalidArgumentException('Invalid election responsibility position.');
        if (trim($reason) === '') throw new InvalidArgumentException('Contract change reason is required.');
        foreach (ElectionResponsibilityContractVersion::REQUIRED_CLAUSES as $key) {
            if (trim((string) ($clauses[$key] ?? '')) === '') {
                throw new InvalidArgumentException("Required E0 contract clause [{$key}] is missing.");
            }
            $clauses[$key] = trim((string) $clauses[$key]);
        }

        return DB::transaction(function () use ($position, $clauses, $actor, $reason): ElectionResponsibilityContractVersion {
            $latest = ElectionResponsibilityContractVersion::query()
                ->where('position', $position)->orderByDesc('version')->lockForUpdate()->first();
            $version = (int) ($latest?->version ?? 0) + 1;
            $body = $this->renderBody($position, $clauses);

            if ($latest !== null && $latest->is_active) {
                DB::table('election_responsibility_contract_versions')->where('id', $latest->id)->update(['is_active' => false, 'updated_at' => now()]);
            }

            return ElectionResponsibilityContractVersion::create([
                'position' => $position,
                'version' => $version,
                'body' => $body,
                'clause_manifest' => $clauses,
                'e0_compliant' => true,
                'is_active' => true,
                'published_at' => now(),
                'created_by' => $actor->id,
                'change_reason' => trim($reason),
            ]);
        }, 3);
    }

    private function renderBody(string $position, array $clauses): string
    {
        $label = $position === 'manager' ? 'مدیر' : 'بازرس';
        $titles = [
            'role_scope_and_cycle' => '۱. سمت، گروه، نوع، سطح، تاریخ شروع و چرخه',
            'term_compensation_and_commitment' => '۲. مدت، حقوق، شیوه پرداخت و تعهد زمانی',
            'duties_reporting_and_member_oversight' => '۳. وظایف، گزارش‌دهی و حق نظارت اعضا',
            'conflicts_confidentiality_and_vote_integrity' => '۴. تعارض منافع، محرمانگی و سلامت رأی',
            'resignation_suspension_disqualification_and_succession' => '۵. استعفا، تعلیق، سلب صلاحیت و جانشینی',
            'complaint_reply_review_and_acceptance_audit' => '۶. شکایت، حق پاسخ، بازبینی و ممیزی پذیرش',
        ];
        $parts = ["قرارداد مسئولیت {$label}"];
        foreach (ElectionResponsibilityContractVersion::REQUIRED_CLAUSES as $key) {
            $parts[] = $titles[$key]."\n".$clauses[$key];
        }
        return implode("\n\n", $parts);
    }
}
