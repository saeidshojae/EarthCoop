<?php

namespace App\Services\NajmHoda\Runtime;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class NajmHodaCoverageHeartbeatService
{
    public function __construct(
        protected RuntimeEventBus $eventBus
    ) {
    }

    /**
     * @return array<int, array{domain:string,event:string,scope:string,payload:array<string,mixed>}>
     */
    public function buildSignals(): array
    {
        return [
            [
                'domain' => 'support',
                'event' => 'najm_hoda.input.support.service.health_snapshot.succeeded',
                'scope' => 'support',
                'payload' => [
                    'open_tickets' => $this->countTableWhere('tickets', ['status' => 'open']),
                    'all_tickets' => $this->countTable('tickets'),
                ],
            ],
            [
                'domain' => 'auth',
                'event' => 'najm_hoda.input.auth.service.health_snapshot.succeeded',
                'scope' => 'auth',
                'payload' => [
                    'users_total' => $this->countTable('users'),
                    'users_with_last_login' => $this->countNotNull('users', 'last_login_at'),
                ],
            ],
            [
                'domain' => 'content',
                'event' => 'najm_hoda.input.content.service.health_snapshot.succeeded',
                'scope' => 'content',
                'payload' => [
                    'pages_total' => $this->countTable('pages'),
                    'kb_articles_total' => $this->countTable('kb_articles'),
                    'blogs_total' => $this->countTable('blogs'),
                ],
            ],
            [
                'domain' => 'najm_bahar',
                'event' => 'najm_hoda.input.najm_bahar.service.health_snapshot.succeeded',
                'scope' => 'economy:najm-bahar',
                'payload' => [
                    'accounts_total' => $this->countTable('najm_bahar_accounts'),
                    'transactions_total' => $this->countTable('najm_bahar_transactions'),
                    'investments_total' => $this->countTable('najm_bahar_investments'),
                ],
            ],
            [
                'domain' => 'groups',
                'event' => 'najm_hoda.input.group_health_snapshot.succeeded',
                'scope' => 'group',
                'payload' => [
                    'groups_total' => $this->countTable('groups'),
                    'messages_total' => $this->countTable('messages'),
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{domain:string,event:string,scope:string,payload:array<string,mixed>,action:string}>
     */
    public function emit(bool $dryRun = false): array
    {
        $signals = $this->buildSignals();
        $result = [];

        foreach ($signals as $signal) {
            $payload = array_merge($signal['payload'], [
                'domain' => $signal['domain'],
                'scope' => $signal['scope'],
                'risk' => 'low',
                'heartbeat' => true,
            ]);

            if (!$dryRun) {
                $this->eventBus->emit($signal['event'], $payload);
            }

            $result[] = [
                'domain' => $signal['domain'],
                'event' => $signal['event'],
                'scope' => $signal['scope'],
                'payload' => $payload,
                'action' => $dryRun ? 'skipped' : 'emitted',
            ];
        }

        return $result;
    }

    protected function countTable(string $table): int
    {
        try {
            if (!Schema::hasTable($table)) {
                return 0;
            }
            return (int) DB::table($table)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @param array<string, mixed> $where
     */
    protected function countTableWhere(string $table, array $where): int
    {
        try {
            if (!Schema::hasTable($table)) {
                return 0;
            }
            return (int) DB::table($table)->where($where)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    protected function countNotNull(string $table, string $column): int
    {
        try {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                return 0;
            }
            return (int) DB::table($table)->whereNotNull($column)->count();
        } catch (Throwable) {
            return 0;
        }
    }
}

