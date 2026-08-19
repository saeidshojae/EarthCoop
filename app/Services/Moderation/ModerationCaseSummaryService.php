<?php

namespace App\Services\Moderation;

use App\Models\ModerationCaseSummary;
use App\Models\Report;
use App\Models\ReportedMessage;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;

class ModerationCaseSummaryService
{
    public function __construct(protected RuntimeEventBus $events) {}

    /** @return array<string,mixed> */
    public function prepare(string $sourceType, int $sourceId, ?string $reasonCode = null): array
    {
        $existing = ModerationCaseSummary::query()
            ->where('source_type', $sourceType)->where('source_id', $sourceId)
            ->where('status', 'draft')->latest('id')->first();
        if ($existing) return $this->summary($existing, 'existing');

        [$reason, $description, $itemType, $priority] = $this->sourceText($sourceType, $sourceId);
        $text = mb_strtolower(trim($reason . ' ' . $description));
        $classification = $this->classify($text);
        $severity = $this->severity($text, $priority, $classification);

        $body = trim('نوع مورد: ' . $itemType . "\n"
            . 'دسته پیشنهادی: ' . $classification . "\n"
            . 'شدت پیشنهادی: ' . $severity . "\n"
            . 'دلیل گزارش: ' . mb_substr($reason, 0, 800)
            . ($description !== '' ? "\nشرح تکمیلی: " . mb_substr($description, 0, 1400) : ''));

        $case = ModerationCaseSummary::create([
            'source_type'=>$sourceType,'source_id'=>$sourceId,'classification'=>$classification,
            'severity'=>$severity,'summary'=>$body,'status'=>'draft','reason_code'=>$reasonCode,
        ]);

        $this->events->emit('najm_hoda.input.moderation.case_summary.created', [
            'source_type'=>$sourceType,'source_id'=>$sourceId,'case_summary_id'=>(int)$case->id,
            'classification'=>$classification,'severity'=>$severity,'reason_code'=>$reasonCode,
        ]);

        return $this->summary($case, 'created');
    }

    /** @return array{0:string,1:string,2:string,3:string} */
    protected function sourceText(string $sourceType, int $sourceId): array
    {
        if ($sourceType === 'report') {
            $r = Report::query()->findOrFail($sourceId);
            return [(string)$r->reason,(string)$r->description,(string)$r->type,(string)($r->priority ?: 'medium')];
        }
        $r = ReportedMessage::query()->findOrFail($sourceId);
        return [(string)$r->reason,(string)$r->description,'message',$r->isEscalatedToAdmin() ? 'high' : 'medium'];
    }

    protected function classify(string $text): string
    {
        $rules = [
            'threat_or_violence'=>['تهدید','خشونت','قتل','آسیب','violence','threat','kill'],
            'fraud_or_scam'=>['کلاهبرداری','فریب','اسکم','fraud','scam'],
            'harassment'=>['توهین','آزار','مزاحمت','تحقیر','harass','abuse','insult'],
            'spam'=>['اسپم','تبلیغ','spam','advertis'],
            'misinformation'=>['اطلاعات نادرست','دروغ','شایعه','misinformation','fake'],
            'privacy'=>['حریم خصوصی','اطلاعات شخصی','شماره تلفن','آدرس','privacy','personal data'],
        ];
        foreach ($rules as $label => $keywords) {
            foreach ($keywords as $keyword) if (str_contains($text, mb_strtolower($keyword))) return $label;
        }
        return 'other';
    }

    protected function severity(string $text, string $priority, string $classification): string
    {
        if ($priority === 'critical' || in_array($classification, ['threat_or_violence','fraud_or_scam'], true)) return 'high';
        if ($priority === 'high' || str_contains($text, 'فوری') || str_contains($text, 'urgent')) return 'high';
        return 'medium';
    }

    /** @return array<string,mixed> */
    protected function summary(ModerationCaseSummary $case, string $mode): array
    {
        return [
            'success'=>true,'status'=>'case_summary_ready','mode'=>$mode,'case_summary_id'=>(int)$case->id,
            'source_type'=>(string)$case->source_type,'source_id'=>(int)$case->source_id,
            'classification'=>(string)$case->classification,'severity'=>(string)$case->severity,
        ];
    }
}
