<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\EmailTemplate;
use App\Models\FounderEmailDraft;
use Illuminate\Validation\ValidationException;

class FounderEmailDraftService
{
    /** @param array<int,string> $recipients */
    public function draft(array $recipients, ?int $templateId = null, ?string $subject = null, ?string $body = null, array $variables = [], ?string $reasonCode = null, ?int $actorId = null): array
    {
        $emails = array_values(array_unique(array_filter(array_map('trim', $recipients), static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)));
        if ($emails === []) throw ValidationException::withMessages(['recipients' => 'At least one valid recipient is required.']);

        if ($templateId !== null) {
            $template = EmailTemplate::query()->whereKey($templateId)->where('is_active', true)->first();
            if (! $template) throw ValidationException::withMessages(['template_id' => 'Active email template not found.']);
            $rendered = $template->render($variables);
            $subject = $rendered['subject'];
            $body = $rendered['body'];
        }

        if (blank($subject) || blank($body)) throw ValidationException::withMessages(['email' => 'Subject and body are required.']);

        $existing = FounderEmailDraft::query()->where('status', 'draft')->where('reason_code', $reasonCode)->first();
        if ($existing && $reasonCode) return ['success'=>true,'status'=>'already_prepared','draft_id'=>$existing->id];

        $draft = FounderEmailDraft::query()->create([
            'template_id'=>$templateId,'recipients'=>$emails,'subject'=>$subject,'body'=>$body,'variables'=>$variables,
            'status'=>'draft','reason_code'=>$reasonCode,'created_by'=>$actorId,
        ]);

        return ['success'=>true,'status'=>'drafted','draft_id'=>$draft->id,'recipient_count'=>count($emails)];
    }
}
