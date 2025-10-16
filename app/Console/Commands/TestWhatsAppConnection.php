<?php

namespace App\Console\Commands;

use App\Services\WhatsAppService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestWhatsAppConnection extends Command
{
    protected $signature = 'whatsapp:test-connection';

    protected $description = 'Test WhatsApp API connection and configuration';

    public function handle()
    {
        $this->info('Testing WhatsApp API connection...');

        // Test configuration
        $apiUrl = config('whatsapp.api_url');
        $apiToken = config('whatsapp.api_token');

        $this->line("API URL: {$apiUrl}");
        $this->line('API Token: '.(empty($apiToken) ? 'NOT SET' : '***'.substr($apiToken, -8)));

        if (empty($apiToken)) {
            $this->error('WHATSAPP_API_TOKEN is not set in .env file');

            return 1;
        }

        // Test basic API connection
        try {
            $this->info('Testing API endpoint...');

            $response = Http::timeout(10)->get("{$apiUrl}/health");

            if ($response->successful()) {
                $this->info('✓ API health check passed');
                $this->line('Response: '.$response->body());
            } else {
                $this->warn("API health check returned status: {$response->status()}");
                $this->line('Response: '.$response->body());
            }
        } catch (Exception $e) {
            $this->error('✗ API health check failed: '.$e->getMessage());
        }

        // Test sessions endpoint
        try {
            $this->info('Testing sessions endpoint...');

            $response = Http::withHeaders([
                'X-Master-Key' => $apiToken,
            ])->timeout(10)->get("{$apiUrl}/api/v1/sessions");

            if ($response->successful()) {
                $sessions = $response->json();
                $this->info('✓ Sessions endpoint accessible');
                $this->line('Active sessions: '.count($sessions));

                if (! empty($sessions)) {
                    $this->table(['Session ID', 'Status'], array_map(function ($session) {
                        return [$session['sessionId'] ?? 'unknown', $session['status'] ?? 'unknown'];
                    }, $sessions));
                }
            } else {
                $this->error("✗ Sessions endpoint failed with status: {$response->status()}");
                $this->line('Response: '.$response->body());
            }
        } catch (Exception $e) {
            $this->error('✗ Sessions endpoint failed: '.$e->getMessage());
        }

        // Test WhatsAppService
        try {
            $this->info('Testing WhatsAppService...');
            $service = app(WhatsAppService::class);

            // Try to get a test session status (this will fail but we want to see the error)
            try {
                $service->getSessionStatus('test-session-123');
            } catch (Exception $e) {
                if (str_contains($e->getMessage(), 'not found')) {
                    $this->info('✓ WhatsAppService is working (test session not found as expected)');
                } else {
                    $this->warn('WhatsAppService error: '.$e->getMessage());
                }
            }
        } catch (Exception $e) {
            $this->error('✗ WhatsAppService failed: '.$e->getMessage());
        }

        $this->info('Connection test completed.');

        return 0;
    }
}
