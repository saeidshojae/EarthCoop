<?php

namespace App\Services\NajmHoda\Context;

use App\Models\Block;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\Secretariat\Models\SecretariatOffice;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Converts narrow browser page hints into server-validated, read-only context.
 * Browser values are never authority; resource details are resolved server-side.
 */
class NajmHodaPageContextResolver
{
    protected NajmHodaPageCapabilityRegistry $capabilityRegistry;

    public function __construct(?NajmHodaPageCapabilityRegistry $capabilityRegistry = null)
    {
        $this->capabilityRegistry = $capabilityRegistry ?? new NajmHodaPageCapabilityRegistry();
    }

    public function resolve(?User $user, array $browserContext): array
    {
        $page = is_array($browserContext['page'] ?? null) ? $browserContext['page'] : [];
        $routeName = $this->cleanToken($page['route_name'] ?? null, 120);
        $module = $this->cleanToken($page['module'] ?? null, 60);
        $resourceType = $this->cleanToken($page['resource_type'] ?? null, 60);
        $resourceId = $this->positiveInt($page['resource_id'] ?? null);
        $description = $this->describePage($routeName, $module, $resourceType);

        $resolved = [
            'route_name' => $routeName, 'module' => $module,
            'page_label' => $description['label'], 'page_kind' => $description['kind'],
            'available_capabilities' => $description['capabilities'],
            'capability_contracts' => [], 'delegated_actions' => [],
            'resource_type' => $resourceType, 'resource_id' => null, 'resource' => null,
        ];

        if (!$user || !$resourceId) return $this->attachCapabilityContracts($resolved);

        if ($this->looksLikeSecretariatOfficeContext($routeName, $module, $resourceType)) {
            $resolved['resource_type'] = 'secretariat_office';
            $resource = $this->resolveSecretariatOffice($user, $resourceId);
            if ($resource !== null) {
                $resolved['resource_id'] = $resourceId;
                $resolved['resource'] = $resource;
            }
            return $this->attachCapabilityContracts($resolved);
        }

        if ($this->looksLikeProjectContext($routeName, $resourceType)) {
            $resolved['resource_type'] = 'najm_bahar_project';
            $resource = $this->resolveProject($user, $resourceId);
            if ($resource !== null) { $resolved['resource_id'] = $resourceId; $resolved['resource'] = $resource; }
            return $this->attachCapabilityContracts($resolved);
        }

        if ($this->looksLikeGroupContext($module, $resourceType)) {
            $resolved['resource_type'] = 'group';
            $resource = $this->resolveGroup($user, $resourceId);
            if ($resource !== null) { $resolved['resource_id'] = $resourceId; $resolved['resource'] = $resource; }
        }
        return $this->attachCapabilityContracts($resolved);
    }

    protected function attachCapabilityContracts(array $resolved): array
    {
        $resource = is_array($resolved['resource'] ?? null) ? $resolved['resource'] : null;
        $contracts = $this->capabilityRegistry->forPage((string) ($resolved['page_kind'] ?? ''), $resource);
        if ($contracts !== []) {
            $resolved['capability_contracts'] = $contracts;
            $resolved['available_capabilities'] = array_values(array_map(fn (array $c): string => (string) ($c['id'] ?? ''), $contracts));
        }
        if ((string) ($resolved['page_kind'] ?? '') === 'group_chat') {
            $resolved['delegated_actions'] = $this->capabilityRegistry->delegatedActionsForGroup($resource);
        }
        return $resolved;
    }

    protected function describePage(?string $routeName, ?string $module, ?string $resourceType): array
    {
        $route = Str::lower((string) $routeName); $module = Str::lower((string) $module);
        $exact = [
            'home' => ['خانه ارثکوپ','home',['navigation','profile_overview','platform_overview']],
            'dashboard' => ['داشبورد کاربر','dashboard',['account_overview','navigation']],
            'najm-bahar.agreement' => ['توافقنامه نجم بهار','najm_bahar_agreement',['read_agreement','accept_agreement']],
            'najm-bahar.projects.index' => ['فهرست پروژه‌های نجم بهار','najm_bahar_projects',['browse_projects','create_project']],
            'najm-bahar.projects.create' => ['ثبت پروژه جدید در نجم بهار','najm_bahar_project_create',['create_project']],
            'najm-bahar.investments.index' => ['فرصت‌های سرمایه‌گذاری نجم بهار','najm_bahar_investments',['browse_investments']],
            'najm-bahar.investments.my-investments' => ['سرمایه‌گذاری‌های من در نجم بهار','najm_bahar_my_investments',['review_own_investments']],
            'secretariat.directory' => ['فهرست دبیرخانه‌ها','secretariat_directory',['browse_secretariat_offices','search_secretariat_records']],
            'secretariat.index' => ['دفتر دبیرخانه','secretariat_office',['browse_secretariat_records','search_secretariat_records','prepare_secretariat_record']],
            'secretariat.records.create' => ['ثبت پیش‌نویس سند دبیرخانه','secretariat_record_create',['prepare_secretariat_record']],
            'secretariat.cases.index' => ['پرونده‌های دبیرخانه','secretariat_cases',['browse_secretariat_cases']],
            'secretariat.cases.create' => ['ایجاد پرونده دبیرخانه','secretariat_case_create',['prepare_secretariat_case']],
            'secretariat.correspondence.create' => ['ثبت مکاتبه دبیرخانه','secretariat_correspondence_create',['prepare_secretariat_correspondence']],
        ];
        if ($route !== '' && isset($exact[$route])) { [$label,$kind,$capabilities] = $exact[$route]; return compact('label','kind','capabilities'); }
        $prefixes = [
            'groups.chat'=>['گفتگوی گروه','group_chat',['read_group_feed','send_message','create_post','create_poll','vote']],
            'groups.comment'=>['نظرات پست گروه','group_comments',['read_comments','create_comment','react_to_comment']],
            'groups.'=>['بخش گروه‌های ارثکوپ','groups',['browse_group','participate_in_group']],
            'najm-bahar.projects.show'=>['جزئیات پروژه نجم بهار','najm_bahar_project',['view_project']],
            'najm-bahar.projects.edit'=>['ویرایش پروژه نجم بهار','najm_bahar_project_edit',['edit_project']],
            'najm-bahar.investments.show'=>['جزئیات فرصت سرمایه‌گذاری نجم بهار','najm_bahar_investment',['view_investment','invest']],
            'secretariat.cases.show'=>['پرونده دبیرخانه','secretariat_case',['view_secretariat_case','review_case_records']],
            'secretariat.correspondence.show'=>['مکاتبه سند دبیرخانه','secretariat_correspondence',['view_secretariat_correspondence']],
            'secretariat.records.show'=>['سند دبیرخانه','secretariat_record',['view_secretariat_record','review_record_context']],
            'secretariat.acl.index'=>['دسترسی‌های سند دبیرخانه','secretariat_record_access',['review_secretariat_access']],
            'admin.najm-hoda.'=>['پنل مدیریت نجم هدا','najm_hoda_admin',['inspect_najm_hoda','manage_najm_hoda']],
            'admin.najm-bahar.'=>['پنل مدیریت نجم بهار','najm_bahar_admin',['review_projects','manage_najm_bahar']],
        ];
        foreach ($prefixes as $prefix => [$label,$kind,$capabilities]) if ($route !== '' && Str::startsWith($route,$prefix)) return compact('label','kind','capabilities');
        if ($resourceType === 'group' || in_array($module,['group','groups'],true)) return ['label'=>'بخش گروه‌های ارثکوپ','kind'=>'groups','capabilities'=>['browse_group','participate_in_group']];
        if (in_array($module,['secretariat','registry'],true)) return ['label'=>'بخش دبیرخانه ارثکوپ','kind'=>'secretariat','capabilities'=>['browse_secretariat_offices','search_secretariat_records']];
        if (in_array($module,['najm-bahar','najm_bahar'],true)) return ['label'=>'بخش نجم بهار','kind'=>'najm_bahar','capabilities'=>['navigate_najm_bahar']];
        if ($module === 'admin') return ['label'=>'پنل مدیریت ارثکوپ','kind'=>'admin','capabilities'=>['admin_navigation']];
        if (in_array($module,['','home'],true)) return ['label'=>'خانه ارثکوپ','kind'=>'home','capabilities'=>['navigation','platform_overview']];
        return ['label'=>'بخش '.$module.' ارثکوپ','kind'=>$module !== '' ? $module : 'unknown','capabilities'=>['navigation']];
    }

    protected function resolveSecretariatOffice(User $user, int $officeId): ?array
    {
        $office = SecretariatOffice::query()->find($officeId);
        if (!$office || !Gate::forUser($user)->allows('view', $office)) return null;
        return [
            'type'=>'secretariat_office', 'id'=>(int)$office->id, 'office_id'=>(int)$office->id,
            'scope_type'=>$this->cleanToken($office->scope_type,80), 'scope_id'=>$office->scope_id ? (int)$office->scope_id : null,
            'default_confidentiality'=>$this->cleanToken($office->default_confidentiality,30),
        ];
    }

    protected function resolveProject(User $user, int $projectId): ?array
    {
        $project = Project::query()->select(['id','owner_type','owner_id','project_type','project_visibility','project_stage','investment_method','status','risk_level','target_market'])->find($projectId);
        if (!$project || !Gate::forUser($user)->allows('view',$project)) return null;
        $viewerRelation='authorized';
        if ($project->owner_type===User::class && (int)$project->owner_id===(int)$user->id) $viewerRelation='owner';
        elseif ($project->status==='approved' && $project->project_visibility==='public') $viewerRelation='public';
        return ['type'=>'najm_bahar_project','id'=>(int)$project->id,'project_type'=>$this->cleanToken($project->project_type,40),'project_visibility'=>$this->cleanToken($project->project_visibility,20),'project_stage'=>$this->cleanToken($project->project_stage,30),'investment_method'=>$this->cleanToken($project->investment_method,40),'status'=>$this->cleanToken($project->status,30),'risk_level'=>$this->cleanToken($project->risk_level,20),'target_market'=>$this->cleanToken($project->target_market,30),'viewer_relation'=>$viewerRelation];
    }

    protected function resolveGroup(User $user, int $groupId): ?array
    {
        $group=Group::query()->select(['id','group_type','location_level','is_open'])->find($groupId); if(!$group)return null;
        $isAdmin=(bool)($user->is_admin??false)||$user->hasRole('super-admin'); $membership=null;
        if(!$isAdmin){$membership=GroupUser::query()->where('group_id',$group->id)->where('user_id',$user->id)->where('status',1)->first(['role']); if(!(bool)$group->is_open&&!$membership)return null;}
        $blockedPositions=Block::query()->where('user_id',$user->id)->whereIn('position',['message','post','poll','election'])->pluck('position')->map(fn($v):string=>(string)$v)->unique()->values()->all();
        return ['type'=>'group','id'=>(int)$group->id,'group_type'=>$this->cleanToken($group->group_type,80),'location_level'=>$this->cleanToken($group->location_level,80),'is_open'=>(bool)$group->is_open,'viewer_relation'=>$isAdmin?'admin':($membership?'member':'public'),'viewer_group_role'=>$membership&&is_scalar($membership->role)?mb_substr((string)$membership->role,0,20):null,'can_participate'=>Gate::forUser($user)->allows('participate',$group),'blocked_positions'=>$blockedPositions];
    }

    protected function looksLikeSecretariatOfficeContext(?string $routeName, ?string $module, ?string $resourceType): bool
    {
        if (in_array($resourceType,['secretariat_office','office'],true)) return true;
        return is_string($routeName) && Str::startsWith($routeName,'secretariat.') && in_array(Str::lower((string)$module),['secretariat','registry',''],true);
    }
    protected function looksLikeProjectContext(?string $routeName, ?string $resourceType): bool { return in_array($resourceType,['project','najm_bahar_project'],true) || (is_string($routeName)&&Str::startsWith($routeName,'najm-bahar.projects.')); }
    protected function looksLikeGroupContext(?string $module, ?string $resourceType): bool { return $resourceType==='group'||in_array(Str::lower((string)$module),['group','groups'],true); }
    protected function positiveInt(mixed $value): ?int { if(is_int($value))return $value>0?$value:null; if(is_string($value)&&ctype_digit($value)){ $n=(int)$value; return $n>0?$n:null;} return null; }
    protected function cleanToken(mixed $value,int $maxLength): ?string { if(!is_scalar($value))return null; $value=trim((string)$value); if($value===''||!preg_match('/^[A-Za-z0-9._:-]+$/',$value))return null; return mb_substr($value,0,$maxLength); }
}
