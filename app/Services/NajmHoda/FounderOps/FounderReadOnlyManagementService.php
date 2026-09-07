<?php

namespace App\Services\NajmHoda\FounderOps;

class FounderReadOnlyManagementService
{
    public function __construct(protected FounderOperationsSnapshotService $snapshots) {}

    public function summarize(string $domain, int $hours = 24): array
    {
        $snapshot=$this->snapshots->snapshot($hours);
        $section=match($domain){
            'groups'=>'groups',
            'governance'=>'governance',
            'invitations'=>'growth',
            'admin_settings'=>'admin_configuration',
            'najm_bahar'=>'najm_bahar',
            default=>null,
        };
        if($section===null) return ['success'=>false,'status'=>'unsupported','reason'=>'read_only_summary_not_supported'];
        return [
            'success'=>true,
            'status'=>'completed',
            'domain'=>$domain,
            'window_hours'=>max(1,min($hours,168)),
            'summary'=>(array)($snapshot[$section]??[]),
            'generated_at'=>(string)data_get($snapshot,'window.generated_at',now()->toIso8601String()),
        ];
    }
}
