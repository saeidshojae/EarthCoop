<?php

namespace App\Services\NajmHoda\Context;

use App\Models\Group;
use App\Services\NajmHoda\NajmHodaGroupKnowledgeService;
use App\Services\NajmHoda\NajmHodaGroupSemanticAnalysisService;
use Carbon\CarbonInterface;

/**
 * Deterministic responder for validated page/group context. Group knowledge
 * requests use a grounded cognitive layer when a provider is available and
 * fall back to factual rendering when it is not.
 */
class NajmHodaGroundedPageResponder
{
    /** @param array<string,mixed> $pageContext */
    public function respond(string $message, array $pageContext): ?array
    {
        $plain = $this->normalize($message);
        if ($plain === '') return null;

        $groupKnowledge = $this->respondToGroupKnowledgeRequest($plain, $pageContext);
        if ($groupKnowledge !== null) return $groupKnowledge;

        $contracts = array_values(array_filter((array) ($pageContext['capability_contracts'] ?? []), 'is_array'));
        $delegated = array_values(array_filter((array) ($pageContext['delegated_actions'] ?? []), 'is_array'));

        if ($this->asksHowTo($plain)) {
            $contract = $this->findRequestedContract($plain, $contracts);
            if ($contract !== null) return $this->response($this->renderContractHowTo($contract));
        }

        if (!$this->asksPageIdentity($plain) && !$this->asksPageCapabilities($plain)) return null;

        $label = trim((string) ($pageContext['page_label'] ?? ''));
        if ($label === '') return $this->response('در حال حاضر اطلاعات معتبر کافی برای تشخیص صفحه بازشده در اختیار ندارم.');

        $lines = ["شما اکنون در صفحه «{$label}» هستید."];
        if ($contracts === []) {
            $lines[] = 'در این لحظه قابلیت قابل‌اعتمادی برای این صفحه در قراردادهای سیستم ثبت نشده است.';
        } else {
            $lines[] = '';
            $lines[] = 'کارهایی که خودتان در همین صفحه می‌توانید انجام دهید:';
            foreach ($contracts as $contract) $lines[] = $this->renderContractSummary($contract);
        }

        if ($this->mentionsDelegation($plain) || $delegated !== []) {
            $lines[] = '';
            if ($delegated === []) {
                $lines[] = 'در این صفحه فعلاً اقدام اجرایی قابل تفویضی به نجم هدا برای نقش فعلی شما ثبت نشده است.';
            } else {
                $lines[] = 'کارهایی که می‌توانید در چت خصوصی به نجم هدا بسپارید:';
                foreach ($delegated as $action) {
                    $actionLabel = trim((string) ($action['label'] ?? $action['id'] ?? 'اقدام'));
                    $suffix = (bool) ($action['requires_confirmation'] ?? false) ? ' — قبل از اجرا پیش‌نمایش می‌دهم و تأیید شما لازم است.' : '';
                    $lines[] = "• {$actionLabel}{$suffix}";
                }
                $lines[] = 'گفت‌وگوی اجرایی در همین چت خصوصی می‌ماند و فقط نتیجهٔ تأییدشده در گروه منتشر می‌شود.';
            }
        }

        return $this->response(implode("\n", $lines));
    }

    /** @param array<string,mixed> $pageContext */
    protected function respondToGroupKnowledgeRequest(string $plain, array $pageContext): ?array
    {
        if ((string) ($pageContext['page_kind'] ?? '') !== 'group_chat') return null;

        $asksSummary = $this->containsAny($plain, ['خلاصه کن','خلاصه بده','خلاصه گروه','جمع بندی','جمع‌بندی','گزارش فعالیت','چه گذشت','چه اتفاقی افتاد','مرور مطالب','مرور گفتگو','مرور گفت‌وگو','تحلیل معنایی','موضوعات اصلی']);
        $asksMinutes = $this->containsAny($plain, ['صورتجلسه','صورت جلسه','صورت‌جلسه','گزارش جلسه','جمع بندی جلسه','جمع‌بندی جلسه']);
        if (!$asksSummary && !$asksMinutes) return null;

        $resource = is_array($pageContext['resource'] ?? null) ? $pageContext['resource'] : [];
        $groupId = (int) ($pageContext['resource_id'] ?? $resource['id'] ?? 0);
        if ($groupId <= 0) return $this->response('شناسه معتبر گروه در context فعلی در دسترس نیست؛ نمی‌توانم بدون حدس محتوای گروه را تحلیل کنم.');

        $group = Group::query()->find($groupId);
        if (!$group) return $this->response('گروه فعلی در سیستم پیدا نشد.');

        [$from, $to, $windowLabel] = $this->resolveKnowledgeWindow($plain);

        // Prefer semantic understanding, but never lose basic group intelligence
        // merely because the external model is unavailable.
        $semantic = app(NajmHodaGroupSemanticAnalysisService::class)->analyze(
            $group,
            $from,
            $to,
            $asksMinutes ? 'minutes' : 'summary'
        );
        if ((bool) ($semantic['available'] ?? false) && trim((string) ($semantic['text'] ?? '')) !== '') {
            $heading = $asksMinutes
                ? "صورتجلسهٔ تحلیلی {$windowLabel} — {$group->name}"
                : "خلاصهٔ تحلیلی {$windowLabel} — {$group->name}";
            return $this->response($heading . "\n\n" . trim((string) $semantic['text']) . "\n\nمبنای این تحلیل فقط snapshot واقعی همین گروه و همین بازه است.", true);
        }

        $snapshot = is_array($semantic['snapshot'] ?? null)
            ? $semantic['snapshot']
            : app(NajmHodaGroupKnowledgeService::class)->snapshot($group, $from, $to, 120);

        return $this->response(
            $asksMinutes ? $this->renderGroundedMinutes($snapshot, $windowLabel) : $this->renderGroundedSummary($snapshot, $windowLabel),
            false
        );
    }

    /** @return array{0:CarbonInterface,1:CarbonInterface,2:string} */
    protected function resolveKnowledgeWindow(string $plain): array
    {
        $now = now();
        if ($this->containsAny($plain, ['دیروز'])) return [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay(), 'دیروز'];
        if ($this->containsAny($plain, ['این هفته','هفته جاری','هفته اخیر'])) return [$now->copy()->startOfWeek(), $now->copy(), 'این هفته'];
        if ($this->containsAny($plain, ['۲۴ ساعت','24 ساعت','شبانه روز','شبانه‌روز'])) return [$now->copy()->subDay(), $now->copy(), '۲۴ ساعت اخیر'];
        return [$now->copy()->startOfDay(), $now->copy(), 'امروز'];
    }

    /** @param array<string,mixed> $snapshot */
    protected function renderGroundedSummary(array $snapshot, string $windowLabel): string
    {
        $groupName = trim((string) data_get($snapshot, 'group.name', 'گروه'));
        $counts = (array) ($snapshot['counts'] ?? []);
        $messages = (array) ($snapshot['messages'] ?? []);
        $posts = (array) ($snapshot['posts'] ?? []);
        $polls = (array) ($snapshot['polls'] ?? []);
        $actionItems = (array) ($snapshot['action_items'] ?? []);
        $lines = ["خلاصهٔ داده‌محور {$windowLabel} برای «{$groupName}»: ",'• پیام‌ها: '.(int)($counts['messages']??0),'• پست‌ها: '.(int)($counts['posts']??0),'• نظرسنجی‌های ایجادشده: '.(int)($counts['polls']??0),'• موارد اقدام ثبت‌شده: '.(int)($counts['action_items']??0)];
        if ($posts !== []) { $lines[]=''; $lines[]='پست‌های این بازه:'; foreach(array_slice($posts,-5) as $post){$title=trim((string)($post['title']??''))?:('پست #'.(int)($post['id']??0));$author=trim((string)($post['author']??''));$lines[]='• '.$title.($author!==''?" — {$author}":'');}}
        if ($polls !== []) { $lines[]=''; $lines[]='نظرسنجی‌های این بازه:'; foreach(array_slice($polls,-5) as $poll)$lines[]='• '.trim((string)($poll['question']??'')); }
        if ($messages !== []) { $lines[]=''; $lines[]='نمونهٔ آخرین گفتگوهای ثبت‌شده:'; foreach(array_slice($messages,-8) as $message){$author=trim((string)($message['author']??''))?:'عضو';$text=mb_substr(trim((string)($message['text']??'')),0,220);if($text!=='')$lines[]="• {$author}: {$text}";}}
        if ($actionItems !== []) { $lines[]=''; $lines[]='موارد اقدام ثبت‌شده در این بازه:'; foreach(array_slice($actionItems,-8) as $item){$title=trim((string)($item['title']??''));$status=trim((string)($item['status']??''));$assignee=trim((string)($item['assignee_name']??''));$suffix=$assignee!==''?" — مسئول: {$assignee}":'';if($status!=='')$suffix.=" — وضعیت: {$status}";$lines[]="• {$title}{$suffix}";}}
        $lines[]=''; $lines[]='این گزارش fallback داده‌محور است؛ سرویس تحلیل معنایی در این درخواست در دسترس نبود و هیچ برداشتی به داده‌ها اضافه نشده است.';
        return implode("\n",$lines);
    }

    /** @param array<string,mixed> $snapshot */
    protected function renderGroundedMinutes(array $snapshot, string $windowLabel): string
    {
        $groupName=trim((string)data_get($snapshot,'group.name','گروه'));$counts=(array)($snapshot['counts']??[]);$posts=(array)($snapshot['posts']??[]);$polls=(array)($snapshot['polls']??[]);$actionItems=(array)($snapshot['action_items']??[]);$messages=(array)($snapshot['messages']??[]);
        $lines=["پیش‌نویس صورتجلسهٔ داده‌محور — {$groupName}","بازه: {$windowLabel}",'','۱) دامنه فعالیت ثبت‌شده','• '.(int)($counts['messages']??0).' پیام، '.(int)($counts['posts']??0).' پست و '.(int)($counts['polls']??0).' نظرسنجی در این بازه ثبت شده است.','','۲) موضوعات قابل استناد'];
        if($posts===[]&&$polls===[])$lines[]='• پست یا نظرسنجی جدیدی برای استخراج موضوع رسمی ثبت نشده است.';else{foreach(array_slice($posts,-6) as $post){$title=trim((string)($post['title']??''))?:('پست #'.(int)($post['id']??0));$lines[]="• پست: {$title}";}foreach(array_slice($polls,-6) as $poll)$lines[]='• نظرسنجی: '.trim((string)($poll['question']??''));}
        $lines[]='';$lines[]='۳) نکات مطرح‌شده در گفتگو';if($messages===[])$lines[]='• پیام متنی قابل استنادی در این بازه ثبت نشده است.';else foreach(array_slice($messages,-10) as $message){$author=trim((string)($message['author']??''))?:'عضو';$text=mb_substr(trim((string)($message['text']??'')),0,260);if($text!=='')$lines[]="• {$author}: {$text}";}
        $lines[]='';$lines[]='۴) تصمیمات/اقدامات ثبت‌شده در سیستم';if($actionItems===[])$lines[]='• در این بازه Action Item ثبت‌شده‌ای وجود ندارد؛ بنابراین نجم هدا چیزی را به‌عنوان مصوبه قطعی حدس نمی‌زند.';else foreach($actionItems as $item){$title=trim((string)($item['title']??''));$assignee=trim((string)($item['assignee_name']??''));$dueAt=trim((string)($item['due_at']??''));$suffix=$assignee!==''?" — مسئول: {$assignee}":'';if($dueAt!=='')$suffix.=" — موعد: {$dueAt}";$lines[]="• {$title}{$suffix}";}
        $lines[]='';$lines[]='این متن fallback داده‌محور است؛ تحلیل معنایی در این درخواست در دسترس نبود. هیچ مصوبه یا برداشت جدیدی ساخته نشده است.';
        return implode("\n",$lines);
    }

    protected function asksPageIdentity(string $plain): bool { return $this->containsAny($plain,['چه صفحه','کجا هستم','کجای','در کجا','صفحه فعلی','صفحه کنونی']); }
    protected function asksPageCapabilities(string $plain): bool { return $this->containsAny($plain,['چه کارهایی','چه کارهائی','چه امکانات','چه قابلیت','میتونم انجام','می توانم انجام','می‌تونم انجام','می‌توانم انجام','به تو بسپار','بسپارم']); }
    protected function asksHowTo(string $plain): bool { return $this->containsAny($plain,['چطور','چگونه','روش ','مراحل ','دقیقاً چطور','دقیقا چطور']); }
    protected function mentionsDelegation(string $plain): bool { return $this->containsAny($plain,['به تو بسپار','بسپارم','تفویض','تو انجام بده','برام انجام بده']); }

    /** @param array<int,array<string,mixed>> $contracts */
    protected function findRequestedContract(string $plain,array $contracts):?array { $keywords=['create_poll'=>['نظرسنجی','نظر سنجی'],'create_post'=>['پست','نوشته'],'send_message'=>['پیام متنی','پیام بفرستم','پیام ارسال'],'send_voice'=>['پیام صوتی','صدا','ویس'],'vote'=>['رای','رأی'],'read_group_feed'=>['گفتگو','گفت‌وگو','فید']];foreach($contracts as $contract){$id=(string)($contract['id']??'');$needles=$keywords[$id]??[];$label=trim((string)($contract['label']??''));if($label!=='')$needles[]=$this->normalize($label);if($this->containsAny($plain,$needles))return $contract;}return null; }
    protected function renderContractSummary(array $contract):string { $label=trim((string)($contract['label']??$contract['id']??'قابلیت'));$summary=trim((string)($contract['summary']??''));return $summary===''?"• {$label}":"• {$label}: {$summary}"; }
    protected function renderContractHowTo(array $contract):string { $label=trim((string)($contract['label']??$contract['id']??'این کار'));$summary=trim((string)($contract['summary']??''));$steps=array_values(array_filter(array_map(static fn($value):string=>is_scalar($value)?trim((string)$value):'',(array)data_get($contract,'ui.steps',[]))));$lines=["برای «{$label}» در همین صفحه:"];if($summary!=='')$lines[]=$summary;foreach($steps as $index=>$step)$lines[]=($index+1).'. '.$step;return implode("\n",$lines); }

    protected function response(string $message, ?bool $semantic = null):array { $response=['success'=>true,'message'=>$message,'agent'=>'runtime','agent_name'=>'نجم‌هدا','agent_icon'=>$semantic?'🧠':'🧭','suggestions'=>[],'grounded_page_response'=>true];if($semantic!==null)$response['semantic_group_analysis']=$semantic;return $response; }
    protected function normalize(string $value):string { $plain=mb_strtolower(trim(strip_tags($value)));$plain=str_replace(['ي','ك','ۀ'],['ی','ک','ه'],$plain);return preg_replace('/\s+/u',' ',$plain)?:$plain; }
    protected function containsAny(string $haystack,array $needles):bool { foreach($needles as $needle)if($needle!==''&&mb_stripos($haystack,$needle)!==false)return true;return false; }
}
