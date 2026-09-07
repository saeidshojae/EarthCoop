<?php

namespace Database\Seeders;

use App\Models\ElectionPolicyVersion;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\GroupSetting;
use App\Models\User;
use App\Services\Elections\ElectionPolicyVersionService;
use Illuminate\Database\Seeder;

class ElectionResponsibilityContractSeeder extends Seeder
{
    public function run(): void
    {
        $actorId = User::query()->where('is_system', true)->orderBy('id')->value('id');

        foreach (['manager', 'inspector'] as $position) {
            $exists = ElectionResponsibilityContractVersion::query()
                ->where('position', $position)
                ->where('is_active', true)
                ->where('e0_compliant', true)
                ->whereNotNull('published_at')
                ->exists();

            if ($exists) {
                continue;
            }

            $label = $position === 'manager' ? 'مدیر' : 'بازرس';
            $clauses = $this->clauses($label, $position);

            ElectionResponsibilityContractVersion::query()->create([
                'position' => $position,
                'version' => 1,
                'body' => $this->renderBody($label, $clauses),
                'clause_manifest' => $clauses,
                'e0_compliant' => true,
                'is_active' => true,
                'published_at' => now(),
                'created_by' => $actorId,
                'change_reason' => 'fresh_install_operational_baseline; replaceable only by publishing a new admin version',
            ]);
        }

        // If an operator already published an effective policy before contracts
        // existed (common in local/bootstrap environments), never mutate that
        // immutable policy in place. Publish a successor with identical settings
        // and the now-available active contract versions.
        $policyVersions = app(ElectionPolicyVersionService::class);
        $affectedSettingIds = ElectionPolicyVersion::query()
            ->where('effective_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('retired_at')->orWhere('retired_at', '>', now());
            })
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('manager_count', '>', 0)->whereNull('manager_contract_version_id');
                })->orWhere(function ($q) {
                    $q->where('inspector_count', '>', 0)->whereNull('inspector_contract_version_id');
                });
            })
            ->pluck('group_setting_id')
            ->unique();

        foreach ($affectedSettingIds as $settingId) {
            $setting = GroupSetting::query()->find($settingId);
            if ($setting === null) {
                continue;
            }

            $policyVersions->publishFromSetting(
                $setting,
                $actorId ? (int) $actorId : null,
                'bootstrap_contract_binding_after_contract_seed',
                now(),
            );
        }
    }

    private function clauses(string $label, string $position): array
    {
        $specificDuty = $position === 'manager'
            ? 'اجرای تصمیم‌های معتبر گروه، اداره امور محوله و ارائه گزارش روشن و دوره‌ای به اعضا.'
            : 'نظارت مستقل بر عملکرد مدیران، کنترل رعایت قواعد و گزارش بی‌طرفانه موارد قابل پیگیری به اعضا.';

        return [
            'role_scope_and_cycle' => "این قرارداد مربوط به مسئولیت {$label} در گروه، نوع، سطح و چرخه‌ای است که در دعوت رسمی سامانه مشخص می‌شود. پذیرش فقط برای همان دعوت و همان نسخه قرارداد معتبر است.",
            'term_compensation_and_commitment' => 'مدت مسئولیت، شیوه جبران خدمت و میزان تعهد زمانی مطابق سیاست مؤثر همان چرخه و مقررات مصوب EarthCoop است. هر تغییر آینده فقط با نسخه جدید قرارداد بر دعوت‌های بعدی اثر می‌گذارد.',
            'duties_reporting_and_member_oversight' => $specificDuty.' اعضای گروه حق نظارت، طرح پرسش، ثبت بازخورد و استفاده از مسیرهای رسمی پاسخ‌خواهی و بازبینی را دارند.',
            'conflicts_confidentiality_and_vote_integrity' => 'دارنده مسئولیت موظف است تعارض منافع را اعلام کند، محرمانگی داده‌های غیرعمومی را رعایت کند و از هرگونه خرید رأی، فشار، تبانی، افشای غیرمجاز رأی یا سوءاستفاده از جایگاه خودداری کند.',
            'resignation_suspension_disqualification_and_succession' => 'استعفا، تعلیق، سلب صلاحیت، تعارض مسئولیت و جانشینی فقط مطابق سیاست نسخه‌دار انتخابات و زنجیره رسمی vacancy/backfill سامانه انجام می‌شود و هیچ انتصاب دستی خارج از آن معتبر نیست.',
            'complaint_reply_review_and_acceptance_audit' => 'اعضا و اشخاص ذی‌نفع می‌توانند از مسیر رسمی شکایت و بازبینی استفاده کنند. مسئول مربوط حق پاسخ موضوعی در چارچوب حریم خصوصی دارد و پذیرش یا رد مسئولیت، زمان، نسخه قرارداد و تصمیم‌های بازبینی در audit سامانه ثبت می‌شود.',
        ];
    }

    private function renderBody(string $label, array $clauses): string
    {
        $titles = [
            'role_scope_and_cycle' => '۱. سمت، گروه، نوع، سطح، تاریخ شروع و چرخه',
            'term_compensation_and_commitment' => '۲. مدت، حقوق، شیوه پرداخت و تعهد زمانی',
            'duties_reporting_and_member_oversight' => '۳. وظایف، گزارش‌دهی و حق نظارت اعضا',
            'conflicts_confidentiality_and_vote_integrity' => '۴. تعارض منافع، محرمانگی و سلامت رأی',
            'resignation_suspension_disqualification_and_succession' => '۵. استعفا، تعلیق، سلب صلاحیت و جانشینی',
            'complaint_reply_review_and_acceptance_audit' => '۶. شکایت، حق پاسخ، بازبینی و ممیزی پذیرش',
        ];

        $parts = ["قرارداد پایه مسئولیت {$label}"];
        foreach (ElectionResponsibilityContractVersion::REQUIRED_CLAUSES as $key) {
            $parts[] = $titles[$key]."\n".$clauses[$key];
        }

        return implode("\n\n", $parts);
    }
}
