<?php

namespace App\Services\NajmHoda\FounderOps;

class FounderExecutiveConnectivityService
{
    public function __construct(protected FounderLowRiskDomainActionService $lowRisk) {}

    public function report(): array
    {
        $policyDomains=(array)config('najm-hoda-founder-action-policy.domains',[]);
        $readDomains=array_flip((array)config('najm-hoda-founder-connectivity.read_domains',[]));
        $proposalAdapters=(array)config('najm-hoda-founder-connectivity.proposal_adapters',[]);
        $approvalAdapters=(array)config('najm-hoda-founder-connectivity.approval_adapters',[]);
        $blockedActions=(array)config('najm-hoda-founder-connectivity.blocked_actions',[]);
        $domains=[];
        $totals=[
            'domains'=>0,'read_connected'=>0,'managed'=>0,'partial'=>0,'observed_only'=>0,
            'missing_executable_actions'=>0,'blocked_executable_actions'=>0,
        ];

        foreach ($policyDomains as $domain=>$definition) {
            if (!is_string($domain)||!is_array($definition)) continue;
            $actions=[]; $required=0; $connected=0; $blocked=0;
            foreach ((array)($definition['actions']??[]) as $action=>$mode) {
                if (!is_string($action)||!is_string($mode)) continue;
                $key=$domain.'.'.$action;
                $state='policy_only'; $adapter=null; $block=null;
                if ($mode==='forbidden') {
                    $state='protected';
                } elseif ($mode==='observe') {
                    $state=isset($readDomains[$domain])?'connected':'missing';
                } elseif (in_array($mode,['delegated_safe','propose','approval_required'],true)) {
                    $required++;
                    if (isset($blockedActions[$key]) && is_array($blockedActions[$key])) {
                        $state='blocked_dependency';
                        $block=$blockedActions[$key];
                        $blocked++;
                        $totals['blocked_executable_actions']++;
                    } elseif ($mode==='delegated_safe') {
                        if ($this->lowRisk->supports($domain,$action)) { $state='connected'; $connected++; }
                        else $state='missing';
                    } elseif ($mode==='propose') {
                        $adapter=$proposalAdapters[$key]??null;
                        if (is_string($adapter)&&class_exists($adapter)) { $state='connected'; $connected++; }
                        else $state='missing';
                    } else {
                        $adapter=$approvalAdapters[$key]??null;
                        if (is_string($adapter)&&class_exists($adapter)) { $state='connected'; $connected++; }
                        else $state='missing';
                    }
                }
                if ($state==='missing' && in_array($mode,['delegated_safe','propose','approval_required'],true)) {
                    $totals['missing_executable_actions']++;
                }
                $actions[$action]=['mode'=>$mode,'state'=>$state,'adapter'=>$adapter,'block'=>$block];
            }

            $readConnected=isset($readDomains[$domain]);
            $stage=$required===0
                ?($readConnected?'observed_only':'mapped')
                :($connected===$required?'managed':($connected>0?'partial':($readConnected?'observed_only':'mapped')));
            $totals['domains']++;
            if($readConnected)$totals['read_connected']++;
            if(isset($totals[$stage]))$totals[$stage]++;
            $domains[$domain]=[
                'read_connected'=>$readConnected,
                'stage'=>$stage,
                'required_executable_actions'=>$required,
                'connected_executable_actions'=>$connected,
                'blocked_executable_actions'=>$blocked,
                'actions'=>$actions,
            ];
        }

        $totals['blocked']=$totals['missing_executable_actions']+$totals['blocked_executable_actions'];
        $totals['executive_coverage_percent']=$totals['domains']>0
            ?round((($totals['managed']+$totals['partial']*0.5)/$totals['domains'])*100,2)
            :0.0;
        return ['summary'=>$totals,'domains'=>$domains,'rollout_queue'=>$this->rolloutQueue($domains)];
    }

    protected function rolloutQueue(array $domains): array
    {
        $priority=(array)config('najm-hoda-founder-operations.domains',[]);
        $queue=[];
        foreach($domains as $key=>$domain){
            if(($domain['stage']??'')==='managed')continue;
            $missing=[]; $blocked=[];
            foreach((array)($domain['actions']??[]) as $action=>$state){
                if(($state['state']??'')==='missing')$missing[]=$action;
                if(($state['state']??'')==='blocked_dependency')$blocked[]=[
                    'action'=>$action,
                    'reason'=>(string)data_get($state,'block.reason',''),
                    'dependency'=>(string)data_get($state,'block.dependency',''),
                ];
            }
            if($missing===[]&&$blocked===[])continue;
            $queue[]=[
                'domain'=>$key,
                'priority'=>(int)($priority[$key]['priority']??5),
                'risk'=>(string)($priority[$key]['risk']??'medium'),
                'missing_actions'=>$missing,
                'blocked_actions'=>$blocked,
                'actionable_now'=>$missing!==[],
            ];
        }
        usort($queue,static fn(array $a,array $b):int=>($b['priority']<=>$a['priority'])?:strcmp($a['domain'],$b['domain']));
        return $queue;
    }
}
