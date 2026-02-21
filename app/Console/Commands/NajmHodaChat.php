<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\NajmHodaOrchestrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NajmHodaChat extends Command
{
    protected $signature = 'najm-hoda:chat {message : پیام شما}';
    protected $description = 'چت سریع با نجم‌هدا از طریق ترمینال';

    public function handle()
    {
        if (!config('najm-hoda.enabled', true)) {
            Log::info('NajmHoda command skipped because system is disabled', [
                'command' => 'najm-hoda:chat',
            ]);
            $this->warn('Najm Hoda is disabled (NAJM_HODA_ENABLED=false).');
            return self::SUCCESS;
        }

        $message = $this->argument('message');
        
        $this->info("📤 شما: $message");
        $this->newLine();

        try {
            $najmHoda = app(NajmHodaOrchestrator::class);
            
            $response = $najmHoda->route($message);

            $icon = $response['agent_icon'] ?? '🤖';
            $agentName = $response['agent_persian_name'] ?? 'نجم‌هدا';
            
            $this->info("$icon $agentName:");
            $this->line($response['message']);
            
            if (!empty($response['suggestions'])) {
                $this->newLine();
                $this->info('💡 پیشنهادات:');
                foreach ($response['suggestions'] as $suggestion) {
                    $this->line("  • $suggestion");
                }
            }

        } catch (\Exception $e) {
            $this->error('❌ خطا: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
