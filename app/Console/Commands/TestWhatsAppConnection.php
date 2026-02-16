<?php

namespace App\Console\Commands;

use App\Services\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestWhatsAppConnection extends Command
{
    protected $signature = 'whatsapp:test-connection';

    protected $description = 'Test WhatsApp Evolution API connection and configuration';

    public function handle(): int
    {
        $this->info('Testing WhatsApp Evolution API connection...');

        $apiUrl = config('whatsapp.api_url');
        $apiKey = config('whatsapp.api_key');

        $this->line("API URL: {$apiUrl}");
        $this->line('API Key: '.(empty($apiKey) ? 'NOT SET' : '***'.substr($apiKey, -4)));

        if (empty($apiKey)) {
            $this->error('WHATSAPP_API_KEY is not set in .env file');

            return 1;
        }

        try {
            $this->info('Testing API health...');

            $response = Http::timeout(10)->get("{$apiUrl}/");

            if ($response->successful()) {
                $this->info('API is reachable');
                $this->line('Response: '.$response->body());
            } else {
                $this->warn("API returned status: {$response->status()}");
            }
        } catch (\Exception $e) {
            $this->error('API health check failed: '.$e->getMessage());
        }
        try {
            $this->info('Testing instances endpoint...');

            $response = Http::withHeaders(['apikey' => $apiKey])
                ->timeout(10)
                ->get("{$apiUrl}/instance/fetchInstances");

            if ($response->successful()) {
                $instances = $response->json();
                $this->info('Instances endpoint accessible');
                $this->line('Active instances: '.count($instances));

                if (! empty($instances)) {
                    $this->table(
                        ['Instance Name', 'State'],
                        array_map(fn ($instance) => [
                            $instance['instance']['instanceName'] ?? 'unknown',
                            $instance['instance']['state'] ?? 'unknown',
                        ], $instances)
                    );
                }
            } else {
                $this->error("Instances endpoint failed with status: {$response->status()}");
                $this->line('Response: '.$response->body());
            }
        } catch (\Exception $e) {
            $this->error('Instances endpoint failed: '.$e->getMessage());
        }
        try {
            $this->info('Testing WhatsAppService...');
            $service = app(WhatsAppService::class);

            $instances = $service->getAllInstances();
            $this->info('WhatsAppService is working ('.count($instances).' instances found)');
        } catch (\Exception $e) {
            $this->error('WhatsAppService failed: '.$e->getMessage());
        }

        $this->info('Connection test completed.');

        return 0;
    }
}
