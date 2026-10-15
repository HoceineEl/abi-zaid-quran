<?php

namespace App\Console\Commands;

use App\Services\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestWhatsAppConnection extends Command
{
    protected $signature = 'whatsapp:test-connection';

    protected $description = 'Test WhatsApp GOWA API connection and configuration';

    public function handle(): int
    {
        $this->info('Testing WhatsApp GOWA API connection...');

        $apiUrl = rtrim((string) config('whatsapp.api_url'), '/');
        $username = config('whatsapp.username');
        $password = config('whatsapp.password');

        $this->line("API URL: {$apiUrl}");
        $this->line('Username: '.($username ?: 'NOT SET'));
        $this->line('Password: '.(empty($password) ? 'NOT SET' : '***'.substr($password, -4)));

        if (empty($username) || empty($password)) {
            $this->error('WHATSAPP_API_USERNAME or WHATSAPP_API_PASSWORD is not set in .env file');

            return 1;
        }

        try {
            $this->info('Testing API health...');

            $response = Http::timeout(10)->get("{$apiUrl}/health");

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
            $this->info('Testing devices endpoint...');

            $instances = app(WhatsAppService::class)->getAllInstances();
            $this->info('Devices endpoint accessible ('.count($instances).' devices found)');

            if (! empty($instances)) {
                $this->table(
                    ['Device ID', 'State', 'Phone'],
                    array_map(fn (array $instance) => [
                        $instance['instance']['instanceName'],
                        $instance['instance']['state'],
                        $instance['instance']['ownerJid'] ?? '-',
                    ], $instances)
                );
            }
        } catch (\Exception $e) {
            $this->error('WhatsAppService failed: '.$e->getMessage());
        }

        $this->info('Connection test completed.');

        return 0;
    }
}
